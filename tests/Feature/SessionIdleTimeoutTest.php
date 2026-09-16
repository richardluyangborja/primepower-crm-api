<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('active session is kept alive', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
});

test('idle session beyond timeout is logged out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['last_activity_time' => time() - 120])
        ->get('/');

    $response->assertRedirect('/login');
    $this->assertGuest('web');
});
