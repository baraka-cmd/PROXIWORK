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
        length: (value) => value.length >= 12,
    };

    const labels = ['À saisir', 'Trop court', 'À renforcer', 'Correct', 'Long'];

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

        score = value.length >= 24 ? 4 : value.length >= 20 ? 3 : value.length >= 16 ? 2 : value.length >= 12 ? 1 : 0;

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
        if (form.hasAttribute('data-professional-wizard')) {
            return;
        }

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


document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-professional-wizard]');

    if (!form) {
        return;
    }

    const steps = Array.from(form.querySelectorAll('[data-register-step]'));
    const progressItems = Array.from(document.querySelectorAll('[data-progress-step]'));
    const previousButton = form.querySelector('[data-step-previous]');
    const nextButton = form.querySelector('[data-step-next]');
    const submitButton = form.querySelector('[data-submit-registration]');
    const validationMessage = form.querySelector('[data-wizard-validation]');
    const categoryChoices = Array.from(form.querySelectorAll('[data-category-choice]'));
    const profileSkills = Array.from(form.querySelectorAll('[data-profile-skill]'));
    let activeStep = 0;

    const announce = (message = '') => {
        if (validationMessage) {
            validationMessage.textContent = message;
            validationMessage.hidden = message === '';
        }
    };

    const selectedCategoryIds = () => categoryChoices
        .filter((input) => input.checked)
        .map((input) => input.value);

    const selectedProfileSkillIds = () => profileSkills
        .filter((input) => input.checked)
        .map((input) => input.value);

    const updateSkillGroups = () => {
        const selected = selectedCategoryIds();

        form.querySelectorAll('[data-skill-group]').forEach((group) => {
            const active = selected.includes(group.dataset.skillGroup);
            group.hidden = !active;
            group.querySelectorAll('[data-profile-skill]').forEach((input) => {
                input.disabled = !active;

                if (!active) {
                    input.checked = false;
                }
            });
        });

        form.querySelectorAll('[data-service-row]').forEach(updateServiceCategoryOptions);
    };

    const updateServiceCategoryOptions = (row) => {
        const select = row.querySelector('[data-service-category]');
        const serviceSkillOptions = Array.from(row.querySelectorAll('[data-service-skill]'));
        const selectedCategories = selectedCategoryIds();

        if (!select) {
            return;
        }

        Array.from(select.options).forEach((option) => {
            if (option.value) {
                const categoryIsSelected = selectedCategories.includes(option.value);
                option.hidden = !categoryIsSelected;
                option.disabled = !categoryIsSelected;
            }
        });

        if (!selectedCategories.includes(select.value)) {
            select.value = '';
        }

        const categoryId = select.value;
        const profileSkillIdSet = new Set(selectedProfileSkillIds());

        serviceSkillOptions.forEach((option) => {
            const visible = categoryId === option.dataset.categoryId
                && profileSkillIdSet.has(option.dataset.skillId);
            option.hidden = !visible;
            const input = option.querySelector('input');
            input.disabled = !visible;

            if (!visible) {
                input.checked = false;
            }
        });
    };

    const updatePricingFields = (row) => {
        const pricingType = row.querySelector('[data-pricing-type]')?.value;
        const priceField = row.querySelector('[data-price-field]');
        const minimumField = row.querySelector('[data-price-min-field]');
        const maximumField = row.querySelector('[data-price-max-field]');
        const showPrice = pricingType === 'fixed' || pricingType === 'from';
        const showRange = pricingType === 'range';

        if (priceField) {
            priceField.hidden = !showPrice;
            priceField.querySelector('input').required = showPrice;
        }

        if (minimumField && maximumField) {
            minimumField.hidden = !showRange;
            maximumField.hidden = !showRange;
            minimumField.querySelector('input').required = showRange;
            maximumField.querySelector('input').required = showRange;
        }
    };

    const updateServiceRows = () => {
        const rows = Array.from(form.querySelectorAll('[data-service-row]'));
        rows.forEach((row, index) => {
            const heading = row.querySelector('.professional-service-card__heading h3');
            const removeButton = row.querySelector('[data-remove-service]');

            if (heading) {
                heading.textContent = `Prestation ${index + 1}`;
            }

            if (removeButton) {
                removeButton.hidden = rows.length === 1;
            }

            updateServiceCategoryOptions(row);
            updatePricingFields(row);
        });
    };

    const updateSummary = () => {
        const firstName = form.querySelector('[name="first_name"]')?.value.trim() || form.dataset.accountFirstName || '';
        const lastName = form.querySelector('[name="last_name"]')?.value.trim() || form.dataset.accountLastName || '';
        const title = form.querySelector('[name="professional_title"]')?.value.trim() || '';
        const city = form.querySelector('[name="city"]')?.value.trim() || '';
        const province = form.querySelector('[name="province"]')?.value.trim() || '';
        const selectedCategoryNames = categoryChoices
            .filter((input) => input.checked)
            .map((input) => input.closest('.professional-category-option')?.querySelector('strong')?.textContent.trim())
            .filter(Boolean);
        const selectedSkillNames = profileSkills
            .filter((input) => input.checked)
            .map((input) => input.closest('.professional-skill-option')?.querySelector('span')?.textContent.trim())
            .filter(Boolean);
        const serviceCount = form.querySelectorAll('[data-service-row]').length;
        const documentCount = Array.from(form.querySelectorAll('[data-document-row] input[type="file"]'))
            .filter((input) => input.files?.length > 0).length;

        const setText = (selector, value, fallback) => {
            const element = form.querySelector(selector);
            if (element) {
                element.textContent = value || fallback;
            }
        };

        setText('[data-summary-name]', `${firstName} ${lastName}`.trim(), 'À compléter');
        setText('[data-summary-activity]', [title, [city, province].filter(Boolean).join(', ')].filter(Boolean).join(' · '), 'À compléter');
        setText('[data-summary-categories]', selectedCategoryNames.join(', '), 'À sélectionner');
        setText('[data-summary-skills]', selectedSkillNames.join(', '), 'À sélectionner');
        setText('[data-summary-services]', `${serviceCount} service${serviceCount > 1 ? 's' : ''}`);
        setText('[data-summary-documents]', documentCount > 0 ? `${documentCount} fichier${documentCount > 1 ? 's' : ''} ajouté${documentCount > 1 ? 's' : ''}` : 'Aucun fichier ajouté');
    };

    const setActiveStep = (nextStep, shouldFocus = true) => {
        activeStep = Math.max(0, Math.min(steps.length - 1, nextStep));
        steps.forEach((step, index) => {
            step.hidden = index !== activeStep;
        });
        progressItems.forEach((item, index) => {
            if (index === activeStep) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
        });
        previousButton.hidden = activeStep === 0;
        nextButton.hidden = activeStep === steps.length - 1;
        submitButton.hidden = activeStep !== steps.length - 1;
        announce();
        updateSummary();

        if (shouldFocus) {
            steps[activeStep].querySelector('h2')?.focus({ preventScroll: true });
            steps[activeStep].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const validateActiveStep = () => {
        const step = steps[activeStep];
        const invalidField = Array.from(step.querySelectorAll('input[required], select[required], textarea[required]'))
            .find((field) => !field.disabled && !field.checkValidity());

        if (invalidField) {
            invalidField.reportValidity();
            invalidField.focus();
            return false;
        }

        if (activeStep === 1) {
            const selected = selectedCategoryIds();
            if (selected.length < 1 || selected.length > 2) {
                announce('Sélectionnez une ou deux catégories pour continuer.');
                return false;
            }

            const missingSkills = selected.some((categoryId) => !form.querySelector(`[data-skill-group="${categoryId}"] [data-profile-skill]:checked`));
            if (missingSkills) {
                announce('Choisissez au moins une compétence dans chaque catégorie sélectionnée.');
                return false;
            }
        }

        if (activeStep === 2) {
            const invalidService = Array.from(form.querySelectorAll('[data-service-row]')).find((row) => {
                const categoryId = row.querySelector('[data-service-category]')?.value;
                return !categoryId || row.querySelectorAll('[data-service-skill]:not([hidden]) input:checked').length === 0;
            });

            if (invalidService) {
                announce('Pour chaque service, choisissez une catégorie et au moins une compétence correspondante.');
                invalidService.querySelector('[data-service-category]')?.focus();
                return false;
            }
        }

        announce();
        return true;
    };

    form.addEventListener('submit', (event) => {
        const password = form.querySelector('[data-register-password]');
        const confirmation = form.querySelector('[data-register-confirm-password]');

        if (password?.value !== confirmation?.value) {
            event.preventDefault();
            setActiveStep(0);
            announce('Les mots de passe doivent être identiques.');
            confirmation?.focus();

            return;
        }

        if (!validateActiveStep()) {
            event.preventDefault();

            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.querySelector('span').textContent = 'Transmission en cours…';
            submitButton.querySelector('i')?.classList.add('fa-spin');
        }
    });

    categoryChoices.forEach((input) => input.addEventListener('change', () => {
        const checked = categoryChoices.filter((choice) => choice.checked);
        if (checked.length > 2) {
            input.checked = false;
            announce('Vous pouvez sélectionner deux catégories au maximum.');
        }

        updateSkillGroups();
        updateSummary();
    }));

    profileSkills.forEach((input) => input.addEventListener('change', () => {
        updateServiceRows();
        updateSummary();
    }));

    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-service-category]')) {
            updateServiceCategoryOptions(event.target.closest('[data-service-row]'));
        }

        if (event.target.matches('[data-pricing-type]')) {
            updatePricingFields(event.target.closest('[data-service-row]'));
        }

        if (event.target.matches('input[type="file"]') && event.target.closest('[data-document-row]')) {
            updateSummary();
        }
    });

    form.addEventListener('input', updateSummary);

    nextButton.addEventListener('click', () => {
        if (validateActiveStep()) {
            setActiveStep(activeStep + 1);
        }
    });

    previousButton.addEventListener('click', () => setActiveStep(activeStep - 1));

    form.querySelector('[data-add-service]')?.addEventListener('click', () => {
        const rows = form.querySelectorAll('[data-service-row]');
        if (rows.length >= 8) {
            announce('Vous pouvez déclarer huit services au maximum.');
            return;
        }

        const index = rows.length;
        const template = document.querySelector('#professional-service-template');
        const container = form.querySelector('[data-service-builder]');
        const row = document.createElement('div');
        row.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).replaceAll('__NUMBER__', String(index + 1));
        container.append(row.firstElementChild);
        updateServiceRows();
        updateSummary();
        form.querySelector(`[data-service-index="${index}"] [data-service-category]`)?.focus();
    });

    form.querySelector('[data-add-document]')?.addEventListener('click', () => {
        const rows = form.querySelectorAll('[data-document-row]');
        if (rows.length >= 5) {
            announce('Vous pouvez transmettre cinq justificatifs au maximum.');
            return;
        }

        const template = document.querySelector('#professional-document-template');
        const row = document.createElement('div');
        row.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(rows.length));
        form.querySelector('[data-document-list]').append(row.firstElementChild);
        updateSummary();
    });

    form.addEventListener('click', (event) => {
        const removeService = event.target.closest('[data-remove-service]');
        if (removeService) {
            removeService.closest('[data-service-row]')?.remove();
            updateServiceRows();
            updateSummary();
        }

        const removeDocument = event.target.closest('[data-remove-document]');
        if (removeDocument) {
            removeDocument.closest('[data-document-row]')?.remove();
            updateSummary();
        }
    });

    const validationKeys = document.querySelector('[data-validation-keys]')?.dataset.validationKeys || '';
    if (validationKeys) {
        const keys = validationKeys.split(',').filter(Boolean);
        const errorStep = keys.some((key) => key.startsWith('services.')) ? 2
            : (keys.some((key) => key.startsWith('documents.')) ? 3
                : (keys.some((key) => ['category_ids', 'skill_ids'].some((prefix) => key.startsWith(prefix))) ? 1
                    : (keys.includes('terms') ? 4 : 0)));
        setActiveStep(errorStep, false);
    } else {
        setActiveStep(0, false);
    }

    updateSkillGroups();
    updateServiceRows();
    updateSummary();
});
