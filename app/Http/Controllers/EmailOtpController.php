<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailOtpRequestRequest;
use App\Http\Requests\EmailOtpResendRequest;
use App\Http\Requests\EmailOtpVerifyRequest;
use App\Models\User;
use App\Support\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmailOtpController extends Controller
{
    public function __construct(public EmailOtpService $codes) {}

    /**
     * Step 1: check email + password, then mail a 6-digit code.
     * Nothing is logged in here — the session starts at verify().
     */
    public function request(EmailOtpRequestRequest $request): JsonResponse
    {
        $email = mb_strtolower(trim($request->string('email')->toString()));

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json([
                'message' => 'These credentials do not match our records.',
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact an administrator.',
            ], 403);
        }

        $issued = $this->codes->issue($user);

        return response()->json([
            'two_factor' => true,
            'email' => $user->email,
            'masked_email' => $this->codes->maskEmail($user->email),
            'expires_in_seconds' => $this->codes->ttlSeconds(),
            'expires_at' => $issued['expires_at']->toIso8601String(),
        ]);
    }

    /**
     * Step 2: check the 6-digit code, then log the user in.
     */
    public function verify(EmailOtpVerifyRequest $request): JsonResponse
    {
        $email = mb_strtolower(trim($request->string('email')->toString()));
        $code = $request->string('code')->toString();

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->is_active) {
            return response()->json([
                'message' => 'This account is no longer available. Please log in again.',
            ], 403);
        }

        $pending = $this->codes->latestPending($email);

        if ($pending === null) {
            return response()->json([
                'message' => 'No verification code is waiting for this email. Please log in again to get a new one.',
                'code' => 'no_pending_code',
            ], 422);
        }

        if ($pending->expires_at->isPast()) {
            $pending->update(['consumed_at' => now()]);

            return response()->json([
                'message' => 'That code has expired. Please request a new one.',
                'code' => 'expired',
            ], 410);
        }

        if ($pending->attempts >= $this->codes->maxAttempts()) {
            $pending->update(['consumed_at' => now()]);

            return response()->json([
                'message' => 'Too many incorrect attempts. That code is now void — please request a new one.',
                'code' => 'locked',
            ], 429);
        }

        if (! Hash::check($code, $pending->code_hash)) {
            $pending->increment('attempts');
            $remaining = $this->codes->maxAttempts() - $pending->fresh()->attempts;

            if ($remaining <= 0) {
                $pending->update(['consumed_at' => now()]);

                return response()->json([
                    'message' => 'Too many incorrect attempts. That code is now void — please request a new one.',
                    'code' => 'locked',
                ], 429);
            }

            return response()->json([
                'message' => "That code is incorrect. You have {$remaining} ".($remaining === 1 ? 'try' : 'tries').' left before it is voided.',
                'code' => 'invalid',
                'attempts_remaining' => $remaining,
            ], 422);
        }

        $pending->update(['consumed_at' => now()]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
        ]);
    }

    /**
     * Send a fresh code, honoring the resend cooldown.
     */
    public function resend(EmailOtpResendRequest $request): JsonResponse
    {
        $email = mb_strtolower(trim($request->string('email')->toString()));

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->is_active) {
            return response()->json([
                'message' => 'This account is no longer available. Please log in again.',
            ], 403);
        }

        $retryAfter = $this->codes->resendCooldownRemaining($email);

        if ($retryAfter > 0) {
            return response()->json([
                'message' => "Please wait {$retryAfter} seconds before requesting another code.",
                'code' => 'cooldown',
                'retry_after_seconds' => $retryAfter,
            ], 429);
        }

        $issued = $this->codes->issue($user);

        return response()->json([
            'two_factor' => true,
            'email' => $user->email,
            'masked_email' => $this->codes->maskEmail($user->email),
            'expires_in_seconds' => $this->codes->ttlSeconds(),
            'expires_at' => $issued['expires_at']->toIso8601String(),
        ]);
    }
}
