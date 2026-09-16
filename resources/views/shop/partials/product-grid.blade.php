<div class="products">
    @forelse ($products as $product)
        <div class="product-card">
            <a href="{{ route('shop.product', $product) }}">
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}">
            </a>
            <h3>{{ $product->name }}</h3>
            <p>{{ $store['currency_symbol'] }}{{ number_format($product->displayPrice(), 2) }}</p>
            @if ($product->isSoldOut())
                <p class="product-soldout">Sold out</p>
            @endif
        </div>
    @empty
        <p class="products-empty">No products to show yet.</p>
    @endforelse
</div>
