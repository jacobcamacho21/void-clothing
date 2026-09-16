<div class="cart" id="cart">
    <h2 class="cart-title">Your Cart</h2>
    <div id="cart-content" class="cart-content">
        <!-- Filled by cart.js -->
    </div>
    <div class="total">
        <div class="total-title">Total:</div>
        <div id="total-price" class="total-price">{{ $store['currency_symbol'] }}0</div>
    </div>
    <button class="btn-buy">Check Out</button>
    <img src="{{ asset('images/icons/close.png') }}" class="v-cart-close" id="cart-close" alt="Close cart">
</div>
