const THEME_STORAGE_KEY = 'proxiwork-theme';
const VALID_THEMES = new Set(['light', 'dark']);

const getStoredTheme = () => {
    try {
        const storedTheme = window.localStorage.getItem(THEME_STORAGE_KEY);
        return VALID_THEMES.has(storedTheme) ? storedTheme : null;
    } catch {
        return null;
    }
};

const getSystemTheme = () => (
    window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
);

const applyTheme = (theme) => {
    const resolvedTheme = VALID_THEMES.has(theme) ? theme : getSystemTheme();
    const root = document.documentElement;

    root.dataset.theme = resolvedTheme;
    root.style.colorScheme = resolvedTheme;

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        const nextTheme = resolvedTheme === 'dark' ? 'light' : 'dark';
        const actionLabel = nextTheme === 'dark' ? 'Activer le mode sombre' : 'Activer le mode clair';
        const icon = toggle.querySelector('[data-theme-toggle-icon]');
        const label = toggle.querySelector('[data-theme-toggle-label]');

        toggle.setAttribute('aria-label', 'Basculer entre le mode clair et le mode sombre');
        toggle.setAttribute('title', actionLabel);
        toggle.setAttribute('aria-pressed', String(resolvedTheme === 'dark'));

        if (icon) {
            icon.classList.toggle('fa-moon', resolvedTheme === 'dark');
            icon.classList.toggle('fa-sun', resolvedTheme === 'light');
        }

        if (label) {
            label.textContent = resolvedTheme === 'dark' ? 'Mode sombre' : 'Mode clair';
        }
    });
};

const saveTheme = (theme) => {
    try {
        window.localStorage.setItem(THEME_STORAGE_KEY, theme);
    } catch {
        // The theme still works for this page if browser storage is unavailable.
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const storedTheme = getStoredTheme();
    applyTheme(storedTheme || getSystemTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            saveTheme(nextTheme);
            applyTheme(nextTheme);
        });
    });

    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
    const syncSystemTheme = (event) => {
        if (!getStoredTheme()) {
            applyTheme(event.matches ? 'dark' : 'light');
        }
    };

    if (typeof systemTheme.addEventListener === 'function') {
        systemTheme.addEventListener('change', syncSystemTheme);
    } else if (typeof systemTheme.addListener === 'function') {
        systemTheme.addListener(syncSystemTheme);
    }
    window.addEventListener('storage', (event) => {
        if (event.key === THEME_STORAGE_KEY) {
            applyTheme(getStoredTheme() || getSystemTheme());
        }
    });

});
