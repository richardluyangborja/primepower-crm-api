<?php

use App\Enums\ClientStatus;
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
use App\Models\EscalationRule;
use App\Models\EscalationTrigger;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\StageHistory;
use App\Models\Team;
use App\Models\User;
use App\Notifications\EscalationNotification;
use App\Support\EscalationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function escalationUser(string $role, array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => $role,
        'email' => $role.'-'.uniqid().'@example.com',
    ], $overrides));
}

function escalationCompany(): Company
{
    return Company::create([
        'name' => 'Escalation Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0300',
        'email' => 'esc+'.uniqid().'@example.com',
    ]);
}

function makeEscalationRule(array $overrides = []): EscalationRule
{
    return EscalationRule::create(array_merge([
        'name' => 'Inactive lead follow-up',
        'entity_type' => 'lead',
        'condition' => 'inactive_lead',
        'threshold_days' => 7,
        'action_type' => 'create_reminder_and_notify',
        'reminder_title' => 'Follow up on lead',
        'reminder_priority' => ReminderPriority::MEDIUM->value,
        'reminder_due_in_days' => 2,
        'is_active' => true,
    ], $overrides));
}

function seedRepWithManager(): array
{
    $team = Team::create(['name' => 'Esc Team '.uniqid(), 'slug' => 'esc-'.uniqid()]);

    $manager = escalationUser(UserRole::MANAGER->value, ['team_id' => $team->id]);
    $rep = escalationUser(UserRole::SALES_REP->value, [
        'team_id' => $team->id,
        'manager_id' => $manager->id,
    ]);

    return [$manager, $rep, $team];
}

