{{--
    Authentication layout.

    ---------------------------------------------------------------------
    One door, two liveries.
    ---------------------------------------------------------------------
    The customer sign-in, the customer registration and the staff sign-in
    are the same page: the same card, in the same place, at the same
    measure, built from the same approved storefront controls — the mark,
    the heading, the white bordered fields, the black uppercase button that
    inverts on hover, and the rule-and-link footer. None of that is stated
    per page; it is all in .auth-card in void-tokens.css, so the three
    cannot drift apart again.

    What differs is only the chrome above and below, because the two
    audiences need different things from it: a shopper needs the shop's
    header and footer to keep browsing, while someone signing in to the
    register needs neither. Both bands are the same height, so the card
    lands on the same pixels either way.

    Sections:
      title       — document title
      auth-title  — heading inside the card
      auth-sub    — optional line under the heading
      content     — the form
      auth-foot   — the links under the form
      auth-chrome — replaces the shopper header (used by the staff sign-in)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', 'Sign in') &mdash; VOID</title>
    <link rel="stylesheet" href="{{ asset('css/index_design.css') }}?v={{ filemtime(public_path('css/index_design.css')) }}">
    @stack('head')
</head>
<body class="auth-page @yield('auth-body-class')">

@hasSection('auth-chrome')
    @yield('auth-chrome')
@else
    @include('shop.partials.topbar')
    @include('shop.partials.cart')
@endif

<main class="auth-main">
    <div class="auth-card">
        <a class="auth-mark" href="{{ route('shop.home') }}" aria-label="VOID home">
            <img src="{{ asset('images/site/Void_Logo_Login.png') }}" class="logoLogin" alt="VOID">
        </a>

        @hasSection('auth-title')
            <div class="auth-head">
                <h1 class="auth-title">@yield('auth-title')</h1>
                @hasSection('auth-sub')
                    <p class="auth-sub">@yield('auth-sub')</p>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="auth-alert" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if (session('status'))
            <p class="shop-flash" role="status">{{ session('status') }}</p>
        @endif

        @yield('content')

        @hasSection('auth-foot')
            <div class="auth-foot">@yield('auth-foot')</div>
        @endif
    </div>
</main>

@sectionMissing('auth-chrome')
    {{-- Shoppers get the full storefront footer. The staff terminal gets a
         single system line instead — the shop's About/Contact links are not
         what someone signing in to the register needs. --}}
    @include('shop.partials.footer')
@else
    <footer class="auth-staff-foot">
        {{ $store['name'] ?? 'VOID' }} Retail System &middot; {{ config('void.pos.terminal') }}
    </footer>
@endif

<script src="{{ asset('js/transition.js') }}"></script>

@sectionMissing('auth-chrome')
    @include('partials.shop-scripts')
@endif

@stack('scripts')
</body>
</html>
