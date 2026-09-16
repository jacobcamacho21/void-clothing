@extends('layouts.shop')

@section('title', $product->name.' — VOID')

@section('content')
<div class="product-detail">
    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="product-image">

    <div class="product-info">
        <h1>{{ $product->name }}</h1>
        <p class="price">{{ $store['currency_symbol'] }}{{ number_format($product->displayPrice(), 2) }}</p>
        <p class="description">{{ $product->description }}</p>

        <div class="product-options">
            <div class="option-group">
                <label for="size">Size</label>
                <select id="size" class="option-select" data-variant-select>
                    <option value="">Select Size</option>
                    @foreach ($variants as $variant)
                        <option value="{{ $variant->id }}"
                                data-price="{{ $variant->price }}"
                                data-stock="{{ $variant->stock }}"
                                @disabled($variant->stock < 1)>
                            {{ $variant->sizeAbbreviation() }}
                            @if ($variant->stock > 0)
                                (Stock: {{ $variant->stock }})
                            @else
                                (Sold out)
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <button class="add-to-cart" id="add-to-cart-btn"
                @disabled($product->isSoldOut())>
            {{ $product->isSoldOut() ? 'Sold Out' : 'Add to Cart' }}
        </button>

        <p class="add-to-cart-feedback" id="add-to-cart-feedback" role="status" hidden></p>

        <div class="size-chart">
            <h3>Size Chart</h3>
            <div class="size-chart-table">
                <table>
                    <thead>
                        <tr>
                            <th>Size (INT)</th>
                            <th>Width <span class="muted">(inch)</span></th>
                            <th>Top Length <span class="muted">(inch)</span></th>
                            <th>Sleeve Length <span class="muted">(inch)</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>S</td><td>20</td><td>26.5</td><td>9.5</td></tr>
                        <tr><td>M</td><td>21</td><td>27.5</td><td>10</td></tr>
                        <tr><td>L</td><td>22</td><td>28</td><td>10.5</td></tr>
                        <tr><td>XL</td><td>23</td><td>29.5</td><td>11</td></tr>
                    </tbody>
                </table>
                <p class="size-note">The measurements may vary slightly.</p>
            </div>
        </div>
    </div>
</div>
@endsection
