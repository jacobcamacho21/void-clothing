document.addEventListener('DOMContentLoaded', function () {
    function closeModal(modalElement, hideDisplay) {
        if (!modalElement) {
            return;
        }

        modalElement.classList.add('is-closing');
        window.setTimeout(function () {
            modalElement.style.display = hideDisplay || 'none';
            modalElement.classList.remove('is-closing');
        }, 180);
    }

    function openModal(modalElement, showDisplay) {
        if (!modalElement) {
            return;
        }

        modalElement.classList.remove('is-closing');
        modalElement.style.display = showDisplay || 'block';
        void modalElement.offsetWidth;
    }

    window.openAnimatedModal = openModal;
    window.closeAnimatedModal = closeModal;
});