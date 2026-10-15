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

            if (helpText) {
                helpText.textContent = 'L’image est contrôlée côté serveur et stockée dans un espace non public.';
                helpText.removeAttribute('role');
                helpText.removeAttribute('aria-live');
            }

            return;
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!allowedTypes.includes(file.type) || file.size > 2 * 1024 * 1024) {
            preview.hidden = true;
            preview.removeAttribute('src');
            avatarInput.value = '';

            if (helpText) {
                helpText.textContent = !allowedTypes.includes(file.type)
                    ? 'Format non pris en charge. Choisissez une image JPG, PNG ou WebP.'
                    : 'Image trop volumineuse. La taille maximale autorisée est de 2 Mo.';
                helpText.setAttribute('role', 'alert');
                helpText.setAttribute('aria-live', 'assertive');
            }

            return;
        }

        if (helpText) {
            helpText.textContent = 'Aperçu local uniquement. Le serveur vérifiera à nouveau le format, la taille et les dimensions.';
            helpText.setAttribute('role', 'status');
            helpText.setAttribute('aria-live', 'polite');
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
