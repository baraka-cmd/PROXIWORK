document.querySelectorAll('[data-notification-read-all], .notification-list form, [data-notification-preferences]').forEach((form) => {
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
