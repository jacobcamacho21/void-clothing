@extends('layouts.pos')

@section('title', 'Register')

@section('body')
<div class="shell">
    @include('partials.sidebar', ['posMode' => true])

    <div class="pos-main">
        <header class="pos-header">
            <div>
                <h1 class="page-title">Register</h1>
                <p class="crumb">Operations &rsaquo; Register &rsaquo; New Sale</p>
            </div>
            <div class="hb-right">
                <p class="shift">Sales this shift<b id="shiftAmount">{{ $store['currency_symbol'] }}{{ number_format($shift['amount'], 2) }}</b></p>
                <p class="shift">Transactions<b id="shiftCount">{{ $shift['count'] }}</b></p>
                <p class="shift">Time<b id="clock">--:--</b></p>
            </div>
        </header>

        <div class="workspace">
            {{-- Collection rail --}}
            <aside class="collections" aria-label="Collections">
                <div class="coll-group">
                    <h5 id="coll-heading">
                        Collections <span class="tally" id="collTally"></span>
                    </h5>
                    <div id="collections" role="group" aria-labelledby="coll-heading"></div>
                </div>

                {{-- Both filters show how many products they would leave on
                     screen, and nothing else. The threshold itself used to be
                     printed beside the heading as "Low ≤ 5" — a loose number
                     next to two real counts, which read as a third count. It
                     is the chip's tooltip now; the Stock Room states the rule
                     in words, which is where a number like that belongs. --}}
                <div class="coll-group">
                    <h5 id="filter-heading">Filters</h5>
                    <div role="group" aria-labelledby="filter-heading">
                        <button type="button" class="coll" data-filter="low-stock"
                                title="Sizes with {{ config('void.low_stock_threshold') }} or fewer left on the shelf">
                            <span class="label">Low stock</span>
                            <span class="count" id="lowStockCount"></span>
                        </button>
                        <button type="button" class="coll" data-filter="in-stock"
                                title="Sizes with stock left to sell">
                            <span class="label">In stock</span>
                            <span class="count" id="inStockCount"></span>
                        </button>
                    </div>
                </div>
            </aside>

            {{-- Product browser --}}
            <section class="browser" aria-label="Product catalog">
                <div class="bsearch">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#111" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
                    </svg>
                    <label for="productSearch" class="visually-hidden">Search products</label>
                    <input id="productSearch" type="search" autocomplete="off"
                           placeholder="Search or scan &mdash; click a size to add it straight to the order">
                </div>
                <div class="rows" id="productRows" aria-live="polite"></div>
            </section>

            {{-- Order panel --}}
            <section class="order-panel" aria-label="Current order">
                <div class="o-head">
                    <div class="t">
                        <h2>Order</h2>
                        <span class="pill" id="orderPill">Empty</span>
                    </div>
                    <p class="o-ref is-pending" id="orderRef">Reference issued at payment</p>
                    <label for="customerSelect" class="visually-hidden">Customer</label>
                    <select id="customerSelect">
                        <option value="">Walk-in Customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->username }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="o-lines" id="orderLines"></div>

                <div class="o-foot">
                    <p class="tr"><span>Items</span><b id="lineCount">0</b></p>
                    <p class="tr"><span>Subtotal</span><b id="subtotal">{{ $store['currency_symbol'] }}0.00</b></p>
                    <p class="tr is-discount" id="discountRow" hidden>
                        <span id="discountLabel">Discount</span><b id="discount">{{ $store['currency_symbol'] }}0.00</b>
                    </p>
                    <p class="tr grand"><span>Total</span><b id="total">{{ $store['currency_symbol'] }}0.00</b></p>

                    <div class="acts">
                        <button type="button" class="btn outl" id="holdBtn" disabled>Hold</button>
                        <button type="button" class="btn outl" id="voidBtn" disabled>Void</button>
                        <button type="button" class="btn solid wide" id="chargeBtn" disabled>Charge Order</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

