<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only activated users play. Everyone else is sent home to wait.
 */
class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "You're not active yet. An admin needs to activate your account before you can play."]);

            return redirect()->route('home');
        }

        return $next($request);
    }
}
