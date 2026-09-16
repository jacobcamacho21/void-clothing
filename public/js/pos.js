/**
 * VOID POS — register behaviour.
 *
 * The server owns pricing: totals shown here are a preview for the cashier,
 * and every figure that ends up on the order is recalculated server-side when
 * the sale is charged. Stock counts are refreshed from the catalog endpoint
 * after each sale so the size chips stay honest.
 */
(function () {
    'use strict';

    const CFG = window.POS;
    if (!CFG) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    /* ------------------------------------------------------------------ state */

    const state = {
        cart: [],            // { variantId, productId, name, size, abbr, price, qty, stock }
        collection: 'All',
        filter: null,        // 'low-stock' | 'in-stock' | null
        search: '',
        method: 'Cash',
        reference: '',
        // The order reference is issued by the server when the sale is
        // charged, or when the basket is parked. Until then there is nothing
        // to show, and the panel says so rather than displaying a placeholder
        // that looks like a real document number but is not one.
        orderRef: null,
        heldRef: null,      // the hold this basket was resumed from, if any
        busy: false,
        lastOrder: null,
        quickView: null,     // the product open in the detail dialog
        quickSize: null,     // the variant chosen inside it
        quickQty: 1,
        heldCount: Number(document.getElementById('heldBadge')?.textContent ?? 0),
    };

    /* ------------------------------------------------------------------ utils */

    const $ = (id) => document.getElementById(id);

    function peso(value) {
        const n = Number(value) || 0;
        return CFG.currency + n.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    let toastTimer = null;
    function toast(message, kind) {
        const el = $('toast');
        el.textContent = message;
        el.className = 'toast on' + (kind ? ' is-' + kind : '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.className = 'toast'; }, 3200);
    }

    async function api(url, options) {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            ...options,
        });

        let body = null;
        try { body = await response.json(); } catch (_) { /* empty body */ }

        if (!response.ok) {
            const message = body?.message
                || Object.values(body?.errors ?? {}).flat()[0]
                || 'Something went wrong. Please try again.';
            const error = new Error(message);
            error.status = response.status;
            error.body = body;
            throw error;
        }

        return body;
    }

    /* ------------------------------------------------------------ catalog view */

    function variantsOf(product) {
        return product.variants || [];
    }

    function totalStock(product) {
        return variantsOf(product).reduce((sum, v) => sum + v.stock, 0);
    }

    function visibleProducts() {
        const term = state.search.trim().toLowerCase();

        return CFG.catalog.filter((product) => {
            if (state.collection !== 'All' && product.category !== state.collection) return false;

            if (state.filter === 'low-stock') {
                const stock = totalStock(product);
                if (stock === 0 || stock > CFG.lowStockThreshold) return false;
            }

            if (state.filter === 'in-stock' && totalStock(product) === 0) return false;

            if (term === '') return true;

            return product.name.toLowerCase().includes(term)
                || (product.category || '').toLowerCase().includes(term)
                || variantsOf(product).some((v) => (v.sku || '').toLowerCase().includes(term));
        });
    }

    function renderCollections() {
        const names = ['All', ...CFG.categories];

        $('collections').innerHTML = names.map((name) => {
            const count = name === 'All'
                ? CFG.catalog.length
                : CFG.catalog.filter((p) => p.category === name).length;

            return `<button type="button" class="coll${name === state.collection ? ' on' : ''}"
                        data-collection="${escapeHtml(name)}"
                        aria-pressed="${name === state.collection}">
                        <span class="label">${escapeHtml(name)}</span>
                        <span class="count">${count}</span>
                    </button>`;
        }).join('');

        // Group heading tally: how many collections are on offer.
        const collTally = $('collTally');
        if (collTally) collTally.textContent = names.length;

        // Both filter chips carry the count of what they would show, using
        // the same test the filter itself applies, so the number on the chip
        // and the list behind it can never disagree.
        const inStock = CFG.catalog.filter((p) => totalStock(p) > 0).length;
        const lowStock = CFG.catalog.filter((p) => {
            const stock = totalStock(p);
            return stock > 0 && stock <= CFG.lowStockThreshold;
        }).length;

        const inStockEl = $('inStockCount');
        if (inStockEl) inStockEl.textContent = inStock;

        const lowStockEl = $('lowStockCount');
        if (lowStockEl) {
            lowStockEl.textContent = lowStock;
            // A count of nothing is not worth an alarm colour.
            lowStockEl.classList.toggle('is-alert', lowStock > 0);
        }

        document.querySelectorAll('[data-filter]').forEach((button) => {
            const on = state.filter === button.dataset.filter;
            button.classList.toggle('on', on);
            button.setAttribute('aria-pressed', String(on));
        });
    }

    function renderCatalog() {
        const list = visibleProducts();
        const rows = $('productRows');

        if (list.length === 0) {
            rows.innerHTML = '<p class="empty">Nothing matches that search.</p>';
            return;
        }

        rows.innerHTML = list.map((product) => {
            const chips = variantsOf(product).map((variant) => {
                const low = variant.stock > 0 && variant.stock <= CFG.lowStockThreshold;
                const inCart = state.cart.find((l) => l.variantId === variant.id)?.qty ?? 0;
                const remaining = variant.stock - inCart;

                // "8" under a size letter could be a price, a size or a
                // count. "8 left" can only be one of them.
                const label = remaining <= 0 ? 'None' : remaining + ' left';
                const stockClass = remaining <= 0 ? ' out' : (low ? ' low' : '');

                return `<button type="button" class="szb${stockClass}"
                            data-variant="${variant.id}"
                            ${remaining <= 0 ? 'disabled' : ''}
                            aria-label="Add ${escapeHtml(product.name)} size ${escapeHtml(variant.size)}, ${remaining} available">
                            <b>${escapeHtml(variant.abbr)}</b><small>${label}</small>
                        </button>`;
            }).join('');

            const stock = totalStock(product);
            const sku = variantsOf(product)[0]?.sku ?? '';

            // Everything left of the size chips opens the product; the chips
            // themselves still add in one tap. Two jobs, so the part that
            // opens the dialog is its own button rather than a click handler
            // on the row — that way it is reachable from the keyboard and
            // announces itself.
            return `<article class="prow">
                <button type="button" class="popen" data-product="${product.id}"
                        aria-label="View ${escapeHtml(product.name)}">
                    <span class="art"><img src="${escapeHtml(product.image)}" alt="" loading="lazy"></span>
                    <span class="idn">
                        <span class="nm">${escapeHtml(product.name)}</span>
                        <span class="meta">${escapeHtml(product.category)} &middot; ${stock} on hand &middot; ${escapeHtml(sku)}</span>
                    </span>
                    <span class="cost">${peso(product.price_from)}</span>
                </button>
                <div class="szrow">${chips}</div>
            </article>`;
        }).join('');
    }

    /* --------------------------------------------------------------- the cart */

    function findVariant(variantId) {
        for (const product of CFG.catalog) {
            const variant = variantsOf(product).find((v) => v.id === variantId);
            if (variant) return { product, variant };
        }
        return null;
    }

    /** How many of a variant are already committed to the open order. */
    function inCart(variantId) {
        return state.cart.find((l) => l.variantId === variantId)?.qty ?? 0;
    }

    function addToCart(variantId, quantity) {
        const found = findVariant(variantId);
        if (!found) return false;

        const wanted = Math.max(1, Number(quantity) || 1);
        const { product, variant } = found;
        const line = state.cart.find((l) => l.variantId === variantId);
        const already = line?.qty ?? 0;

        if (already + wanted > variant.stock) {
            toast('Only ' + variant.stock + ' of ' + product.name + ' (' + variant.size + ') on hand.', 'error');
            return false;
        }

        if (line) {
            line.qty += wanted;
        } else {
            if (variant.stock < 1) return false;
            state.cart.push({
                variantId: variant.id,
                productId: product.id,
                name: product.name,
                size: variant.size,
                abbr: variant.abbr,
                price: variant.price,
                qty: wanted,
                stock: variant.stock,
            });
        }

        renderOrder();
        renderCatalog();
        return true;
    }

    function bumpLine(index, delta) {
        const line = state.cart[index];
        if (!line) return;

        if (delta === 'remove') {
            state.cart.splice(index, 1);
        } else {
            const next = line.qty + delta;
            if (next > line.stock) {
                toast('Only ' + line.stock + ' on hand for that size.', 'error');
                return;
            }
            if (next < 1) {
                state.cart.splice(index, 1);
            } else {
                line.qty = next;
            }
        }

        renderOrder();
        renderCatalog();
    }

    function cartQuantity() {
        return state.cart.reduce((sum, l) => sum + l.qty, 0);
    }

    function cartSubtotal() {
        return state.cart.reduce((sum, l) => sum + (l.price * l.qty), 0);
    }

    /** Preview of the bundle discount; the server applies the real one. */
    function cartDiscount() {
        const qty = cartQuantity();
        if (!CFG.discount.threshold || qty < CFG.discount.threshold) return 0;
        return round2(cartSubtotal() * CFG.discount.rate);
    }

    function cartTotal() {
        return round2(cartSubtotal() - cartDiscount());
    }

    function round2(value) {
        return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
    }

    function renderOrder() {
        const lines = $('orderLines');

        if (state.cart.length === 0) {
            lines.innerHTML = '<p class="empty">Order is empty.<br>Click a product to see it in full,<br>or a size chip to add it in one tap.</p>';
        } else {
            lines.innerHTML = state.cart.map((line, index) => `
                <div class="ln">
                    <div class="top">
                        <span class="nm">${escapeHtml(line.name)}</span>
                        <span class="amt">${peso(line.price * line.qty)}</span>
                    </div>
                    <div class="bot">
                        <span class="sz">${escapeHtml(line.size)} &middot; ${peso(line.price)}</span>
                        <span class="controls">
                            <span class="stepper">
                                <button type="button" data-bump="${index}" data-delta="-1" aria-label="Decrease quantity">&minus;</button>
                                <span aria-live="polite">${line.qty}</span>
                                <button type="button" data-bump="${index}" data-delta="1"
                                    ${line.qty >= line.stock ? 'disabled' : ''} aria-label="Increase quantity">+</button>
                            </span>
                            <button type="button" class="del" data-bump="${index}" data-delta="remove">Remove</button>
                        </span>
                    </div>
                </div>`).join('');
        }

        const subtotal = round2(cartSubtotal());
        const discount = cartDiscount();
        const total = cartTotal();
        const quantity = cartQuantity();

        $('lineCount').textContent = quantity;
        $('subtotal').textContent = peso(subtotal);
        $('total').textContent = peso(total);

        const discountRow = $('discountRow');
        discountRow.hidden = discount <= 0;
        if (discount > 0) {
            const pct = Math.round(CFG.discount.rate * 100);
            $('discountLabel').textContent = 'Bundle discount ' + pct + '%';
            $('discount').textContent = '−' + peso(discount);
        }

        const hasItems = state.cart.length > 0;
        $('chargeBtn').disabled = !hasItems;
        $('holdBtn').disabled = !hasItems;
        $('voidBtn').disabled = !hasItems;

        const pill = $('orderPill');
        pill.textContent = hasItems ? 'Open' : 'Empty';
        pill.className = 'pill' + (hasItems ? ' open' : '');

        // Three things this line can be saying, and it should never be
        // ambiguous about which: the reference the finished sale was given,
        // the hold this basket came back from, or that neither exists yet.
        const ref = $('orderRef');
        ref.textContent = state.orderRef
            ?? (state.heldRef ? 'Resumed from ' + state.heldRef : 'Reference issued at payment');
        ref.classList.toggle('is-pending', state.orderRef === null);
    }

    /* ---------------------------------------------------------- quick view */

    /**
     * The product dialog.
     *
     * A row on the register used to be a strip of buttons and nothing more —
     * clicking the product itself did nothing, and the cashier could not see
     * what they were selling. This opens the same thing a customer sees on the
     * shop: the picture at size, the description, the sizes with what is left
     * of each, and one clear action. The size chips on the row stay exactly as
     * they were, so the one-tap path a busy counter relies on is untouched.
     */
    function productById(id) {
        return CFG.catalog.find((p) => p.id === id) ?? null;
    }

    function openQuickView(productId) {
        const product = productById(productId);
        if (!product) return;

        state.quickView = product;
        state.quickQty = 1;

        // Open on the first size that can still be sold, so the common case
        // is one click away and nothing has to be chosen twice.
        const available = variantsOf(product).find((v) => v.stock - inCart(v.id) > 0);
        state.quickSize = available?.id ?? null;

        renderQuickView();
        openModal('productModal');
    }

    function remainingFor(variant) {
        return variant.stock - inCart(variant.id);
    }

    function renderQuickView() {
        const product = state.quickView;
        if (!product) return;

        const variants = variantsOf(product);
        const chosen = variants.find((v) => v.id === state.quickSize) ?? null;
        const remaining = chosen ? remainingFor(chosen) : 0;
        const soldOut = variants.every((v) => remainingFor(v) <= 0);

        $('quickTitle').textContent = product.name;
        $('quickImage').src = product.image;
        $('quickImage').alt = product.name;
        $('quickCategory').textContent = product.category || 'Uncategorised';
        $('quickName').textContent = product.name;
        $('quickPrice').textContent = peso(chosen ? chosen.price : product.price_from);

        const description = $('quickDescription');
        description.textContent = product.description || '';
        description.hidden = !product.description;

        $('quickSizes').innerHTML = variants.map((variant) => {
            const left = remainingFor(variant);
            const on = variant.id === state.quickSize;

            return `<button type="button" class="qsize${on ? ' on' : ''}${left <= 0 ? ' out' : ''}"
                        data-quick-size="${variant.id}" ${left <= 0 ? 'disabled' : ''}
                        role="radio" aria-checked="${on}"
                        aria-label="Size ${escapeHtml(variant.size)}, ${left} available">
                        <b>${escapeHtml(variant.abbr)}</b>
                        <small>${left <= 0 ? 'None left' : left + ' left'}</small>
                    </button>`;
        }).join('');

        const sku = $('quickSku');
        sku.textContent = chosen?.sku ? 'SKU ' + chosen.sku : '';
        sku.hidden = !chosen?.sku;

        // The quantity can never be raised past what is actually on the shelf
        // once the open order is taken into account.
        state.quickQty = Math.min(Math.max(1, state.quickQty), Math.max(1, remaining));

        $('quickQty').textContent = state.quickQty;
        $('quickMinus').disabled = state.quickQty <= 1;
        $('quickPlus').disabled = state.quickQty >= remaining;

        const already = chosen ? inCart(chosen.id) : 0;
        const inOrder = $('quickInOrder');
        inOrder.textContent = already > 0
            ? already + ' × ' + chosen.abbr + ' already on this order'
            : '';
        inOrder.hidden = already < 1;

        const add = $('quickAdd');
        add.disabled = !chosen || remaining < 1;
        add.textContent = soldOut
            ? 'Sold out'
            : (remaining < 1 ? 'All stock on order' : 'Add to order');

        const line = $('quickLine');
        line.textContent = chosen && remaining > 0
            ? peso(chosen.price * state.quickQty)
            : '';
        line.hidden = !(chosen && remaining > 0);
    }

    function addQuickToOrder() {
        if (!state.quickSize) return;

        if (addToCart(state.quickSize, state.quickQty)) {
            const variant = findVariant(state.quickSize)?.variant;
            toast(state.quickQty + ' × ' + state.quickView.name
                + ' (' + variant.abbr + ') added.', 'ok');
            closeModal('productModal');
        }
    }

    /* --------------------------------------------------------------- modals */

    let lastFocused = null;

    function openModal(id) {
        const modal = $(id);
        lastFocused = document.activeElement;
        modal.hidden = false;
        modal.classList.add('on');

        // A dialog can nominate where the cursor should land — the payment
        // dialog puts it in the cash field so the cashier can type straight
        // into it rather than tabbing past the method buttons.
        const target = modal.querySelector('[data-autofocus]')
            ?? modal.querySelector('input:not([type=hidden]), button:not([data-close]), select, a[href]')
            ?? modal.querySelector('[data-close]');

        target?.focus();

        if (target?.tagName === 'INPUT' && target.type === 'text') {
            target.select();
        }
    }

    function closeModal(id) {
        const modal = $(id);
        modal.classList.remove('on');
        modal.hidden = true;
        lastFocused?.focus();
    }

    function openModals() {
        return Array.from(document.querySelectorAll('.veil.on'));
    }

    /* -------------------------------------------------------------- payment */

    function openPayment() {
        if (state.cart.length === 0) return;

        $('paymentError').hidden = true;
        $('amountDue').textContent = peso(cartTotal());
        setTender(0);
        openModal('paymentModal');
    }

    function pickMethod(button) {
        document.querySelectorAll('.pay').forEach((el) => {
            const on = el === button;
            el.classList.toggle('on', on);
            el.setAttribute('aria-checked', String(on));
        });

        state.method = button.dataset.method;
        const isCash = state.method === 'Cash';
        $('cashBox').hidden = !isCash;
        $('referenceBox').hidden = isCash;
    }

    function setTender(value) {
        $('tendered').value = Number(value).toFixed(2);
        recalcChange();
    }

    function recalcChange() {
        const tendered = parseFloat($('tendered').value) || 0;
        const change = round2(tendered - cartTotal());
        const box = $('changeBox');

        if (change >= 0) {
            $('changeDue').textContent = peso(change);
            box.classList.remove('is-short');
            box.firstElementChild.textContent = 'Change due';
        } else {
            $('changeDue').textContent = peso(Math.abs(change));
            box.classList.add('is-short');
            box.firstElementChild.textContent = 'Still short';
        }
    }

    async function confirmPayment() {
        if (state.busy || state.cart.length === 0) return;

        const tendered = parseFloat($('tendered').value) || 0;
        const errorBox = $('paymentError');

        if (state.method === 'Cash' && tendered + 0.001 < cartTotal()) {
            errorBox.textContent = 'Cash tendered is less than the amount due.';
            errorBox.hidden = false;
            $('tendered').focus();
            return;
        }

        errorBox.hidden = true;
        setBusy($('confirmPaymentBtn'), true);

        const customerId = $('customerSelect').value;
        const customerName = customerId
            ? $('customerSelect').selectedOptions[0].textContent.trim()
            : 'Walk-in Customer';

        try {
            const result = await api(CFG.routes.sales, {
                method: 'POST',
                body: JSON.stringify({
                    items: state.cart.map((l) => ({
                        product_variant_id: l.variantId,
                        quantity: l.qty,
                    })),
                    payment_method: state.method,
                    amount_tendered: state.method === 'Cash' ? tendered : null,
                    reference: state.method === 'Cash' ? null : ($('paymentReference').value.trim() || null),
                    customer_id: customerId || null,
                    customer_name: customerName,
                }),
            });

            state.lastOrder = result.order;
            state.orderRef = result.order.order_ref;

            updateShift(result.order.total_amount);
            closeModal('paymentModal');
            renderOrder();
            await showReceipt(result.order);
            await refreshCatalog();
            toast('Sale ' + result.order.order_ref + ' completed.', 'ok');
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.hidden = false;
        } finally {
            setBusy($('confirmPaymentBtn'), false);
        }
    }

    function setBusy(button, busy) {
        state.busy = busy;
        button.classList.toggle('is-busy', busy);
        button.disabled = busy;
    }

    function updateShift(amount) {
        CFG.shift.amount = round2(CFG.shift.amount + Number(amount));
        CFG.shift.count += 1;
        $('shiftAmount').textContent = peso(CFG.shift.amount);
        $('shiftCount').textContent = CFG.shift.count;
    }

    /* -------------------------------------------------------------- receipt */

    /**
     * The receipt is fetched, not rebuilt.
     *
     * The register used to assemble its own copy of the document in this
     * file, which meant the receipt on screen and the receipt off the printer
     * were two implementations of the same thing and drifted apart. It now
     * asks the server for the rendered document and shows exactly what will
     * print.
     */
    async function showReceipt(order) {
        const body = $('receiptBody');
        body.innerHTML = '<p class="empty">Preparing receipt&hellip;</p>';
        openModal('receiptModal');

        const url = CFG.routes.receiptShow.replace('__REF__', encodeURIComponent(order.order_ref));

        try {
            const response = await fetch(url + '?fragment=1', {
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error('Receipt unavailable.');

            body.innerHTML = await response.text();
        } catch (error) {
            // The sale itself is recorded either way, so this reports the
            // document rather than the transaction as the thing that failed.
            body.innerHTML = '<p class="empty">Sale ' + escapeHtml(order.order_ref)
                + ' is recorded.<br>The receipt could not be loaded &mdash; use Print to open it.</p>';
        }
    }

    function printReceipt() {
        if (!state.lastOrder) return;
        window.open(
            CFG.routes.receiptPrint.replace('__REF__', encodeURIComponent(state.lastOrder.order_ref)),
            '_blank',
            'noopener'
        );
    }

    function startNewSale() {
        state.cart = [];
        state.orderRef = null;
        state.heldRef = null;
        state.lastOrder = null;
        $('customerSelect').value = '';
        $('paymentReference').value = '';
        closeModal('receiptModal');
        renderOrder();
        renderCatalog();
        $('productSearch').focus();
    }

    /* ----------------------------------------------------------- held sales */

    async function holdSale() {
        if (state.cart.length === 0 || state.busy) return;

        const button = $('holdBtn');
        setBusy(button, true);

        const customerId = $('customerSelect').value;

        try {
            const result = await api(CFG.routes.heldStore, {
                method: 'POST',
                body: JSON.stringify({
                    items: state.cart.map((l) => ({
                        product_variant_id: l.variantId,
                        quantity: l.qty,
                    })),
                    customer_id: customerId || null,
                    customer_name: customerId
                        ? $('customerSelect').selectedOptions[0].textContent.trim()
                        : 'Walk-in Customer',
                }),
            });

            setHeldCount(result.held_count);
            state.cart = [];
            state.orderRef = null;
            state.heldRef = null;
            $('customerSelect').value = '';
            renderOrder();
            renderCatalog();
            toast('Held as ' + result.held.reference + '. Resume it from Held Sales.', 'ok');
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            setBusy(button, false);
        }
    }

    function setHeldCount(count) {
        state.heldCount = count;
        $('heldBadge').textContent = count;
    }

    async function openHeld() {
        openModal('heldModal');
        const body = $('heldBody');
        body.innerHTML = '<p class="empty">Loading&hellip;</p>';

        try {
            const { held } = await api(CFG.routes.heldIndex);

            if (held.length === 0) {
                body.innerHTML = '<p class="empty">No held sales.</p>';
                return;
            }

            body.innerHTML = held.map((sale) => `
                <div class="held-row">
                    <div class="idn">
                        <h4>${escapeHtml(sale.reference)}</h4>
                        <p>${escapeHtml(sale.customer_name)} &middot; ${sale.line_count} line(s)
                           &middot; ${sale.item_count} item(s) &middot; held ${escapeHtml(sale.held_at ?? '')}</p>
                    </div>
                    <div class="cost">${peso(sale.total)}</div>
                    <button type="button" class="mini" data-resume="${sale.id}">Resume</button>
                </div>`).join('');
        } catch (error) {
            body.innerHTML = '<p class="empty">' + escapeHtml(error.message) + '</p>';
        }
    }

    async function resumeHeld(id) {
        if (state.cart.length > 0 && !confirm('Resuming replaces the order currently on screen. Continue?')) {
            return;
        }

        try {
            const result = await api(CFG.routes.heldDestroy.replace('__ID__', id), { method: 'DELETE' });
            const resumed = result.resumed;

            state.cart = [];

            // Rebuild from the live catalog so prices and stock are current,
            // not whatever they were when the sale was parked.
            resumed.items.forEach((item) => {
                const found = findVariant(item.product_variant_id);
                if (!found) return;

                const available = Math.min(item.quantity, found.variant.stock);
                if (available < 1) return;

                state.cart.push({
                    variantId: found.variant.id,
                    productId: found.product.id,
                    name: found.product.name,
                    size: found.variant.size,
                    abbr: found.variant.abbr,
                    price: found.variant.price,
                    qty: available,
                    stock: found.variant.stock,
                });
            });

            state.orderRef = null;
            state.heldRef = resumed.reference;
            $('customerSelect').value = resumed.customer_id ?? '';

            setHeldCount(result.held_count);
            closeModal('heldModal');
            renderOrder();
            renderCatalog();

            const dropped = resumed.items.length - state.cart.length;
            toast(dropped > 0
                ? 'Sale resumed. ' + dropped + ' line(s) dropped — no longer in stock.'
                : 'Sale resumed.', dropped > 0 ? 'error' : 'ok');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    /* -------------------------------------------------------- order tracking */

    async function openTracking() {
        openModal('trackingModal');
        const body = $('trackingBody');
        body.innerHTML = '<tr><td colspan="6" class="empty">Loading orders&hellip;</td></tr>';

        try {
            const { orders } = await api(CFG.routes.recent);

            if (orders.length === 0) {
                body.innerHTML = '<tr><td colspan="6" class="empty">No orders yet.</td></tr>';
                return;
            }

            body.innerHTML = orders.map((order) => `
                <tr>
                    <td class="ref">${escapeHtml(order.order_ref)}</td>
                    <td>${escapeHtml(order.customer)}</td>
                    <td class="meta">${escapeHtml(order.channel)}<br>${escapeHtml(order.placed_at ?? '')}</td>
                    <td><b>${peso(order.total)}</b></td>
                    <td><span class="sbadge status-${escapeHtml(order.status)}">${escapeHtml(order.status_label)}</span></td>
                    <td><a class="mini" href="${escapeHtml(order.receipt_url)}" target="_blank" rel="noopener">Receipt</a></td>
                </tr>`).join('');
        } catch (error) {
            body.innerHTML = '<tr><td colspan="6" class="empty">' + escapeHtml(error.message) + '</td></tr>';
        }
    }

    /* ---------------------------------------------------------------- refresh */

    async function refreshCatalog() {
        try {
            const data = await api(CFG.routes.catalog);
            CFG.catalog = data.products;
            CFG.categories = data.categories;
            renderCollections();
            renderCatalog();
        } catch (_) {
            // A failed refresh is not worth interrupting the cashier; the next
            // sale re-validates stock server-side anyway.
        }
    }

    /* ----------------------------------------------------------------- events */

    document.addEventListener('click', (event) => {
        const target = event.target;

        const chip = target.closest('[data-variant]');
        if (chip && !chip.disabled) {
            addToCart(Number(chip.dataset.variant));
            return;
        }

        const quickSize = target.closest('[data-quick-size]');
        if (quickSize && !quickSize.disabled) {
            state.quickSize = Number(quickSize.dataset.quickSize);
            state.quickQty = 1;
            renderQuickView();
            return;
        }

        const quickStep = target.closest('[data-quick-step]');
        if (quickStep && !quickStep.disabled) {
            state.quickQty += Number(quickStep.dataset.quickStep);
            renderQuickView();
            return;
        }

        // The row itself opens the product. Checked after the size chips so a
        // chip still adds in one tap rather than opening the dialog.
        const row = target.closest('[data-product]');
        if (row) {
            openQuickView(Number(row.dataset.product));
            return;
        }

        const bump = target.closest('[data-bump]');
        if (bump && !bump.disabled) {
            const delta = bump.dataset.delta;
            bumpLine(Number(bump.dataset.bump), delta === 'remove' ? 'remove' : Number(delta));
            return;
        }

        const collection = target.closest('[data-collection]');
        if (collection) {
            state.collection = collection.dataset.collection;
            state.filter = null;
            renderCollections();
            renderCatalog();
            return;
        }

        const filter = target.closest('[data-filter]');
        if (filter) {
            state.filter = state.filter === filter.dataset.filter ? null : filter.dataset.filter;
            renderCollections();
            renderCatalog();
            return;
        }

        const method = target.closest('.pay');
        if (method) {
            pickMethod(method);
            return;
        }

        const tender = target.closest('[data-tender]');
        if (tender) {
            const value = tender.dataset.tender;
            setTender(value === 'exact' ? cartTotal() : Number(value));
            return;
        }

        const resume = target.closest('[data-resume]');
        if (resume) {
            resumeHeld(Number(resume.dataset.resume));
            return;
        }

        if (target.closest('[data-open-tracking]')) { openTracking(); return; }
        if (target.closest('[data-open-held]')) { openHeld(); return; }

        const close = target.closest('[data-close]');
        if (close) {
            closeModal(close.closest('.veil').id);
            return;
        }

        // Clicking the backdrop dismisses the dialog.
        if (target.classList.contains('veil')) {
            closeModal(target.id);
        }
    });

    $('productSearch').addEventListener('input', (event) => {
        state.search = event.target.value;
        renderCatalog();
    });

    $('quickAdd').addEventListener('click', addQuickToOrder);

    $('chargeBtn').addEventListener('click', openPayment);
    $('confirmPaymentBtn').addEventListener('click', confirmPayment);
    $('holdBtn').addEventListener('click', holdSale);
    $('newSaleBtn').addEventListener('click', startNewSale);
    $('printReceiptBtn').addEventListener('click', printReceipt);

    $('voidBtn').addEventListener('click', () => {
        if (state.cart.length === 0) return;
        if (!confirm('Void this order? The lines on screen will be cleared.')) return;
        state.cart = [];
        state.orderRef = null;
        state.heldRef = null;
        renderOrder();
        renderCatalog();
        toast('Order voided.');
    });

    $('tendered').addEventListener('input', recalcChange);

    $('tendered').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            confirmPayment();
        }
    });

    document.addEventListener('keydown', (event) => {
        // Escape closes the topmost dialog.
        if (event.key === 'Escape') {
            const open = openModals();
            if (open.length > 0) {
                closeModal(open[open.length - 1].id);
            }
            return;
        }

        // F2 charges the order — the one shortcut a busy counter benefits from.
        if (event.key === 'F2' && !state.busy) {
            event.preventDefault();
            openModals().length === 0 ? openPayment() : confirmPayment();
            return;
        }

        // Typing anywhere that is not a field jumps to the search box, so a
        // barcode scanner works without the cashier clicking first.
        const tag = event.target.tagName;
        if (tag !== 'INPUT' && tag !== 'SELECT' && tag !== 'TEXTAREA'
            && openModals().length === 0
            && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
            $('productSearch').focus();
        }
    });

    /* -------------------------------------------------------------- start-up */

    function tickClock() {
        $('clock').textContent = new Date().toLocaleTimeString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    tickClock();
    setInterval(tickClock, 30000);

    renderCollections();
    renderCatalog();
    renderOrder();
    $('productSearch').focus();
})();
