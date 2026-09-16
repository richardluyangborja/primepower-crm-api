<?php

use App\Enums\CommunicationDirection;
use App\Enums\CommunicationType;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Enums\ReminderPriority;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Communication;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'admin@example.com']);
    $this->manager = User::factory()->create(['role' => UserRole::MANAGER, 'email' => 'manager@example.com']);
    $this->salesRepA = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'repA@example.com']);
    $this->salesRepB = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'repB@example.com']);
    $this->otherManager = User::factory()->create(['role' => UserRole::MANAGER, 'email' => 'othermgr@example.com']);
});

function makeLead(string $assignedToId): Lead
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

function makeClient(string $assignedToId): array
{
    $company = Company::create([
        'name' => 'Client Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0100',
        'email' => 'clientco+'.uniqid().'@example.com',
        'website' => null,
    ]);

    $client = $company->client()->create([
        'assigned_to_id' => $assignedToId,
        'status' => 'active',
        'client_since' => now(),
    ]);

    return [$company, $client];
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

it('allows every manager to view any lead, regardless of owner', function () {
    $leadA = makeLead($this->salesRepA->id);
    $leadB = makeLead($this->salesRepB->id);

    expect($this->manager->can('view', $leadA))->toBeTrue();
    expect($this->otherManager->can('view', $leadB))->toBeTrue();
});

it('managers see every user id; sales reps see only themselves', function () {
    foreach ([$this->admin, $this->manager, $this->otherManager] as $user) {
        $visible = $user->visibleUserIds();

        expect($visible)->toContain($this->salesRepA->id);
        expect($visible)->toContain($this->salesRepB->id);
    }

    expect($this->salesRepA->visibleUserIds()->all())->toBe([$this->salesRepA->id]);
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

it('gives managers the same dataset scope as admins even without a reporting chain', function () {
    expect($this->manager->visibleUserIds()->sort()->values()->all())
        ->toBe(User::query()->pluck('id')->sort()->values()->all());
});

it('blocks managers from creating records in every module', function () {
    expect($this->manager->can('create', Lead::class))->toBeFalse();
    expect($this->manager->can('create', Client::class))->toBeFalse();
    expect($this->manager->can('create', Opportunity::class))->toBeFalse();
    expect($this->manager->can('create', Reminder::class))->toBeFalse();
    expect($this->manager->can('create', Communication::class))->toBeFalse();
    expect($this->manager->can('create', Contact::class))->toBeFalse();
});

it('blocks managers from editing records they can view', function () {
    $lead = makeLead($this->salesRepA->id);
    expect($this->manager->can('update', $lead))->toBeFalse();

    [$company, $client] = makeClient($this->salesRepA->id);
    expect($this->manager->can('update', $client))->toBeFalse();

    $opportunity = Opportunity::create([
        'company_id' => $company->id,
        'assigned_to_id' => $this->salesRepA->id,
        'title' => 'Big deal',
        'stage' => OpportunityStage::INITIAL_CONTACT,
    ]);
    expect($this->manager->can('update', $opportunity))->toBeFalse();

    $reminder = Reminder::create([
        'company_id' => $company->id,
        'related_to_type' => 'lead',
        'related_to_id' => $lead->id,
        'user_id' => $this->salesRepA->id,
        'title' => 'Call back',
        'due_date' => now()->addDay()->toDateString(),
        'priority' => ReminderPriority::HIGH,
        'status' => 'pending',
        'is_completed' => false,
    ]);
    expect($this->manager->can('update', $reminder))->toBeFalse();

    $communication = Communication::create([
        'company_id' => $company->id,
        'user_id' => $this->salesRepA->id,
        'type' => CommunicationType::EMAIL->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'subject' => 'Follow up',
    ]);
    expect($this->manager->can('update', $communication))->toBeFalse();

    $contact = Contact::create([
        'company_id' => $company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'title' => 'Director',
        'email' => 'jane+'.uniqid().'@example.com',
        'phone' => '+1 555 0101',
        'is_primary' => true,
    ]);
    expect($this->manager->can('update', $contact))->toBeFalse();
});

it('keeps reassign as the sole write action, and an admin can still write everywhere', function () {
    $lead = makeLead($this->salesRepA->id);

    expect($this->manager->can('reassign', $lead))->toBeTrue();

    expect($this->admin->can('create', Lead::class))->toBeTrue();
    expect($this->admin->can('update', $lead))->toBeTrue();
});
