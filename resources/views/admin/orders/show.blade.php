@extends('layouts.admin')

@section('title', 'Order '.$order->order_ref)
@section('heading', 'Order '.$order->order_ref)

@section('header-actions')
    <a class="btn-secondary" href="{{ route('admin.orders') }}">Back to queue</a>
    <a class="btn-primary" href="{{ route('pos.receipt', $order) }}" target="_blank" rel="noopener">View receipt</a>
@endsection

@section('content')
@php
    $symbol = $store['currency_symbol'];
    $payment = $order->payment;
@endphp

<div class="detail-grid">
    <div>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Items</h2>
                    <p class="panel-subtitle">{{ $order->items->sum('quantity') }} unit(s) across {{ $order->items->count() }} line(s)</p>
                </div>
                <span class="channel-tag {{ $order->isPos() ? 'is-pos' : '' }}">{{ $order->channel->label() }}</span>
            </div>
            <div class="panel-body flush">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Size</th>
                                <th scope="col">Qty</th>
                                <th scope="col">Unit Price</th>
                                <th scope="col">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($order->items as $item)
                                <tr>
                                    <td>{{ $item->product_name }}</td>
                                    <td>{{ $item->product_size ?? '—' }}</td>
                                    <td class="numeric">{{ $item->quantity }}</td>
                                    <td class="numeric">{{ $symbol }}{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="numeric">{{ $symbol }}{{ number_format($item->line_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-row">No items recorded on this order.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Payment</h2>
                <span class="status-badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
            </div>
            <div class="panel-body">
                <ul class="detail-list">
                    <li><span>Subtotal</span><b>{{ $symbol }}{{ number_format($order->subtotal, 2) }}</b></li>

                    @if ($order->discount_amount > 0)
                        <li><span>Discount</span><b>−{{ $symbol }}{{ number_format($order->discount_amount, 2) }}</b></li>
                    @endif

                    @if ($order->shipping_fee > 0)
                        <li><span>Shipping</span><b>{{ $symbol }}{{ number_format($order->shipping_fee, 2) }}</b></li>
                    @endif

                    {{-- No VAT line. The shop does not itemise tax to anyone,
                         on the receipt or here, so the order desk shows the
                         customer exactly the figures the customer was shown. --}}
                    <li class="total"><span>Total</span><b>{{ $symbol }}{{ number_format($order->total_amount, 2) }}</b></li>

                    <li><span>Method</span><b>{{ $order->payment_method }}</b></li>

                    @if ($payment)
                        <li><span>Tendered</span><b>{{ $symbol }}{{ number_format($payment->amount_tendered, 2) }}</b></li>

                        @if ($payment->isCash())
                            <li><span>Change given</span><b>{{ $symbol }}{{ number_format($payment->change_due, 2) }}</b></li>
                        @endif

                        @if ($payment->reference)
                            <li><span>Reference</span><b>{{ $payment->reference }}</b></li>
                        @endif

                        <li><span>Paid at</span><b>{{ $payment->paid_at?->format('M d, Y g:i A') ?? '—' }}</b></li>
                    @endif

                    @if ($order->receipt)
                        <li>
                            <span>Receipt</span>
                            <b>
                                {{ $order->receipt->receipt_number }}
                                @if ($order->receipt->print_count > 0)
                                    <span class="muted-cell">({{ $order->receipt->print_count }} print(s))</span>
                                @endif
                            </b>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        @if ($order->proof_of_payment)
            <div class="panel">
                <div class="panel-header"><h2 class="panel-title">Proof of Payment</h2></div>
                <div class="panel-body">
                    <a href="{{ route('admin.orders.proof', $order) }}" target="_blank" rel="noopener">
                        <img class="proof-image" src="{{ route('admin.orders.proof', $order) }}"
                             alt="Proof of payment uploaded by the customer">
                    </a>
                    <p class="form-hint">Open in a new tab to view it full size.</p>
                </div>
            </div>
        @endif
    </div>

    <div>
        <div class="panel">
            <div class="panel-header"><h2 class="panel-title">Customer</h2></div>
            <div class="panel-body">
                <ul class="detail-list">
                    <li><span>Name</span><b>{{ $order->buyerName() }}</b></li>

                    @if ($order->customer)
                        <li><span>Account</span><b>{{ $order->customer->username }}</b></li>
                        <li><span>Email</span><b>{{ $order->customer->email }}</b></li>
                    @endif

                    @if ($order->cashier)
                        <li><span>Cashier</span><b>{{ $order->cashier->displayName() }}</b></li>
                    @endif

                    <li><span>Placed</span><b>{{ ($order->placed_at ?? $order->created_at)?->format('M d, Y g:i A') }}</b></li>

                    @if ($order->completed_at)
                        <li><span>Completed</span><b>{{ $order->completed_at->format('M d, Y g:i A') }}</b></li>
                    @endif
                </ul>

                @if ($order->shipping_address)
                    <p class="field-label">Ship to</p>
                    <p class="field-value">{{ $order->shipping_address }}</p>
                @endif

                @if ($order->status === \App\Enums\OrderStatus::CancellationRequested)
                    <div class="panel-body">
                        <p class="field-label">Customer cancellation reason</p>
                        <p class="field-value">{{ $order->cancellation_reason }}</p>
                        <form method="POST" action="{{ route('admin.orders.cancellation', $order) }}" class="status-actions">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit" class="btn-danger">Approve Cancellation</button>
                        </form>
                        <form method="POST" action="{{ route('admin.orders.cancellation', $order) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="decision" value="reject">
                            <input type="text" name="note" placeholder="Optional note to customer" class="form-input">
                            <button type="submit" class="btn-secondary">Reject Cancellation</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        @can('review', $order)
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Move this order</h2>
                        <p class="panel-subtitle">Stock follows the status automatically</p>
                    </div>
                </div>
                <div class="panel-body">
                    @if (count($allowedTransitions) === 0)
                        <p class="muted-cell">This order is {{ strtolower($order->status->label()) }} and cannot move further.</p>
                    @else
                        <div class="status-actions">
                            @foreach ($allowedTransitions as $target)
                                <form method="POST" action="{{ route('admin.orders.status', $order) }}"
                                      data-confirm="{{ $confirmations[$target->value] ?? 'Mark this order '.$target->label().'?' }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $target->value }}">
                                    <button type="submit"
                                            class="{{ in_array($target->value, ['rejected', 'cancelled'], true) ? 'btn-danger' : 'btn-primary' }}">
                                        {{ $labels[$target->value] ?? $target->label() }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endcan

        <div class="panel">
            <div class="panel-header"><h2 class="panel-title">History</h2></div>
            <div class="panel-body">
                <ul class="timeline">
                    @forelse ($order->statusHistories as $entry)
                        <li>
                            <b>{{ $entry->to_status->label() }}</b>
                            @if ($entry->from_status)
                                <span class="muted-cell">from {{ $entry->from_status->label() }}</span>
                            @endif
                            @if ($entry->note)
                                <div class="muted-cell">{{ $entry->note }}</div>
                            @endif
                            <span class="when">
                                {{ $entry->created_at?->format('M d, Y g:i A') }}
                                @if ($entry->author) &middot; {{ $entry->author->displayName() }} @endif
                            </span>
                        </li>
                    @empty
                        <li><span class="muted-cell">No status changes recorded.</span></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
