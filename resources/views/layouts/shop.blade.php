<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', 'VOID')</title>
    <link rel="stylesheet" href="{{ asset('css/index_design.css') }}?v={{ filemtime(public_path('css/index_design.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/shop-pages.css') }}?v={{ filemtime(public_path('css/shop-pages.css')) }}">
    @stack('head')
</head>
<body class="@yield('body-class')">
    @include('shop.partials.topbar')

    {{-- Mobile Navigation Drawer --}}
    <div class="mobile-nav-backdrop" id="mobile-nav-backdrop"></div>
    <div class="mobile-nav" id="mobile-nav">
        <div class="mobile-nav-head">
            <button type="button" class="mobile-nav-close" id="mobile-nav-close" aria-label="Close menu">&times;</button>
        </div>

        <nav class="mobile-nav-links">
            <a href="{{ route('shop.home') }}">HOME</a>
            <a href="{{ route('shop.products') }}">ALL PRODUCTS</a>
            <a href="{{ route('shop.apparel') }}">APPAREL</a>
        </nav>

        <a href="{{ auth('customer')->check() ? route('shop.account') : route('shop.login') }}" class="mobile-nav-login">
            <img src="{{ asset('images/icons/User.png') }}" alt="">
            {{ auth('customer')->check() ? 'ACCOUNT' : 'LOGIN' }}
        </a>
    </div>

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
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('mobile-nav-btn');
            var closeBtn = document.getElementById('mobile-nav-close');
            var panel = document.getElementById('mobile-nav');
            var backdrop = document.getElementById('mobile-nav-backdrop');

            if (!btn || !closeBtn || !panel || !backdrop) return;

            function openNav() {
                if (panel.classList.contains('is-open')) return;
                document.documentElement.classList.add('mobile-nav-locked');
                document.body.classList.add('mobile-nav-locked');
                panel.classList.add('is-open');
                backdrop.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
            }

            function closeNav() {
                if (!panel.classList.contains('is-open')) return;
                panel.classList.remove('is-open');
                backdrop.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
                document.documentElement.classList.remove('mobile-nav-locked');
                document.body.classList.remove('mobile-nav-locked');
            }

            btn.addEventListener('click', openNav);
            closeBtn.addEventListener('click', closeNav);
            backdrop.addEventListener('click', closeNav);

            panel.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', closeNav);
            });
        });
    </script>
    @stack('scripts')
</body>
</html>