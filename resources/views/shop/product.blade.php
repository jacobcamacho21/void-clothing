@extends('layouts.shop')

@section('title', $product->name.' — VOID')

@section('content')
<div class="product-detail">
    @php($imageUrls = $product->imageUrls())
    <div class="product-gallery" data-product-gallery>
        <div class="product-gallery-frame">
            @if (count($imageUrls) > 1)
                <button type="button" class="product-gallery-arrow product-gallery-prev"
                        data-gallery-step="-1" aria-label="Previous {{ $product->name }} image">&#8249;</button>
            @endif

            <div class="product-gallery-stage">
            @foreach ($imageUrls as $index => $imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $product->name }}{{ $index > 0 ? ' view '.($index + 1) : '' }}"
                     class="product-image" @if ($index > 0) hidden @endif>
            @endforeach
            </div>

            @if (count($imageUrls) > 1)
                <button type="button" class="product-gallery-arrow product-gallery-next"
                        data-gallery-step="1" aria-label="Next {{ $product->name }} image">&#8250;</button>
            @endif
        </div>

        @if (count($imageUrls) > 1)
            <p class="product-gallery-count" aria-live="polite">1 / {{ count($imageUrls) }}</p>
        @endif
    </div>

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

<section class="product-reviews" aria-labelledby="product-reviews-title">
    <div class="product-reviews-head">
        <div>
            <h2 id="product-reviews-title">{{ $product->name }} reviews</h2>
        </div>
        @if ($reviews->isNotEmpty())
            <p class="product-rating-summary">
                <strong>{{ number_format($reviews->avg('rating'), 1) }}</strong>
                <span class="review-stars" aria-label="Average rating {{ number_format($reviews->avg('rating'), 1) }} out of 5 stars">{{ str_repeat('★', (int) round($reviews->avg('rating'))) }}</span>
                <small>{{ $reviews->count() }} {{ $reviews->count() === 1 ? 'review' : 'reviews' }}</small>
            </p>
        @endif
    </div>

    @if ($reviews->isEmpty())
        <p class="home-reviews-empty">No reviews yet. Be the first to share your experience with this piece.</p>
    @else
        <div class="review-grid">
            @foreach ($reviews as $review)
                <article class="review-card">
                    <div class="review-card-topline">
                        <span class="review-avatar">{{ strtoupper(substr($review->customer->username, 0, 1)) }}</span>
                        <div>
                            <h3>{{ $review->customer->username }}</h3>
                            <p>Verified purchase</p>
                        </div>
                    </div>
                    <p class="review-stars" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}<span>{{ str_repeat('★', 5 - $review->rating) }}</span></p>
                    <blockquote>{{ $review->body }}</blockquote>
                    <p class="review-meta">{{ $review->created_at->diffForHumans() }}</p>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection

@if (count($imageUrls) > 1)
    @push('scripts')
        <script src="{{ asset('js/product-gallery.js') }}?v={{ filemtime(public_path('js/product-gallery.js')) }}"></script>
    @endpush
@endif
