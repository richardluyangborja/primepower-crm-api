<?php

use App\Enums\ClientStatus;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function lcdCompany(?string $suffix = null): Company
{
    return Company::create([
        'name' => 'Delete Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0100',
        'email' => $suffix.'del'.uniqid().'@example.com',
        'website' => null,
    ]);
}

function lcdLeadFor(int $userId, ?string $source = 'Test'): Lead
{
    $company = lcdCompany();

    return Lead::create([
        'company_id' => $company->id,
        'assigned_to_id' => $userId,
        'source' => $source,
        'status' => LeadStatus::NEW,
        'notes' => null,
    ]);
}

function lcdClientFor(int $userId): Client
{
    $company = lcdCompany();

    return Client::create([
        'company_id' => $company->id,
        'assigned_to_id' => $userId,
        'status' => ClientStatus::ACTIVE,
        'client_since' => now()->toDateString(),
        'notes' => null,
    ]);
}

function lcdOpportunityFor(Lead $lead): Opportunity
{
    return Opportunity::create([
        'company_id' => $lead->company_id,
        'lead_id' => $lead->id,
        'client_id' => null,
        'assigned_to_id' => $lead->assigned_to_id,
        'title' => 'Deal for '.$lead->company->name,
        'stage' => OpportunityStage::PROPOSAL,
        'estimated_contract_value' => 50000,
    ]);
}

// ---------------------------------------------------------------------------
// Lead delete
// ---------------------------------------------------------------------------

it('lets an admin delete a clean lead and its orphaned company', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'deladm@example.com']);
    $lead = lcdLeadFor($admin->id);
    $companyId = $lead->company_id;

    $response = $this->actingAs($admin)->deleteJson("/api/leads/{$lead->id}");

    $response->assertNoContent();
    expect(Lead::find($lead->id))->toBeNull()
        ->and(Company::find($companyId))->toBeNull();
});

it('lets a sales rep delete their own clean lead', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'delrep@example.com']);
    $lead = lcdLeadFor($rep->id);

    $this->actingAs($rep)->deleteJson("/api/leads/{$lead->id}")->assertNoContent();
    expect(Lead::find($lead->id))->toBeNull();
});

it('blocks a sales rep from deleting another rep lead', function () {
    $repA = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'delrepa@example.com']);
    $repB = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'delrepb@example.com']);
    $lead = lcdLeadFor($repA->id);

    $this->actingAs($repB)->deleteJson("/api/leads/{$lead->id}")->assertForbidden();
    expect(Lead::find($lead->id))->not->toBeNull();
});

it('blocks a manager from deleting a lead', function () {
    $manager = User::factory()->create(['role' => UserRole::MANAGER, 'email' => 'delmgr@example.com']);
    $lead = lcdLeadFor($manager->id);

    $this->actingAs($manager)->deleteJson("/api/leads/{$lead->id}")->assertForbidden();
    expect(Lead::find($lead->id))->not->toBeNull();
});

it('refuses to delete a lead with a related opportunity', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'deloppadm@example.com']);
    $lead = lcdLeadFor($admin->id);
    lcdOpportunityFor($lead);

    $response = $this->actingAs($admin)->deleteJson("/api/leads/{$lead->id}");

    $response->assertStatus(409);
    expect($response->json('message'))->toContain('opportunities')
        ->and(Lead::find($lead->id))->not->toBeNull();
});

it('refuses to delete a converted lead', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'delconv@example.com']);
    $lead = lcdLeadFor($admin->id);
    Client::create([
        'company_id' => $lead->company_id,
        'lead_id' => $lead->id,
        'assigned_to_id' => $admin->id,
        'status' => ClientStatus::ACTIVE,
        'client_since' => now()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->deleteJson("/api/leads/{$lead->id}");

    $response->assertStatus(409);
    expect($response->json('message'))->toContain('converted client');
});

// ---------------------------------------------------------------------------
// Client delete
// ---------------------------------------------------------------------------

it('lets an admin delete a clean client and its orphaned company', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'cldeladm@example.com']);
    $client = lcdClientFor($admin->id);
    $companyId = $client->company_id;

    $this->actingAs($admin)->deleteJson("/api/clients/{$client->id}")->assertNoContent();
    expect(Client::find($client->id))->toBeNull()
        ->and(Company::find($companyId))->toBeNull();
});

it('lets a sales rep delete their own clean client', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'cldelrep@example.com']);
    $client = lcdClientFor($rep->id);

    $this->actingAs($rep)->deleteJson("/api/clients/{$client->id}")->assertNoContent();
    expect(Client::find($client->id))->toBeNull();
});

it('refuses to delete a client with a related opportunity', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'cldelopp@example.com']);
    $client = lcdClientFor($admin->id);
    Opportunity::create([
        'company_id' => $client->company_id,
        'lead_id' => null,
        'client_id' => $client->id,
        'assigned_to_id' => $admin->id,
        'title' => 'Client deal',
        'stage' => OpportunityStage::WON,
        'estimated_contract_value' => 100000,
    ]);

    $response = $this->actingAs($admin)->deleteJson("/api/clients/{$client->id}");

    $response->assertStatus(409);
    expect($response->json('message'))->toContain('opportunities')
        ->and(Client::find($client->id))->not->toBeNull();
});

it('blocks a sales rep from deleting another rep client', function () {
    $repA = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'cldelrepa@example.com']);
    $repB = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'cldelrepb@example.com']);
    $client = lcdClientFor($repA->id);

    $this->actingAs($repB)->deleteJson("/api/clients/{$client->id}")->assertForbidden();
    expect(Client::find($client->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Lead filters + sources
// ---------------------------------------------------------------------------

it('hides converted leads when exclude_converted is set, shows them otherwise', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'filtadm@example.com']);
    $fresh = lcdLeadFor($admin->id, 'Web');
    $converted = lcdLeadFor($admin->id, 'Referral');
    $converted->update(['status' => LeadStatus::CONVERTED]);

    $excluded = $this->actingAs($admin)->getJson('/api/leads?exclude_converted=1')->json('data');
    $included = $this->actingAs($admin)->getJson('/api/leads')->json('data');

    expect(collect($excluded)->pluck('id'))->toContain($fresh->id)->not->toContain($converted->id)
        ->and(collect($included)->pluck('id'))->toContain($converted->id);
});

it('returns scoped distinct lead sources', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'srcadm@example.com']);
    $repA = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'srcrpa@example.com']);
    $repB = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'srcrpb@example.com']);
    lcdLeadFor($repA->id, 'OwnSource');
    lcdLeadFor($repB->id, 'OtherSource');

    $adminSources = $this->actingAs($admin)->getJson('/api/leads/sources')->json('data');
    $repSources = $this->actingAs($repA)->getJson('/api/leads/sources')->json('data');

    expect($adminSources)->toContain('OwnSource')->toContain('OtherSource')
        ->and($repSources)->toContain('OwnSource')->not->toContain('OtherSource');
});
