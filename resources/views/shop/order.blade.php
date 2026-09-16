@extends('layouts.shop')

@section('title', 'Order '.$order->order_ref.' — VOID')
@section('body-class', 'shop-app')

@section('content')
@php
    use App\Enums\OrderStatus;

    $symbol = $store['currency_symbol'];

    $explanations = [
        'pending' => 'We have received your order and are checking the payment proof you uploaded. This usually happens within a business day.',
        'approved' => 'Your payment has been confirmed and your items are reserved. We are preparing your parcel now.',
        'processing' => 'Your payment has been confirmed. We are preparing your parcel now.',
        'dispatched' => 'Your parcel has been handed to the courier and is on its way.',
        'cancellation_requested' => 'Your cancellation request is being reviewed by our team.',
        'completed' => 'This order has been fulfilled. Thank you for shopping with VOID.',
        'rejected' => 'We could not confirm the payment for this order, so it was not processed. Please contact us if you believe this is a mistake.',
        'cancelled' => 'This order was cancelled. Any reserved stock has been released.',
    ];

    // The happy path, marked off against where this order actually got to.
    $steps = [
        ['status' => OrderStatus::Pending, 'label' => 'Order placed'],
        ['status' => OrderStatus::Approved, 'label' => 'Payment approved'],
        ['status' => OrderStatus::Processing, 'label' => 'Processing'],
        ['status' => OrderStatus::Dispatched, 'label' => 'Dispatched'],
        ['status' => OrderStatus::Completed, 'label' => 'Fulfilled'],
    ];

    $reached = $order->statusHistories->pluck('to_status')->map(fn ($s) => $s->value)->all();
    $isTerminal = $order->status->isTerminal();
    $canRequestCancellation = in_array($order->status, [OrderStatus::Pending, OrderStatus::Approved, OrderStatus::Processing], true);
@endphp

<div class="shop-page shop-page--narrow">
    <a href="{{ route('shop.account') }}" class="back-link">&larr; Back to your account</a>

    <div class="shop-card">
        <div class="shop-section-title">
            <span>
                Order {{ $order->order_ref }}
                <span class="shop-meta">
                    Placed {{ ($order->placed_at ?? $order->created_at)?->format('M d, Y \a\t g:i A') }}
                    &middot; {{ $order->payment_method }}
                </span>
            </span>
            <span class="order-status {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
        </div>

        <p class="shop-note">{{ $explanations[$order->status->value] }}</p>

        @if ($canRequestCancellation)
            <button type="button" class="void-btn void-btn--ghost" data-open="cancelOrderModal">Request Cancellation</button>
        @elseif ($order->status === OrderStatus::CancellationRequested)
            <p class="shop-note">Cancellation requested on {{ $order->cancellation_requested_at?->format('M d, Y g:i A') }}.</p>
        @endif

        @unless ($isTerminal)
            <div class="track-steps">
                @foreach ($steps as $step)
                    @php
                        $done = in_array($step['status']->value, $reached, true);
                        $when = $order->statusHistories
                            ->firstWhere('to_status', $step['status'])?->created_at;
                    @endphp
                    <div class="track-step {{ $done ? 'done' : '' }}">
                        <b>{{ $step['label'] }}</b>
                        <span class="step-when">{{ $done ? $when?->format('M d, g:i A') : 'Pending' }}</span>
                    </div>
                @endforeach
            </div>
        @endunless
    </div>

    <div class="shop-card">
        <h2 class="shop-section-title">Items</h2>

        <table class="shop-table">
            <thead>
                <tr>
                    <th scope="col">Product</th>
                    <th scope="col">Size</th>
                    <th scope="col" class="num">Qty</th>
                    <th scope="col" class="num">Price</th>
                    <th scope="col" class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->product_size }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ $symbol }}{{ number_format($item->unit_price, 2) }}</td>
                        <td class="num">{{ $symbol }}{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <ul class="shop-totals">
            <li><span>Subtotal</span><b>{{ $symbol }}{{ number_format($order->subtotal, 2) }}</b></li>

            @if ($order->discount_amount > 0)
                <li><span>Discount</span><b>&minus;{{ $symbol }}{{ number_format($order->discount_amount, 2) }}</b></li>
            @endif

            @if ($order->shipping_fee > 0)
                <li><span>Shipping</span><b>{{ $symbol }}{{ number_format($order->shipping_fee, 2) }}</b></li>
            @endif

            <li class="grand"><span>Total</span><b>{{ $symbol }}{{ number_format($order->total_amount, 2) }}</b></li>
        </ul>
    </div>

    @if ($order->shipping_address)
        <div class="shop-card">
            <h2 class="shop-section-title">Delivery</h2>
            <p class="shop-note">
                {{ $order->customer_name }}<br>
                {{ $order->shipping_address }}
            </p>
        </div>
    @endif
</div>

@if ($canRequestCancellation)
    <div class="shop-modal" id="cancelOrderModal">
        <div class="shop-modal-card">
            <form method="POST" action="{{ route('shop.account.order.cancellation', $order) }}">
                @csrf
                <h3>Request Cancellation</h3>
                <p class="shop-note">Cancellation is only available before dispatch. Tell us why you need to cancel.</p>
                <div class="shop-field">
                    <label class="shop-label" for="cancellation_reason">Reason</label>
                    <select name="cancellation_reason" id="cancellation_reason" class="shop-input" required>
                        <option value="">Select a reason</option>
                        <option>Incorrect item ordered</option>
                        <option>Duplicate order</option>
                        <option>Address correction</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="shop-field">
                    <label class="shop-label" for="cancellation_note">Details</label>
                    <textarea name="cancellation_details" id="cancellation_note" class="shop-input" maxlength="1000" placeholder="Optional additional details"></textarea>
                </div>
                <div class="shop-actions">
                    <button type="submit" class="void-btn void-btn--block">Send Request</button>
                    <button type="button" class="void-btn void-btn--block void-btn--ghost" data-close>Keep Order</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
