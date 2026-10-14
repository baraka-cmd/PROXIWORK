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


document.querySelectorAll('[data-review-response-form], .review-report__form').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');

        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.dataset.originalLabel = button.textContent.trim();
            button.textContent = 'Envoi en cours…';
        }
    });
});
