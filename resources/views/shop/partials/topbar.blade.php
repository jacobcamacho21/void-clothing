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

<script>
    (function () {
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
    })();
</script>