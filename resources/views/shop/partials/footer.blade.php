<footer class="footer">
    <div class="footer-content">
        <img src="{{ asset('images/site/Void_Logo_W.png') }}" alt="VOID logo" class="footer-logo">

        <div class="footer-links">
            <a href="{{ route('shop.about') }}">About</a>
            <a href="#">Contact</a>
            <a href="https://www.facebook.com/voidstreetwearph?share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F1BgN9B1or8%2F#">Socials</a>
            <a href="{{ route('shop.terms') }}">Terms &amp; Conditions</a>
        </div>

        <p class="copyright">
            &copy; {{ date('Y') }} VOID Clothing. All rights reserved.
        </p>
    </div>
</footer>
