@extends('layouts.shop')

@section('title', 'Contact VOID Support')
@section('body-class', 'shop-contact')

@section('content')
<main class="support-page">
    <div class="support-intro">
        <p class="support-kicker">VOID CLOTHING / CUSTOMER CARE</p>
        <h1>Send us a message for any support.</h1>
        <p class="support-copy">
            Questions about an order, sizing, delivery, or a piece you have your eye on?
            Tell us what is going on and the VOID team will get back to you.
        </p>
        <p class="support-note">We usually reply within one business day.</p>
    </div>

    <section class="support-form-panel" aria-labelledby="support-form-title">
        <div class="support-panel-head">
            <p class="support-label">CONTACT VOID</p>
            <h2 id="support-form-title">We are here to help.</h2>
        </div>

        @if ($errors->any())
            <div class="support-errors" role="alert">
                <p>Please check the marked fields.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('shop.contact.send') }}" class="support-form">
            @csrf

            <div class="shop-field">
                <label for="supportEmail">Email</label>
                <input id="supportEmail" name="email" type="email" value="{{ $email }}"
                       autocomplete="email" placeholder="you@example.com" required>
                @error('email')
                    <p class="shop-field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="shop-field">
                <label for="supportMessage">Message</label>
                <textarea id="supportMessage" name="message" rows="7"
                          placeholder="Tell us how we can help." required>{{ old('message') }}</textarea>
                @error('message')
                    <p class="shop-field-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="support-submit">Send message</button>
        </form>
    </section>
</main>
@endsection
