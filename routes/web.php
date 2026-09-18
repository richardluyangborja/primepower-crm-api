<?php

use App\Http\Controllers\EmailOtpController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * Email OTP two-factor login (password + 6-digit Gmail code).
 *
 * These live here — NOT in routes/api.php — on purpose. The `api` group
 * runs Sanctum's EnsureFrontendRequestsAreStateful, which wraps frontend
 * requests in its own EncryptCookies/StartSession/VerifyCsrfToken stack.
 * Adding `web` on top of that (as api.php would) decrypts cookies twice:
 * the first POST passes, but every later POST in the same jar 419s because
 * the route-level session can no longer read its own cookies. Fortify
 * avoids this the same way (`api` prefix + `web` middleware only), so the
 * OTP ceremony follows that exact pattern: single `web` stack, no `api`
 * group. Fortify's password-only POST /api/login is disabled in
 * FortifyServiceProvider so it can never bypass the code step.
 */
Route::middleware([EnsureFrontendRequestsAreStateful::class])->group(function () {
    Route::post('api/auth/otp/request', [EmailOtpController::class, 'request'])->middleware('throttle:5,1');
    Route::post('api/auth/otp/verify', [EmailOtpController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('api/auth/otp/resend', [EmailOtpController::class, 'resend'])->middleware('throttle:5,1');
});

/**
 * Single-artifact deployment: Laravel serves the React frontend build.
 *
 * Deploy step (from repo root): build the frontend, then sync its output
 * into the backend's public dir:
 *
 *   cd front && npm ci && npm run build   # VITE_API_URL empty => same-origin
 *   ../backend/scripts/sync-frontend.sh   # copies dist/* to backend/public/
 *
 * Apache (.htaccess) serves existing static files (index.html, /assets/*)
 * directly; anything else falls through to index.php, i.e. the routes below.
 * API routes (routes/api.php, /api/*) and /sanctum/* take precedence over
 * the fallback, so they always return JSON and never the SPA shell.
 */
if (! function_exists('spaIndexHtmlPath')) {
    function spaIndexHtmlPath(): string
    {
        return public_path('index.html');
    }
}

Route::get('/', function () {
    $index = spaIndexHtmlPath();

    if (file_exists($index)) {
        return response()->file($index);
    }

    // Local dev without a synced frontend build: keep the Laravel default.
    return view('welcome');
});

// SPA fallback: serves the frontend build's index.html for direct visits
// (e.g. shared /survey/{token} links) on single-artifact deployments where
// the backend serves the frontend. Only active when public/index.html exists;
// on split deployments the frontend static host must provide its own rewrite.
Route::fallback(function () {
    $index = spaIndexHtmlPath();

    abort_unless(file_exists($index), 404);

    return response()->file($index);
});
