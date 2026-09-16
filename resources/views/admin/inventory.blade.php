@extends('layouts.admin')

@section('title', 'Inventory')
@section('heading', 'Stock Room')

@section('content')
@php
    $symbol = $store['currency_symbol'];
    $threshold = config('void.low_stock_threshold');
@endphp

@php
    // Each stock count is shown against the catalog it is counted from, the
    // same way the dashboard shows it, so the two pages agree.
    $variantsUnit = 'of '.number_format($summary['total']).' '
        .\Illuminate\Support\Str::plural('variant', $summary['total']);
@endphp

<div class="summary-grid">
    <div class="summary-card">
        <p class="summary-label">Total Variants</p>
        <p class="summary-value">{{ number_format($summary['total']) }}</p>
        <p class="summary-note">Every product-and-size combination</p>
    </div>
    <div class="summary-card">
        <p class="summary-label">Low Stock</p>
        <p class="summary-value metric-line">
            {{ number_format($summary['low_stock']) }}
            <span class="metric-unit">{{ $variantsUnit }}</span>
        </p>
        <p class="summary-note">
            At or below {{ $threshold }} {{ \Illuminate\Support\Str::plural('unit', $threshold) }} on the shelf
        </p>
    </div>
    <div class="summary-card">
        <p class="summary-label">Out of Stock</p>
        <p class="summary-value metric-line">
            {{ number_format($summary['out_of_stock']) }}
            <span class="metric-unit">{{ $variantsUnit }}</span>
        </p>
        <p class="summary-note">Cannot be sold until restocked</p>
    </div>
    <div class="summary-card">
        <p class="summary-label">Stock Value</p>
        <p class="summary-value">{{ $symbol }}{{ number_format($summary['stock_value'], 2) }}</p>
        <p class="summary-note">Retail value of everything on hand</p>
    </div>
</div>

