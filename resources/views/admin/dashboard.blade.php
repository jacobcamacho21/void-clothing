@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
@php
    $symbol = $store['currency_symbol'];
@endphp

<div class="page-intro">
    <h2>
        Welcome, {{ auth()->user()->displayName() }}
    </h2>
    <p>
        {{ $isAdmin
            ? 'Overview of revenue, orders, product movement and account activity.'
            : 'Overview of order flow, stock movement and daily operations.' }}
    </p>
</div>

@if ($cancellationRequests->isNotEmpty())
    <div class="panel cancellation-alert">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Cancellation Requests</h2>
                <p class="panel-subtitle">Review these before dispatch. Approving returns reserved stock.</p>
            </div>
            <span class="status-badge status-pending">{{ $cancellationRequests->count() }} pending</span>
        </div>
        <div class="panel-body flush">
            <table class="data-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Reason</th><th>Requested</th><th></th></tr></thead>
                <tbody>
                    @foreach ($cancellationRequests as $request)
                        <tr>
                            <td><a class="row-link" href="{{ route('admin.orders.show', $request) }}">{{ $request->order_ref }}</a></td>
                            <td>{{ $request->buyerName() }}</td>
                            <td>{{ $request->cancellation_reason }}</td>
                            <td>{{ $request->cancellation_requested_at?->format('M d, g:i A') }}</td>
                            <td><a class="row-link" href="{{ route('admin.orders.show', $request) }}">Review</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{--
    Stock figures are counts of variants, so each one is shown against the
    size of the catalog it is counted from and carries the rule that
    produced it. A bare "6" on a card is not a number anyone can act on.
--}}
@php
    $threshold = config('void.low_stock_threshold');
    $variants = $metrics['total_variants'];
    $variantsUnit = 'of '.number_format($variants).' '.\Illuminate\Support\Str::plural('variant', $variants);
@endphp

<div class="summary-grid {{ $isAdmin ? '' : 'cols-2' }}">
    @if ($isAdmin)
        <div class="summary-card is-accent">
            <p class="summary-label">Total Revenue</p>
            <p class="summary-value">{{ $symbol }}{{ number_format($metrics['revenue'], 2) }}</p>
            <p class="summary-note">Approved and completed orders</p>
        </div>
        <div class="summary-card">
            <p class="summary-label">Total Orders</p>
            <p class="summary-value">{{ number_format($metrics['orders']) }}</p>
            <p class="summary-note">Across the counter and the online store</p>
        </div>
        <div class="summary-card">
            <p class="summary-label">In Stock</p>
            <p class="summary-value metric-line">
                {{ number_format($metrics['active_products']) }}
                <span class="metric-unit">{{ $variantsUnit }}</span>
            </p>
            <p class="summary-note">Size variants with stock on the shelf</p>
        </div>
        <div class="summary-card">
            <p class="summary-label">Stock Health</p>
            <p class="summary-value metric-line">
                {{ number_format($metrics['stock_health']) }}<span class="metric-unit">%</span>
            </p>
            <p class="summary-note">Share of the catalog that can be sold today</p>
        </div>
    @else
        <div class="summary-card is-accent">
            <p class="summary-label">Low Stock</p>
            <p class="summary-value metric-line">
                {{ number_format($metrics['low_stock']) }}
                <span class="metric-unit">{{ $variantsUnit }}</span>
            </p>
            <p class="summary-note">
                At or below {{ $threshold }} {{ \Illuminate\Support\Str::plural('unit', $threshold) }} &mdash;
                <a href="{{ route('admin.inventory') }}" class="row-link">open the stock room</a>
            </p>
        </div>
        <div class="summary-card">
            <p class="summary-label">Orders Today</p>
            <p class="summary-value">{{ number_format($metrics['orders_today']) }}</p>
            <p class="summary-note">Placed today across both channels</p>
        </div>
    @endif
</div>

<div class="summary-grid cols-3">
    <div class="summary-card">
        <p class="summary-label">Awaiting Review</p>
        <p class="summary-value">{{ number_format($metrics['pending_review']) }}</p>
        <p class="summary-note">
            Online orders needing a payment check
            @if ($metrics['pending_review'] > 0)
                &mdash; <a href="{{ route('admin.orders', ['status' => 'pending']) }}" class="row-link">review now</a>
            @endif
        </p>
    </div>
    <div class="summary-card">
        <p class="summary-label">Counter Sales Today</p>
        <p class="summary-value">{{ $symbol }}{{ number_format($metrics['counter_sales_today'], 2) }}</p>
        <p class="summary-note">Taken at the register since midnight</p>
    </div>

    {{-- Staff already lead with Low Stock above, so this slot shows them the
         sharper number instead of repeating it. --}}
    @if ($isAdmin)
        <div class="summary-card">
            <p class="summary-label">Low Stock</p>
            <p class="summary-value metric-line">
                {{ number_format($metrics['low_stock']) }}
                <span class="metric-unit">{{ $variantsUnit }}</span>
            </p>
            <p class="summary-note">
                At or below {{ $threshold }} {{ \Illuminate\Support\Str::plural('unit', $threshold) }} &mdash;
                <a href="{{ route('admin.inventory') }}" class="row-link">open the stock room</a>
            </p>
        </div>
    @else
        <div class="summary-card">
            <p class="summary-label">Out of Stock</p>
            <p class="summary-value metric-line">
                {{ number_format($metrics['out_of_stock']) }}
                <span class="metric-unit">{{ $variantsUnit }}</span>
            </p>
            <p class="summary-note">
                Cannot be sold until restocked &mdash;
                <a href="{{ route('admin.inventory') }}" class="row-link">open the stock room</a>
            </p>
        </div>
    @endif
