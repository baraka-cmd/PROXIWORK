'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const errorSummary = document.querySelector('[data-error-summary]');

    if (errorSummary instanceof HTMLElement) {
        window.requestAnimationFrame(() => errorSummary.focus());
    }

    document.querySelectorAll('form[data-address-form][data-submit-label]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            const submitter = event.submitter;
            if (!(submitter instanceof HTMLButtonElement)) {
                return;
            }

            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            submitter.disabled = true;
            submitter.setAttribute('aria-disabled', 'true');
            submitter.textContent = form.dataset.submitLabel;
        });
    });
});