<div class="content-card">
    <div class="toolbar">
        <h2>Item List</h2>
        <div class="toolbar-controls">
            <form method="GET" action="{{ route('admin.inventory') }}" data-search-form>
                <div class="search-field">
                    <img src="{{ asset('images/admin/search.png') }}" alt="">
                    <label for="inventorySearch" class="visually-hidden">Search inventory</label>
                    <input type="search" id="inventorySearch" name="q" value="{{ $search }}"
                           placeholder="Search product, size or SKU">
                </div>
            </form>
            @if ($canManage)
                <button type="button" class="btn-primary" data-modal-open="addVariantModal">Add Product</button>
            @endif
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Image</th>
                    <th scope="col">Product</th>
                    <th scope="col">Size</th>
                    <th scope="col">SKU</th>
                    <th scope="col">Price</th>
                    <th scope="col">Stock</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($variants as $index => $variant)
                    <tr>
                        <td>{{ $variants->firstItem() + $index }}</td>
                        <td>
                            <img src="{{ $variant->product?->imageUrl() }}" alt="" class="thumb">
                        </td>
                        <td>
                            {{ $variant->product?->name ?? 'Unlinked product' }}
                            <span class="muted-cell">&middot; {{ $variant->product?->category }}</span>
                        </td>
                        <td>{{ $variant->size }}</td>
                        <td class="muted-cell">{{ $variant->sku ?? '—' }}</td>
                        <td class="numeric">{{ $symbol }}{{ number_format($variant->price, 2) }}</td>
                        {{-- The count and its flag are two aligned columns
                             inside one cell, so a column of stock reads down
                             the digits with the flags in their own lane
                             rather than trailing off each number. --}}
                        <td>
                            <div class="stock-cell">
                                <span class="stock-count
                                    @if ($variant->stock === 0) is-out
                                    @elseif ($variant->isLowStock()) is-low @endif">{{ number_format($variant->stock) }}</span>

                                {{-- The flag lane is always here, even when a
                                     row has nothing to flag, so healthy rows
                                     do not pull their count across into it. --}}
                                <span class="stock-flag">
                                    @if ($variant->stock === 0)
                                        <span class="status-badge status-rejected">Out</span>
                                    @elseif ($variant->isLowStock())
                                        <span class="status-badge status-pending">Low</span>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="action-cell">
                                <button type="button" class="icon-btn"
                                        title="Edit {{ $variant->label() }}"
                                        data-modal-open="editVariantModal"
                                        data-action="{{ route('admin.inventory.update', $variant) }}"
                                        data-field-label="{{ $variant->label() }}"
                                        data-field-size="{{ $variant->size }}"
                                        data-field-price="{{ $variant->price }}"
                                        data-field-stock="{{ $variant->stock }}">
                                    <img src="{{ asset('images/admin/edit.png') }}" alt="Edit">
                                </button>

                                @if ($canManage)
                                    <form method="POST" class="inline-form"
                                          action="{{ route('admin.inventory.destroy', $variant) }}"
                                          data-confirm="Remove {{ $variant->label() }} from the catalog?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn" title="Delete {{ $variant->label() }}">
                                            <img src="{{ asset('images/admin/delete.png') }}" alt="Delete">
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-row">
                            {{ $search !== '' ? 'No items match that search.' : 'No products in the catalog yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">{{ $variants->links() }}</div>
</div>

{{-- ------------------------------ Add ------------------------------ --}}
@if ($canManage)
<div id="addVariantModal" class="modal">
    <div class="modal-content">
        <button type="button" class="close" data-modal-close aria-label="Close">&times;</button>
        <h3>Add Product Variant</h3>

        <form method="POST" action="{{ route('admin.inventory.store') }}">
            @csrf

            <label for="addProductId">Existing product</label>
            <select id="addProductId" name="product_id">
                <option value="">— Create a new product —</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
            <p class="form-hint">Pick a design to add another size to it, or leave blank and name a new one.</p>

            <label for="addName">New product name</label>
            <input type="text" id="addName" name="name" value="{{ old('name') }}" maxlength="100">

            <div class="form-row">
                <div>
                    <label for="addCategory">Category</label>
                    <input type="text" id="addCategory" name="category" value="{{ old('category', 'General') }}">
                </div>
                <div>
                    <label for="addSize">Size</label>
                    <select id="addSize" name="size" required>
                        @foreach ($sizes as $size)
                            <option value="{{ $size }}" @selected(old('size') === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="addPrice">Price</label>
                    <input type="number" id="addPrice" name="price" step="0.01" min="0"
                           value="{{ old('price') }}" required>
                </div>
                <div>
                    <label for="addStock">Stock</label>
                    <input type="number" id="addStock" name="stock" min="0"
                           value="{{ old('stock', 0) }}" required>
                </div>
            </div>

            <label for="addSku">SKU</label>
            <input type="text" id="addSku" name="sku" value="{{ old('sku') }}" maxlength="50">
            <p class="form-hint">Leave blank to generate one automatically.</p>

            <label for="addDescription">Description</label>
            <textarea id="addDescription" name="description" rows="3">{{ old('description') }}</textarea>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn-primary">Add Variant</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ------------------------------ Edit ------------------------------ --}}
<div id="editVariantModal" class="modal">
    <div class="modal-content">
        <button type="button" class="close" data-modal-close aria-label="Close">&times;</button>
        <h3>{{ $canManage ? 'Edit Variant' : 'Adjust Stock' }}</h3>

        <form method="POST" action="">
            @csrf
            @method('PATCH')

            <p class="modal-record" data-field="label"></p>

            @if ($canManage)
                <label for="editSize">Size</label>
                <select id="editSize" name="size" data-field="size">
                    @foreach ($sizes as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>

                <label for="editPrice">Price</label>
                <input type="number" id="editPrice" name="price" data-field="price" step="0.01" min="0" required>
            @endif

            <label for="editStock">Stock</label>
            <input type="number" id="editStock" name="stock" data-field="stock" min="0" required>

            @unless ($canManage)
                <p class="form-hint">Only an administrator can change size or pricing.</p>
            @endunless

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
