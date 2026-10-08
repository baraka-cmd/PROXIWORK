const confirmationController = () => {
    const dialog = document.querySelector('[data-confirmation-dialog]');

    if (!dialog) {
        return;
    }

    const panel = dialog.querySelector('.confirmation__dialog');
    const acceptButton = dialog.querySelector('[data-confirmation-accept]');
    const cancelControls = dialog.querySelectorAll('[data-confirmation-cancel]');
    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    let trigger = null;
    let confirmedAction = null;

    const getFocusable = () => Array.from(panel.querySelectorAll(focusableSelector))
        .filter((element) => !element.hasAttribute('hidden'))
        .filter((element) => element.getAttribute('aria-hidden') !== 'true');

    const close = ({ restoreFocus = true } = {}) => {
        dialog.hidden = true;
        dialog.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('confirmation-is-open');
        confirmedAction = null;

        if (restoreFocus && trigger && typeof trigger.focus === 'function') {
            trigger.focus();
        }

        trigger = null;
    };

    const open = (source, action, options = {}) => {
        trigger = source;
        confirmedAction = action;

        const title = options.title || source?.dataset.confirmTitle;
        const message = options.message || source?.dataset.confirmMessage;
        const confirmLabel = options.confirmLabel || source?.dataset.confirmLabel;

        if (title) {
            panel.querySelector('.confirmation__title').textContent = title;
        }

        if (message) {
            panel.querySelector('.confirmation__message').textContent = message;
        }

        if (confirmLabel) {
            acceptButton.textContent = confirmLabel;
        }

        dialog.hidden = false;
        dialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('confirmation-is-open');

        requestAnimationFrame(() => {
            acceptButton.focus();
        });
    };

    document.addEventListener('click', (event) => {
        const source = event.target.closest('[data-confirm]');

        if (!source || source.closest('[data-confirmation-dialog]')) {
            return;
        }

        const form = source.matches('form') ? source : source.form;
        if (form && source.matches('button, input[type="submit"]')) {
            return;
        }

        event.preventDefault();
        open(source, () => {
            if (source.matches('a[href]')) {
                window.location.assign(source.href);
                return;
            }

            if (typeof source._confirmationAction === 'function') {
                source._confirmationAction();
            }
        });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
            return;
        }

        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }

        event.preventDefault();
        open(form, () => {
            form.dataset.confirmed = 'true';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });

    acceptButton.addEventListener('click', () => {
        const action = confirmedAction;
        close({ restoreFocus: false });
        if (action) {
            action();
        }
    });

    cancelControls.forEach((control) => {
        control.addEventListener('click', () => close());
    });

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = getFocusable();
        if (focusable.length === 0) {
            event.preventDefault();
            panel.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
};

document.addEventListener('DOMContentLoaded', confirmationController);
