<?php

use App\Enums\UserRole;
use App\Mail\EmailOtpMail;
use App\Models\EmailOtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(RefreshDatabase::class);

function makeOtpUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => UserRole::SALES_REP,
        'email' => 'otp-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'is_active' => true,
    ], $overrides));
}

function requestOtpCode(TestCase $test, User $user): string
{
    Mail::fake();

    $response = $test->postJson('/api/auth/otp/request', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()->assertJson(['two_factor' => true]);

    $code = null;
    Mail::assertSent(EmailOtpMail::class, function (EmailOtpMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    expect($code)->toMatch('/^[0-9]{6}$/');

    return $code;
}

it('mails a code on correct password without logging in', function () {
    $user = makeOtpUser();

    Mail::fake();

    $response = $this->postJson('/api/auth/otp/request', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['two_factor', 'email', 'masked_email', 'expires_in_seconds', 'expires_at']);

    Mail::assertSent(EmailOtpMail::class, 1);
    $this->assertGuest('web');

    expect(EmailOtpCode::query()->where('email', $user->email)->count())->toBe(1);
});

it('rejects a wrong password without mailing a code', function () {
    $user = makeOtpUser();

    Mail::fake();

    $response = $this->postJson('/api/auth/otp/request', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    Mail::assertNotSent(EmailOtpMail::class);
});

it('logs in on a correct code', function () {
    $user = makeOtpUser();
    $code = requestOtpCode($this, $user);

    $response = $this->postJson('/api/auth/otp/verify', [
        'email' => $user->email,
        'code' => $code,
    ]);

    $response->assertOk()->assertJsonPath('user.email', $user->email);
    $this->assertAuthenticatedAs($user, 'web');
});

it('rejects a wrong code and counts attempts', function () {
    $user = makeOtpUser();
    requestOtpCode($this, $user);

    $response = $this->postJson('/api/auth/otp/verify', [
        'email' => $user->email,
        'code' => '000000',
    ]);

    $response->assertStatus(422)->assertJson(['code' => 'invalid']);
    $this->assertGuest('web');

    expect(EmailOtpCode::query()->latest('id')->first()->attempts)->toBe(1);
});

it('rejects an expired code', function () {
    $user = makeOtpUser();
    $code = requestOtpCode($this, $user);

    EmailOtpCode::query()->where('email', $user->email)->update([
        'expires_at' => now()->subMinute(),
    ]);

    $response = $this->postJson('/api/auth/otp/verify', [
        'email' => $user->email,
        'code' => $code,
    ]);

    $response->assertStatus(410)->assertJson(['code' => 'expired']);
    $this->assertGuest('web');
});

it('voids a code after too many wrong attempts', function () {
    $user = makeOtpUser();
    requestOtpCode($this, $user);

    $max = (int) config('otp.max_attempts', 5);

    for ($i = 0; $i < $max; $i++) {
        $attempt = $this->postJson('/api/auth/otp/verify', [
            'email' => $user->email,
            'code' => '000000',
        ]);

        if ($i < $max - 1) {
            $attempt->assertStatus(422)->assertJson(['code' => 'invalid']);
        } else {
            $attempt->assertStatus(429)->assertJson(['code' => 'locked']);
        }
    }

    // The voided code is gone — even another guess reports no pending code.
    $this->postJson('/api/auth/otp/verify', [
        'email' => $user->email,
        'code' => '000000',
    ])->assertStatus(422)->assertJson(['code' => 'no_pending_code']);

    $this->assertGuest('web');
});

it('enforces the resend cooldown then resends', function () {
    $user = makeOtpUser();
    requestOtpCode($this, $user);

    Mail::fake();

    $this->postJson('/api/auth/otp/resend', ['email' => $user->email])
        ->assertStatus(429)
        ->assertJson(['code' => 'cooldown']);

    EmailOtpCode::query()->where('email', $user->email)->update([
        'created_at' => now()->subMinutes(5),
    ]);

    $this->postJson('/api/auth/otp/resend', ['email' => $user->email])
        ->assertOk()
        ->assertJson(['two_factor' => true]);

    Mail::assertSent(EmailOtpMail::class, 1);
});

it('does not log in through fortify password-only login', function () {
    $user = makeOtpUser();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(422);
    $this->assertGuest('web');
});
