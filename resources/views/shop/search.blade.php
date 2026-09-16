@extends('layouts.shop')

@section('title', 'Search — VOID')

@section('content')
    <div class="products-section">
        <h1>{{ strtoupper($heading) }}</h1>

        <form method="GET" action="{{ route('shop.search') }}" class="shop-search-form" role="search">
            <label for="shopSearch" class="visually-hidden">Search products</label>
            <input type="search" id="shopSearch" name="q" value="{{ $term }}"
                   placeholder="Search the catalog" autofocus>
            <button type="submit">Search</button>
        </form>
    </div>

    @include('shop.partials.product-grid')
@endsection
