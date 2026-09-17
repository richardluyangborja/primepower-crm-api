<?php

namespace App\Support;

use App\Mail\EmailOtpMail;
use App\Models\EmailOtpCode;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class EmailOtpService
{
    public function ttlSeconds(): int
    {
        return (int) config('otp.ttl_seconds', 600);
    }

    public function maxAttempts(): int
    {
        return (int) config('otp.max_attempts', 5);
    }

    public function resendCooldownSeconds(): int
    {
        return (int) config('otp.resend_cooldown_seconds', 60);
    }

    /**
     * Issue a fresh code for the user, voiding older unconsumed ones.
     *
     * @return array{expires_at: CarbonInterface}
     */
    public function issue(User $user): array
    {
        $email = mb_strtolower(trim($user->email));

        EmailOtpCode::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(100000, 999999);

        $record = EmailOtpCode::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds($this->ttlSeconds()),
            'attempts' => 0,
        ]);

        // Sent synchronously: the code must be in the inbox before the
        // 10-minute countdown matters, and local queues may have no worker.
        Mail::to($user->email)->send(new EmailOtpMail(
            code: $code,
            userName: $user->name,
            expiresInMinutes: (int) ceil($this->ttlSeconds() / 60),
        ));

        return ['expires_at' => $record->expires_at];
    }

    public function latestPending(string $email): ?EmailOtpCode
    {
        return EmailOtpCode::query()
            ->where('email', mb_strtolower(trim($email)))
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();
    }

    public function resendCooldownRemaining(string $email): int
    {
        $latest = EmailOtpCode::query()
            ->where('email', mb_strtolower(trim($email)))
            ->latest('id')
            ->first();

        if ($latest === null) {
            return 0;
        }

        // Carbon 3 diffs are signed by default — pass absolute explicitly.
        $elapsed = now()->diffInSeconds($latest->created_at, true);

        return (int) max(0, $this->resendCooldownSeconds() - $elapsed);
    }

    public function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return $email;
        }

        $visible = mb_substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }
}
