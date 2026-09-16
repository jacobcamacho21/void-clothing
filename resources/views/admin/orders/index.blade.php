@extends('layouts.admin')

@section('title', 'Orders')
@section('heading', 'Order Desk')

@section('content')
@php
    $symbol = $store['currency_symbol'];
@endphp

<div class="summary-grid">
    <div class="summary-card">
        <p class="summary-label">All orders</p>
        <p class="summary-value">{{ number_format($summary['total']) }}</p>
        <p class="summary-note">Counter and online combined</p>
    </div>
    <div class="summary-card">
        <p class="summary-label">Today</p>
        <p class="summary-value">{{ number_format($summary['today']) }}</p>
        <p class="summary-note">Placed since midnight</p>
    </div>
    <div class="summary-card">
        <p class="summary-label">Awaiting review</p>
        <p class="summary-value">{{ number_format($summary['pending']) }}</p>
        <p class="summary-note">Online orders with payment proof to check</p>
    </div>
    <div class="summary-card">
        <p class="summary-label">Counter sales today</p>
        <p class="summary-value">{{ number_format($summary['counter_today']) }}</p>
        <p class="summary-note">Rung up at the register</p>
    </div>
</div>

<div class="content-card">
    <div class="toolbar">
        <h2>Order Queue</h2>
        <form method="GET" action="{{ route('admin.orders') }}" class="toolbar-controls" data-search-form>
            <div class="search-field">
                <img src="{{ asset('images/admin/search.png') }}" alt="">
                <label for="orderSearch" class="visually-hidden">Search orders</label>
                <input type="search" id="orderSearch" name="q" value="{{ $search }}"
                       placeholder="Search reference or customer">
            </div>

            <label for="statusFilter" class="visually-hidden">Filter by status</label>
            <select id="statusFilter" name="status" class="filter-select" onchange="this.form.requestSubmit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $case)
                    <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>

            <label for="channelFilter" class="visually-hidden">Filter by channel</label>
            <select id="channelFilter" name="channel" class="filter-select" onchange="this.form.requestSubmit()">
                <option value="">All channels</option>
                <option value="online" @selected($channel?->value === 'online')>Online</option>
                <option value="pos" @selected($channel?->value === 'pos')>Counter</option>
            </select>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th scope="col">Order Ref</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Channel</th>
                    <th scope="col">Products</th>
                    <th scope="col">Qty</th>
                    <th scope="col">Total</th>
                    <th scope="col">Status</th>
                    <th scope="col">Created</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php
                        $names = $order->items->pluck('product_name')->unique()->values();
                        $summaryText = $names->take(2)->implode(', ');
                        if ($names->count() > 2) {
                            $summaryText .= ' +'.($names->count() - 2).' more';
                        }
                    @endphp
                    <tr>
                        <td>
                            <a class="row-link" href="{{ route('admin.orders.show', $order) }}">
                                {{ $order->order_ref }}
                            </a>
                        </td>
                        <td>{{ $order->buyerName() }}</td>
                        <td>
                            <span class="channel-tag {{ $order->isPos() ? 'is-pos' : '' }}">
                                {{ $order->channel->label() }}
                            </span>
                        </td>
                        <td class="muted-cell">{{ $summaryText ?: '—' }}</td>
                        <td class="numeric">{{ $order->items->sum('quantity') }}</td>
                        <td class="numeric">{{ $symbol }}{{ number_format($order->total_amount, 2) }}</td>
                        <td>
                            <span class="status-badge {{ $order->status->badgeClass() }}">
                                {{ $order->status->label() }}
                            </span>
                        </td>
                        <td class="muted-cell">{{ $order->created_at?->format('M d, Y g:i A') }}</td>
                        <td>
                            <div class="action-cell">
                                <a class="row-link" href="{{ route('admin.orders.show', $order) }}">View</a>

                                @can('delete', $order)
                                    <form method="POST" class="inline-form"
                                          action="{{ route('admin.orders.destroy', $order) }}"
                                          data-confirm="Delete {{ $order->order_ref }}? Stock it still holds will be returned.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn" title="Delete order">
                                            <img src="{{ asset('images/admin/delete.png') }}" alt="Delete">
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-row">No orders match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">{{ $orders->links() }}</div>
</div>
@endsection
