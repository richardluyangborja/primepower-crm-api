<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionIdleTimeout
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! Auth::check()) {
            return $next($request);
        }

        $timeout = (int) env('SESSION_IDLE_TIMEOUT', 60);
        $lastActivity = (int) $request->session()->get('last_activity_time', 0);
        $now = time();

        if ($lastActivity > 0 && ($now - $lastActivity) > $timeout) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Session expired due to inactivity.'], 401);
            }

            return redirect('/login');
        }

        $request->session()->put('last_activity_time', $now);

        return $next($request);
    }
}
