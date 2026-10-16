import './search.js';

document.querySelectorAll('[data-professional-photo]').forEach((image) => {
    const portrait = image.closest('[data-professional-portrait]');

    if (!portrait) {
        return;
    }

    const showPhoto = () => {
        if (image.naturalWidth > 0) {
            portrait.classList.add('has-photo');
        }
    };

    const useInitials = () => {
        portrait.classList.remove('has-photo');
        image.hidden = true;
    };

    image.addEventListener('load', showPhoto, { once: true });
    image.addEventListener('error', useInitials, { once: true });

    if (image.complete) {
        if (image.naturalWidth > 0) {
            showPhoto();
        } else {
            useInitials();
        }
    }
});
