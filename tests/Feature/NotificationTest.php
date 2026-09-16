<?php

use App\Enums\ReminderPriority;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Reminder;
use App\Models\ReminderView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedNotificationActors(): array
{
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
        'email' => 'admin-notif-'.uniqid().'@example.com',
    ]);
    $manager = User::factory()->create([
        'role' => UserRole::MANAGER,
        'email' => 'mgr-notif-'.uniqid().'@example.com',
    ]);
    $rep = User::factory()->create([
        'role' => UserRole::SALES_REP,
        'email' => 'rep-notif-'.uniqid().'@example.com',
    ]);
    $other = User::factory()->create([
        'role' => UserRole::SALES_REP,
        'email' => 'rep2-notif-'.uniqid().'@example.com',
    ]);

    $company = Company::create([
        'name' => 'Notif Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0200',
        'email' => 'notifco+'.uniqid().'@example.com',
    ]);

    return [$admin, $manager, $rep, $other, $company];
}

function makeOverdueReminder(User $owner, Company $company, array $overrides = []): Reminder
{
    return Reminder::create(array_merge([
        'company_id' => $company->id,
        'related_to_type' => 'lead',
        'related_to_id' => (string) Str::uuid(),
        'title' => 'Overdue follow-up',
        'description' => 'Call back',
        'due_date' => now()->subDay()->toDateString(),
        'priority' => ReminderPriority::HIGH,
        'status' => 'pending',
        'is_completed' => false,
        'user_id' => $owner->id,
    ], $overrides));
}

it('lists overdue reminders as unviewed without any scheduler run', function () {
    [$admin, $manager, $rep, $other, $company] = seedNotificationActors();
    makeOverdueReminder($rep, $company);

    $response = $this->actingAs($admin)->getJson('/api/notifications');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['type'])->toBe('reminder_overdue');
    expect($data[0]['reminder_id'])->toBe(Reminder::first()->id);
    expect($data[0]['read_at'])->toBeNull();
});

it('gives admin and manager read access to every overdue reminder', function () {
    [$admin, $manager, $rep, $other, $company] = seedNotificationActors();
    makeOverdueReminder($rep, $company, ['title' => 'Rep overdue']);
    makeOverdueReminder($other, $company, ['title' => 'Other overdue']);

    $adminIds = collect($this->actingAs($admin)->getJson('/api/notifications')->json('data'))
        ->pluck('reminder_id')->sort()->values()->all();
    $managerIds = collect($this->actingAs($manager)->getJson('/api/notifications')->json('data'))
        ->pluck('reminder_id')->sort()->values()->all();

    expect($adminIds)->toHaveCount(2);
    expect($managerIds)->toHaveCount(2);
    expect($this->actingAs($admin)->getJson('/api/notifications/unread-count')->json('count'))->toBe(2);
});

it('restricts sales reps to their own overdue reminders', function () {
    [$admin, $manager, $rep, $other, $company] = seedNotificationActors();
    $mine = makeOverdueReminder($rep, $company, ['title' => 'Mine']);
    makeOverdueReminder($other, $company, ['title' => 'Theirs']);

    $response = $this->actingAs($rep)->getJson('/api/notifications');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['reminder_id'])->toBe($mine->id);
    expect($this->actingAs($rep)->getJson('/api/notifications/unread-count')->json('count'))->toBe(1);
});

it('marks an overdue reminder as viewed and drops the unread count', function () {
    [$admin, $manager, $rep, $other, $company] = seedNotificationActors();
    $reminder = makeOverdueReminder($rep, $company);

    $response = $this->actingAs($rep)->patchJson("/api/notifications/{$reminder->id}/read");

    $response->assertOk();
    expect($response->json('data.read_at'))->not->toBeNull();
    expect(ReminderView::where('reminder_id', $reminder->id)->where('user_id', $rep->id)->exists())->toBeTrue();
    expect($this->actingAs($rep)->getJson('/api/notifications/unread-count')->json('count'))->toBe(0);
});

it('keeps viewed state per user', function () {
    [$admin, $manager, $rep, $other, $company] = seedNotificationActors();
    $reminder = makeOverdueReminder($rep, $company);

    $this->actingAs($rep)->patchJson("/api/notifications/{$reminder->id}/read");

    $adminItem = collect($this->actingAs($admin)->getJson('/api/notifications')->json('data'))->first();
    expect($adminItem['read_at'])->toBeNull();
});

it('forbids a sales rep from marking another rep overdue reminder as viewed', function () {
    [$admin, $manager, $rep, $other, $company] = seedNotificationActors();
    $reminder = makeOverdueReminder($other, $company);

    $this->actingAs($rep)->patchJson("/api/notifications/{$reminder->id}/read")->assertForbidden();
});

it('marks all visible overdue reminders as viewed', function () {
    [$admin, $manager, $rep, $other, $company] = seedNotificationActors();
    makeOverdueReminder($rep, $company);
    makeOverdueReminder($rep, $company, ['title' => 'Second']);
    makeOverdueReminder($other, $company, ['title' => 'Theirs']);

    $this->actingAs($rep)->postJson('/api/notifications/mark-all-read')->assertOk();

    expect($this->actingAs($rep)->getJson('/api/notifications/unread-count')->json('count'))->toBe(0);
    // The other rep's overdue reminder is outside the rep's scope, still unviewed for admin.
    expect($this->actingAs($admin)->getJson('/api/notifications/unread-count')->json('count'))->toBe(3);
});

it('excludes completed and upcoming reminders from the feed', function () {
    [$admin, $manager, $rep, $other, $company] = seedNotificationActors();
    makeOverdueReminder($rep, $company);
    makeOverdueReminder($rep, $company, [
        'title' => 'Done',
        'is_completed' => true,
        'status' => 'completed',
    ]);
    makeOverdueReminder($rep, $company, [
        'title' => 'Upcoming',
        'due_date' => now()->addDay()->toDateString(),
    ]);

    $data = $this->actingAs($admin)->getJson('/api/notifications')->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['title'])->toBe('Overdue follow-up');
});
