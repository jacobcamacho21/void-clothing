(function () {
    'use strict';

    const endpoint = document.body.dataset.updateVersionEndpoint;
    if (!endpoint) return;

    let knownVersion = null;
    let updatePending = false;
    let reloadScheduled = false;

    function isEditing() {
        const active = document.activeElement;
        return active && (
            active.matches('input, textarea, select, [contenteditable="true"]')
            || active.closest('form')?.matches('[data-submitting]')
        );
    }

    function reloadWhenSafe() {
        if (!updatePending || reloadScheduled || isEditing()) return;

        reloadScheduled = true;
        window.location.reload();
    }

    function showUpdateNotice() {
        if (document.getElementById('liveUpdateNotice')) return;

        const notice = document.createElement('div');
        notice.id = 'liveUpdateNotice';
        notice.className = 'live-update-notice';
        notice.setAttribute('role', 'status');
        notice.innerHTML = '<span>New updates are available.</span>'
            + '<button type="button">Refresh</button>';

        notice.querySelector('button').addEventListener('click', function () {
            reloadScheduled = true;
            window.location.reload();
        });

        document.body.appendChild(notice);
    }

    function handleNewVersion(version) {
        if (knownVersion === null) {
            knownVersion = version;
            return;
        }

        if (version === knownVersion) return;

        knownVersion = version;
        updatePending = true;

        if (isEditing()) {
            showUpdateNotice();
            return;
        }

        reloadWhenSafe();
    }

    async function checkForUpdates() {
        try {
            const response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const payload = await response.json();
            if (payload.version) handleNewVersion(payload.version);
        } catch (_) {
            // A failed background check must never interrupt the current page.
        }
    }

    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented) return;
        event.target.setAttribute('data-submitting', 'true');
    });

    document.addEventListener('focusout', function () {
        window.setTimeout(reloadWhenSafe, 0);
    });

    checkForUpdates();
    window.setInterval(checkForUpdates, 15000);
})();
