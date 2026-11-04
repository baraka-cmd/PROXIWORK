const pages = document.querySelectorAll('[data-directory-page]');

const historyKey = 'proxiwork.search.history';

const readHistory = () => {
    try {
        const history = JSON.parse(localStorage.getItem(historyKey) || '[]');
        return Array.isArray(history)
            ? history.map((entry) => typeof entry === 'string'
                ? { term: entry, params: [['search', entry]] }
                : entry)
                .filter((entry) => typeof entry?.term === 'string' && Array.isArray(entry.params))
                .map((entry) => ({
                    term: entry.term.slice(0, 120),
                    params: entry.params.filter((pair) => Array.isArray(pair)
                        && typeof pair[0] === 'string'
                        && typeof pair[1] === 'string'),
                }))
                .slice(0, 5)
            : [];
    } catch {
        return [];
    }
};

const saveHistory = (value, submittedParams = null) => {
    const normalized = value.trim();
    if (!normalized) return;
    const params = submittedParams ?? new URLSearchParams(window.location.search);
    if (!submittedParams) params.set('search', normalized);
    params.delete('page');
    const entry = { term: normalized, params: Array.from(params.entries()) };
    const history = [entry, ...readHistory().filter((item) => item.term.toLowerCase() !== normalized.toLowerCase())].slice(0, 5);
    try {
        localStorage.setItem(historyKey, JSON.stringify(history));
    } catch {
        // Search submission should keep working when browser storage is unavailable.
    }
};

document.querySelectorAll('[data-service-search-form]').forEach((form) => {
    const input = form.querySelector('input[name="search"]');
    const suggestions = form.querySelector('[data-search-suggestions]');
    const clear = form.querySelector('[data-search-clear]');
    const filterForm = document.querySelector('[data-directory-form]');
    if (!input || !suggestions) return;

    const sources = [
        ...document.querySelectorAll('select[name="category"] option'),
        ...document.querySelectorAll('input[name="skills[]"]'),
    ].map((element) => ({
        label: element.matches('input[name="skills[]"]')
            ? element.closest('label')?.querySelector('span')?.textContent?.trim() || element.value
            : element.textContent?.trim() || element.value,
        value: element.value,
        category: element.matches('option') ? 'Catégorie' : 'Compétence',
    })).filter((source) => source.value);

    let activeIndex = -1;
    let suggestionTimer;

    const closeSuggestions = () => {
        suggestions.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    };

    const chooseSuggestion = (match) => {
        closeSuggestions();
        if (match.category === 'Catégorie' && filterForm) {
            const category = filterForm.querySelector('select[name="category"]');
            if (category) category.value = match.value;
            const search = filterForm.querySelector('input[name="search"]');
            if (search) search.value = '';
            filterForm.requestSubmit();
            return;
        }
        if (match.category === 'Compétence' && filterForm) {
            const skill = [...filterForm.querySelectorAll('input[name="skills[]"]')].find((item) => item.value === match.value);
            if (skill) skill.checked = true;
            const search = filterForm.querySelector('input[name="search"]');
            if (search) search.value = '';
            filterForm.requestSubmit();
            return;
        }
        input.value = match.value;
        form.requestSubmit();
    };

    const renderSuggestions = () => {
        const term = input.value.trim().toLowerCase();
        suggestions.replaceChildren();
        activeIndex = -1;
        if (term.length < 2) {
            closeSuggestions();
            return;
        }
        const matches = sources.filter((source) => source.label.toLowerCase().includes(term)).slice(0, 6);
        const options = [...matches, { label: `Rechercher « ${input.value.trim()} »`, value: input.value.trim(), category: 'Recherche' }];
        options.forEach((match, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'search-suggestion';
            button.id = `search-suggestion-${index}`;
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', 'false');
            const label = document.createElement('span');
            label.textContent = match.label;
            const category = document.createElement('small');
            category.textContent = match.category;
            button.append(label, category);
            button.addEventListener('click', () => chooseSuggestion(match));
            suggestions.append(button);
        });
        suggestions.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    input.addEventListener('input', () => {
        clear.hidden = input.value.length === 0;
        window.clearTimeout(suggestionTimer);
        suggestionTimer = window.setTimeout(renderSuggestions, 180);
    });
    input.addEventListener('keydown', (event) => {
        const options = [...suggestions.querySelectorAll('[role="option"]')];
        if (event.key === 'Escape') {
            closeSuggestions();
            return;
        }
        if (suggestions.hidden || options.length === 0) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            activeIndex = activeIndex < 0
                ? (direction > 0 ? 0 : options.length - 1)
                : (activeIndex + direction + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === activeIndex)));
            input.setAttribute('aria-activedescendant', options[activeIndex].id);
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            options[activeIndex].click();
        }
    });
    clear?.addEventListener('click', () => {
        input.value = '';
        clear.hidden = true;
        closeSuggestions();
        input.focus();
    });
    form.addEventListener('submit', () => saveHistory(input.value));
    filterForm?.addEventListener('submit', () => {
        const params = new URLSearchParams();
        for (const [key, value] of new FormData(filterForm)) {
            const normalizedValue = String(value).trim();
            if (normalizedValue) params.append(key, normalizedValue);
        }
        const selectedValue = params.get('category') || params.get('skills[]');
        const selectedLabel = sources.find((source) => source.value === selectedValue)?.label;
        saveHistory(params.get('search') || selectedLabel || '', params);
    });
    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) closeSuggestions();
    });
    clear.hidden = input.value.length === 0;
});

document.querySelectorAll('[data-search-history]').forEach((container) => {
    const list = container.querySelector('[data-search-history-list]');
    const clear = container.querySelector('[data-search-history-clear]');
    const render = () => {
        const history = readHistory();
        list.replaceChildren();
        history.forEach((entry) => {
            const link = document.createElement('a');
            const destination = new URL(container.dataset.searchUrl || '/search', window.location.origin);
            entry.params.forEach(([key, value]) => destination.searchParams.append(key, value));
            link.href = destination.toString();
            link.textContent = entry.term;
            list.append(link);
        });
        container.hidden = history.length === 0;
    };
    clear?.addEventListener('click', () => {
        localStorage.removeItem(historyKey);
        render();
    });
    render();
});

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
