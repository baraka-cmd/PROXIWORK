const profileForms = [...document.querySelectorAll('.profile-edit-section form')];

const fileInput = document.querySelector('#avatar');
if (fileInput) {
    const form = fileInput.closest('form');
    const status = document.createElement('p');
    status.className = 'profile-photo-preview';
    status.hidden = true;
    status.setAttribute('aria-live', 'polite');
    const previewImage = document.createElement('img');
    previewImage.alt = 'Aperçu local de la nouvelle photo';
    previewImage.hidden = true;
    const previewText = document.createElement('p');
    status.append(previewImage, previewText);
    fileInput.insertAdjacentElement('afterend', status);

    let previewUrl = null;
    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;

        if (!file) {
            status.hidden = true;
            previewImage.removeAttribute('src');
            return;
        }

        status.hidden = false;
        const sizeMb = (file.size / (1024 * 1024)).toLocaleString(document.documentElement.lang || 'fr-FR', { maximumFractionDigits: 2 });
        previewText.textContent = `${file.name} · ${sizeMb} Mo. L’image sera envoyée uniquement après validation du formulaire.`;

        if (file.type.startsWith('image/')) {
            previewUrl = URL.createObjectURL(file);
            previewImage.src = previewUrl;
            previewImage.hidden = false;
        } else {
            previewImage.hidden = true;
            previewImage.removeAttribute('src');
        }
    });

    form?.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }
    });
    window.addEventListener('pagehide', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
    });
}

document.querySelectorAll('.profile-edit-section .form-error').forEach((error, index) => {
    const container = error.closest('.form-field');
    const field = container?.querySelector('input:not([type="hidden"]):not([type="file"]), select, textarea');
    if (!field) return;
    if (!error.id) error.id = `profile-field-error-${index + 1}`;
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
});

document.querySelectorAll('.profile-edit-section textarea[maxlength], .profile-edit-section input[maxlength]').forEach((field) => {
    if (!field.id || field.type === 'file' || field.type === 'hidden') return;
    const helpId = field.getAttribute('aria-describedby')?.split(/\s+/).find((id) => document.getElementById(id));
    const counter = document.createElement('span');
    counter.className = 'profile-char-count';
    counter.setAttribute('aria-live', 'polite');
    counter.id = `${field.id}-character-count`;
    field.insertAdjacentElement('afterend', counter);

    const updateCount = () => {
        const max = Number(field.maxLength);
        const length = [...field.value].length;
        counter.textContent = `${length.toLocaleString()} / ${max.toLocaleString()} caractères`;
        counter.classList.toggle('is-near-limit', max > 0 && length / max >= 0.9);
    };
    updateCount();
    field.addEventListener('input', updateCount);

    if (helpId) {
        const help = document.getElementById(helpId);
        if (help && !help.textContent.includes(counter.id)) {
            field.setAttribute('aria-describedby', `${field.getAttribute('aria-describedby')} ${counter.id}`);
        }
    } else {
        field.setAttribute('aria-describedby', counter.id);
    }
});

const serializeForm = (form) => [...form.elements]
    .filter((element) => element.name && !element.disabled && element.type !== 'submit' && element.type !== 'button' && element.type !== 'file')
    .map((element) => {
        if (element.type === 'checkbox' || element.type === 'radio') return `${element.name}:${element.checked ? element.value : ''}`;
        if (element instanceof HTMLSelectElement && element.multiple) return `${element.name}:${[...element.selectedOptions].map((option) => option.value).join(',')}`;
        return `${element.name}:${element.value}`;
    })
    .join('|');

const formSnapshots = new Map(profileForms.map((form) => [form, serializeForm(form)]));
profileForms.forEach((form) => {
    form.addEventListener('submit', () => form.dataset.submitting = 'true');
});

window.addEventListener('beforeunload', (event) => {
    const hasUnsavedChanges = profileForms.some((form) => form.dataset.submitting !== 'true' && serializeForm(form) !== formSnapshots.get(form));
    if (!hasUnsavedChanges) return;
    event.preventDefault();
    event.returnValue = '';
});