</div>

@if ($isAdmin)
    @php
        $width = 940;
        $height = 240;
        $pad = 20;
        $values = array_column($trend, 'revenue');
        $max = max($values ?: [0]);
        $max = $max > 0 ? $max : 1;
        $count = count($trend);

        $points = [];
        foreach ($trend as $i => $point) {
            $x = $count > 1
                ? $pad + ($i * (($width - ($pad * 2)) / ($count - 1)))
                : $pad;
            $y = ($height - $pad) - (($point['revenue'] / $max) * ($height - ($pad * 2)));
            $points[] = round($x, 2).','.round($y, 2);
        }

        $line = implode(' ', $points);
        $area = $count > 0
            ? $line.' '.($width - $pad).','.($height - $pad).' '.$pad.','.($height - $pad)
            : '';

        // Three ticks: first, middle and last day in the window.
        $tickIndexes = array_values(array_unique(array_filter(
            [0, $count > 2 ? intdiv($count - 1, 2) : null, $count > 1 ? $count - 1 : null],
            fn ($v) => $v !== null
        )));
    @endphp

    <div class="dash-split">
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Sales Trend</h2>
                <p class="panel-subtitle">Daily revenue across the last {{ $selectedDays }} days</p>
            </div>
            <div class="range-controls">
                @foreach ($ranges as $range)
                    <a class="range-link {{ $selectedDays === $range ? 'active' : '' }}"
                       href="{{ route('admin.dashboard', ['range' => $range]) }}">{{ $range }} Days</a>
                @endforeach
            </div>
        </div>
        <div class="chart-wrap">
            <svg class="chart" viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none"
                 role="img" aria-label="Daily revenue over the last {{ $selectedDays }} days">
                <text x="6" y="24" class="chart-axis-title">Revenue</text>

                <line x1="20" y1="220" x2="920" y2="220" stroke="#d9d9d9" stroke-width="1"></line>
                <line x1="20" y1="140" x2="920" y2="140" stroke="#efefef" stroke-width="1"></line>
                <line x1="20" y1="60" x2="920" y2="60" stroke="#efefef" stroke-width="1"></line>

                <text x="22" y="58" class="chart-axis-label">{{ $symbol }}{{ number_format($max, 0) }}</text>
                <text x="22" y="138" class="chart-axis-label">{{ $symbol }}{{ number_format($max / 2, 0) }}</text>
                <text x="22" y="218" class="chart-axis-label">{{ $symbol }}0</text>

                @if ($area !== '')
                    <polygon points="{{ $area }}" fill="rgba(0,0,0,0.08)"></polygon>
                @endif
                <polyline points="{{ $line }}" fill="none" stroke="#4b4b4b" stroke-width="2.4"
                          stroke-linecap="round" stroke-linejoin="round"></polyline>

                @foreach ($tickIndexes as $idx)
                    @php
                        $x = $count > 1
                            ? $pad + ($idx * (($width - ($pad * 2)) / ($count - 1)))
                            : $pad;
                    @endphp
                    <line x1="{{ round($x, 2) }}" y1="220" x2="{{ round($x, 2) }}" y2="224"
                          stroke="#bfbfbf" stroke-width="1"></line>
                    <text x="{{ round($x, 2) }}" y="236" text-anchor="middle"
                          class="chart-axis-label">{{ $trend[$idx]['label'] }}</text>
                @endforeach
            </svg>
        </div>
    </div>

    {{-- Right column of the chart row: best sellers as a ranked list. --}}
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Most Popular Items</h2>
                <p class="panel-subtitle">Best sellers by revenue</p>
            </div>
        </div>
        <div class="panel-body flush">
            @include('admin.partials.top-products')
        </div>
    </div>
    </div>{{-- /.dash-split --}}
@endif

@unless ($isAdmin)
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Most Popular Items</h2>
                <p class="panel-subtitle">Best sellers across approved and completed orders</p>
            </div>
        </div>
        <div class="panel-body flush">
            @include('admin.partials.top-products')
        </div>
    </div>
@endunless

@if ($isAdmin)
    <div class="summary-grid cols-2">
        <div class="summary-card">
            <p class="summary-label">Staff Accounts</p>
            <p class="summary-value">{{ number_format($totalUsers) }}</p>
            <p class="summary-note">
                <a href="{{ route('admin.users') }}" class="row-link">Manage access</a>
            </p>
        </div>
        <div class="summary-card">
            <p class="summary-label">Customers</p>
            <p class="summary-value">{{ number_format($totalCustomers) }}</p>
            <p class="summary-note">
                <a href="{{ route('admin.customers') }}" class="row-link">View customers</a>
            </p>
        </div>
    </div>
@endif
@endsection