{{-- ============================ PAYMENT ============================ --}}
<div class="veil" id="paymentModal" role="dialog" aria-modal="true" aria-labelledby="paymentTitle" hidden>
    <div class="modal">
        <div class="m-head">
            <h2 id="paymentTitle">Payment</h2>
            <button type="button" data-close aria-label="Close payment">&times;</button>
        </div>
        <div class="m-body">
            <p class="form-error" id="paymentError" hidden></p>

            <div class="due">
                <span>Amount due</span>
                <b id="amountDue">{{ $store['currency_symbol'] }}0.00</b>
            </div>

            <div role="radiogroup" aria-label="Payment method">
                <button type="button" class="pay on" data-method="Cash"
                        role="radio" aria-checked="true" aria-label="Pay with cash">
                    <span><b>Cash</b><small>Change computed automatically</small></span>
                </button>
                <button type="button" class="pay" data-method="Digital Payment"
                        role="radio" aria-checked="false" aria-label="Pay with a digital wallet">
                    <span><b>Digital Payment</b><small>GCash / Maya &mdash; matches the online channel</small></span>
                </button>
            </div>

            <div id="cashBox" class="pay-panel">
                <div class="tender">
                    <label for="tendered">Cash received</label>
                    <input id="tendered" type="text" inputmode="decimal" value="0.00"
                           autocomplete="off" data-autofocus>
                </div>
                <div class="quick">
                    <button type="button" data-tender="exact">Exact</button>
                    <button type="button" data-tender="500">500</button>
                    <button type="button" data-tender="1000">1000</button>
                    <button type="button" data-tender="2000">2000</button>
                </div>
                <p class="change" id="changeBox">
                    <span>Change due</span><b id="changeDue">{{ $store['currency_symbol'] }}0.00</b>
                </p>
            </div>

            <div id="referenceBox" class="pay-panel" hidden>
                <div class="tender">
                    <label for="paymentReference">Reference number (optional)</label>
                    <input id="paymentReference" class="is-text" type="text" autocomplete="off">
                </div>
            </div>
        </div>
        <div class="m-foot">
            <button type="button" class="btn outl" data-close>Back</button>
            <button type="button" class="btn solid" id="confirmPaymentBtn">Confirm Payment</button>
        </div>
    </div>
</div>

{{-- =========================== PRODUCT ============================
     The same look at a product the customer gets on the shop: the picture
     at size, the description, the sizes with what is left of each, and one
     action. Opened by clicking anywhere on a product row.
     ================================================================ --}}
<div class="veil" id="productModal" role="dialog" aria-modal="true" aria-labelledby="quickTitle" hidden>
    <div class="modal is-wide">
        <div class="m-head">
            <h2 id="quickTitle">Product</h2>
            <button type="button" data-close aria-label="Close product">&times;</button>
        </div>

        <div class="m-body quick-view">
            <figure class="quick-art">
                <img id="quickImage" src="" alt="">
            </figure>

            <div class="quick-info">
                <p class="quick-cat" id="quickCategory"></p>
                <h3 class="quick-name" id="quickName"></h3>
                <p class="quick-price" id="quickPrice"></p>
                <p class="quick-desc" id="quickDescription" hidden></p>

                <div class="quick-field">
                    <span class="quick-label" id="quick-size-label">Size</span>
                    <div class="quick-sizes" id="quickSizes" role="radiogroup" aria-labelledby="quick-size-label"></div>
                    <p class="quick-sku" id="quickSku" hidden></p>
                </div>

                <div class="quick-field">
                    <span class="quick-label" id="quick-qty-label">Quantity</span>
                    <div class="quick-qty" role="group" aria-labelledby="quick-qty-label">
                        <span class="stepper">
                            <button type="button" id="quickMinus" data-quick-step="-1" aria-label="Decrease quantity">&minus;</button>
                            <span id="quickQty" aria-live="polite">1</span>
                            <button type="button" id="quickPlus" data-quick-step="1" aria-label="Increase quantity">+</button>
                        </span>
                        <b class="quick-line" id="quickLine" hidden></b>
                    </div>
                </div>

                <p class="quick-inorder" id="quickInOrder" hidden></p>

                <button type="button" class="quick-add" id="quickAdd">Add to order</button>
            </div>
        </div>
    </div>
