@extends('layouts.auth')

@section('title', 'Staff sign in')
@section('auth-title', 'Staff sign in')
@section('auth-sub', 'Register, stock room and order desk. Staff and administrator accounts only.')

{{-- The card, the fields, the button and the type are the customer sign-in's,
     inherited rather than restated. What is the staff page's own is the band
     it wears instead of the shop header — the register's dark chrome, saying
     which system you are signing in to — and the terminal line at the foot in
     place of the shop's About/Contact links. --}}
@section('auth-body-class', 'auth-staff')

@section('auth-chrome')
    <div class="auth-staffbar">
        <span class="auth-staffbar-mark">VOID</span>
        <span class="auth-staffbar-sub">Retail System</span>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('staff.login.attempt') }}">
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
    Shopping instead? <a href="{{ route('shop.home') }}">Visit the store</a>
@endsection
