@extends('layouts.shop')

@section('title', 'Your Account — VOID')
@section('body-class', 'shop-app')

@section('content')
@php $symbol = $store['currency_symbol']; @endphp

<div class="shop-page">
    {{-- One page title, above both columns. --}}
    <header class="shop-page-head">
        <div>
            <h1 class="shop-page-title">Your Account</h1>
            <p class="shop-page-sub">Your details, delivery addresses and the orders you have placed.</p>
        </div>
    </header>

    @if (auth('customer')->check() && ! auth('customer')->user()->hasVerifiedEmail())
        <div class="shop-alert" style="background: #000000; color: #ffffff; border: 1px solid #333333; display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem;" role="alert">
            <div>
                <b style="color: #ffffff; text-transform: uppercase; font-size: 0.8125rem; letter-spacing: 0.05em;">Email Not Verified</b>
                <div style="font-size: 0.8125rem; color: #a3a3a3; margin-top: 0.25rem;">Please check your inbox and verify your email address to secure your account.</div>
            </div>
            <form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit" class="void-btn void-btn--ghost" style="background: #ffffff; color: #000000; border: none; padding: 0.375rem 0.75rem; font-size: 0.75rem; white-space: nowrap;">
        Resend Link
    </button>
</form>
        </div>
    @endif

    @if (session('status'))
        <div class="shop-alert is-ok" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if (session('order_placed'))
        <div class="shop-alert is-ok" role="status">
            <b>Thank you — your order has been placed.</b>
            Reference {{ session('order_placed') }}. We are checking your payment proof now;
            the status below updates as soon as it is approved.
        </div>
    @endif

    <div class="account-grid">
        {{-- Left: identity and addresses --}}
        <div class="account-col">
            <section class="shop-card">
                <h2 class="shop-section-title">
                    Details
                    <button type="button" class="shop-inline-link" data-open="editNameModal">Edit name</button>
                </h2>

                <dl class="shop-datalist">
                    <div class="shop-data">
                        <dt>Name</dt>
                        <dd>{{ $customer->username }}</dd>
                    </div>
                    <div class="shop-data">
                        <dt>Email</dt>
                        <dd>{{ $customer->email }}</dd>
                    </div>
                    <div class="shop-data">
                        <dt>Member since</dt>
                        <dd>{{ $customer->created_at?->format('M d, Y') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="shop-card">
                <h2 class="shop-section-title">
                    <span>Addresses
                        @if ($addresses->isNotEmpty())
                            <span class="shop-count">{{ $addresses->count() }}</span>
                        @endif
                    </span>
                    <button type="button" class="shop-inline-link" data-open="addAddressModal">Add new</button>
                </h2>

                @forelse ($addresses as $address)
                    <div class="shop-tile">
                        <div class="name">{{ $address->recipient_name }}</div>
                        <div>{{ $address->phone }}</div>
                        <div>{{ $address->formatted() }}</div>
                        <div class="actions">
                            <button type="button" class="shop-inline-link"
                                    data-open="editAddressModal"
                                    data-action="{{ route('shop.account.addresses.update', $address) }}"
                                    data-recipient_name="{{ $address->recipient_name }}"
                                    data-phone="{{ $address->phone }}"
                                    data-street="{{ $address->street }}"
                                    data-city="{{ $address->city }}"
                                    data-province="{{ $address->province }}"
                                    data-postal_code="{{ $address->postal_code }}"
                                    data-country="{{ $address->country }}">Edit</button>

                            <form class="inline-form" method="POST"
                                  action="{{ route('shop.account.addresses.destroy', $address) }}"
                                  onsubmit="return confirm('Remove this address?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="shop-inline-link is-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="shop-empty">No saved addresses yet. Add one to check out faster.</p>
                @endforelse
            </section>

            <form method="POST" action="{{ route('shop.logout') }}">
                @csrf
                <button type="submit" class="void-btn void-btn--block void-btn--ghost">Logout</button>
            </form>
        </div>

        {{-- Right: order history --}}
        <div class="account-col">
            <section class="shop-card">
                <h2 class="shop-section-title">
                    <span>Orders
                        @if ($orders->isNotEmpty())
                            <span class="shop-count">{{ $orders->count() }}</span>
                        @endif
                    </span>
                </h2>

                @if ($orders->isEmpty())
                    <p class="shop-empty">
                        No orders yet. Browse the <a href="{{ route('shop.products') }}">catalog</a> to get started.
                    </p>
                @else
                    <table class="shop-table">
                        <thead>
                            <tr>
                                <th scope="col">Order</th>
                                <th scope="col">Status</th>
                                <th scope="col">Items</th>
                                <th scope="col">Date</th>
                                <th scope="col" class="num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td>
                                        <a class="ref-link" href="{{ route('shop.account.order', $order) }}">
                                            {{ $order->order_ref }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="order-status {{ $order->status->badgeClass() }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $order->items->sum('quantity') }} item(s)
                                        <div class="shop-subitems">
                                            @foreach ($order->items as $item)
                                                {{ $item->product_name }} ({{ $item->product_size }}) &times;{{ $item->quantity }}@if (! $loop->last)<br>@endif
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="nowrap">{{ $order->created_at?->format('M d, Y') }}</td>
                                    <td class="num"><b>{{ $symbol }}{{ number_format($order->total_amount, 2) }}</b></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>
    </div>
</div>

{{-- Edit name modal --}}
<div class="shop-modal" id="editNameModal">
    <div class="shop-modal-card">
        <form method="POST" action="{{ route('shop.account.name') }}">
            @csrf
            @method('PATCH')
            <h3>Edit Name</h3>
            <div class="shop-field">
                <label for="account-username" class="shop-label">Name</label>
                <input type="text" id="account-username" name="username" class="shop-input"
                       value="{{ $customer->username }}" maxlength="50" required>
                @error('username')<p class="shop-field-error">{{ $message }}</p>@enderror
            </div>
            <div class="shop-actions">
                <button type="submit" class="void-btn void-btn--block">Save</button>
                <button type="button" class="void-btn void-btn--block void-btn--ghost" data-close>Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Add address modal --}}
<div class="shop-modal" id="addAddressModal">
    <div class="shop-modal-card">
        <form method="POST" action="{{ route('shop.account.addresses.store') }}">
            @csrf
            <h3>Add Address</h3>

            <div class="shop-field">
                <label for="add-recipient" class="shop-label">Recipient Name</label>
                <input type="text" id="add-recipient" name="recipient_name" class="shop-input" maxlength="100" required>
            </div>
            <div class="shop-field">
                <label for="add-phone" class="shop-label">Phone</label>
                <input type="text" id="add-phone" name="phone" class="shop-input" maxlength="30" required>
            </div>
            <div class="shop-field">
                <label for="add-street" class="shop-label">Street Address</label>
                <input type="text" id="add-street" name="street" class="shop-input" maxlength="255" required>
            </div>
            <div class="shop-field-row">
                <div class="shop-field">
                    <label for="add-city" class="shop-label">City</label>
                    <input type="text" id="add-city" name="city" class="shop-input" maxlength="100" required>
                </div>
                <div class="shop-field">
                    <label for="add-province" class="shop-label">Province</label>
                    <input type="text" id="add-province" name="province" class="shop-input" maxlength="100" required>
                </div>
            </div>
            <div class="shop-field-row">
                <div class="shop-field">
                    <label for="add-postal" class="shop-label">Postal Code</label>
                    <input type="text" id="add-postal" name="postal_code" class="shop-input" maxlength="20" required>
                </div>
                <div class="shop-field">
                    <label for="add-country" class="shop-label">Country</label>
                    <input type="text" id="add-country" name="country" class="shop-input" value="Philippines" maxlength="100" required>
                </div>
            </div>

            <div class="shop-actions">
                <button type="submit" class="void-btn void-btn--block">Add Address</button>
                <button type="button" class="void-btn void-btn--block void-btn--ghost" data-close>Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit address modal --}}
