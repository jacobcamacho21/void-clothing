@extends('layouts.shop')

@section('title', 'Verify Email — VOID')

@section('content')
<div class="auth-container" style="max-width: 460px; margin: 4rem auto; padding: 2rem; background: #ffffff; border: 1px solid #e5e5e5; border-radius: 8px;">
    <h1 style="font-size: 1.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; color: #000000;">Verify Your Email</h1>
    <p style="font-size: 0.875rem; color: #666666; margin-bottom: 1.5rem; line-height: 1.5;">
        Thanks for joining VOID! Before getting started, please check your inbox and verify your email address by clicking the link we sent you.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div style="padding: 0.75rem 1rem; margin-bottom: 1.5rem; font-size: 0.875rem; color: #000000; background-color: #f4f4f6; border-left: 3px solid #000000;">
            A new verification link has been sent to the email address you provided during registration.
        </div>
    @endif

    <div style="display: flex; gap: 1rem; align-items: center; justify-content: space-between;">
        <form method="POST" action="{{ route('shop.verification.send') }}">
            @csrf
            <button type="submit" style="padding: 0.75rem 1.25rem; background: #000000; color: #ffffff; border: none; border-radius: 4px; font-weight: 600; text-transform: uppercase; font-size: 0.875rem; cursor: pointer;">
                Resend Email
            </button>
        </form>

        <form method="POST" action="{{ route('shop.logout') }}">
            @csrf
            <button type="submit" style="background: none; border: none; font-size: 0.875rem; color: #666666; text-decoration: underline; cursor: pointer;">
                Log Out
            </button>
        </form>
    </div>
</div>
@endsection