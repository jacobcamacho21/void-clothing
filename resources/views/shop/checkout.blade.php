@extends('layouts.shop')

@section('title', 'Checkout — VOID')
@section('body-class', 'shop-app')
@section('hide-cart', true)

@section('content')
@php $symbol = $store['currency_symbol']; @endphp

<div class="shop-page">
    {{-- The page title sits above the grid, the way it does on the account
         page, rather than inside the left card where it read as that card's
         heading instead of the page's. --}}
    <header class="shop-page-head">
        <div>
            <h1 class="shop-page-title">Checkout</h1>
            <p class="shop-page-sub">Where the parcel goes, and the proof that it was paid for.</p>
        </div>
    </header>

    @if ($errors->any())
        <div class="shop-alert is-error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="checkout-grid">
    <div class="shop-card">
        <h2 class="shop-section-title">Delivery Details</h2>

        <form method="POST" action="{{ route('shop.checkout.store') }}" enctype="multipart/form-data" id="checkoutForm">
            @csrf

            <div class="shop-field">
                <label class="shop-label" for="recipient_name">Full Name</label>
                <input type="text" id="recipient_name" name="recipient_name" class="shop-input"
                       value="{{ old('recipient_name', $address?->recipient_name ?? $customer->username) }}" required>
            </div>

            <div class="shop-field">
                <label class="shop-label" for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" class="shop-input"
                       value="{{ old('phone', $address?->phone) }}" required>
            </div>

            <div class="shop-field">
                <label class="shop-label" for="street">Street Address</label>
                <input type="text" id="street" name="street" class="shop-input"
                       value="{{ old('street', $address?->street) }}" required>
            </div>

            {{-- The short fields pair up, the same way they do in the
                 account page's address dialog. --}}
            <div class="shop-field-row">
                <div class="shop-field">
                    <label class="shop-label" for="city">City</label>
                    <input type="text" id="city" name="city" class="shop-input"
                           value="{{ old('city', $address?->city) }}" required>
                </div>

                <div class="shop-field">
                    <label class="shop-label" for="province">Province</label>
                    <input type="text" id="province" name="province" class="shop-input"
                           value="{{ old('province', $address?->province) }}" required>
                </div>
            </div>

            <div class="shop-field-row">
                <div class="shop-field">
                    <label class="shop-label" for="postal_code">Postal Code</label>
                    <input type="text" id="postal_code" name="postal_code" class="shop-input"
                           value="{{ old('postal_code', $address?->postal_code) }}" required>
                </div>

                <div class="shop-field">
                    <label class="shop-label" for="country">Country</label>
                    <input type="text" id="country" name="country" class="shop-input"
                           value="{{ old('country', $address?->country ?? 'Philippines') }}" required>
                </div>
            </div>

            <div class="shop-field">
                <label class="shop-label" for="payment">Payment Method</label>
                <select id="payment" name="payment" class="shop-input" required>
                    <option value="digital" selected>Digital Payment</option>
                </select>
            </div>

            <div class="pay-block">
                <span class="shop-label">Scan to Pay</span>
                <img src="{{ asset('images/site/paymentqr.jpg') }}" alt="Digital payment QR code" class="pay-qr">
                <p class="shop-note">
                    Scan the QR code above to complete your payment, then upload a screenshot as proof below.
                    Your order is reviewed by our team once the proof is received.
                </p>

                <div class="shop-field">
                    <label class="shop-label" for="proof_of_payment">Proof of Payment</label>
                    <input type="file" id="proof_of_payment" name="proof_of_payment" class="shop-input"
                           accept="image/png,image/jpeg,image/webp" required>
                </div>
            </div>

            <label class="shop-check">
                <input type="checkbox" name="agreed_to_terms" value="1" required id="checkoutTerms">
                <span>I have read and agree to the <a href="{{ route('shop.terms') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a> regarding delivery, cancellations, and returns.</span>
            </label>

            <div class="shop-actions">
                <button type="submit" class="void-btn void-btn--block" id="placeOrderBtn" disabled>Place Order</button>
            </div>
        </form>
    </div>

    <div class="shop-card">
        <h2 class="shop-section-title">Order Summary</h2>

        <ul class="basket-list">
            @foreach ($lines as $line)
                <li class="basket-item">
                    <img src="{{ $line['product_image'] }}" class="basket-img" alt="{{ $line['product_name'] }}">
                    <div class="basket-details">
                        <div class="basket-name">{{ $line['product_name'] }}</div>
                        <div class="basket-sub">Size {{ $line['product_size'] }} &middot; Qty {{ $line['quantity'] }}</div>
                    </div>
                    <div class="basket-price">{{ $symbol }}{{ number_format($line['line_total'], 2) }}</div>
                </li>
            @endforeach
        </ul>

        <ul class="shop-totals">
            <li><span>Subtotal</span><b>{{ $symbol }}{{ number_format($totals['subtotal'], 2) }}</b></li>
            <li><span>Shipping</span><b>{{ $symbol }}{{ number_format($totals['shipping_fee'], 2) }}</b></li>
            <li class="grand"><span>Total</span><b>{{ $symbol }}{{ number_format($totals['total_amount'], 2) }}</b></li>
        </ul>
    </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Uploading the proof can take a moment on a phone connection; disabling
    // the button keeps an impatient tap from placing the order twice.
    document.getElementById('checkoutForm').addEventListener('submit', function () {
        const button = document.getElementById('placeOrderBtn');
        button.disabled = true;
        button.textContent = 'Placing order…';
    });
    document.getElementById('checkoutTerms').addEventListener('change', function () {
        document.getElementById('placeOrderBtn').disabled = !this.checked;
    });
</script>
@endpush
