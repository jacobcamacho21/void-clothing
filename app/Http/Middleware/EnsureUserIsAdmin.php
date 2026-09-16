<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the admin-only corners of the back office: staff accounts, customer
 * records and order deletion.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isAdmin(), 403, 'This area is limited to administrators.');

        return $next($request);
    }
}
