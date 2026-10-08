document.querySelectorAll('[data-notification-read-all]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('button');

        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }
    });
});
