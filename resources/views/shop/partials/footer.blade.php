<footer class="footer">
    <div class="footer-content">
        <img src="{{ asset('images/site/Void_Logo_W.png') }}" alt="VOID logo" class="footer-logo">

        <div class="footer-links">
            <a href="#">About</a>
            <a href="#">Contact</a>
            <a href="#">Socials</a>
            <a href="{{ route('shop.terms') }}">Terms &amp; Conditions</a>
        </div>

        <p class="copyright">
            &copy; {{ date('Y') }} VOID Clothing. All rights reserved.
        </p>
    </div>
</footer>
