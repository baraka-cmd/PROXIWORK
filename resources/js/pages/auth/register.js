document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-registration-page]');
    const form = page?.querySelector('[data-register-form]');
    if (!page || !form) return;

    const accountInputs = [...form.querySelectorAll('input[name="account_type"]')];
    const professionalFields = form.querySelector('[data-professional-step-fields]');
    const wizard = form.querySelector('[data-professional-wizard]');
    const identityStep = form.querySelector('[data-registration-step="1"]');
    const steps = [...form.querySelectorAll('[data-registration-step]')];
    const nextButtons = [...form.querySelectorAll('[data-registration-next]')];
    const previousButton = form.querySelector('[data-registration-previous]');
    const clientSubmit = form.querySelector('[data-client-submit]');
    const professionalSubmit = form.querySelector('[data-professional-submit]');
    const wizardActions = form.querySelector('[data-wizard-actions]');
    const categoryChoices = [...form.querySelectorAll('[data-category-choice]')];
    const skillOptions = [...form.querySelectorAll('[data-skill-option]')];
    const serviceContainer = form.querySelector('[data-services-container]');
    const serviceTemplate = form.querySelector('[data-service-template]');
    const documentsContainer = form.querySelector('[data-documents-container]');
    let currentStep = 1;
    let serviceCount = 0;
    let documentCount = 1;

    const accountType = () => accountInputs.find((input) => input.checked)?.value || 'client';
    const selectedCategories = () => categoryChoices.filter((input) => input.checked).map((input) => input.value);
    const selectedSkills = () => [...form.querySelectorAll('[data-skill-choice]:checked')].map((input) => input.value);

    const setEnabled = (container, enabled) => {
        container?.querySelectorAll('input, select, textarea, button').forEach((element) => {
            if (element.matches('[data-registration-next], [data-registration-previous], [data-client-submit], [data-professional-submit], [data-add-service], [data-add-document], [data-remove-service], [data-remove-document], [data-register-password-toggle], [data-register-confirm-toggle]')) return;
            if (element.type === 'hidden' || element.name === '_token') return;
            element.disabled = !enabled;
        });
    };

    const refreshAccountType = () => {
        const professional = accountType() === 'professional';
        page.classList.toggle('is-professional-registration', professional);
        page.querySelectorAll('[data-account-choice]').forEach((card) => {
            card.classList.toggle('is-selected', card.dataset.accountChoice === accountType());
        });
        professionalFields?.classList.toggle('is-visible', professional);
        setEnabled(professionalFields, professional);
        const cityField = form.querySelector('#city');
        if (cityField) cityField.required = professional;
        wizard?.classList.toggle('is-visible', professional);
        clientSubmit?.classList.toggle('is-visible', !professional);
        nextButtons.forEach((button) => button.classList.toggle('is-visible', professional));
        wizardActions?.classList.toggle('is-visible', professional && currentStep > 1);
        if (professional) {
            currentStep = Math.min(Math.max(currentStep, 1), 5);
            showStep(currentStep);
        } else {
            steps.forEach((step) => {
                if (step.dataset.registrationStep !== '1') {
                    step.classList.remove('is-active');
                    setEnabled(step, false);
                }
            });
            setEnabled(identityStep, true);
            if (clientSubmit) clientSubmit.disabled = false;
        }
        updateSkillVisibility();
    };

    const showStep = (stepNumber) => {
        currentStep = stepNumber;
        steps.forEach((step) => {
            const active = Number(step.dataset.registrationStep) === stepNumber;
            step.classList.toggle('is-active', active);
            setEnabled(step, active || stepNumber === 5);
        });
        if (professionalFields) setEnabled(professionalFields, accountType() === 'professional' && (stepNumber === 1 || stepNumber === 5));
        wizardActions?.classList.toggle('is-visible', accountType() === 'professional' && stepNumber > 1);
        page.querySelectorAll('[data-progress-step]').forEach((item) => {
            const n = Number(item.dataset.progressStep);
            item.classList.toggle('is-current', n === stepNumber);
            item.classList.toggle('is-complete', n < stepNumber);
        });
        if (previousButton) previousButton.classList.toggle('is-visible', stepNumber > 1);
        nextButtons.forEach((button) => {
            const inIdentityActions = Boolean(button.closest('.register-actions'));
            button.classList.toggle('is-visible', accountType() === 'professional' && stepNumber < 5 && (inIdentityActions ? stepNumber === 1 : stepNumber > 1));
        });
        if (professionalSubmit) professionalSubmit.classList.toggle('is-visible', stepNumber === 5 && accountType() === 'professional');
        updateSummary();
        if (stepNumber > 1) {
            const heading = form.querySelector('[data-registration-step="' + stepNumber + '"] h2');
            heading?.setAttribute('tabindex', '-1');
            heading?.focus({ preventScroll: true });
        }
    };

    const updateSkillVisibility = () => {
        const categories = selectedCategories();
        categoryChoices.forEach((input) => {
            const disabled = !input.checked && categories.length >= 2;
            input.disabled = disabled;
            input.closest('label')?.classList.toggle('is-disabled', disabled);
        });
        skillOptions.forEach((option) => {
            const visible = categories.includes(option.dataset.categoryId);
            const search = form.querySelector('[data-skill-search]')?.value.trim().toLocaleLowerCase() || '';
            const matches = (option.dataset.skillName || '').includes(search);
            option.hidden = !visible || !matches;
            const checkbox = option.querySelector('input');
            if (!visible) {
                if (checkbox) checkbox.checked = false;
            }
            if (checkbox) checkbox.disabled = !visible;
        });
        form.querySelectorAll('[data-service-card]').forEach((card) => updateServiceSkills(card));
    };

    const updateServiceSkills = (card) => {
        const categoryId = card.querySelector('[data-service-category]')?.value || '';
        const selected = selectedSkills();
        card.querySelectorAll('[data-service-skill-option]').forEach((option) => {
            const allowed = option.dataset.categoryId === categoryId && selected.includes(option.dataset.skillId);
            option.hidden = !allowed;
            const checkbox = option.querySelector('input');
            if (!allowed && checkbox) checkbox.checked = false;
            if (checkbox) checkbox.disabled = !allowed;
        });
        const categorySelect = card.querySelector('[data-service-category]');
        if (categorySelect) categorySelect.disabled = accountType() !== 'professional' || currentStep !== 3;
    };

    const updatePricingFields = (card) => {
        const type = card.querySelector('[data-pricing-type]')?.value || 'quote';
        const price = card.querySelector('[data-price-field]');
        const min = card.querySelector('[data-price-min-field]');
        const max = card.querySelector('[data-price-max-field]');
        if (price) price.hidden = !['fixed', 'from'].includes(type);
        if (min) min.hidden = type !== 'range';
        if (max) max.hidden = type !== 'range';
        const priceInput = price?.querySelector('input');
        const minInput = min?.querySelector('input');
        const maxInput = max?.querySelector('input');
        if (priceInput) { priceInput.disabled = !['fixed', 'from'].includes(type); priceInput.required = ['fixed', 'from'].includes(type); }
        if (minInput) { minInput.disabled = type !== 'range'; minInput.required = type === 'range'; }
        if (maxInput) { maxInput.disabled = type !== 'range'; maxInput.required = type === 'range'; }
    };

    const updateServiceNumbers = () => {
        const cards = [...serviceContainer.querySelectorAll('[data-service-card]')];
        cards.forEach((card, index) => {
            card.querySelector('[data-service-number]').textContent = String(index + 1);
            const remove = card.querySelector('[data-remove-service]');
            if (remove) remove.hidden = cards.length === 1;
        });
    };

    const addService = () => {
        if (!serviceTemplate || serviceCount >= 10) return;
        const index = serviceCount++;
        const fragment = serviceTemplate.content.cloneNode(true);
        const card = fragment.querySelector('[data-service-card]');
        card.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replaceAll('__INDEX__', String(index));
        });
        serviceContainer.appendChild(fragment);
        const added = serviceContainer.querySelectorAll('[data-service-card]')[index];
        added.querySelector('[data-service-category]')?.addEventListener('change', () => updateServiceSkills(added));
        added.querySelector('[data-pricing-type]')?.addEventListener('change', () => updatePricingFields(added));
        added.querySelector('[data-remove-service]')?.addEventListener('click', () => {
            if (serviceContainer.querySelectorAll('[data-service-card]').length > 1) {
                added.remove();
                updateServiceNumbers();
            }
        });
        updateServiceSkills(added);
        updatePricingFields(added);
        updateServiceNumbers();
    };

    const addDocument = () => {
        if (documentCount >= 8 || !documentsContainer) return;
        const row = documentsContainer.querySelector('[data-document-row]')?.cloneNode(true);
        if (!row) return;
        row.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace(/documents\[\d+\]/, 'documents[' + documentCount + ']');
            if (field.type === 'file') field.value = '';
            else field.selectedIndex = 0;
        });
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'register-remove-button';
        remove.dataset.removeDocument = '';
        remove.textContent = 'Retirer';
        remove.addEventListener('click', () => { row.remove(); });
        row.appendChild(remove);
        documentsContainer.appendChild(row);
        documentCount += 1;
    };

    const updateSummary = () => {
        const text = (selector) => form.querySelector(selector)?.value?.trim() || '—';
        const selectedNames = (selector) => [...form.querySelectorAll(selector + ':checked')].map((input) => input.closest('label')?.querySelector('strong')?.textContent?.trim()).filter(Boolean).join(', ') || '—';
        const put = (selector, value) => { const target = form.querySelector(selector); if (target) target.textContent = value; };
        put('[data-summary-name]', (text('#first_name') + ' ' + text('#last_name')).trim());
        put('[data-summary-email]', text('#email'));
        put('[data-summary-phone]', text('#phone'));
        put('[data-summary-city]', text('#city'));
        put('[data-summary-categories]', selectedNames('[data-category-choice]'));
        put('[data-summary-skills]', selectedNames('[data-skill-choice]'));
        put('[data-summary-services]', String(serviceContainer?.querySelectorAll('[data-service-card]').length || 0));
        put('[data-summary-documents]', String([...form.querySelectorAll('[name^="documents["][type="file"]')].filter((input) => input.files?.length).length));
    };

    const validateStep = (stepNumber) => {
        const step = form.querySelector('[data-registration-step="' + stepNumber + '"]');
        if (!step) return true;
        const fields = [...step.querySelectorAll('input, select, textarea')].filter((field) => !field.disabled && field.type !== 'file' && field.type !== 'checkbox' || (field.type === 'checkbox' && !field.disabled));
        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                field.focus();
                return false;
            }
        }
        if (stepNumber === 1) {
            const password = form.querySelector('[data-register-password]');
            const confirmation = form.querySelector('[data-register-confirm-password]');
            if (password && confirmation && password.value !== confirmation.value) {
                confirmation.setCustomValidity('Les deux mots de passe ne correspondent pas.');
                confirmation.reportValidity();
                confirmation.focus();
                return false;
            }
            confirmation?.setCustomValidity('');
        }
        if (stepNumber === 2) {
            if (selectedCategories().length < 1 || selectedCategories().length > 2) {
                alert('Choisissez une ou deux catégories principales.');
                return false;
            }
            if (selectedSkills().length < 1) {
                alert('Choisissez au moins une compétence associée à vos catégories.');
                return false;
            }
        }
        if (stepNumber === 3) {
            const cards = [...serviceContainer.querySelectorAll('[data-service-card]')];
            if (!cards.length) { alert('Ajoutez au moins un service.'); return false; }
            for (const card of cards) {
                for (const field of card.querySelectorAll('input[required], select[required], textarea[required]')) {
                    if (!field.disabled && !field.checkValidity()) {
                        field.reportValidity();
                        field.focus();
                        return false;
                    }
                }
                if (!card.querySelector('[data-service-skill-option] input:checked')) {
                    alert('Choisissez au moins une compétence pour chaque service.');
                    return false;
                }
            }
        }
        return true;
    };

    accountInputs.forEach((input) => input.addEventListener('change', refreshAccountType));
    categoryChoices.forEach((input) => input.addEventListener('change', updateSkillVisibility));
    form.querySelector('[data-skill-search]')?.addEventListener('input', updateSkillVisibility);
    form.querySelectorAll('[data-skill-choice]').forEach((input) => input.addEventListener('change', () => {
        form.querySelectorAll('[data-service-card]').forEach(updateServiceSkills);
        updateSummary();
    }));
    form.querySelector('[data-add-service]')?.addEventListener('click', addService);
    form.querySelector('[data-add-document]')?.addEventListener('click', addDocument);
    previousButton?.addEventListener('click', () => { if (currentStep > 1) showStep(currentStep - 1); });
    nextButtons.forEach((button) => button.addEventListener('click', () => {
        if (accountType() !== 'professional') return;
        if (!validateStep(currentStep)) return;
        if (currentStep < 5) showStep(currentStep + 1);
    }));

    const bindPasswordToggle = (button, input) => {
        if (!button || !input) return;
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            const icon = button.querySelector('i');
            icon?.classList.toggle('fa-eye', !show);
            icon?.classList.toggle('fa-eye-slash', show);
        });
    };
    bindPasswordToggle(form.querySelector('[data-register-password-toggle]'), form.querySelector('[data-register-password]'));
    bindPasswordToggle(form.querySelector('[data-register-confirm-toggle]'), form.querySelector('[data-register-confirm-password]'));
    form.querySelector('[data-register-confirm-password]')?.addEventListener('input', (event) => event.target.setCustomValidity(''));

    form.addEventListener('submit', (event) => {
        if (accountType() === 'professional') {
            for (let step = 1; step <= 3; step += 1) {
                if (!validateStep(step)) {
                    event.preventDefault();
                    showStep(step);
                    return;
                }
            }
            if (currentStep !== 5) {
                event.preventDefault();
                showStep(5);
                return;
            }
        } else if (!validateStep(1)) {
            event.preventDefault();
            return;
        }
        const submitter = accountType() === 'professional' ? professionalSubmit : clientSubmit;
        if (submitter) {
            submitter.disabled = true;
            const label = submitter.querySelector('span');
            if (label) label.textContent = 'Création du compte…';
        }
    });

    // Start with one complete service card, preserving the simple no-JavaScript server form fallback.
    addService();
    refreshAccountType();
    if (accountType() === 'professional') showStep(1);
    const alertBox = page.querySelector('.register-alert');
    if (alertBox) requestAnimationFrame(() => alertBox.focus());
});
