<?php

use App\Enums\UserRole;
use App\Models\ClientSurvey;
use App\Models\Company;
use App\Models\SurveyTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function manualUser(string $role, array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => $role,
        'email' => $role.'-'.uniqid().'@example.com',
    ], $overrides));
}

function manualCompany(): Company
{
    return Company::create([
        'name' => 'Manual Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0500',
        'email' => 'co.'.uniqid().'@test.com',
    ]);
}

function sampleManualResponses(): array
{
    return [
        ['question_id' => 'q1', 'score' => 4],
        ['question_id' => 'q2', 'score' => 5],
    ];
}

// ── Admin can log manual survey for any client ──

it('lets an admin log a manual survey for any client', function () {
    $admin = manualUser(UserRole::ADMIN->value);
    $rep = manualUser(UserRole::SALES_REP->value);

    $company = manualCompany();
    $client = $company->client()->create([
        'assigned_to_id' => $rep->id,
        'status' => 'active',
        'client_since' => now(),
    ]);

    $this->actingAs($admin)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [
        'responses' => sampleManualResponses(),
        'respondent_name' => 'John Doe',
        'respondent_position' => 'CTO',
        'feedback' => 'Great work!',
    ])->assertCreated();

    $survey = ClientSurvey::where('client_id', $client->id)->first();
    expect($survey)->not->toBeNull();
    expect($survey->status->value)->toBe('completed');
    expect((float) $survey->average_score)->toBe(4.5);
    expect($survey->respondent_name)->toBe('John Doe');
    expect($survey->feedback)->toBe('Great work!');
});

// ── Manager can log manual survey for visible clients ──

it('lets a manager log a manual survey for visible clients', function () {
    $manager = manualUser(UserRole::MANAGER->value);
    $rep = manualUser(UserRole::SALES_REP->value, ['manager_id' => $manager->id]);

    $company = manualCompany();
    $client = $company->client()->create([
        'assigned_to_id' => $rep->id,
        'status' => 'active',
        'client_since' => now(),
    ]);

    $this->actingAs($manager)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [
        'responses' => sampleManualResponses(),
    ])->assertCreated();
});

// ── Sales rep can log manual survey for own clients ──

it('lets a sales rep log a manual survey for their own client', function () {
    $rep = manualUser(UserRole::SALES_REP->value);

    $company = manualCompany();
    $client = $company->client()->create([
        'assigned_to_id' => $rep->id,
        'status' => 'active',
        'client_since' => now(),
    ]);

    $this->actingAs($rep)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [
        'responses' => sampleManualResponses(),
    ])->assertCreated();
});

// ── Sales rep cannot log for other rep's client ──

it('denies a sales rep logging a manual survey for another rep client', function () {
    $rep1 = manualUser(UserRole::SALES_REP->value);
    $rep2 = manualUser(UserRole::SALES_REP->value);

    $company = manualCompany();
    $client = $company->client()->create([
        'assigned_to_id' => $rep1->id,
        'status' => 'active',
        'client_since' => now(),
    ]);

    $this->actingAs($rep2)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [
        'responses' => sampleManualResponses(),
    ])->assertForbidden();
});

// ── Validates required fields ──

it('validates required fields on manual survey entry', function () {
    $admin = manualUser(UserRole::ADMIN->value);

    $company = manualCompany();
    $client = $company->client()->create([
        'assigned_to_id' => $admin->id,
        'status' => 'active',
        'client_since' => now(),
    ]);

    // Missing responses
    $this->actingAs($admin)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [])
        ->assertUnprocessable();

    // Empty responses
    $this->actingAs($admin)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [
        'responses' => [],
    ])->assertUnprocessable();

    // Invalid score
    $this->actingAs($admin)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [
        'responses' => [['question_id' => 'q1', 'score' => 6]],
    ])->assertUnprocessable();

    $this->actingAs($admin)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [
        'responses' => [['question_id' => 'q1', 'score' => 0]],
    ])->assertUnprocessable();
});

// ── Computes average_score correctly ──

it('computes the correct average_score from responses', function () {
    $admin = manualUser(UserRole::ADMIN->value);

    $company = manualCompany();
    $client = $company->client()->create([
        'assigned_to_id' => $admin->id,
        'status' => 'active',
        'client_since' => now(),
    ]);

    $this->actingAs($admin)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [
        'responses' => [
            ['question_id' => 'q1', 'score' => 2],
            ['question_id' => 'q2', 'score' => 4],
            ['question_id' => 'q3', 'score' => 3],
        ],
    ])->assertCreated();

    $survey = ClientSurvey::where('client_id', $client->id)->first();
    expect((float) $survey->average_score)->toBe(3.0);
});

// ── Optional template_id snapshots version ──

it('snapshots template version when template_id is provided for manual entry', function () {
    $admin = manualUser(UserRole::ADMIN->value);

    $template = SurveyTemplate::create([
        'name' => 'Manual Template '.uniqid(),
        'description' => 'For manual tests',
        'is_active' => true,
    ]);
    $template->versions()->create([
        'version' => 1,
        'questions' => sampleManualResponses(),
        'is_current' => true,
    ]);

    $company = manualCompany();
    $client = $company->client()->create([
        'assigned_to_id' => $admin->id,
        'status' => 'active',
        'client_since' => now(),
    ]);

    $this->actingAs($admin)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [
        'template_id' => $template->id,
        'responses' => sampleManualResponses(),
    ])->assertCreated();

    $survey = ClientSurvey::where('client_id', $client->id)->first();
    expect($survey->template_version_id)->toBe($template->currentVersion->id);
});

// ── Accepts custom completed_at ──

it('accepts a custom completed_at date', function () {
    $admin = manualUser(UserRole::ADMIN->value);

    $company = manualCompany();
    $client = $company->client()->create([
        'assigned_to_id' => $admin->id,
        'status' => 'active',
        'client_since' => now(),
    ]);

    $this->actingAs($admin)->postJson("/api/satisfaction/{$client->id}/surveys/manual", [
        'responses' => sampleManualResponses(),
        'completed_at' => '2025-06-15',
    ])->assertCreated();

    $survey = ClientSurvey::where('client_id', $client->id)->first();
    expect($survey->completed_at->format('Y-m-d'))->toBe('2025-06-15');
});
