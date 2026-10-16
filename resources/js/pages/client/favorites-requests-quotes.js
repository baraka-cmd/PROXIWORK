document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target.closest('[data-confirm-action]') : null;
    if (!target) return;

    const message = target.getAttribute('data-confirm-action');
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    const button = event.submitter;
    if (!(button instanceof HTMLButtonElement)) return;

    if (button.dataset.confirmSubmit && !window.confirm(button.dataset.confirmSubmit)) {
        event.preventDefault();
        return;
    }

    if (button.disabled) {
        event.preventDefault();
        return;
    }

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    const label = button.dataset.loadingLabel || 'Traitement…';
    const spinner = document.createElement('i');
    spinner.className = 'fa-solid fa-spinner fa-spin';
    spinner.setAttribute('aria-hidden', 'true');
    button.replaceChildren(spinner, document.createTextNode(' ' + label));
});

const requestAttachmentInput = document.querySelector('[data-request-attachment-input]');
const requestAttachmentPreview = document.querySelector('[data-attachment-preview]');

if (requestAttachmentInput instanceof HTMLInputElement && requestAttachmentPreview instanceof HTMLElement) {
    let previewUrl = null;
    const allowedExtensions = new Set(['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx']);
    const maxFileSize = 10 * 1024 * 1024;

    requestAttachmentInput.addEventListener('change', () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        requestAttachmentPreview.replaceChildren();
        requestAttachmentPreview.classList.remove('is-error');
        requestAttachmentPreview.hidden = true;
        requestAttachmentInput.setCustomValidity('');
        requestAttachmentInput.removeAttribute('aria-invalid');

        const file = requestAttachmentInput.files?.[0];
        if (!file) return;

        const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
        let error = '';

        if (!allowedExtensions.has(extension)) {
            error = 'Ce format de fichier n’est pas pris en charge. Choisissez un PDF, JPG, PNG, WEBP, DOC ou DOCX.';
        } else if (file.size > maxFileSize) {
            error = 'Ce fichier dépasse la limite de 10 Mo. Choisissez un fichier plus léger.';
        } else if (file.size === 0) {
            error = 'Ce fichier est vide. Choisissez un autre document.';
        }

        const icon = document.createElement('span');
        icon.className = 'request-attachment-selection__icon';
        icon.setAttribute('aria-hidden', 'true');

        const imageTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
        if (!error && imageTypes.has(file.type) && ['jpg', 'jpeg', 'png', 'webp'].includes(extension)) {
            const image = document.createElement('img');
            image.className = 'request-attachment-selection__preview';
            image.alt = '';
            previewUrl = URL.createObjectURL(file);
            image.src = previewUrl;
            requestAttachmentPreview.append(image);
        } else {
            const iconElement = document.createElement('i');
            iconElement.className = extension === 'pdf' ? 'fa-regular fa-file-pdf' : 'fa-regular fa-file-lines';
            icon.append(iconElement);
            requestAttachmentPreview.append(icon);
        }

        const details = document.createElement('div');
        details.className = 'request-attachment-selection__details';
        const name = document.createElement('strong');
        name.textContent = file.name;
        const metadata = document.createElement('small');
        metadata.textContent = (file.size / 1024 / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 2 }) + ' Mo · ' + extension.toUpperCase();
        details.append(name, metadata);
        requestAttachmentPreview.append(details);

        if (error) {
            const message = document.createElement('span');
            message.className = 'request-attachment-selection__message';
            message.textContent = error;
            requestAttachmentPreview.append(message);
            requestAttachmentPreview.classList.add('is-error');
            requestAttachmentInput.setCustomValidity(error);
            requestAttachmentInput.setAttribute('aria-invalid', 'true');
        }

        requestAttachmentPreview.hidden = false;
    });

    window.addEventListener('pagehide', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const summary = document.querySelector('[data-error-summary]');
    if (summary instanceof HTMLElement) {
        summary.focus({ preventScroll: true });
        summary.scrollIntoView({
            block: 'start',
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        });
    }
});
