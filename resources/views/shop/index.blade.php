@extends('layouts.shop')

@section('title', 'VOID')

@section('content')
    <div class="hp">
        <img src="{{ asset('images/site/Homepage.png') }}" alt="VOID">
    </div>

    <div class="products-section">
        <h1>ALL PRODUCTS</h1>
    </div>

    @include('shop.partials.product-grid')

    <section class="home-reviews" aria-labelledby="reviews-title">
        <div class="home-reviews-head">
            <p class="home-reviews-kicker">FROM THE VOID COMMUNITY</p>
            <h2 id="reviews-title">Why our customers <em>love</em> VOID</h2>
        </div>

        @if ($reviews->isEmpty())
            <p class="home-reviews-empty">Wear it. Live in it. Then tell us what you think.</p>
        @else
            <div class="review-grid">
                @foreach ($reviews as $review)
                    <article class="review-card">
                        <div class="review-card-topline">
                            <span class="review-avatar">{{ strtoupper(substr($review->customer->username, 0, 1)) }}</span>
                            <div>
                                <h3>{{ $review->customer->username }}</h3>
                                <p>{{ $review->product->name }}</p>
                            </div>
                        </div>
                        <p class="review-stars" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}<span>{{ str_repeat('★', 5 - $review->rating) }}</span></p>
                        <blockquote>{{ $review->body }}</blockquote>
                        <p class="review-meta">Verified purchase &middot; {{ $review->created_at->diffForHumans() }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
