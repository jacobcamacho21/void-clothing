@extends('layouts.auth')

@section('title', 'Sign in')
@section('auth-title', 'Sign in')
@section('auth-sub', 'Welcome back. Sign in to check out and track your orders.')

@section('content')
    <form method="POST" action="{{ route('shop.login.attempt') }}">
        @csrf

        <label for="username" class="visually-hidden">Username</label>
        <input type="text" id="username" name="username" placeholder="Username"
               value="{{ old('username') }}" required autofocus autocomplete="username">

        <label for="password" class="visually-hidden">Password</label>
        <input type="password" id="password" name="password" placeholder="Password"
               required autocomplete="current-password">

        <button type="submit">Login</button>
    </form>
@endsection

@section('auth-foot')
    Don't have an account? <a href="{{ route('shop.register') }}">Register here</a>
@endsection
