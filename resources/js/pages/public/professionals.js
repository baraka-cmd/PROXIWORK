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

// Public portfolio images are served through a visibility-checked endpoint.
// If an image becomes unavailable, remove only that item and keep the profile usable.
document.querySelectorAll('[data-professional-realizations] img').forEach((image) => {
    const figure = image.closest('[data-professional-realization]');
    const section = image.closest('[data-professional-realizations]');

    if (!figure || !section) {
        return;
    }

    const removeUnavailableRealization = () => {
        figure.remove();

        const remaining = section.querySelectorAll('[data-professional-realization]').length;
        const count = section.querySelector('[data-professional-realization-count]');

        if (count) {
            count.textContent = String(remaining);
        }

        if (remaining === 0) {
            section.remove();
        }
    };

    image.addEventListener('error', removeUnavailableRealization, { once: true });

    if (image.complete && image.naturalWidth === 0) {
        removeUnavailableRealization();
    }
});
