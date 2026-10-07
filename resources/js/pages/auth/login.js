document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-login-form]');
    const password = document.querySelector('[data-password-strength]');
    const passwordToggle = document.querySelector('[data-login-password-toggle]');
    const strengthBar = document.querySelector('[data-strength-bar]');
    const strengthLabel = document.querySelector('[data-strength-label]');
    const submitButton = document.querySelector('[data-login-submit]');

    if (!form || !password) {
        return;
    }

    const checks = {
        length: (value) => value.length >= 8,
        lower: (value) => /[a-z]/.test(value),
        upper: (value) => /[A-Z]/.test(value),
        number: (value) => /\d/.test(value),
    };

    const strengthLabels = ['À saisir', 'Faible', 'Moyen', 'Bon', 'Fort'];

    const updateStrength = () => {
        const value = password.value;
        let score = 0;

        Object.entries(checks).forEach(([name, test]) => {
            const valid = test(value);
            const item = document.querySelector('[data-check="' + name + '"]');

            if (item) {
                item.classList.toggle('is-valid', valid);
                const icon = item.querySelector('i');

                if (icon) {
                    icon.classList.toggle('fa-circle', !valid);
                    icon.classList.toggle('fa-circle-check', valid);
                }
            }

            if (valid) {
                score += 1;
            }
        });

        if (value.length >= 12 && /[^A-Za-z0-9]/.test(value)) {
            score = Math.min(4, score + 1);
        }

        const width = score === 0 ? 0 : Math.min(score * 25, 100);

        strengthBar.style.width = width + '%';
        strengthLabel.textContent = strengthLabels[score];

        strengthBar.classList.remove('strength-1', 'strength-2', 'strength-3', 'strength-4');

        if (score > 0) {
            strengthBar.classList.add('strength-' + score);
        }
    };

    password.addEventListener('input', updateStrength);

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
        submitButton.querySelector('span').textContent = 'Connexion...';

        const icon = submitButton.querySelector('i');

        if (icon) {
            icon.className = 'fa-solid fa-spinner fa-spin';
            icon.setAttribute('aria-hidden', 'true');
        }
    });

    document.querySelectorAll('[data-google-login]').forEach((button) => {
        button.addEventListener('click', () => {
            button.setAttribute('aria-disabled', 'true');
            button.title = 'La connexion Google sera activée avec la configuration OAuth de PROXIWORK.';
        });
    });

    updateStrength();

    const alert = document.querySelector('.login-alert');

    if (alert) {
        requestAnimationFrame(() => alert.focus());
    }
});
