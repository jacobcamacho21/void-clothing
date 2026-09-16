@extends('layouts.admin')

@section('title', 'Customers')
@section('heading', 'Customers')

@section('content')
<div class="content-card">
    <div class="toolbar">
        <h2>Customer Accounts</h2>
        <div class="toolbar-controls">
            <form method="GET" action="{{ route('admin.customers') }}" data-search-form>
                <div class="search-field">
                    <img src="{{ asset('images/admin/search.png') }}" alt="">
                    <label for="customerSearch" class="visually-hidden">Search customers</label>
                    <input type="search" id="customerSearch" name="q" value="{{ $search }}"
                           placeholder="Search name or email">
                </div>
            </form>
            <button type="button" class="btn-primary" data-modal-open="addCustomerModal">Add Customer</button>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Username</th>
                    <th scope="col">Email</th>
                    <th scope="col">Address</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Orders</th>
                    <th scope="col">Joined</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    @php $address = $customer->addresses->first(); @endphp
                    <tr>
                        <td>{{ $customer->id }}</td>
                        <td>{{ $customer->username }}</td>
                        <td class="muted-cell">{{ $customer->email }}</td>
                        <td class="muted-cell">{{ $address?->formatted() ?? '—' }}</td>
                        <td class="muted-cell">{{ $address?->phone ?? '—' }}</td>
                        <td class="numeric">{{ $customer->orders_count }}</td>
                        <td class="muted-cell">{{ $customer->created_at?->format('M d, Y') }}</td>
                        <td>
                            <div class="action-cell">
                                <button type="button" class="icon-btn" title="Edit {{ $customer->username }}"
                                        data-modal-open="editCustomerModal"
                                        data-action="{{ route('admin.customers.update', $customer) }}"
                                        data-field-username="{{ $customer->username }}"
                                        data-field-email="{{ $customer->email }}"
                                        data-field-recipientName="{{ $address?->recipient_name }}"
                                        data-field-phone="{{ $address?->phone }}"
                                        data-field-street="{{ $address?->street }}"
                                        data-field-city="{{ $address?->city }}"
                                        data-field-province="{{ $address?->province }}"
                                        data-field-postalCode="{{ $address?->postal_code }}"
                                        data-field-country="{{ $address?->country }}">
                                    <img src="{{ asset('images/admin/edit.png') }}" alt="Edit">
                                </button>

                                <form method="POST" class="inline-form"
                                      action="{{ route('admin.customers.destroy', $customer) }}"
                                      data-confirm="Delete {{ $customer->username }}? Their past orders are kept.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn" title="Delete {{ $customer->username }}">
                                        <img src="{{ asset('images/admin/delete.png') }}" alt="Delete">
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-row">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">{{ $customers->links() }}</div>
</div>

@foreach ([
    ['id' => 'addCustomerModal', 'title' => 'Add Customer', 'action' => route('admin.customers.store'), 'method' => 'POST', 'submit' => 'Create Customer', 'requirePassword' => true],
    ['id' => 'editCustomerModal', 'title' => 'Edit Customer', 'action' => '', 'method' => 'PATCH', 'submit' => 'Save Changes', 'requirePassword' => false],
] as $modal)
    <div id="{{ $modal['id'] }}" class="modal">
        <div class="modal-content">
            <button type="button" class="close" data-modal-close aria-label="Close">&times;</button>
            <h3>{{ $modal['title'] }}</h3>

            <form method="POST" action="{{ $modal['action'] }}">
                @csrf
                @if ($modal['method'] !== 'POST')
                    @method($modal['method'])
                @endif

                <label>Username
                    <input type="text" name="username" data-field="username" maxlength="50" required>
                </label>

                <label>Email
                    <input type="email" name="email" data-field="email" maxlength="100" required>
                </label>

                <label>Password
                    <input type="password" name="password" minlength="8"
                           @required($modal['requirePassword'])
                           autocomplete="new-password">
                </label>
                @unless ($modal['requirePassword'])
                    <p class="form-hint">Leave blank to keep the current password.</p>
                @endunless

                <div class="form-row">
                    <label>Recipient name
                        <input type="text" name="recipient_name" data-field="recipientName" maxlength="100">
                    </label>
                    <label>Phone
                        <input type="text" name="phone" data-field="phone" maxlength="30">
                    </label>
                </div>

                <label>Street
                    <input type="text" name="street" data-field="street" maxlength="255">
                </label>

                <div class="form-row">
                    <label>City
                        <input type="text" name="city" data-field="city" maxlength="100">
                    </label>
                    <label>Province
                        <input type="text" name="province" data-field="province" maxlength="100">
                    </label>
                </div>

                <div class="form-row">
                    <label>Postal code
                        <input type="text" name="postal_code" data-field="postalCode" maxlength="20">
                    </label>
                    <label>Country
                        <input type="text" name="country" data-field="country" maxlength="100" value="Philippines">
                    </label>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                    <button type="submit" class="btn-primary">{{ $modal['submit'] }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
