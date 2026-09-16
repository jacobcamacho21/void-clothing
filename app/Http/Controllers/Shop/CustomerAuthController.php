<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function showLogin(): View
    {
        return view('shop.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'shop|'.mb_strtolower($credentials['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'username' => sprintf(
                    'Too many sign-in attempts. Try again in %d seconds.',
                    RateLimiter::availableIn($throttleKey)
                ),
            ]);
        }

        if (! Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'username' => 'Invalid username or password.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('shop.home'));
    }

    public function showRegister(): View
    {
        return view('shop.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:customers,username'],
            'email' => ['required', 'email', 'max:100', 'unique:customers,email'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'terms_accepted' => ['accepted'],
        ], [
            'username.unique' => 'That username is already taken.',
            'email.unique' => 'An account already exists for that email.',
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $customer = Customer::create([
            ...$data,
            'terms_accepted_at' => Carbon::now(),
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()
            ->route('shop.home')
            ->with('status', 'Welcome to VOID, '.$customer->username.'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        // Only the shopper's session state is dropped; a cashier signed in on
        // the same browser through the `web` guard keeps their session.
        $request->session()->forget('shop.cart');
        $request->session()->regenerate();

        return redirect()->route('shop.home');
    }
}
