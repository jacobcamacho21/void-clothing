<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', 'VOID')</title>
    <link rel="stylesheet" href="{{ asset('css/index_design.css') }}?v={{ filemtime(public_path('css/index_design.css')) }}">
    {{-- The account pages the shop grew around the approved storefront —
         checkout, the account and an order's page. Loaded after the
         storefront sheet so it has the last word on the markup it owns. --}}
    <link rel="stylesheet" href="{{ asset('css/shop-pages.css') }}?v={{ filemtime(public_path('css/shop-pages.css')) }}">
    @stack('head')
</head>
<body class="@yield('body-class')">
    @include('shop.partials.topbar')

    @unless (View::hasSection('hide-cart'))
        @include('shop.partials.cart')
    @endunless

    @if (session('status'))
        <p class="shop-flash" role="status">{{ session('status') }}</p>
    @endif

    @yield('content')

    @include('shop.partials.footer')

    <script src="{{ asset('js/transition.js') }}"></script>
    @include('partials.shop-scripts')
    @stack('scripts')
</body>
</html>
