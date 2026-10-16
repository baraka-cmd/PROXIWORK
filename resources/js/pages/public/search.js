const pages = document.querySelectorAll('[data-directory-page]');

const historyKey = 'proxiwork.search.history';

const readHistory = () => {
    try {
        const history = JSON.parse(localStorage.getItem(historyKey) || '[]');
        return Array.isArray(history) ? history.filter((entry) => typeof entry === 'string').slice(0, 5) : [];
    } catch {
        return [];
    }
};

const saveHistory = (value) => {
    const normalized = value.trim();
    if (!normalized) return;
    const history = [normalized, ...readHistory().filter((entry) => entry.toLowerCase() !== normalized.toLowerCase())].slice(0, 5);
    try {
        localStorage.setItem(historyKey, JSON.stringify(history));
    } catch {
        // Search history is optional; private browsing or storage restrictions
        // must never prevent a search from being submitted.
    }
};

document.querySelectorAll('[data-service-search-form]').forEach((form) => {
    const input = form.querySelector('input[name="search"]');
    const suggestions = form.querySelector('[data-search-suggestions]');
    const clear = form.querySelector('[data-search-clear]');
    if (!input || !suggestions) return;

    const sources = [
        ...document.querySelectorAll('select[name="category"] option'),
        ...document.querySelectorAll('input[name="skills[]"]'),
    ].map((element) => ({
        label: element.textContent?.trim() || element.value,
        value: element.value,
        category: element.matches('option') ? 'Catégorie' : 'Compétence',
    })).filter((source) => source.value);

    const closeSuggestions = () => {
        suggestions.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    };

    const renderSuggestions = () => {
        const term = input.value.trim().toLowerCase();
        suggestions.replaceChildren();
        if (term.length < 2) {
            closeSuggestions();
            return;
        }
        const matches = sources.filter((source) => source.label.toLowerCase().includes(term)).slice(0, 6);
        matches.forEach((match) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'search-suggestion';
            button.setAttribute('role', 'option');
            const label = document.createElement('span');
            label.textContent = match.label;

            const category = document.createElement('small');
            category.textContent = match.category;

            button.replaceChildren(label, category);
            button.addEventListener('click', () => {
                input.value = match.label;
                closeSuggestions();
                form.requestSubmit();
            });
            suggestions.append(button);
        });
        suggestions.hidden = matches.length === 0;
        input.setAttribute('aria-expanded', String(matches.length > 0));
    };

    input.addEventListener('input', () => {
        if (clear) clear.hidden = input.value.length === 0;
        renderSuggestions();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' && !suggestions.hidden) {
            const firstSuggestion = suggestions.querySelector('.search-suggestion');
            if (firstSuggestion) {
                event.preventDefault();
                firstSuggestion.focus();
            }
        }

        if (event.key === 'Escape') {
            closeSuggestions();
        }
    });
    clear?.addEventListener('click', () => {
        input.value = '';
        clear.hidden = true;
        closeSuggestions();
        input.focus();
    });
    form.addEventListener('submit', () => saveHistory(input.value));
    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) closeSuggestions();
    });
    if (clear) clear.hidden = input.value.length === 0;
});

document.querySelectorAll('[data-results-sort]').forEach((select) => {
    select.addEventListener('change', () => {
        const form = select.form;
        if (form && typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else if (form) {
            form.submit();
        }
    });
});

document.querySelectorAll('[data-search-history]').forEach((container) => {
    const list = container.querySelector('[data-search-history-list]');
    const clear = container.querySelector('[data-search-history-clear]');
    const render = () => {
        const history = readHistory();
        list.replaceChildren();
        history.forEach((term) => {
            const link = document.createElement('a');
            link.href = '/search?search=' + encodeURIComponent(term);
            link.textContent = term;
            list.append(link);
        });
        container.hidden = history.length === 0;
    };
    clear?.addEventListener('click', () => {
        try {
            localStorage.removeItem(historyKey);
        } catch {
            // The history panel remains optional when storage is unavailable.
        }
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
