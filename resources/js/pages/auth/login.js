document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-login-form]');
    const password = form?.querySelector('#password');
    const passwordToggle = form?.querySelector('[data-login-password-toggle]');
    const submitButton = form?.querySelector('[data-login-submit]');

    if (!form || !password) {
        return;
    }

    if (passwordToggle) {
        passwordToggle.addEventListener('click', () => {
            const isPassword = password.type === 'password';

            password.type = isPassword ? 'text' : 'password';
            passwordToggle.setAttribute('aria-pressed', String(isPassword));
            passwordToggle.setAttribute(
                'aria-label',
                isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe',
            );

            const icon = passwordToggle.querySelector('i');

            if (icon) {
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            }
        });
    }

    form.addEventListener('submit', () => {
        if (!form.checkValidity() || !submitButton) {
            return;
        }

        submitButton.disabled = true;
        const label = submitButton.querySelector('span');

        if (label) {
            label.textContent = 'Connexion...';
        }

        const icon = submitButton.querySelector('i');

        if (icon) {
            icon.className = 'fa-solid fa-spinner fa-spin';
            icon.setAttribute('aria-hidden', 'true');
        }
    });

    const alert = document.querySelector('.login-alert');

    if (alert) {
        requestAnimationFrame(() => alert.focus());
    }
});
