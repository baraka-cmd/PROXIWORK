'use strict';

const submittingForms = new WeakSet();

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || !form.checkValidity()) {
        return;
    }

    if (submittingForms.has(form)) {
        event.preventDefault();
        return;
    }

    submittingForms.add(form);

    const button = event.submitter;
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    const spinner = document.createElement('i');
    spinner.className = 'fa-solid fa-spinner fa-spin';
    spinner.setAttribute('aria-hidden', 'true');
    button.replaceChildren(spinner, document.createTextNode(' Traitement…'));
});

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    if (target.closest('[data-print-receipt]')) {
        window.print();
    }

    const copyButton = target.closest('[data-copy-hash]');
    if (!(copyButton instanceof HTMLElement)) return;

    const value = copyButton.getAttribute('data-copy-value');
    const feedback = copyButton.parentElement?.querySelector('[data-copy-feedback]');
    if (!value) return;

    const announce = (message) => {
        if (feedback instanceof HTMLElement) feedback.textContent = message;
    };

    if (!navigator.clipboard || !window.isSecureContext) {
        announce('Copie indisponible dans ce contexte. Sélectionnez l’empreinte pour la copier.');
        return;
    }

    navigator.clipboard.writeText(value).then(() => {
        announce('Empreinte copiée.');
    }).catch(() => {
        announce('Copie indisponible. Sélectionnez l’empreinte pour la copier.');
    });
});

document.addEventListener('input', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLTextAreaElement)) return;

    if (field.matches('[data-character-counter]')) {
        const counterId = field.dataset.characterCounter;
        const counter = counterId ? document.getElementById(counterId) : null;
        if (counter) counter.textContent = String(field.value.length);
    }

    if (field.matches('[data-char-count]')) {
        const counter = document.querySelector(
            '[data-char-counter][data-count-for="' + field.id + '"]',
        );
        if (counter) {
            counter.textContent = field.value.length.toLocaleString('fr-FR') + ' / 1 000 caractères';
        }
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea[data-character-counter]').forEach((field) => {
        if (field instanceof HTMLTextAreaElement) {
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    document.querySelectorAll('textarea[data-char-count]').forEach((field) => {
        if (field instanceof HTMLTextAreaElement) {
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    const errorSummary = document.querySelector('[data-order-error-summary], [data-error-summary]');
    if (errorSummary instanceof HTMLElement) {
        errorSummary.focus({ preventScroll: true });
        errorSummary.scrollIntoView({
            block: 'start',
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        });
    }
});
