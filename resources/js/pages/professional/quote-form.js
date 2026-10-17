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
            const date = validUntil?.value ? new Date(validUntil.value) : null;
            validitySummary.textContent = date && !Number.isNaN(date.getTime())
                ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
                : 'À renseigner';
        }
    };

    [amount, currency, durationValue, durationUnit, validUntil].forEach((field) => {
        field?.addEventListener('input', updateSummary);
        field?.addEventListener('change', updateSummary);
    });

    updateSummary();
});
