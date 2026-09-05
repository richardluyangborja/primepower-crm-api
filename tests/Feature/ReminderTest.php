<?php

use App\Enums\ReminderPriority;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedReminderActor(array $overrides = []): array
{
    $manager = User::factory()->create(array_merge([
        'role' => UserRole::MANAGER,
        'email' => 'mgr-reminder-'.uniqid().'@example.com',
    ], $overrides['manager'] ?? []));

    $rep = User::factory()->create([
        'role' => UserRole::SALES_REP,
        'email' => 'rep-reminder-'.uniqid().'@example.com',
        'manager_id' => $manager->id,
    ]);

    $other = User::factory()->create([
        'role' => UserRole::SALES_REP,
        'email' => 'rep2-reminder-'.uniqid().'@example.com',
    ]);

    $company = Company::create([
        'name' => 'Reminder Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0200',
        'email' => 'remco+'.uniqid().'@example.com',
    ]);

    return [$manager, $rep, $other, $company];
}

function makeReminder(User $user, Company $company, array $overrides = []): Reminder
{
    return Reminder::create(array_merge([
        'company_id' => $company->id,
        'related_to_type' => 'lead',
        'related_to_id' => 1,
        'title' => 'Call back',
        'description' => 'Discuss proposal',
        'due_date' => now()->addDay()->toDateString(),
        'priority' => ReminderPriority::HIGH,
        'status' => 'pending',
        'is_completed' => false,
        'user_id' => $user->id,
    ], $overrides));
}

it('lets a rep create a recurring reminder', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();

    $response = $this->actingAs($rep)->postJson('/api/reminders', [
        'company_id' => $company->id,
        'related_to_type' => 'lead',
        'related_to_id' => 1,
        'title' => 'Weekly check-in',
        'due_date' => now()->addWeek()->toDateString(),
        'priority' => ReminderPriority::MEDIUM->value,
        'recurrence_rule' => 'weekly',
    ]);

    $response->assertCreated();
    $reminder = Reminder::first();
    expect($reminder->recurrence_rule)->toBe('weekly');
    expect($reminder->user_id)->toBe($rep->id);
});

it('rejects invalid recurrence rules', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();

    $this->actingAs($rep)->postJson('/api/reminders', [
        'company_id' => $company->id,
        'related_to_type' => 'lead',
        'related_to_id' => 1,
        'title' => 'Bad',
        'due_date' => now()->addDay()->toDateString(),
        'priority' => ReminderPriority::LOW->value,
        'recurrence_rule' => 'bi-yearly',
    ])->assertStatus(422);
});

it('spawns the next occurrence when a recurring reminder is completed', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();

    $reminder = makeReminder($rep, $company, [
        'due_date' => now()->addDay()->toDateString(),
        'recurrence_rule' => 'weekly',
    ]);

    $this->actingAs($rep)->patchJson("/api/reminders/{$reminder->id}/complete")
        ->assertOk();

    expect(Reminder::where('recurrence_parent_id', $reminder->id)->count())->toBe(1);
    $next = Reminder::where('recurrence_parent_id', $reminder->id)->first();
    expect($next->due_date->toDateString())->toBe(now()->addDay()->addWeek()->toDateString());
    expect($next->recurrence_rule)->toBe('weekly');
});

it('snoozes a reminder to a future date', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $reminder = makeReminder($rep, $company);

    $newDate = now()->addDays(3)->toDateString();

    $response = $this->actingAs($rep)->patchJson("/api/reminders/{$reminder->id}/snooze", [
        'due_date' => $newDate,
    ]);

    $response->assertOk();
    expect($reminder->fresh()->due_date->toDateString())->toBe($newDate);
    expect($reminder->fresh()->status)->toBe('snoozed');
});

it('rejects a snooze to the past', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $reminder = makeReminder($rep, $company);

    $this->actingAs($rep)->patchJson("/api/reminders/{$reminder->id}/snooze", [
        'due_date' => now()->subDay()->toDateString(),
    ])->assertStatus(422);
});

it('blocks a sales rep from snoozing another reps reminder', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $reminder = makeReminder($rep, $company);

    $this->actingAs($other)->patchJson("/api/reminders/{$reminder->id}/snooze", [
        'due_date' => now()->addDays(2)->toDateString(),
    ])->assertForbidden();
});

it('lets a manager snooze a team members reminder', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $reminder = makeReminder($rep, $company);

    $this->actingAs($manager)->patchJson("/api/reminders/{$reminder->id}/snooze", [
        'due_date' => now()->addDays(2)->toDateString(),
    ])->assertOk();
});

it('lets a manager delete a team reminder and logs the action', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $reminder = makeReminder($rep, $company);

    $this->actingAs($manager)->deleteJson("/api/reminders/{$reminder->id}")
        ->assertNoContent();

    expect(Reminder::find($reminder->id))->toBeNull();
    expect(AuditLog::where('subject_id', (string) $reminder->id)
        ->where('action', 'Deleted')->exists())->toBeTrue();
});

it('blocks a rep from deleting their own reminder', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $reminder = makeReminder($rep, $company);

    $this->actingAs($rep)->deleteJson("/api/reminders/{$reminder->id}")
        ->assertForbidden();
});

it('sends due reminder notifications via the artisan command', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $due = makeReminder($rep, $company, [
        'due_date' => now()->toDateString(),
        'is_completed' => false,
    ]);
    $future = makeReminder($rep, $company, [
        'due_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('reminders:send-due --days=1')->assertSuccessful();

    expect($rep->notifications()->count())->toBe(1);
    expect($rep->notifications()->first()->data['reminder_id'])->toBe($due->id);
    expect($rep->notifications()->where('data->reminder_id', $future->id)->exists())->toBeFalse();
});

it('does not double-notify for the same reminder', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    makeReminder($rep, $company, ['due_date' => now()->toDateString()]);

    $this->artisan('reminders:send-due --days=1')->assertSuccessful();
    $this->artisan('reminders:send-due --days=1')->assertSuccessful();

    expect($rep->notifications()->count())->toBe(1);
});

it('marks notifications as read via the api', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    makeReminder($rep, $company, ['due_date' => now()->toDateString()]);

    $this->artisan('reminders:send-due --days=1')->assertSuccessful();

    $notification = $rep->notifications()->first();

    $this->actingAs($rep)->patchJson("/api/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('returns the unread count', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    makeReminder($rep, $company, ['due_date' => now()->toDateString()]);
    $this->artisan('reminders:send-due --days=1')->assertSuccessful();

    $this->actingAs($rep)->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('count', 1);
});
