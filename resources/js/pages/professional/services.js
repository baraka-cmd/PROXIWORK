const pricingType = document.querySelector('[data-pricing-type]');
const pricingHelp = document.querySelector('[data-pricing-help]');
const pricingFields = {
    price: document.querySelector('#price'),
    minimum: document.querySelector('#price_min'),
    maximum: document.querySelector('#price_max'),
    currency: document.querySelector('#currency'),
};

const pricingMessages = {
    fixed: 'Le montant indiqué correspond au prix fixe de cette prestation.',
    from: 'Le montant indiqué est un prix de départ ; le coût final peut dépendre du besoin.',
    range: 'Indiquez une borne minimale et une borne maximale cohérentes.',
    quote: 'Aucun montant n’est affiché ici : le prix sera défini dans le processus de devis.',
};

if (pricingType) {
    let currentPricingType = pricingType.value;

    const updatePricingVisibility = () => {
        const type = pricingType.value;
        document.querySelectorAll('[data-price-field]').forEach((field) => { field.hidden = !(type === 'fixed' || type === 'from'); });
        document.querySelectorAll('[data-range-field]').forEach((field) => { field.hidden = type !== 'range'; });
        document.querySelectorAll('[data-currency-field]').forEach((field) => { field.hidden = type === 'quote'; });
        if (pricingHelp) pricingHelp.textContent = pricingMessages[type] || 'Choisissez le mode de tarification de cette prestation.';
    };

    pricingType.addEventListener('change', () => {
        const nextType = pricingType.value;
        const valuesToClear = [];

        if (nextType === 'range' && pricingFields.price?.value) valuesToClear.push(pricingFields.price);
        if ((nextType === 'fixed' || nextType === 'from') && (pricingFields.minimum?.value || pricingFields.maximum?.value)) {
            valuesToClear.push(pricingFields.minimum, pricingFields.maximum);
        }
        if (nextType === 'quote') {
            valuesToClear.push(...Object.values(pricingFields).filter((field) => field?.value));
        }

        if (valuesToClear.length > 0) {
            const confirmed = window.confirm('Ce changement de tarification effacera les montants incompatibles avec le nouveau mode. Voulez-vous continuer ?');
            if (!confirmed) {
                pricingType.value = currentPricingType;
                updatePricingVisibility();
                return;
            }
            valuesToClear.forEach((field) => { if (field) field.value = ''; });
        }

        currentPricingType = nextType;
        updatePricingVisibility();
    });

    updatePricingVisibility();
}

document.querySelectorAll('[data-action-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.hasAttribute('data-confirm-archive') && !window.confirm('Archiver ce service ? Il sera retiré du catalogue actif ; son historique sera conservé.')) {
            event.preventDefault();
            return;
        }
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }
    });
});

document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm('Supprimer cet élément ? Cette action ne peut pas être annulée.')) event.preventDefault();
    });
});

document.querySelectorAll('.service-form').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-action-button]');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }
    });
});

document.querySelectorAll('input[type="file"]').forEach((input) => {
    const preview = document.createElement('div');
    preview.className = 'service-file-preview';
    preview.hidden = true;
    preview.setAttribute('aria-live', 'polite');

    const image = document.createElement('img');
    image.alt = 'Aperçu local du fichier sélectionné';
    image.hidden = true;

    const details = document.createElement('p');
    preview.append(image, details);
    input.insertAdjacentElement('afterend', preview);

    let objectUrl = null;
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;

        if (!file) {
            preview.hidden = true;
            image.removeAttribute('src');
            return;
        }

        preview.hidden = false;
        const size = (file.size / (1024 * 1024)).toLocaleString(document.documentElement.lang || 'fr-FR', { maximumFractionDigits: 2 });
        details.textContent = `${file.name} · ${size} Mo. L’aperçu est local ; le fichier ne sera envoyé qu’à la soumission du formulaire.`;

        if (file.type.startsWith('image/')) {
            objectUrl = URL.createObjectURL(file);
            image.src = objectUrl;
            image.hidden = false;
        } else {
            image.hidden = true;
            image.removeAttribute('src');
        }
    });

    window.addEventListener('pagehide', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
    });
});

document.querySelectorAll('.service-form [aria-invalid="true"]').forEach((field) => {
    field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), field.id ? `${field.id}-error` : null].filter(Boolean).join(' '));
});
