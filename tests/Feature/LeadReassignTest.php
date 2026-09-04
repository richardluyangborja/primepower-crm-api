<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeLeadFor(int $assignedToId): Lead
{
    $company = Company::create([
        'name' => 'Acme Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0100',
        'email' => 'lead+'.uniqid().'@example.com',
        'website' => null,
    ]);

    return Lead::create([
        'company_id' => $company->id,
        'assigned_to_id' => $assignedToId,
        'source' => 'Test',
        'status' => LeadStatus::NEW,
        'notes' => null,
    ]);
}

it('lets an admin reassign a lead', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'adm@example.com']);
    $repA = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'a@example.com']);
    $repB = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'b@example.com']);

    $lead = makeLeadFor($repA->id);

    $response = $this->actingAs($admin)->patchJson("/api/leads/{$lead->id}/reassign", [
        'assigned_to_id' => $repB->id,
        'note' => 'Coverage change',
    ]);

    $response->assertOk();
    expect($lead->fresh()->assigned_to_id)->toBe($repB->id);
});

it('rejects reassignment from a sales rep', function () {
    $repA = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'repA2@example.com']);
    $repB = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'repB2@example.com']);
    $lead = makeLeadFor($repA->id);

    $response = $this->actingAs($repA)->patchJson("/api/leads/{$lead->id}/reassign", [
        'assigned_to_id' => $repB->id,
    ]);

    $response->assertForbidden();
    expect($lead->fresh()->assigned_to_id)->toBe($repA->id);
});

it('auto-creates a client when a lead is converted', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'repconvert@example.com']);
    $lead = makeLeadFor($rep->id);
    $lead->update(['status' => LeadStatus::QUALIFIED]);

    $response = $this->actingAs($rep)->patchJson("/api/leads/{$lead->id}/status", [
        'to_status' => 'converted',
        'reason' => 'Closed the deal',
    ]);

    $response->assertOk();
    expect($lead->fresh()->company->client)->not->toBeNull();
    expect($lead->fresh()->status)->toBe(LeadStatus::CONVERTED);
});
