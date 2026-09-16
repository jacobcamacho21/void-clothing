document.addEventListener('DOMContentLoaded', function () {
    function isModifiedClick(event) {
        return event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
    }

    function shouldIgnoreLink(link) {
        if (!link) {
            return true;
        }

        const href = link.getAttribute('href') || '';
        return href === '' || href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank' || link.hasAttribute('download');
    }

    function shouldHandleUrl(url) {
        try {
            const targetUrl = new URL(url, window.location.href);
            return targetUrl.origin === window.location.origin && targetUrl.href !== window.location.href;
        } catch (error) {
            return false;
        }
    }

    function leavePage(nextUrl) {
        if (!shouldHandleUrl(nextUrl)) {
            window.location.href = nextUrl;
            return;
        }

        document.body.classList.add('page-is-leaving');
        window.setTimeout(function () {
            window.location.href = nextUrl;
        }, 220);
    }

    function handleLinkClick(event) {
        if (isModifiedClick(event)) {
            return;
        }

        const link = event.target.closest('a');
        if (!link || shouldIgnoreLink(link)) {
            return;
        }

        const href = link.getAttribute('href');
        if (!href || !shouldHandleUrl(href)) {
            return;
        }

        event.preventDefault();
        leavePage(href);
    }

    function handleFormSubmit(event) {
        const form = event.target;
        if (!form || form.hasAttribute('data-no-transition') || form.target === '_blank') {
            return;
        }

        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'get' && method !== 'post') {
            return;
        }

        const action = form.getAttribute('action') || window.location.href;
        if (!shouldHandleUrl(action)) {
            return;
        }

        event.preventDefault();
        document.body.classList.add('page-is-leaving');
        window.setTimeout(function () {
            form.submit();
        }, 220);
    }

    document.addEventListener('click', handleLinkClick, true);
    document.addEventListener('submit', handleFormSubmit, true);

    window.navigateWithTransition = function (url) {
        leavePage(url);
    };

    window.addEventListener('pageshow', function (event) {
        document.body.classList.remove('page-is-leaving');
        if (event.persisted) {
            document.body.classList.remove('page-is-leaving');
        }
    });
});