it('lets every role read escalation rules', function () {
    makeEscalationRule();

    foreach ([UserRole::ADMIN->value, UserRole::MANAGER->value, UserRole::SALES_REP->value] as $role) {
        $user = escalationUser($role);

        $this->actingAs($user)->getJson('/api/escalation-rules')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
});

it('only lets an admin create escalation rules', function () {
    $payload = [
        'name' => 'Stale opp rule',
        'entity_type' => 'opportunity',
        'condition' => 'stale_opportunity',
        'threshold_days' => 10,
        'action_type' => 'notify_manager',
        'is_active' => true,
    ];

    $admin = escalationUser(UserRole::ADMIN->value);
    $this->actingAs($admin)->postJson('/api/escalation-rules', $payload)
        ->assertCreated();

    expect(EscalationRule::where('name', 'Stale opp rule')->exists())->toBeTrue();

    foreach ([UserRole::MANAGER->value, UserRole::SALES_REP->value] as $role) {
        $user = escalationUser($role);
        $this->actingAs($user)->postJson('/api/escalation-rules', $payload)
            ->assertForbidden();
    }
});

it('only lets an admin update and delete escalation rules', function () {
    $rule = makeEscalationRule();

    $admin = escalationUser(UserRole::ADMIN->value);
    $this->actingAs($admin)->putJson("/api/escalation-rules/{$rule->id}", [
        'name' => 'Renamed',
        'entity_type' => 'lead',
        'condition' => 'inactive_lead',
        'threshold_days' => 3,
        'action_type' => 'notify_manager',
        'is_active' => false,
    ])->assertOk();

    expect($rule->fresh()->name)->toBe('Renamed');
    expect($rule->fresh()->is_active)->toBeFalse();

    $manager = escalationUser(UserRole::MANAGER->value);
    $this->actingAs($manager)->putJson("/api/escalation-rules/{$rule->id}", [
        'name' => 'Nope',
        'entity_type' => 'lead',
        'condition' => 'inactive_lead',
        'threshold_days' => 3,
        'action_type' => 'notify_manager',
        'is_active' => true,
    ])->assertForbidden();

    $rep = escalationUser(UserRole::SALES_REP->value);
    $this->actingAs($rep)->deleteJson("/api/escalation-rules/{$rule->id}")
        ->assertForbidden();

    $this->actingAs($admin)->deleteJson("/api/escalation-rules/{$rule->id}")
        ->assertNoContent();

    expect(EscalationRule::find($rule->id))->toBeNull();
});

it('rejects a condition that does not match the entity type', function () {
    $admin = escalationUser(UserRole::ADMIN->value);

    $this->actingAs($admin)->postJson('/api/escalation-rules', [
        'name' => 'Bad pairing',
        'entity_type' => 'lead',
        'condition' => 'stale_opportunity',
        'threshold_days' => 7,
        'action_type' => 'notify_manager',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('condition');
});

it('requires reminder fields when the action creates a reminder', function () {
    $admin = escalationUser(UserRole::ADMIN->value);

    $this->actingAs($admin)->postJson('/api/escalation-rules', [
        'name' => 'Missing reminder config',
        'entity_type' => 'lead',
        'condition' => 'inactive_lead',
        'threshold_days' => 7,
        'action_type' => 'create_reminder',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['reminder_title', 'reminder_priority', 'reminder_due_in_days']);
});

it('forbids creating a reminder on a reminder entity', function () {
    $admin = escalationUser(UserRole::ADMIN->value);

    $this->actingAs($admin)->postJson('/api/escalation-rules', [
        'name' => 'Bad reminder action',
        'entity_type' => 'reminder',
        'condition' => 'overdue_reminder',
        'threshold_days' => 3,
        'action_type' => 'create_reminder_and_notify',
        'reminder_title' => 'Nope',
        'reminder_priority' => ReminderPriority::HIGH->value,
        'reminder_due_in_days' => 1,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('action_type');
});

it('fires an inactive-lead rule: reminder + manager escalation, once', function () {
    [$manager, $rep] = seedRepWithManager();
    $company = escalationCompany();

    $stale = Lead::create([
        'company_id' => $company->id,
        'assigned_to_id' => $rep->id,
        'source' => 'Test',
        'status' => LeadStatus::NEW,
        'notes' => null,
    ]);

    // A lead with recent communication must NOT match.
    $activeCompany = escalationCompany();
    $active = Lead::create([
        'company_id' => $activeCompany->id,
        'assigned_to_id' => $rep->id,
        'source' => 'Test',
        'status' => LeadStatus::NEW,
        'notes' => null,
    ]);
    Communication::create([
        'company_id' => $activeCompany->id,
        'user_id' => $rep->id,
        'type' => CommunicationType::EMAIL->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'subject' => 'Recent',
        'created_at' => now(),
    ]);

    $rule = makeEscalationRule([
        'entity_type' => 'lead',
        'condition' => 'inactive_lead',
        'threshold_days' => 7,
        'action_type' => 'create_reminder_and_notify',
        'reminder_title' => 'Follow up on lead',
        'reminder_priority' => ReminderPriority::HIGH->value,
        'reminder_due_in_days' => 2,
    ]);

    $fired = (new EscalationEngine)->evaluate();

    expect($fired)->toBe(1);

    $reminders = Reminder::where('related_to_type', 'lead')->where('related_to_id', $stale->id)->get();
    expect($reminders)->toHaveCount(1);
    expect($reminders->first()->user_id)->toBe($rep->id);
    expect($reminders->first()->assigned_to_name)->toBe($rep->name);
    expect($reminders->first()->due_date->toDateString())->toBe(now()->addDays(2)->toDateString());
    expect($reminders->first()->priority)->toBe(ReminderPriority::HIGH);
    expect($reminders->first()->is_completed)->toBeFalse();

    expect(Reminder::where('related_to_id', $active->id)->count())->toBe(0);

    expect($manager->notifications()->where('type', EscalationNotification::class)->count())->toBe(1);

    expect(EscalationTrigger::where('escalation_rule_id', $rule->id)->count())->toBe(1);

    // Idempotent: a second run must not double-fire.
    (new EscalationEngine)->evaluate();
    expect(Reminder::count())->toBe(1);
    expect(EscalationTrigger::where('escalation_rule_id', $rule->id)->count())->toBe(1);
    expect($manager->notifications()->where('type', EscalationNotification::class)->count())->toBe(1);
});

it('fires an inactive-client rule', function () {
    [$manager, $rep] = seedRepWithManager();
    $company = escalationCompany();

    $stale = Client::create([
        'company_id' => $company->id,
        'assigned_to_id' => $rep->id,
        'status' => ClientStatus::ACTIVE->value,
        'client_since' => now()->subMonths(3)->toDateString(),
        'notes' => null,
    ]);

    $rule = makeEscalationRule([
        'entity_type' => 'client',
        'condition' => 'inactive_client',
        'threshold_days' => 14,
        'action_type' => 'create_reminder',
        'reminder_title' => 'Touch base',
        'reminder_due_in_days' => 1,
    ]);

    $fired = (new EscalationEngine)->evaluate();

    expect($fired)->toBe(1);
    expect(Reminder::where('related_to_type', 'client')->where('related_to_id', $stale->id)->count())->toBe(1);
    // notify_manager action is off → no escalation notification.
    expect($manager->notifications()->where('type', EscalationNotification::class)->count())->toBe(0);
});

it('fires a stale-opportunity rule', function () {
    [$manager, $rep] = seedRepWithManager();
    $company = escalationCompany();

    $stale = Opportunity::create([
        'company_id' => $company->id,
        'assigned_to_id' => $rep->id,
        'title' => 'Stale deal',
        'stage' => OpportunityStage::DISCUSSION->value,
    ]);
    $staleHistory = StageHistory::create([
        'opportunity_id' => $stale->id,
        'user_id' => $rep->id,
        'from_stage' => OpportunityStage::INITIAL_CONTACT->value,
        'to_stage' => OpportunityStage::DISCUSSION->value,
    ]);
    $staleHistory->forceFill(['created_at' => now()->subDays(20)])->save();

    $recent = Opportunity::create([
        'company_id' => $company->id,
        'assigned_to_id' => $rep->id,
        'title' => 'Fresh deal',
        'stage' => OpportunityStage::DISCUSSION->value,
    ]);
    $recentHistory = StageHistory::create([
        'opportunity_id' => $recent->id,
        'user_id' => $rep->id,
        'from_stage' => OpportunityStage::INITIAL_CONTACT->value,
        'to_stage' => OpportunityStage::DISCUSSION->value,
    ]);
    $recentHistory->forceFill(['created_at' => now()])->save();

    $rule = makeEscalationRule([
        'entity_type' => 'opportunity',
        'condition' => 'stale_opportunity',
        'threshold_days' => 7,
        'action_type' => 'notify_manager',
    ]);

    $fired = (new EscalationEngine)->evaluate();

    expect($fired)->toBe(1);
    expect($manager->notifications()->where('type', EscalationNotification::class)->count())->toBe(1);
    expect(Reminder::where('related_to_type', 'opportunity')->where('related_to_id', $stale->id)->count())->toBe(0);
});

it('fires an overdue-reminder rule and escalates to the manager', function () {
    [$manager, $rep] = seedRepWithManager();
    $company = escalationCompany();

    Reminder::create([
        'company_id' => $company->id,
        'related_to_type' => 'lead',
        'related_to_id' => 1,
        'title' => 'Overdue task',
        'due_date' => now()->subDays(10)->toDateString(),
        'priority' => ReminderPriority::HIGH->value,
        'status' => 'pending',
        'is_completed' => false,
        'user_id' => $rep->id,
    ]);

    $rule = makeEscalationRule([
        'entity_type' => 'reminder',
        'condition' => 'overdue_reminder',
        'threshold_days' => 7,
        'action_type' => 'notify_manager',
    ]);

    $fired = (new EscalationEngine)->evaluate();

    expect($fired)->toBe(1);
    expect($manager->notifications()->where('type', EscalationNotification::class)->count())->toBe(1);

    // The escalated notification points at the overdue reminder.
    $data = $manager->notifications()->where('type', EscalationNotification::class)->first()->data;
    expect($data['reminder_id'])->toBeInt();
    expect($data['reminder_id'])->toBe(Reminder::first()->id);

    // No follow-up reminder is created on top of a reminder.
    expect(Reminder::count())->toBe(1);
});

it('ignores inactive rules', function () {
    [$manager, $rep] = seedRepWithManager();
    $company = escalationCompany();

    Lead::create([
        'company_id' => $company->id,
        'assigned_to_id' => $rep->id,
        'source' => 'Test',
        'status' => LeadStatus::NEW,
        'notes' => null,
    ]);

    makeEscalationRule(['is_active' => false]);

    $fired = (new EscalationEngine)->evaluate();

    expect($fired)->toBe(0);
    expect(Reminder::count())->toBe(0);
});

it('audits each escalation fire', function () {
    [$manager, $rep] = seedRepWithManager();
    $company = escalationCompany();

    Lead::create([
        'company_id' => $company->id,
        'assigned_to_id' => $rep->id,
        'source' => 'Test',
        'status' => LeadStatus::NEW,
        'notes' => null,
    ]);

    $rule = makeEscalationRule();

    (new EscalationEngine)->evaluate();

    expect(AuditLog::where('module', 'Escalation')->where('action', 'Triggered')->count())->toBe(1);
});
