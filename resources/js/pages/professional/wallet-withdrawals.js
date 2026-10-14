document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-withdrawal-form]');
    const currency = document.querySelector('[data-currency-select]');

    if (currency) {
        currency.addEventListener('change', () => {
            const base = currency.dataset.createUrl;
            if (base) {
                window.location.href = `${base}?currency=${encodeURIComponent(currency.value)}`;
            }
        });
    }

    if (!form) return;

    const amount = form.querySelector('[data-amount-input]');
    const review = form.querySelector('[data-review-amount]');
    const grossReview = form.querySelector('[data-review-gross]');
    const submit = form.querySelector('[data-withdrawal-submit]');

    // This is a presentation-only preview. Keep the entered decimal as a string
    // so a binary float cannot silently round the displayed cents.
    const formatDecimal = (rawValue) => {
        const value = String(rawValue ?? '').trim().replace(',', '.');
        if (!/^\d*(?:\.\d{0,2})?$/.test(value) || value === '' || value === '.') return '0,00';

        const [rawWhole, rawFraction = ''] = value.split('.');
        const whole = (rawWhole || '0').replace(/^0+(?=\d)/, '');
        const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return `${grouped},${rawFraction.padEnd(2, '0')}`;
    };

    const update = () => {
        const formatted = formatDecimal(amount?.value);
        if (review) review.textContent = formatted;
        if (grossReview) grossReview.textContent = formatted;
    };

    amount?.addEventListener('input', update);

    form.addEventListener('submit', () => {
        if (!submit) return;
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Enregistrement…';
    });

    update();
});
