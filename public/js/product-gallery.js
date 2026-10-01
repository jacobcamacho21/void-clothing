(function () {
    'use strict';

    document.querySelectorAll('[data-product-gallery]').forEach(function (gallery) {
        const images = Array.from(gallery.querySelectorAll('.product-image'));
        const stage = gallery.querySelector('.product-gallery-stage');
        const count = gallery.querySelector('.product-gallery-count');
        let current = 0;

        function showImage(index) {
            current = (index + images.length) % images.length;

            images.forEach(function (image, imageIndex) {
                image.hidden = imageIndex !== current;
            });

            stage.classList.remove('is-changing');
            void stage.offsetWidth;
            stage.classList.add('is-changing');

            if (count) count.textContent = (current + 1) + ' / ' + images.length;
        }

        gallery.querySelectorAll('[data-gallery-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                showImage(current + Number(button.dataset.galleryStep));
            });
        });

        gallery.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft') showImage(current - 1);
            if (event.key === 'ArrowRight') showImage(current + 1);
        });

        gallery.tabIndex = 0;
    });
})();
