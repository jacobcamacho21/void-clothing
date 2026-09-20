@extends('layouts.shop')

@section('title', 'Forgot Password — VOID')

@section('content')
<div class="auth-container" style="max-width: 420px; margin: 4rem auto; padding: 2rem; background: #ffffff; border: 1px solid #e5e5e5; border-radius: 8px;">
    <h1 style="font-size: 1.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; color: #000000;">Reset Password</h1>
    <p style="font-size: 0.875rem; color: #666666; margin-bottom: 1.5rem;">Enter your email address below and we'll send you a link to reset your password.</p>

    @if (session('status'))
        <div style="padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.875rem; color: #000000; background-color: #f4f4f6; border-left: 3px solid #000000;">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('shop.password.email') }}">
        @csrf

        <div style="margin-bottom: 1.25rem;">
            <label for="email" style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; tracking: 0.05em; margin-bottom: 0.5rem; color: #000000;">Email Address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;"
                   onfocus="this.style.borderColor='#000'" onblur="this.style.borderColor='#ccc'">
            @error('email')
                <p style="font-size: 0.75rem; color: #dc2626; margin-top: 0.375rem;">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" style="width: 100%; padding: 0.875rem; background: #000000; color: #ffffff; border: none; border-radius: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: opacity 0.2s;"
                onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
            Send Reset Link
        </button>
    </form>

    <div style="margin-top: 1.5rem; text-align: center;">
        <a href="{{ route('shop.login') }}" style="font-size: 0.875rem; color: #000000; text-decoration: underline; font-weight: 500;">Back to Login</a>
    </div>
</div>
@endsection