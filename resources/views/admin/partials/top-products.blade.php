{{--
    "Most Popular Items" — the best sellers as a ranked list.

    Used both in the dashboard's chart row (admins) and as a standalone panel
    (staff), so the same visual appears wherever top products are shown.

    Expects: $topProducts, $isAdmin, $symbol.
--}}
@if ($topProducts->isNotEmpty())
    <ol class="rank-list">
        @foreach ($topProducts as $item)
            <li class="rank-item">
                <span class="rank-badge">{{ $loop->iteration }}</span>
                <div class="rank-main">
                    <p class="rank-name">{{ $item->product_name }}</p>
                    @php
                        $units = (int) $item->units_sold;
                        $lines = (int) $item->line_count;
                    @endphp
                    <p class="rank-sub">
                        {{ number_format($units) }} sold &middot;
                        {{ number_format($lines) }} {{ \Illuminate\Support\Str::plural('order', $lines) }}
                    </p>
                </div>
                <span class="rank-value">
                    @if ($isAdmin)
                        {{ $symbol }}{{ number_format((float) $item->revenue, 2) }}
                    @else
                        {{ (int) $item->units_sold }}
                    @endif
                </span>
            </li>
        @endforeach
    </ol>
@else
    <p class="empty-row">No sales recorded yet. Ring up an order to populate these figures.</p>
@endif
