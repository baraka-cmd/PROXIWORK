document.querySelectorAll('[data-message-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');

        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }
    });
});

document.querySelectorAll('[data-message-thread]').forEach((thread) => {
    thread.scrollTop = thread.scrollHeight;
});
