'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const submittingForms = new WeakSet();

    document.querySelectorAll('.profile-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            // The shared confirmation dialog must be allowed to cancel first.
            if (event.defaultPrevented) {
                return;
            }

            if (submittingForms.has(form)) {
                event.preventDefault();
                return;
            }

            submittingForms.add(form);

            const submitter = event.submitter;

            if (submitter instanceof HTMLButtonElement) {
                submitter.disabled = true;
                submitter.setAttribute('aria-busy', 'true');
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
        preview.onload = () => {
            if (preview.naturalWidth > 2000 || preview.naturalHeight > 2000) {
                preview.hidden = true;
                preview.removeAttribute('src');
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
                avatarInput.value = '';

                if (helpText) {
                    helpText.textContent = 'Les dimensions maximales sont de 2 000 × 2 000 pixels. Choisissez une image plus petite.';
                    helpText.setAttribute('role', 'alert');
                    helpText.setAttribute('aria-live', 'assertive');
                }

                return;
            }

            preview.hidden = false;
        };
        preview.onerror = () => {
            preview.hidden = true;
            preview.removeAttribute('src');

            if (helpText) {
                helpText.textContent = 'Impossible de prévisualiser ce fichier. Choisissez une image valide.';
                helpText.setAttribute('role', 'alert');
                helpText.setAttribute('aria-live', 'assertive');
            }

            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }
        };
        preview.src = previewUrl;
    });

    window.addEventListener('pagehide', () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
    }, { once: true });
});
