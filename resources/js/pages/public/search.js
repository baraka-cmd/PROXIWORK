const pages = document.querySelectorAll('[data-directory-page]');

pages.forEach((page) => {
    const filterPanel = page.querySelector('.directory-filter-panel');
    const openButton = page.querySelector('[data-filters-open]');
    const closeButton = page.querySelector('[data-filters-close]');
    const form = page.querySelector('[data-directory-form]');

    const closeFilters = () => {
        filterPanel?.classList.remove('is-open');
        openButton?.setAttribute('aria-expanded', 'false');
    };

    openButton?.addEventListener('click', () => {
        const isOpen = filterPanel?.classList.toggle('is-open') ?? false;
        openButton.setAttribute('aria-expanded', String(isOpen));

        if (isOpen) {
            filterPanel?.querySelector('input, select, button')?.focus();
        }
    });

    closeButton?.addEventListener('click', closeFilters);

    form?.addEventListener('submit', () => {
        const submit = form.querySelector('button[type="submit"]');

        if (!submit) {
            return;
        }

        submit.setAttribute('aria-busy', 'true');
        submit.disabled = true;
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeFilters();
        }
    });
});
