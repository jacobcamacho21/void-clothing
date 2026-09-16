<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Back-office sign in. Staff reach both the admin pages and the register with
 * the same account.
 */
class StaffAuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Throttled by username + IP so a wrong password on one account cannot
        // be used to lock anyone else out.
        $throttleKey = mb_strtolower($credentials['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'username' => sprintf(
                    'Too many sign-in attempts. Try again in %d seconds.',
                    RateLimiter::availableIn($throttleKey)
                ),
            ]);
        }

        if (! Auth::guard('web')->attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'username' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // Staff spend their shift at the register; admins start on the
        // dashboard. Either can navigate to the other.
        return redirect()->intended(
            Auth::guard('web')->user()->isAdmin()
                ? route('admin.dashboard')
                : route('pos.register')
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // Only the staff guard is dropped. Invalidating the whole session here
        // would also sign out a shopper browsing the storefront in the same
        // browser, which the two separate sessions in the old system avoided.
        // Regenerating the id still closes off session fixation.
        $request->session()->regenerate();

        return redirect()->route('staff.login');
    }
}
