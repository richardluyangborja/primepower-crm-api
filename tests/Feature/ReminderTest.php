<?php

use App\Enums\ReminderPriority;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

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
        'related_to_id' => (string) Str::uuid(),
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
        'related_to_id' => (string) Str::uuid(),
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
        'related_to_id' => (string) Str::uuid(),
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

it('blocks a manager from deleting a team reminder', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $reminder = makeReminder($rep, $company);

    $this->actingAs($manager)->deleteJson("/api/reminders/{$reminder->id}")
        ->assertForbidden();

    expect(Reminder::find($reminder->id))->not->toBeNull();
});

it('allows a rep to delete their own reminder', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $reminder = makeReminder($rep, $company);

    $this->actingAs($rep)->deleteJson("/api/reminders/{$reminder->id}")
        ->assertNoContent();

    expect(Reminder::find($reminder->id))->toBeNull();
});

it('blocks a rep from deleting another reps reminder', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $reminder = makeReminder($rep, $company);

    $this->actingAs($other)->deleteJson("/api/reminders/{$reminder->id}")
        ->assertForbidden();

    expect(Reminder::find($reminder->id))->not->toBeNull();
});

it('lists overdue reminders in the notifications feed without any scheduler run', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $due = makeReminder($rep, $company, [
        'due_date' => now()->subDay()->toDateString(),
        'is_completed' => false,
    ]);
    $future = makeReminder($rep, $company, [
        'due_date' => now()->addDays(5)->toDateString(),
    ]);

    $data = $this->actingAs($rep)->getJson('/api/notifications')->assertOk()->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['reminder_id'])->toBe($due->id);
    expect(collect($data)->pluck('reminder_id')->contains($future->id))->toBeFalse();
});

it('does not duplicate feed entries for the same reminder', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    makeReminder($rep, $company, ['due_date' => now()->subDay()->toDateString()]);

    $first = $this->actingAs($rep)->getJson('/api/notifications')->json('data');
    $second = $this->actingAs($rep)->getJson('/api/notifications')->json('data');

    expect($first)->toHaveCount(1);
    expect($second)->toHaveCount(1);
});

it('marks notifications as read via the api', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    $reminder = makeReminder($rep, $company, ['due_date' => now()->subDay()->toDateString()]);

    $response = $this->actingAs($rep)->patchJson("/api/notifications/{$reminder->id}/read");

    $response->assertOk()->assertJsonPath('data.id', (string) $reminder->id);
    expect($response->json('data.read_at'))->not->toBeNull();

    expect($this->actingAs($rep)->getJson('/api/notifications/unread-count')->json('count'))->toBe(0);
});

it('returns the unread count', function () {
    [$manager, $rep, $other, $company] = seedReminderActor();
    makeReminder($rep, $company, ['due_date' => now()->subDay()->toDateString()]);

    $this->actingAs($rep)->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('count', 1);
});
