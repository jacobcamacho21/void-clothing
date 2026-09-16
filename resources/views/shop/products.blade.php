@extends('layouts.shop')

@section('title', $heading.' — VOID')

@section('content')
    <div class="products-section">
        <h1>{{ strtoupper($heading) }}</h1>
    </div>

    @include('shop.partials.product-grid')
@endsection
