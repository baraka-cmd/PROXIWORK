document.querySelectorAll('[data-service-cover]').forEach((image) => {
    const media = image.closest('.service-card__media');

    if (!media) {
        return;
    }

    const showFallback = () => media.classList.add('has-failed-image');

    image.addEventListener('error', showFallback, { once: true });

    if (image.complete && image.naturalWidth === 0) {
        showFallback();
    }
});
