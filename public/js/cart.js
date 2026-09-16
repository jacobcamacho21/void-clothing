/**
 * Storefront cart — slide-out panel, add/remove, quantity steppers.
 *
 * Same interaction as before; prices and stock now come from the server on
 * every response instead of being hard-coded in the browser, so the panel can
 * never disagree with the catalog.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const CFG = window.SHOP;
    if (!CFG) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const cartPanel = document.getElementById('cart');
    const cartContent = document.getElementById('cart-content');
    const totalPrice = document.getElementById('total-price');
    const openCart = document.getElementById('cart-btn');
    const closeCart = document.getElementById('cart-close');
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    const variantSelect = document.querySelector('[data-variant-select]');
    const feedback = document.getElementById('add-to-cart-feedback');

    function peso(value) {
        return CFG.currency + (Number(value) || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    async function request(url, method, payload) {
        const options = {
            method: method,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        };

        if (payload) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }

        const response = await fetch(url, options);
        const body = await response.json().catch(() => null);

        if (!response.ok && !body) {
            throw new Error('The cart could not be reached.');
        }

        return body;
    }

    function paint(data) {
        if (!cartContent) return;

        const items = data?.items ?? [];

        if (items.length === 0) {
            cartContent.innerHTML = '<p class="empty-cart">Your cart is empty.</p>';
        } else {
            cartContent.innerHTML = items.map((item) => `
                <div class="cart-box">
                    <img src="${escapeHtml(item.product_image)}" class="cart-img" alt="${escapeHtml(item.product_name)}">
                    <div class="cart-detail">
                        <h2 class="cart-product-title">${escapeHtml(item.product_name)} (${escapeHtml(item.product_size)})</h2>
                        <span class="cart-price">${peso(item.unit_price)}</span>
                        <div class="cart-quantity">
                            <button class="decrement" type="button"
                                    data-variant="${item.product_variant_id}"
                                    data-quantity="${item.quantity - 1}"
                                    aria-label="Decrease quantity">-</button>
                            <span class="number">${item.quantity}</span>
                            <button class="increment" type="button"
                                    data-variant="${item.product_variant_id}"
                                    data-quantity="${item.quantity + 1}"
                                    ${item.quantity >= item.stock ? 'disabled' : ''}
                                    aria-label="Increase quantity">+</button>
                        </div>
                    </div>
                    <img src="${escapeHtml(trashIcon())}" class="v-cart-remove remove-item"
                         data-variant="${item.product_variant_id}" alt="Remove item" role="button" tabindex="0">
                </div>`).join('');
        }

        if (totalPrice) {
            totalPrice.textContent = peso(data?.totals?.subtotal ?? 0);
        }
    }

    let cachedTrashIcon = null;
    function trashIcon() {
        if (cachedTrashIcon === null) {
            // Derived from the cart panel's own close icon so the path stays
            // correct whatever directory the page is served from.
            const close = document.getElementById('cart-close');
            cachedTrashIcon = close
                ? close.getAttribute('src').replace(/close\.png$/, 'trash.png')
                : '/images/icons/trash.png';
        }
        return cachedTrashIcon;
    }

    async function refresh() {
        try {
            paint(await request(CFG.routes.cart, 'GET'));
        } catch (error) {
            cartContent.innerHTML = '<p class="empty-cart" style="color:#b00020;">'
                + escapeHtml(error.message) + '</p>';
        }
    }

    function showFeedback(message, isError) {
        if (!feedback) {
            if (isError) window.alert(message);
            return;
        }

        feedback.textContent = message;
        feedback.hidden = false;
        feedback.classList.toggle('is-error', Boolean(isError));

        window.clearTimeout(showFeedback.timer);
        showFeedback.timer = window.setTimeout(function () {
            feedback.hidden = true;
        }, 3200);
    }

    if (openCart) {
        openCart.addEventListener('click', function (event) {
            event.preventDefault();
            cartPanel.classList.add('cart-active');
            refresh();
        });
    }

    if (closeCart) {
        closeCart.addEventListener('click', function () {
            cartPanel.classList.remove('cart-active');
        });
    }

    if (addToCartBtn && variantSelect) {
        addToCartBtn.addEventListener('click', async function () {
            const variantId = variantSelect.value;

            if (!variantId) {
                showFeedback('Please select a size.', true);
                variantSelect.focus();
                return;
            }

            addToCartBtn.disabled = true;

            try {
                const data = await request(CFG.routes.add, 'POST', {
                    product_variant_id: Number(variantId),
                    quantity: 1,
                });

                if (data?.success) {
                    paint(data);
                    cartPanel.classList.add('cart-active');
                    showFeedback('Added to your cart.', false);
                } else {
                    showFeedback(data?.error ?? 'Could not add that item.', true);
                }
            } catch (error) {
                showFeedback(error.message, true);
            } finally {
                addToCartBtn.disabled = false;
            }
        });
    }

    if (cartContent) {
        cartContent.addEventListener('click', async function (event) {
            const target = event.target;
            const variantId = Number(target.dataset.variant);
            if (!variantId) return;

            try {
                if (target.classList.contains('remove-item')) {
                    paint(await request(CFG.routes.remove, 'DELETE', { product_variant_id: variantId }));
                    return;
                }

                if (target.classList.contains('increment') || target.classList.contains('decrement')) {
                    const data = await request(CFG.routes.update, 'PATCH', {
                        product_variant_id: variantId,
                        quantity: Number(target.dataset.quantity),
                    });

                    paint(data);

                    if (data && data.success === false) {
                        showFeedback(data.error, true);
                    }
                }
            } catch (error) {
                showFeedback(error.message, true);
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (!event.target.classList.contains('btn-buy')) return;

        const next = CFG.authenticated ? CFG.routes.checkout : CFG.routes.login;

        if (typeof window.navigateWithTransition === 'function') {
            window.navigateWithTransition(next);
        } else {
            window.location.href = next;
        }
    });

    // Prime the panel so the total is right before it is ever opened.
    refresh();
});
