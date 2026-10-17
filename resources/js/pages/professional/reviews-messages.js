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

document.querySelectorAll('[data-review-response-input]').forEach((textarea) => {
    const counterId = textarea.getAttribute('aria-describedby')?.split(' ').find((id) => id.startsWith('response-count-'));
    const counter = counterId ? document.getElementById(counterId) : null;

    if (!counter) {
        return;
    }

    const updateCount = () => {
        counter.textContent = `${textarea.value.length.toLocaleString('fr-FR')} / 2 000 caractères`;
    };

    textarea.addEventListener('input', updateCount);
    updateCount();
});
