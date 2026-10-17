document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-quote-form]');

    if (!form) return;

    const amount = form.querySelector('#amount');
    const currency = form.querySelector('#currency');
    const durationValue = form.querySelector('#duration_value');
    const durationUnit = form.querySelector('#duration_unit');
    const validUntil = form.querySelector('#valid_until');
    const amountSummary = form.querySelector('[data-quote-summary-amount]');
    const durationSummary = form.querySelector('[data-quote-summary-duration]');
    const validitySummary = form.querySelector('[data-quote-summary-validity]');
    const description = form.querySelector('#description');
    const descriptionSummary = form.querySelector('[data-quote-summary-description]');

    const units = {
        hours: ['heure', 'heures'],
        days: ['jour', 'jours'],
        weeks: ['semaine', 'semaines'],
    };

    const updateSummary = () => {
        const numericAmount = Number.parseFloat(amount?.value ?? '');
        const currencyCode = (currency?.value ?? '').trim().toUpperCase();

        if (amountSummary) {
            amountSummary.textContent = Number.isFinite(numericAmount) && numericAmount > 0
                ? `${new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(numericAmount)} ${currencyCode}`.trim()
                : 'À renseigner';
        }

        if (durationSummary) {
            const value = Number.parseInt(durationValue?.value ?? '', 10);
            const unit = durationUnit?.value ?? '';
            const labels = units[unit];
            durationSummary.textContent = Number.isFinite(value) && value > 0 && labels
                ? `${value} ${value > 1 ? labels[1] : labels[0]}`
                : 'À renseigner';
        }

        if (validitySummary) {
            const dateParts = (validUntil?.value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
            validitySummary.textContent = dateParts
                ? `${dateParts[3]}/${dateParts[2]}/${dateParts[1]} à ${dateParts[4]}:${dateParts[5]} (fuseau PROXIWORK)`
                : 'À renseigner';
        }

        if (descriptionSummary) {
            const value = (description?.value ?? '').trim();
            descriptionSummary.textContent = value || 'À renseigner';
        }
    };

    [amount, currency, durationValue, durationUnit, validUntil, description].forEach((field) => {
        field?.addEventListener('input', updateSummary);
        field?.addEventListener('change', updateSummary);
    });

    updateSummary();
});
