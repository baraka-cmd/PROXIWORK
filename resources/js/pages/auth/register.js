document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-register-form]');

    if (!form) {
        return;
    }

    const password = form.querySelector('[data-register-password]');
    const confirmation = form.querySelector('[data-register-confirm-password]');
    const strengthBar = form.querySelector('[data-register-strength-bar]');
    const strengthLabel = form.querySelector('[data-register-strength-label]');
    const matchMessage = form.querySelector('[data-register-match]');
    const submitButton = form.querySelector('[data-register-submit]');

    const checks = {
        length: (value) => value.length >= 8,
        lower: (value) => /[a-z]/.test(value),
        upper: (value) => /[A-Z]/.test(value),
        number: (value) => /\d/.test(value),
    };

    const labels = ['À saisir', 'Faible', 'Moyen', 'Bon', 'Fort'];

    const updateStrength = () => {
        if (!password || !strengthBar || !strengthLabel) {
            return;
        }

        const value = password.value;
        let score = 0;

        Object.entries(checks).forEach(([name, test]) => {
            const valid = test(value);
            const item = form.querySelector('[data-register-check="' + name + '"]');

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

        strengthBar.style.width = (score === 0 ? 0 : score * 25) + '%';
        strengthLabel.textContent = labels[score];

        strengthBar.classList.remove('strength-1', 'strength-2', 'strength-3', 'strength-4');

        if (score > 0) {
            strengthBar.classList.add('strength-' + score);
        }
    };

    const updateMatch = () => {
        if (!confirmation || !matchMessage) {
            return;
        }

        if (!confirmation.value) {
            matchMessage.textContent = '';
            matchMessage.className = 'register-match';
            return;
        }

        const matches = password.value === confirmation.value;
        matchMessage.className = matches
            ? 'register-match is-valid'
            : 'register-match is-invalid';
        matchMessage.innerHTML = matches
            ? '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> Les mots de passe correspondent.'
            : '<i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> Les mots de passe ne correspondent pas.';
    };

    const bindPasswordToggle = (button, input) => {
        if (!button || !input) {
            return;
        }

        button.addEventListener('click', () => {
            const isPassword = input.type === 'password';

            input.type = isPassword ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(isPassword));
            button.setAttribute(
                'aria-label',
                isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe',
            );

            const icon = button.querySelector('i');

            if (icon) {
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            }
        });
    };

    bindPasswordToggle(
        form.querySelector('[data-register-password-toggle]'),
        password,
    );
    bindPasswordToggle(
        form.querySelector('[data-register-confirm-toggle]'),
        confirmation,
    );

    password?.addEventListener('input', () => {
        updateStrength();
        updateMatch();
    });

    confirmation?.addEventListener('input', updateMatch);

    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
            return;
        }

        if (password && confirmation && password.value !== confirmation.value) {
            event.preventDefault();
            updateMatch();
            confirmation.focus();
            return;
        }

        if (!submitButton) {
            return;
        }

        submitButton.disabled = true;
        submitButton.querySelector('span').textContent = 'Création...';

        const icon = submitButton.querySelector('i');

        if (icon) {
            icon.className = 'fa-solid fa-spinner fa-spin';
            icon.setAttribute('aria-hidden', 'true');
        }
    });

    form.querySelectorAll('[data-google-register]').forEach((button) => {
        button.addEventListener('click', () => {
            button.setAttribute('aria-disabled', 'true');
            button.title = 'La connexion Google sera activée avec la configuration OAuth de PROXIWORK.';
        });
    });

    updateStrength();
    updateMatch();

    const alert = document.querySelector('.register-alert');

    if (alert) {
        requestAnimationFrame(() => alert.focus());
    }
});
