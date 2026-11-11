document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const button = event.submitter;
    if (!(button instanceof HTMLButtonElement)) return;
    if (button.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }
    button.dataset.submitting = 'true';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Envoi en cours…';
});

document.addEventListener('click', (event) => {
    const printButton = event.target instanceof Element ? event.target.closest('[data-print-receipt]') : null;
    if (printButton) window.print();

    const copyButton = event.target instanceof Element ? event.target.closest('[data-copy-hash]') : null;
    if (!copyButton) return;
    const value = copyButton.getAttribute('data-copy-value');
    const feedback = copyButton.parentElement?.querySelector('[data-copy-feedback]');
    if (!value) return;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(value).then(() => {
            if (feedback) feedback.textContent = 'Empreinte copiée.';
        }).catch(() => {
            if (feedback) feedback.textContent = 'Copie indisponible. Sélectionnez l’empreinte pour la copier.';
        });
    } else {
        if (feedback) feedback.textContent = 'Copie indisponible dans ce contexte. Sélectionnez l’empreinte pour la copier.';
    }
});

document.addEventListener('input', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLTextAreaElement) || !field.matches('[data-char-count]')) return;
    const counter = document.querySelector('[data-char-counter][data-count-for="' + field.id + '"]');
    if (!counter) return;
    counter.textContent = field.value.length.toLocaleString('fr-FR') + ' / 1 000 caractères';
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-char-count]').forEach((field) => {
        if (field instanceof HTMLTextAreaElement) field.dispatchEvent(new Event('input', { bubbles: true }));
    });
    const errorSummary = document.querySelector('[data-error-summary]');
    if (errorSummary instanceof HTMLElement) errorSummary.focus({ preventScroll: false });
});
