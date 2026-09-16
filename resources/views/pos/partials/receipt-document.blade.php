@php
    use App\Enums\OrderStatus;
    use App\Support\Code39;
    use Illuminate\Support\Str;

    /**
     * The receipt. One template for the screen, the counter printer and the
     * register's own modal, so the copy the cashier reads, the copy that comes
     * off the printer and the copy the customer is emailed a link to are the
     * same document.
     *
     * Set as a narrow monospaced column, the way a till roll prints: a heading
     * block that identifies the business, a document block that identifies the
     * transaction, the goods, the money, and a closing block. No tax line —
     * the shop does not itemise VAT to the customer.
     *
     * @var \App\Models\Order   $order
     * @var \App\Models\Receipt $receipt
     */
    $symbol = $store['currency_symbol'];
    $payment = $order->payment;
    $isCash = $payment && $payment->isCash();
    $issuedAt = $order->placed_at ?? $order->created_at;

    $stampClass = match ($order->status) {
        OrderStatus::Completed, OrderStatus::Approved => '',
        OrderStatus::Rejected, OrderStatus::Cancelled => ' is-void',
        default => ' is-pending',
    };

    $stampText = match ($order->status) {
        OrderStatus::Completed => 'PAID',
        OrderStatus::Approved => 'APPROVED',
        OrderStatus::Pending => 'UNPAID',
        OrderStatus::Rejected => 'REJECTED',
        OrderStatus::Cancelled => 'CANCELLED',
    };

    // The reference in bars as well as in type, so it can be scanned back at
    // the counter for a return instead of being keyed in by hand.
    $barcode = Code39::symbol($order->order_ref);
@endphp

<div class="rcpt">

    {{-- ---------------------------------------------------------------
         Who issued it
         --------------------------------------------------------------- --}}
    <header class="rcpt-head">
        <p class="rcpt-brand">{{ $store['brand'] }}</p>
        <p class="rcpt-store">{{ $store['name'] }}</p>
        <p class="rcpt-line">{{ $store['address'] }}</p>
        <p class="rcpt-line">TIN {{ $store['tin'] }}</p>
    </header>

    <p class="rcpt-doctype">
        {{ $order->isPos() ? 'Sales Receipt' : 'Order Receipt' }}
    </p>

    {{-- ---------------------------------------------------------------
         What it is
         --------------------------------------------------------------- --}}
    <dl class="rcpt-meta">
        <div><dt>Receipt No.</dt><dd>{{ $receipt->receipt_number }}</dd></div>
        <div><dt>Order Ref</dt><dd class="is-key">{{ $order->order_ref }}</dd></div>
        <div><dt>Date</dt><dd>{{ $issuedAt->format('d M Y') }}</dd></div>
        <div><dt>Time</dt><dd>{{ $issuedAt->format('g:i A') }}</dd></div>

        @if ($order->isPos())
            <div><dt>Cashier</dt><dd>{{ $order->cashier?->displayName() ?? 'Counter' }}</dd></div>
            <div><dt>Terminal</dt><dd>{{ $terminal }}</dd></div>
        @else
            <div><dt>Channel</dt><dd>Online store</dd></div>
        @endif

        <div><dt>Customer</dt><dd>{{ $order->buyerName() }}</dd></div>
    </dl>

    @if ($order->shipping_address)
        <div class="rcpt-block">
            <p class="rcpt-block-label">Deliver to</p>
            <p class="rcpt-address">{{ $order->shipping_address }}</p>
        </div>
    @endif

    {{-- ---------------------------------------------------------------
         What was bought
         --------------------------------------------------------------- --}}
    <table class="rcpt-items">
        <thead>
            <tr>
                <th scope="col" class="q">Qty</th>
                <th scope="col">Item</th>
                <th scope="col" class="a">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->items as $item)
                <tr>
                    <td class="q">{{ $item->quantity }}</td>
                    <td>
                        {{ $item->product_name }}
                        <span class="v">Size {{ $item->sizeAbbreviation() }} &middot; {{ $symbol }}{{ number_format($item->unit_price, 2) }} each</span>
                    </td>
                    <td class="a">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="rcpt-none">No items recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ---------------------------------------------------------------
         What it came to
         --------------------------------------------------------------- --}}
    <dl class="rcpt-sum">
        <div>
            <dt>Subtotal ({{ $order->totalQuantity() }} {{ Str::plural('item', $order->totalQuantity()) }})</dt>
            <dd>{{ $symbol }}{{ number_format($order->subtotal, 2) }}</dd>
        </div>

        @if ($order->discount_amount > 0)
            <div><dt>Discount</dt><dd>&minus;{{ $symbol }}{{ number_format($order->discount_amount, 2) }}</dd></div>
        @endif

        @if ($order->shipping_fee > 0)
            {{-- "Shipping", because that is the word the basket, the checkout
                 and the customer's own order page all use for it. --}}
            <div><dt>Shipping</dt><dd>{{ $symbol }}{{ number_format($order->shipping_fee, 2) }}</dd></div>
        @endif
    </dl>

    <p class="rcpt-total">
        <span>Total</span>
        <b>{{ $symbol }}{{ number_format($order->total_amount, 2) }}</b>
    </p>

    {{-- ---------------------------------------------------------------
         How it was paid
         --------------------------------------------------------------- --}}
    <dl class="rcpt-sum is-payment">
        <div>
            <dt>{{ $order->payment_method }}</dt>
            <dd>{{ $symbol }}{{ number_format($payment->amount_tendered ?? $order->total_amount, 2) }}</dd>
        </div>

        @if ($isCash)
            <div><dt>Change</dt><dd>{{ $symbol }}{{ number_format($payment->change_due, 2) }}</dd></div>
        @elseif ($payment?->reference)
            <div><dt>Payment ref.</dt><dd>{{ $payment->reference }}</dd></div>
        @endif
    </dl>

    {{-- ---------------------------------------------------------------
         Closing
         --------------------------------------------------------------- --}}
    <div class="rcpt-foot">
        <p class="rcpt-stamp{{ $stampClass }}">{{ $stampText }}</p>

        @if ($receipt->isReprint())
            <p class="rcpt-reprint">Reprint &middot; copy {{ $receipt->print_count }}</p>
        @endif

        <div class="rcpt-barcode" aria-hidden="true">
            @foreach ($barcode['elements'] as $element)
                <i class="{{ $element['bar'] ? 'b' : 's' }}"
                   style="width:{{ round($element['units'] / $barcode['units'] * 100, 4) }}%"></i>
            @endforeach
        </div>
        <p class="rcpt-barcode-text">{{ $order->order_ref }}</p>

        <p class="rcpt-thanks">Thank you for shopping {{ $store['brand'] }}.</p>
        <p class="rcpt-note">Exchange within {{ config('void.receipt.return_window_days') }} days with this receipt.</p>
        <p class="rcpt-note">{{ config('void.receipt.footer_note') }}</p>
    </div>
</div>
