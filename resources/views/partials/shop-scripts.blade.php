{{--
    The storefront's client-side wiring: route table for cart.js plus the
    cart script itself. Shared by the shop layout and the customer auth
    pages, so the cart stays reachable while signing in or registering.
--}}
<script>
    window.SHOP = {
        routes: {
            cart: @json(route('shop.cart.index')),
            add: @json(route('shop.cart.add')),
            update: @json(route('shop.cart.update')),
            remove: @json(route('shop.cart.remove')),
            checkout: @json(route('shop.checkout')),
            login: @json(route('shop.login')),
        },
        authenticated: @json(auth('customer')->check()),
        currency: @json($store['currency_symbol']),
    };
</script>
<script src="{{ asset('js/cart.js') }}?v={{ filemtime(public_path('js/cart.js')) }}"></script>
