<?php

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ClientSurvey;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function satisfactionCompany(string $suffix = ''): Company
{
    return Company::create([
        'name' => 'Satisfaction Co '.$suffix.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0500',
        'email' => 'satco.'.uniqid().'@test.com',
    ]);
}

function makeSatisfactionClient(User $owner): Client
{
    return satisfactionCompany()->client()->create([
        'assigned_to_id' => $owner->id,
        'status' => 'active',
        'client_since' => now(),
    ]);
}

it('gives an admin every client in the satisfaction index', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'admin-sat-'.uniqid().'@example.com']);
    $repA = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-sat-a-'.uniqid().'@example.com']);
    $repB = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-sat-b-'.uniqid().'@example.com']);

    $clientA = makeSatisfactionClient($repA);
    $clientB = makeSatisfactionClient($repB);

    $this->actingAs($admin)->getJson('/api/satisfaction')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $clientA->id)
        ->assertJsonPath('data.1.id', $clientB->id);
});

it('gives a manager every client in the satisfaction index', function () {
    $manager = User::factory()->create(['role' => UserRole::MANAGER, 'email' => 'mgr-sat-'.uniqid().'@example.com']);
    $repA = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-sat-a-'.uniqid().'@example.com']);
    $repB = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-sat-b-'.uniqid().'@example.com']);

    $clientA = makeSatisfactionClient($repA);
    $clientB = makeSatisfactionClient($repB);

    $this->actingAs($manager)->getJson('/api/satisfaction')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $clientA->id)
        ->assertJsonPath('data.1.id', $clientB->id);
});

it('lets an owner rep send a survey to their own client', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-sat-own-'.uniqid().'@example.com']);
    $client = makeSatisfactionClient($rep);

    $this->actingAs($rep)->postJson("/api/satisfaction/{$client->id}/surveys")
        ->assertOk()
        ->assertJsonStructure(['data' => ['survey' => ['id'], 'link']]);

    expect(ClientSurvey::where('client_id', $client->id)->count())->toBe(1);
});

it('blocks a manager from sending a survey', function () {
    $manager = User::factory()->create(['role' => UserRole::MANAGER, 'email' => 'mgr-sat-block-'.uniqid().'@example.com']);
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-sat-block-'.uniqid().'@example.com']);
    $client = makeSatisfactionClient($rep);

    $this->actingAs($manager)->postJson("/api/satisfaction/{$client->id}/surveys")
        ->assertForbidden();

    expect(ClientSurvey::where('client_id', $client->id)->count())->toBe(0);
});

it('blocks a manager from deleting a survey', function () {
    $manager = User::factory()->create(['role' => UserRole::MANAGER, 'email' => 'mgr-sat-del-'.uniqid().'@example.com']);
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-sat-del-'.uniqid().'@example.com']);
    $client = makeSatisfactionClient($rep);

    $survey = ClientSurvey::create([
        'client_id' => $client->id,
        'token' => 'srv_'.bin2hex(random_bytes(16)),
        'status' => 'pending',
    ]);

    $this->actingAs($manager)->deleteJson("/api/satisfaction/{$client->id}/surveys/{$survey->id}")
        ->assertForbidden();

    expect(ClientSurvey::find($survey->id))->not->toBeNull();
});

it('blocks a rep from viewing a client they do not own', function () {
    $repA = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-sat-view-a-'.uniqid().'@example.com']);
    $repB = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-sat-view-b-'.uniqid().'@example.com']);
    $clientB = makeSatisfactionClient($repB);

    $this->actingAs($repA)->getJson("/api/satisfaction/{$clientB->id}")
        ->assertForbidden();
});

function makeCompletedSurvey(Client $client, float $score, string $completedAt): ClientSurvey
{
    return ClientSurvey::create([
        'client_id' => $client->id,
        'token' => 'srv_'.bin2hex(random_bytes(16)),
        'status' => 'completed',
        'average_score' => $score,
        'completed_at' => $completedAt,
    ]);
}

it('filters the satisfaction index by company search', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'admin-sat-search-'.uniqid().'@example.com']);
    $clientA = makeSatisfactionClient($admin);
    $clientB = makeSatisfactionClient($admin);
    $clientA->company->update(['name' => 'Acme Widgets '.uniqid()]);

    $this->actingAs($admin)->getJson('/api/satisfaction?q=Acme')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $clientA->id);
});

it('filters the satisfaction index by trend', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'admin-sat-trend-'.uniqid().'@example.com']);
    $up = makeSatisfactionClient($admin);
    $down = makeSatisfactionClient($admin);

    makeCompletedSurvey($up, 3, now()->subDays(5)->toDateTimeString());
    makeCompletedSurvey($up, 5, now()->toDateTimeString());

    makeCompletedSurvey($down, 5, now()->subDays(5)->toDateTimeString());
    makeCompletedSurvey($down, 3, now()->toDateTimeString());

    $this->actingAs($admin)->getJson('/api/satisfaction?trend=up')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $up->id);

    $this->actingAs($admin)->getJson('/api/satisfaction?trend=down')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $down->id);
});

it('filters the satisfaction index by average score', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'admin-sat-score-'.uniqid().'@example.com']);
    $strong = makeSatisfactionClient($admin);
    $weak = makeSatisfactionClient($admin);

    makeCompletedSurvey($strong, 4, now()->subDays(3)->toDateTimeString());
    makeCompletedSurvey($strong, 5, now()->toDateTimeString());

    makeCompletedSurvey($weak, 2, now()->subDays(3)->toDateTimeString());
    makeCompletedSurvey($weak, 2, now()->toDateTimeString());

    $this->actingAs($admin)->getJson('/api/satisfaction?score=ge4')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $strong->id);

    $this->actingAs($admin)->getJson('/api/satisfaction?score=lt3')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $weak->id);
});

it('filters the satisfaction index by last survey date', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'admin-sat-date-'.uniqid().'@example.com']);
    $recent = makeSatisfactionClient($admin);
    $old = makeSatisfactionClient($admin);

    makeCompletedSurvey($recent, 4, now()->subDay()->toDateTimeString());
    makeCompletedSurvey($old, 4, now()->subMonths(2)->toDateTimeString());

    $this->actingAs($admin)->getJson('/api/satisfaction?from='.now()->subWeek()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $recent->id);
});

it('allows an admin to delete a completed survey', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'admin-sat-del-completed-'.uniqid().'@example.com']);
    $client = makeSatisfactionClient($admin);

    $survey = ClientSurvey::create([
        'client_id' => $client->id,
        'token' => 'srv_'.bin2hex(random_bytes(16)),
        'status' => 'completed',
        'average_score' => 4,
        'completed_at' => now(),
    ]);

    $this->actingAs($admin)->deleteJson("/api/satisfaction/{$client->id}/surveys/{$survey->id}")
        ->assertOk();

    expect(ClientSurvey::find($survey->id))->toBeNull();
});

it('applies filters to the satisfaction mine endpoint', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-sat-mine-filter-'.uniqid().'@example.com']);
    $mine = makeSatisfactionClient($rep);
    $mine->company->update(['name' => 'Zeta Consulting '.uniqid()]);
    $other = makeSatisfactionClient($rep);

    $this->actingAs($rep)->getJson('/api/satisfaction/mine?q=Zeta')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});
