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
            <p class="shop-page-sub">Where your parcel goes, and how you pay.</p>
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

        <form method="POST" action="{{ route('shop.checkout.store') }}" id="checkoutForm">
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

            <p class="shop-note" id="quoteDeliveryStatus" role="status">Enter your delivery details to calculate shipping.</p>

            <div class="shop-field">
                <label class="shop-label" for="payment">Payment Method</label>
                <select id="payment" name="payment" class="shop-input" required>
                    <option value="paymongo" selected>PayMongo QR Ph</option>
                </select>
            </div>

            <div class="pay-block">
                <p class="shop-note">
                    You will continue to PayMongo to scan a QR Ph code. Your order is confirmed after PayMongo reports the payment to us.
                </p>
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
            <li><span>Shipping</span><b id="shippingFeeValue">Awaiting address</b></li>
            <li class="grand"><span>Total</span><b id="orderTotalValue">{{ $symbol }}{{ number_format($totals['total_amount'], 2) }}</b></li>
        </ul>
    </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const checkoutForm = document.getElementById('checkoutForm');
    const quoteStatus = document.getElementById('quoteDeliveryStatus');
    const placeOrderButton = document.getElementById('placeOrderBtn');
    const termsCheckbox = document.getElementById('checkoutTerms');
    const addressFields = ['recipient_name', 'phone', 'street', 'city', 'province', 'postal_code', 'country']
        .map((name) => document.getElementById(name));
    let deliveryQuoteReady = false;
    let quoteTimer;
    let quoteRequest;

    function updatePlaceOrderState() {
        placeOrderButton.disabled = !termsCheckbox.checked || !deliveryQuoteReady;
    }

    async function requestDeliveryQuote() {
        if (addressFields.some((field) => !field.value.trim())) {
            quoteStatus.textContent = 'Enter your delivery details to calculate shipping.';
            deliveryQuoteReady = false;
            updatePlaceOrderState();
            return;
        }

        if (quoteRequest) {
            quoteRequest.abort();
        }

        quoteRequest = new AbortController();
        quoteStatus.textContent = 'Getting a live delivery quote…';
        deliveryQuoteReady = false;
        updatePlaceOrderState();

        try {
            const response = await fetch('{{ route('shop.checkout.shipping-quote') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                },
                body: new FormData(checkoutForm),
                signal: quoteRequest.signal,
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Delivery pricing is unavailable right now.');
            }

            document.getElementById('shippingFeeValue').textContent = '{{ $symbol }}' + Number(data.fee).toFixed(2);
            document.getElementById('orderTotalValue').textContent = '{{ $symbol }}' + Number(data.total).toFixed(2);
            quoteStatus.textContent = 'Delivery quote ready.';
            deliveryQuoteReady = true;
        } catch (error) {
            if (error.name !== 'AbortError') {
                quoteStatus.textContent = error.message;
            }
        } finally {
            quoteRequest = null;
            updatePlaceOrderState();
        }
    }

    addressFields.forEach((field) => field.addEventListener('input', function () {
        clearTimeout(quoteTimer);
        deliveryQuoteReady = false;
        updatePlaceOrderState();
        quoteTimer = setTimeout(requestDeliveryQuote, 700);
    }));

    requestDeliveryQuote();

    // Prevent a second submission while the customer is being redirected.
    checkoutForm.addEventListener('submit', function () {
        placeOrderButton.disabled = true;
        placeOrderButton.textContent = 'Placing order…';
    });
    termsCheckbox.addEventListener('change', function () {
        updatePlaceOrderState();
    });
</script>
@endpush
