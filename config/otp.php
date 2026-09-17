<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email OTP Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | After a correct email + password, login pauses until the user enters
    | the 6-digit verification code mailed to their inbox (Gmail SMTP).
    | Codes are stored hashed, single-use, and short-lived.
    |
    */

    // How long an issued code stays valid, in seconds.
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 600),

    // Wrong-code guesses allowed per issued code before it is voided.
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    // Seconds between resend requests for the same email address.
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),

];