</div>

{{-- ======================== ORDER TRACKING ======================== --}}
<div class="veil" id="trackingModal" role="dialog" aria-modal="true" aria-labelledby="trackingTitle" hidden>
    <div class="modal is-board">
        <div class="m-head">
            <h2 id="trackingTitle">Order Tracking</h2>
            <button type="button" data-close aria-label="Close order tracking">&times;</button>
        </div>
        <div class="m-body">
            <table class="track">
                <thead>
                    <tr>
                        <th scope="col">Order Ref</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Channel</th>
                        <th scope="col">Total</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody id="trackingBody">
                    <tr><td colspan="6" class="empty">Loading orders&hellip;</td></tr>
                </tbody>
            </table>
        </div>
        <div class="m-foot">
            <a class="btn outl" href="{{ route('admin.orders') }}">Open Order Desk</a>
            <button type="button" class="btn solid" data-close>Close</button>
        </div>
    </div>
</div>

{{-- ========================== HELD SALES ========================== --}}
<div class="veil" id="heldModal" role="dialog" aria-modal="true" aria-labelledby="heldTitle" hidden>
    <div class="modal is-list">
        <div class="m-head">
            <h2 id="heldTitle">Held Sales</h2>
            <button type="button" data-close aria-label="Close held sales">&times;</button>
        </div>
        <div class="m-body" id="heldBody">
            <p class="empty">Loading&hellip;</p>
        </div>
    </div>
</div>

{{-- =========================== RECEIPT ============================ --}}
<div class="veil" id="receiptModal" role="dialog" aria-modal="true" aria-labelledby="receiptTitle" hidden>
    <div class="modal is-receipt">
        <div class="m-head">
            <h2 id="receiptTitle">Receipt</h2>
            <button type="button" data-close aria-label="Close receipt">&times;</button>
        </div>
        <div class="m-body">
            {{-- Filled with the document rendered by pos/partials/receipt-document,
                 so the copy on screen is the copy that prints. --}}
            <div id="receiptBody"></div>
        </div>
        <div class="m-foot">
            <button type="button" class="btn outl" id="newSaleBtn">New Sale</button>
            <button type="button" class="btn solid" id="printReceiptBtn">Print</button>
        </div>
    </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
@endsection

@push('scripts')
<script>
{{-- Only what the register actually uses. The store identity, the cashier
     and the terminal used to be handed to the browser so it could assemble
     its own receipt; the receipt now comes rendered from the server, so
     they no longer need to leave PHP. --}}
window.POS = {
    catalog: @json($catalog),
    categories: @json($categories),
    discount: @json($discountRule),
    lowStockThreshold: {{ (int) config('void.low_stock_threshold') }},
    currency: @json($store['currency_symbol']),
    shift: @json($shift),
    routes: {
        catalog: @json(route('pos.catalog')),
        sales: @json(route('pos.sales.store')),
        recent: @json(route('pos.sales.recent')),
        heldIndex: @json(route('pos.held.index')),
        heldStore: @json(route('pos.held.store')),
        heldDestroy: @json(route('pos.held.destroy', ['heldSale' => '__ID__'])),
        receiptPrint: @json(route('pos.receipt.print', ['order' => '__REF__'])),
        receiptShow: @json(route('pos.receipt', ['order' => '__REF__'])),
    },
};
</script>
<script src="{{ asset('js/pos.js') }}?v={{ filemtime(public_path('js/pos.js')) }}"></script>
@endpush
