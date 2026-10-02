<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gatekeeper for every admin page:
 *  - must be logged in, an active admin
 *  - the login is valid for 24 hours from the moment it happened (hard limit, not refreshed by activity)
 *  - admin pages are never cached by the browser (so "Back" after logout shows nothing)
 */
class EnsureUserIsAdmin
{
    public const LOGIN_TTL = 24 * 60 * 60; // seconds

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $loggedInAt = (int) $request->session()->get('admin_login_at', 0);

        if (! $user || ! $user->isAdmin() || ! $user->is_active) {
            return $this->kick($request, 'You are not authorized to access the admin panel.');
        }

        if (! $loggedInAt || time() - $loggedInAt > self::LOGIN_TTL) {
            return $this->kick($request, 'Your session has expired. Please sign in again.');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');

        return $response;
    }

    private function kick(Request $request, string $message): Response
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->route('admin.login')->withErrors(['email' => $message]);
    }
}
