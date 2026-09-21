@extends('layouts.auth')

@section('title', 'Sign in')
@section('auth-title', 'Sign in')
@section('auth-sub', 'Welcome back. Sign in to check out and track your orders.')

@section('content')
    <form method="POST" action="{{ route('shop.login.attempt') }}">
        @csrf

        <label for="username" class="visually-hidden">Username</label>
        <input type="email" name="email" placeholder="Email" value="{{ old('email') }}" required autofocus>

        <label for="password" class="visually-hidden">Password</label>
        <input type="password" id="password" name="password" placeholder="Password"
               required autocomplete="current-password">

        <div style="display: flex; justify-content: flex-end; margin: -0.25rem 0 1rem 0;">
            <a href="{{ route('shop.password.request') }}" style="font-size: 0.8125rem; color: #666; text-decoration: underline;">
                Forgot password?
            </a>
        </div>

        <button type="submit">Login</button>
    </form>
@endsection

@section('auth-foot')
    Don't have an account? <a href="{{ route('shop.register') }}">Register here</a>
@endsection