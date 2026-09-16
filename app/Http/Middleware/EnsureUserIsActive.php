<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out an account that was deactivated while its session was still open,
 * so revoking access takes effect on the next request instead of at logout.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            // As with an explicit logout, only the staff guard is dropped so a
            // shopper signed in on the same browser is left alone.
            Auth::guard('web')->logout();
            $request->session()->regenerate();

            return redirect()
                ->route('staff.login')
                ->withErrors(['username' => 'This account has been deactivated.']);
        }

        return $next($request);
    }
}
