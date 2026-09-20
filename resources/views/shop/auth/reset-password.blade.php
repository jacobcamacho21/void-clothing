@extends('layouts.shop')

@section('title', 'Set New Password — VOID')

@section('content')
<div class="auth-container" style="max-width: 420px; margin: 4rem auto; padding: 2rem; background: #ffffff; border: 1px solid #e5e5e5; border-radius: 8px;">
    <h1 style="font-size: 1.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; color: #000000;">Set New Password</h1>
    <p style="font-size: 0.875rem; color: #666666; margin-bottom: 1.5rem;">Please enter your new password below.</p>

    <form method="POST" action="{{ route('shop.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div style="margin-bottom: 1.25rem;">
            <label for="email" style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem; color: #000000;">Email Address</label>
            <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus
                   style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; font-size: 0.875rem;" readonly>
            @error('email')
                <p style="font-size: 0.75rem; color: #dc2626; margin-top: 0.375rem;">{{ $message }}</p>
            @enderror
        </div>

        <div style="margin-bottom: 1.25rem;">
            <label for="password" style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem; color: #000000;">New Password</label>
            <input id="password" type="password" name="password" required
                   style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; font-size: 0.875rem;">
            @error('password')
                <p style="font-size: 0.75rem; color: #dc2626; margin-top: 0.375rem;">{{ $message }}</p>
            @enderror
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label for="password_confirmation" style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem; color: #000000;">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                   style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; font-size: 0.875rem;">
        </div>

        <button type="submit" style="width: 100%; padding: 0.875rem; background: #000000; color: #ffffff; border: none; border-radius: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer;">
            Reset Password
        </button>
    </form>
</div>
@endsection