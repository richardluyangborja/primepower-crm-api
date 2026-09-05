<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientSurvey;
use App\Models\Company;
use App\Models\SatisfactionSetting;
use App\Models\User;
use App\Support\SatisfactionThresholdEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function atRiskUser(string $role): User
{
    return User::factory()->create([
        'role' => $role,
        'email' => $role.'-'.uniqid().'@example.com',
    ]);
}

function atRiskCompany(string $suffix = ''): Company
{
    return Company::create([
        'name' => 'At Risk Co '.$suffix.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0400',
        'email' => 'co.'.uniqid().'@test.com',
    ]);
}

function seedDefaultSettings(): void
{
    SatisfactionSetting::updateOrCreate(['key' => 'low_score_threshold'], ['value' => '3.0']);
    SatisfactionSetting::updateOrCreate(['key' => 'at_risk_lookback_surveys'], ['value' => '1']);
}

function createClientWithSurvey(User $owner, float $score): Client
{
    $company = atRiskCompany();
    $client = $company->client()->create([
        'assigned_to_id' => $owner->id,
        'status' => 'active',
        'client_since' => now(),
    ]);

    $survey = new ClientSurvey;
    $survey->forceFill([
        'client_id' => $client->id,
        'token' => 'srv_'.bin2hex(random_bytes(16)),
        'status' => 'completed',
        'average_score' => $score,
        'responses' => [['question_id' => 'q1', 'score' => (int) $score]],
        'completed_at' => now(),
    ])->save();

    return $client->fresh(['surveys']);
}

// ── Mark at risk ──

it('flags a client at risk when score is below threshold', function () {
    seedDefaultSettings();
    $owner = atRiskUser(UserRole::SALES_REP->value);
    $client = createClientWithSurvey($owner, 2.5);

    $changed = (new SatisfactionThresholdEngine)->evaluate();

    expect($changed)->toBe(1);
    expect($client->fresh()->at_risk)->toBeTrue();
    expect($client->fresh()->at_risk_reason)->toContain('2.50');
});

it('sends notification to the client owner when flagged at risk', function () {
    seedDefaultSettings();
    $owner = atRiskUser(UserRole::SALES_REP->value);
    $client = createClientWithSurvey($owner, 2.0);

    (new SatisfactionThresholdEngine)->evaluate();

    expect($owner->notifications()->count())->toBe(1);
    $notification = $owner->notifications()->first();
    expect($notification->type)->toBe('App\Notifications\SatisfactionAtRiskNotification');
    expect($notification->data['client_id'])->toBe($client->id);
});

it('does not flag at risk when score is above threshold', function () {
    seedDefaultSettings();
    $owner = atRiskUser(UserRole::SALES_REP->value);
    $client = createClientWithSurvey($owner, 4.0);

    $changed = (new SatisfactionThresholdEngine)->evaluate();

    expect($changed)->toBe(0);
    expect($client->fresh()->at_risk)->toBeFalse();
});

it('logs an audit entry when flagging at risk', function () {
    seedDefaultSettings();
    $owner = atRiskUser(UserRole::SALES_REP->value);
    $client = createClientWithSurvey($owner, 1.5);

    (new SatisfactionThresholdEngine)->evaluate();

    expect(AuditLog::where('module', 'Client Satisfaction')
        ->where('action', 'At Risk')
        ->where('subject_id', (string) $client->id)
        ->exists())->toBeTrue();
});

// ── Recovery ──

it('clears at_risk when score rises above threshold', function () {
    seedDefaultSettings();
    $owner = atRiskUser(UserRole::SALES_REP->value);
    $client = createClientWithSurvey($owner, 2.0);

    // First run → flags at risk
    (new SatisfactionThresholdEngine)->evaluate();
    expect($client->fresh()->at_risk)->toBeTrue();

    // Replace with a high score
    $client->surveys()->delete();
    $survey = new ClientSurvey;
    $survey->forceFill([
        'client_id' => $client->id,
        'token' => 'srv_'.bin2hex(random_bytes(16)),
        'status' => 'completed',
        'average_score' => 4.5,
        'responses' => [['question_id' => 'q1', 'score' => 5]],
        'completed_at' => now(),
    ])->save();

    $changed = (new SatisfactionThresholdEngine)->evaluate();

    expect($changed)->toBe(1);
    expect($client->fresh()->at_risk)->toBeFalse();
    expect($client->fresh()->at_risk_reason)->toBeNull();
});

it('logs audit entry on recovery', function () {
    seedDefaultSettings();
    $owner = atRiskUser(UserRole::SALES_REP->value);
    $client = createClientWithSurvey($owner, 2.0);

    (new SatisfactionThresholdEngine)->evaluate();

    $client->surveys()->delete();
    $survey = new ClientSurvey;
    $survey->forceFill([
        'client_id' => $client->id,
        'token' => 'srv_'.bin2hex(random_bytes(16)),
        'status' => 'completed',
        'average_score' => 4.5,
        'responses' => [['question_id' => 'q1', 'score' => 5]],
        'completed_at' => now(),
    ])->save();

    (new SatisfactionThresholdEngine)->evaluate();

    expect(AuditLog::where('module', 'Client Satisfaction')
        ->where('action', 'At Risk Resolved')
        ->where('subject_id', (string) $client->id)
        ->exists())->toBeTrue();
});

// ── Idempotency ──

it('is idempotent — running twice does not duplicate notifications', function () {
    seedDefaultSettings();
    $owner = atRiskUser(UserRole::SALES_REP->value);
    createClientWithSurvey($owner, 2.0);

    (new SatisfactionThresholdEngine)->evaluate();
    (new SatisfactionThresholdEngine)->evaluate();

    expect($owner->notifications()->count())->toBe(1);
});

it('is idempotent for recovery — no duplicate audit entries', function () {
    seedDefaultSettings();
    $owner = atRiskUser(UserRole::SALES_REP->value);
    $client = createClientWithSurvey($owner, 2.0);

    (new SatisfactionThresholdEngine)->evaluate();

    $client->surveys()->delete();
    $survey = new ClientSurvey;
    $survey->forceFill([
        'client_id' => $client->id,
        'token' => 'srv_'.bin2hex(random_bytes(16)),
        'status' => 'completed',
        'average_score' => 4.5,
        'responses' => [['question_id' => 'q1', 'score' => 5]],
        'completed_at' => now(),
    ])->save();

    (new SatisfactionThresholdEngine)->evaluate();
    (new SatisfactionThresholdEngine)->evaluate();

    expect(AuditLog::where('module', 'Client Satisfaction')
        ->where('action', 'At Risk Resolved')
        ->where('subject_id', (string) $client->id)
        ->count())->toBe(1);
});

// ── Custom threshold ──

it('respects a custom threshold from satisfaction_settings', function () {
    SatisfactionSetting::updateOrCreate(['key' => 'low_score_threshold'], ['value' => '4.0']);
    SatisfactionSetting::updateOrCreate(['key' => 'at_risk_lookback_surveys'], ['value' => '1']);

    $owner = atRiskUser(UserRole::SALES_REP->value);
    $client = createClientWithSurvey($owner, 3.5);

    $changed = (new SatisfactionThresholdEngine)->evaluate();

    expect($changed)->toBe(1);
    expect($client->fresh()->at_risk)->toBeTrue();
});
