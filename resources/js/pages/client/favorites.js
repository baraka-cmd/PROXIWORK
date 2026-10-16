document.querySelectorAll('[data-favorite-photo], [data-favorite-cover]').forEach((image) => {
    const media = image.closest('.favorite-card__media');

    if (!media) {
        return;
    }

    const showImage = () => {
        if (image.naturalWidth > 0) {
            media.classList.add('has-photo');
        }
    };

    const showFallback = () => {
        media.classList.add('has-failed-image');
        media.classList.remove('has-photo');
    };

    image.addEventListener('load', showImage, { once: true });
    image.addEventListener('error', showFallback, { once: true });

    if (image.complete) {
        if (image.naturalWidth > 0) {
            showImage();
        } else {
            showFallback();
        }
    }
});
