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
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> ' + label;
});
