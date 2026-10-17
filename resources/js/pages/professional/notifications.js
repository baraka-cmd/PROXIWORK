const notificationForms = new Set([
    ...document.querySelectorAll('.notification-list form, [data-notification-preferences]'),
]);

document.querySelectorAll('[data-notification-read-all]').forEach((control) => {
    const form = control.closest('form');

    if (form) {
        notificationForms.add(form);
    }
});

notificationForms.forEach((form) => {
    form.addEventListener('submit', () => {
        if (form.dataset.submitting === 'true') {
            return;
        }

        form.dataset.submitting = 'true';
        const button = form.querySelector('button[type="submit"], button:not([type])');

        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.dataset.originalLabel = button.textContent.trim();
        }
    });
});