<div class="shop-modal" id="editAddressModal">
    <div class="shop-modal-card">
        <form method="POST" action="" id="editAddressForm">
            @csrf
            @method('PATCH')
            <h3>Edit Address</h3>

            <div class="shop-field">
                <label for="edit-recipient" class="shop-label">Recipient Name</label>
                <input type="text" id="edit-recipient" name="recipient_name" data-fill="recipient_name" class="shop-input" maxlength="100" required>
            </div>
            <div class="shop-field">
                <label for="edit-phone" class="shop-label">Phone</label>
                <input type="text" id="edit-phone" name="phone" data-fill="phone" class="shop-input" maxlength="30" required>
            </div>
            <div class="shop-field">
                <label for="edit-street" class="shop-label">Street Address</label>
                <input type="text" id="edit-street" name="street" data-fill="street" class="shop-input" maxlength="255" required>
            </div>
            <div class="shop-field-row">
                <div class="shop-field">
                    <label for="edit-city" class="shop-label">City</label>
                    <input type="text" id="edit-city" name="city" data-fill="city" class="shop-input" maxlength="100" required>
                </div>
                <div class="shop-field">
                    <label for="edit-province" class="shop-label">Province</label>
                    <input type="text" id="edit-province" name="province" data-fill="province" class="shop-input" maxlength="100" required>
                </div>
            </div>
            <div class="shop-field-row">
                <div class="shop-field">
                    <label for="edit-postal" class="shop-label">Postal Code</label>
                    <input type="text" id="edit-postal" name="postal_code" data-fill="postal_code" class="shop-input" maxlength="20" required>
                </div>
                <div class="shop-field">
                    <label for="edit-country" class="shop-label">Country</label>
                    <input type="text" id="edit-country" name="country" data-fill="country" class="shop-input" maxlength="100" required>
                </div>
            </div>

            <div class="shop-actions">
                <button type="submit" class="void-btn void-btn--block">Save Changes</button>
                <button type="button" class="void-btn void-btn--block void-btn--ghost" data-close>Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-open]');

        if (opener) {
            const modal = document.getElementById(opener.dataset.open);
            if (!modal) return;

            // Copy the clicked address into the edit form.
            if (opener.dataset.action) {
                modal.querySelector('form').action = opener.dataset.action;

                modal.querySelectorAll('[data-fill]').forEach(function (input) {
                    input.value = opener.dataset[input.dataset.fill] ?? '';
                });
            }

            modal.classList.add('on');
            modal.querySelector('input:not([type="hidden"])')?.focus();
            return;
        }

        if (event.target.closest('[data-close]') || event.target.classList.contains('shop-modal')) {
            event.target.closest('.shop-modal')?.classList.remove('on');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.shop-modal.on').forEach(function (modal) {
                modal.classList.remove('on');
            });
        }
    });

    // Reopen the name dialog when its validation failed, so the error is seen.
    @if ($errors->has('username'))
        document.getElementById('editNameModal').classList.add('on');
    @endif
})();
</script>
@endpush