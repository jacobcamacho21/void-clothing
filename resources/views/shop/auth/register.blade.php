@extends('layouts.auth')

@section('title', 'Create account')
@section('auth-title', 'Create account')
@section('auth-sub', 'One account for your orders, addresses and order tracking.')

@section('content')
    <form method="POST" action="{{ route('shop.register.attempt') }}">
        @csrf

        <label for="username" class="visually-hidden">Username</label>
        <input type="text" id="username" name="username" placeholder="Username"
               value="{{ old('username') }}" maxlength="50" required autofocus autocomplete="username">

        <label for="email" class="visually-hidden">Email</label>
        <input type="email" id="email" name="email" placeholder="Email"
               value="{{ old('email') }}" maxlength="100" required autocomplete="email">

        <label for="password" class="visually-hidden">Password</label>
        <input type="password" id="password" name="password" placeholder="Password"
               minlength="8" required autocomplete="new-password">
        <p class="auth-hint">At least 8 characters.</p>

        <label for="password_confirmation" class="visually-hidden">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation"
               placeholder="Confirm Password" minlength="8" required autocomplete="new-password">

              <label class="auth-check">
                     <input type="checkbox" name="terms_accepted" value="1" required id="termsAccepted">
                     <span>I agree to the Void Clothing <a href="{{ route('shop.terms') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a>.</span>
              </label>

              <button type="submit" id="registerButton" disabled>Create Account</button>
    </form>
@endsection

@section('auth-foot')
    Already have an account? <a href="{{ route('shop.login') }}">Login here</a>
@endsection

@push('scripts')
<script>
       const termsAccepted = document.getElementById('termsAccepted');
       const registerButton = document.getElementById('registerButton');
       termsAccepted.addEventListener('change', () => { registerButton.disabled = !termsAccepted.checked; });
</script>
@endpush
