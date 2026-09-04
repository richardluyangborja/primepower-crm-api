<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'admin@example.com']);
    $this->manager = User::factory()->create(['role' => UserRole::MANAGER, 'email' => 'manager@example.com']);
    $this->salesRepA = User::factory()->create([
        'role' => UserRole::SALES_REP,
        'email' => 'repA@example.com',
        'manager_id' => $this->manager->id,
    ]);
    $this->salesRepB = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'repB@example.com']);
    $this->otherManager = User::factory()->create(['role' => UserRole::MANAGER, 'email' => 'othermgr@example.com']);
});

function makeLead(int $assignedToId): Lead
{
    $company = Company::create([
        'name' => 'Acme Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0100',
        'email' => 'acme+'.uniqid().'@example.com',
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

it('allows admin to view every lead', function () {
    $lead = makeLead($this->salesRepB->id);

    expect($this->admin->can('view', $lead))->toBeTrue();
});

it('allows sales rep to view their own lead', function () {
    $lead = makeLead($this->salesRepA->id);

    expect($this->salesRepA->can('view', $lead))->toBeTrue();
});

it('forbids a sales rep from viewing another reps lead', function () {
    $lead = makeLead($this->salesRepB->id);

    expect($this->salesRepA->can('view', $lead))->toBeFalse();
});

it('allows manager to view their reports leads', function () {
    $lead = makeLead($this->salesRepA->id);

    expect($this->manager->can('view', $lead))->toBeTrue();
});

it('forbids an unrelated manager from viewing a sales reps lead', function () {
    $lead = makeLead($this->salesRepB->id);

    expect($this->otherManager->can('view', $lead))->toBeFalse();
});

it('only managers or admins can reassign leads', function () {
    $lead = makeLead($this->salesRepA->id);

    expect($this->manager->can('reassign', $lead))->toBeTrue();
    expect($this->admin->can('reassign', $lead))->toBeTrue();
    expect($this->salesRepA->can('reassign', $lead))->toBeFalse();
});

it('forbids sales rep from viewing audit logs', function () {
    expect($this->salesRepA->can('viewAny', AuditLog::class))->toBeFalse();
    expect($this->manager->can('viewAny', AuditLog::class))->toBeTrue();
    expect($this->admin->can('viewAny', AuditLog::class))->toBeTrue();
});

it('manages a multi-level reporting chain for managers', function () {
    $teamLead = User::factory()->create([
        'role' => UserRole::SALES_REP,
        'email' => 'teamlead@example.com',
        'manager_id' => $this->manager->id,
    ]);
    $nested = User::factory()->create([
        'role' => UserRole::SALES_REP,
        'email' => 'nested@example.com',
        'manager_id' => $teamLead->id,
    ]);

    expect($this->manager->visibleUserIds()->contains($nested->id))->toBeTrue();
});
