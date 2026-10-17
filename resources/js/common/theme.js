const STORAGE_KEY = 'proxiwork-theme-preference';
const VALID_THEMES = new Set(['light', 'dark']);

const getSystemTheme = () => (
    window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
);

const readSavedTheme = () => {
    try {
        const saved = window.localStorage.getItem(STORAGE_KEY);
        return VALID_THEMES.has(saved) ? saved : null;
    } catch {
        return null;
    }
};

const applyTheme = (theme, { persist = false } = {}) => {
    if (!VALID_THEMES.has(theme)) {
        return;
    }

    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const isDark = theme === 'dark';
        const label = 'Mode sombre';
        const title = isDark ? 'Thème sombre activé — passer au thème clair' : 'Activer le thème sombre';

        button.setAttribute('aria-label', label);
        button.setAttribute('title', title);
        button.setAttribute('aria-pressed', String(isDark));

        button.querySelector('[data-theme-icon="dark"]')?.toggleAttribute('hidden', isDark);
        button.querySelector('[data-theme-icon="light"]')?.toggleAttribute('hidden', !isDark);
    });

    if (persist) {
        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch {
            // The theme still works for this page when storage is unavailable.
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(document.documentElement.dataset.theme || getSystemTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme, { persist: true });
        });
    });

    window.addEventListener('storage', (event) => {
        if (event.key !== STORAGE_KEY) {
            return;
        }

        const savedTheme = VALID_THEMES.has(event.newValue) ? event.newValue : getSystemTheme();
        applyTheme(savedTheme);
    });

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
        if (readSavedTheme() === null) {
            applyTheme(event.matches ? 'dark' : 'light');
        }
    });
});
