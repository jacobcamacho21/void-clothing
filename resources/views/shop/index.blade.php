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
@endsection
