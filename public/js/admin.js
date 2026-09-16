/**
 * Back-office helpers: modal open/close, prefilling edit forms from the row
 * that was clicked, and confirm-before-destroy.
 *
 * Data comes from `data-*` attributes on the trigger, so a page only has to
 * render the attributes — no per-page script.
 */
(function () {
    'use strict';

    function openModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-closing');
        modal.style.display = 'block';
        modal.querySelector('input:not([type=hidden]), select, textarea')?.focus();
    }

    function closeModal(modal) {
        if (!modal) return;

        if (window.closeAnimatedModal) {
            window.closeAnimatedModal(modal);
            return;
        }

        modal.style.display = 'none';
    }

    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-modal-open]');
        if (opener) {
            event.preventDefault();
            const modal = document.getElementById(opener.dataset.modalOpen);

            // Index the modal's fields by lower-cased name. The browser
            // lower-cases attribute names, so `data-field-postalCode` on the
            // trigger arrives as `fieldPostalcode` and would never match a
            // `data-field="postalCode"` target compared case-sensitively.
            const targets = new Map(
                Array.from(modal?.querySelectorAll('[data-field]') ?? [])
                    .map((el) => [el.dataset.field.toLowerCase(), el])
            );

            // Copy every data-field-* value on the trigger into its control.
            Object.entries(opener.dataset).forEach(function ([key, value]) {
                if (!key.startsWith('field')) return;

                const field = targets.get(key.slice(5).toLowerCase());
                if (!field) return;

                if (field.type === 'checkbox') {
                    field.checked = value === '1' || value === 'true';
                } else if ('value' in field && field.tagName !== 'P' && field.tagName !== 'SPAN') {
                    field.value = value;
                } else {
                    // Read-only labels inside the dialog, e.g. "which record
                    // am I editing".
                    field.textContent = value;
                }
            });

            // Point the form at the record being edited.
            if (opener.dataset.action && modal) {
                const form = modal.querySelector('form');
                if (form) form.action = opener.dataset.action;
            }

            openModal(modal);
            return;
        }

        const closer = event.target.closest('[data-modal-close]');
        if (closer) {
            event.preventDefault();
            closeModal(closer.closest('.modal'));
            return;
        }

        // Clicking the backdrop dismisses the dialog.
        if (event.target.classList.contains('modal')) {
            closeModal(event.target);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('.modal').forEach(function (modal) {
            if (modal.style.display === 'block') closeModal(modal);
        });
    });

    document.addEventListener('submit', function (event) {
        const message = event.target.dataset.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Search boxes submit their form on Enter and after a short pause, so the
    // list filters without a separate button.
    document.querySelectorAll('[data-search-form] input[type="search"]').forEach(function (input) {
        let timer = null;

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                input.form.requestSubmit();
            }, 450);
        });
    });
})();
