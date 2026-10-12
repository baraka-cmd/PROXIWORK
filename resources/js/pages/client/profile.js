'use strict';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirm;

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('.profile-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return;
            }

            const submitter = event.submitter;

            if (submitter instanceof HTMLButtonElement) {
                submitter.disabled = true;
                submitter.setAttribute('aria-disabled', 'true');
                submitter.dataset.originalLabel = submitter.textContent.trim();
                submitter.textContent = 'Enregistrement…';
            }
        });
    });

    const avatarInput = document.querySelector('#avatar');

    if (!(avatarInput instanceof HTMLInputElement)) {
        return;
    }

    const helpText = document.querySelector('#avatar-help');
    const preview = document.createElement('img');
    preview.className = 'profile-photo-preview';
    preview.alt = 'Aperçu de la nouvelle photo de profil';
    preview.hidden = true;

    if (helpText?.parentElement) {
        helpText.insertAdjacentElement('afterend', preview);
    }

    let previewUrl = null;

    avatarInput.addEventListener('change', () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        const file = avatarInput.files?.[0];

        if (!file) {
            preview.hidden = true;
            preview.removeAttribute('src');
            return;
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!allowedTypes.includes(file.type) || file.size > 2 * 1024 * 1024) {
            preview.hidden = true;
            preview.removeAttribute('src');
            return;
        }

        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.hidden = false;
    });

    window.addEventListener('pagehide', () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
    }, { once: true });
});
