'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const errorSummary = document.querySelector('[data-error-summary]');

    if (errorSummary instanceof HTMLElement) {
        window.requestAnimationFrame(() => errorSummary.focus());
    }

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const inputId = toggle.getAttribute('aria-controls');
            const input = inputId ? document.getElementById(inputId) : null;

            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            toggle.setAttribute('aria-pressed', String(!isVisible));
            toggle.textContent = isVisible ? 'Afficher' : 'Masquer';

            const currentLabel = toggle.getAttribute('aria-label') || 'Afficher le mot de passe';
            toggle.setAttribute(
                'aria-label',
                isVisible ? currentLabel.replace(/^Masquer/, 'Afficher') : currentLabel.replace(/^Afficher/, 'Masquer'),
            );
        });
    });

    const newPassword = document.querySelector('#password');
    const passwordConfirmation = document.querySelector('[data-password-match]');
    const passwordMatchError = document.querySelector('#password-match-error');

    const syncPasswordConfirmation = () => {
        if (!(newPassword instanceof HTMLInputElement)
            || !(passwordConfirmation instanceof HTMLInputElement)) {
            return;
        }

        const mismatch = passwordConfirmation.value.length > 0
            && newPassword.value !== passwordConfirmation.value;

        passwordConfirmation.setCustomValidity(mismatch ? 'Les deux mots de passe ne correspondent pas.' : '');
        passwordConfirmation.setAttribute('aria-invalid', String(mismatch));

        if (passwordMatchError instanceof HTMLElement) {
            passwordMatchError.textContent = mismatch ? 'Saisissez le même nouveau mot de passe dans les deux champs.' : '';
            passwordMatchError.hidden = !mismatch;
        }
    };

    if (newPassword instanceof HTMLInputElement && passwordConfirmation instanceof HTMLInputElement) {
        newPassword.addEventListener('input', syncPasswordConfirmation);
        passwordConfirmation.addEventListener('input', syncPasswordConfirmation);
        syncPasswordConfirmation();
    }

    document.querySelectorAll('.profile-form[data-submit-label]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            if (form.dataset.avatarInvalid === 'true') {
                event.preventDefault();
                const avatarInput = form.querySelector('#avatar');
                if (avatarInput instanceof HTMLInputElement) {
                    avatarInput.focus();
                }
                return;
            }

            const submitter = event.submitter;
            if (!(submitter instanceof HTMLButtonElement)) {
                return;
            }

            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            submitter.disabled = true;
            submitter.setAttribute('aria-disabled', 'true');
            submitter.textContent = form.dataset.submitLabel;
        });
    });

    const avatarInput = document.querySelector('#avatar');
    const previewContainer = document.querySelector('[data-avatar-preview]');
    const preview = previewContainer?.querySelector('img');
    const fileName = previewContainer?.querySelector('[data-avatar-filename]');
    const feedback = document.querySelector('#avatar-client-error');
    const photoForm = avatarInput?.closest('form');
    let previewUrl = null;
    let selectionVersion = 0;

    const clearPreview = () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        if (preview instanceof HTMLImageElement) {
            preview.removeAttribute('src');
        }

        if (previewContainer instanceof HTMLElement) {
            previewContainer.hidden = true;
        }

        if (fileName instanceof HTMLElement) {
            fileName.textContent = '';
        }
    };

    const showFileError = (message) => {
        if (feedback instanceof HTMLElement) {
            feedback.textContent = message;
            feedback.hidden = false;
        }

        if (avatarInput instanceof HTMLInputElement) {
            avatarInput.setAttribute('aria-invalid', 'true');
        }

        if (photoForm instanceof HTMLFormElement) {
            photoForm.dataset.avatarInvalid = 'true';
        }
        clearPreview();
    };

    const clearFileError = () => {
        if (feedback instanceof HTMLElement) {
            feedback.textContent = '';
            feedback.hidden = true;
        }

        if (avatarInput instanceof HTMLInputElement) {
            avatarInput.removeAttribute('aria-invalid');
        }

        if (photoForm instanceof HTMLFormElement) {
            delete photoForm.dataset.avatarInvalid;
        }
    };

    if (avatarInput instanceof HTMLInputElement && preview instanceof HTMLImageElement) {
        avatarInput.addEventListener('change', () => {
            selectionVersion += 1;
            const version = selectionVersion;
            clearPreview();
            clearFileError();

            const file = avatarInput.files?.[0];
            if (!file) {
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                avatarInput.value = '';
                showFileError('Choisissez une image JPG, PNG ou WebP.');
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                avatarInput.value = '';
                showFileError('La photo dépasse la limite de 2 Mo. Choisissez un fichier plus léger.');
                return;
            }

            previewUrl = URL.createObjectURL(file);
            const currentUrl = previewUrl;
            const dimensionCheck = new Image();

            dimensionCheck.onload = () => {
                if (version !== selectionVersion) {
                    return;
                }

                if (dimensionCheck.naturalWidth > 2000 || dimensionCheck.naturalHeight > 2000) {
                    avatarInput.value = '';
                    showFileError('Les dimensions maximales sont de 2 000 × 2 000 pixels. Choisissez une image plus petite.');
                    return;
                }

                preview.src = currentUrl;
                preview.alt = 'Aperçu de la nouvelle photo de profil';
                if (previewContainer instanceof HTMLElement) {
                    previewContainer.hidden = false;
                }
                if (fileName instanceof HTMLElement) {
                    fileName.textContent = file.name;
                }
            };

            dimensionCheck.onerror = () => {
                if (version !== selectionVersion) {
                    return;
                }
                avatarInput.value = '';
                showFileError('Impossible de lire cette image. Essayez un autre fichier JPG, PNG ou WebP.');
            };

            dimensionCheck.src = currentUrl;
        });

        window.addEventListener('pagehide', () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }
        }, { once: true });
    }
});
