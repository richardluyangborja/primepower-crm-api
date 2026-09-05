<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedRoleFixtures(): array
{
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
        'email' => 'admin-'.uniqid().'@example.com',
    ]);

    $manager = User::factory()->create([
        'role' => UserRole::MANAGER,
        'email' => 'mgr-'.uniqid().'@example.com',
    ]);

    $repActive = User::factory()->create([
        'role' => UserRole::SALES_REP,
        'email' => 'rep-active-'.uniqid().'@example.com',
        'manager_id' => $manager->id,
    ]);

    $repInactive = User::factory()->create([
        'role' => UserRole::SALES_REP,
        'email' => 'rep-inactive-'.uniqid().'@example.com',
        'is_active' => false,
    ]);

    $repOther = User::factory()->create([
        'role' => UserRole::SALES_REP,
        'email' => 'rep-other-'.uniqid().'@example.com',
    ]);

    return compact('admin', 'manager', 'repActive', 'repInactive', 'repOther');
}

it('returns only active sales reps by default for an admin caller', function () {
    ['admin' => $admin, 'repActive' => $repActive, 'repInactive' => $repInactive] = seedRoleFixtures();

    $response = $this->actingAs($admin)
        ->getJson('/api/sales-representatives');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($repActive->id);
    expect($ids)->not->toContain($repInactive->id);
});

it('automatically includes managers for a manager caller by default', function () {
    ['manager' => $manager, 'repActive' => $repActive] = seedRoleFixtures();

    $response = $this->actingAs($manager)
        ->getJson('/api/sales-representatives');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($repActive->id);
    expect($ids)->toContain($manager->id);
});

it('a sales rep can opt out of managers by not passing the flag', function () {
    ['repActive' => $repActive, 'manager' => $manager] = seedRoleFixtures();

    $response = $this->actingAs($repActive)
        ->getJson('/api/sales-representatives');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($repActive->id);
    expect($ids)->not->toContain($manager->id);
});

it('returns managers when include_managers=1 is passed by a sales rep', function () {
    ['repActive' => $repActive, 'manager' => $manager] = seedRoleFixtures();

    $response = $this->actingAs($repActive)
        ->getJson('/api/sales-representatives?include_managers=1');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($repActive->id);
    expect($ids)->toContain($manager->id);
});

it('automatically includes managers for an admin caller', function () {
    ['admin' => $admin, 'manager' => $manager, 'repActive' => $repActive] = seedRoleFixtures();

    $response = $this->actingAs($admin)
        ->getJson('/api/sales-representatives');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($repActive->id);
    expect($ids)->toContain($manager->id);
});

it('automatically includes managers for a manager caller', function () {
    ['manager' => $manager, 'repActive' => $repActive] = seedRoleFixtures();

    $response = $this->actingAs($manager)
        ->getJson('/api/sales-representatives');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($repActive->id);
    expect($ids)->toContain($manager->id);
});

it('rejects unauthenticated callers', function () {
    $this->getJson('/api/sales-representatives')
        ->assertUnauthorized();
});
