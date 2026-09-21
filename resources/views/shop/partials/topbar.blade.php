<div class="topbar">
    <button type="button" class="hamburger-btn" id="mobile-nav-btn" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-nav">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div class="logo">
        <a href="{{ route('shop.home') }}" class="logo-link">
            <img src="{{ asset('images/site/Void_Logo.png') }}" alt="VOID logo">
        </a>
    </div>

    <nav>
        <a href="{{ route('shop.home') }}" class="link">HOME</a>
        <a href="{{ route('shop.products') }}" class="link">ALL PRODUCTS</a>
        <a href="{{ route('shop.apparel') }}" class="link">APPAREL</a>
    </nav>

    <div class="nav-icons">
        <a href="{{ route('shop.search') }}"><img src="{{ asset('images/icons/Search.png') }}" alt="Search"></a>
        <a href="#" id="cart-btn"><img src="{{ asset('images/icons/Cart.png') }}" alt="Cart"></a>
        <a href="{{ auth('customer')->check() ? route('shop.account') : route('shop.login') }}" class="nav-icon-user">
            <img src="{{ asset('images/icons/User.png') }}" alt="Profile">
        </a>
    </div>
</div>