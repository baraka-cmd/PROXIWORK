const STORAGE_KEY = 'proxiwork-theme';
const VALID_THEMES = new Set(['light', 'dark']);

const getSystemTheme = () => (
    window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
);

const getStoredTheme = () => {
    try {
        const storedTheme = window.localStorage.getItem(STORAGE_KEY);

        return VALID_THEMES.has(storedTheme) ? storedTheme : null;
    } catch (error) {
        return null;
    }
};

const updateThemeControls = (theme) => {
    const isDark = theme === 'dark';

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const label = isDark ? 'Mode clair' : 'Mode sombre';
        const accessibleLabel = 'Basculer entre le mode clair et le mode sombre';
        const icon = button.querySelector('[data-theme-toggle-icon]');
        const text = button.querySelector('[data-theme-toggle-label]');

        button.setAttribute('aria-pressed', String(isDark));
        button.setAttribute('aria-label', accessibleLabel);
        button.setAttribute('title', accessibleLabel);

        if (text) {
            text.textContent = label;
        }

        if (icon) {
            icon.classList.toggle('fa-sun', isDark);
            icon.classList.toggle('fa-moon', !isDark);
        }
    });
};

const applyTheme = (theme) => {
    const safeTheme = VALID_THEMES.has(theme) ? theme : 'light';

    document.documentElement.dataset.theme = safeTheme;
    updateThemeControls(safeTheme);
};

const persistTheme = (theme) => {
    try {
        window.localStorage.setItem(STORAGE_KEY, theme);
    } catch (error) {
        // The current page still updates if browser storage is unavailable.
    }
};

const initializeTheme = () => {
    const storedTheme = getStoredTheme();
    applyTheme(storedTheme || getSystemTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const currentTheme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

            applyTheme(nextTheme);
            persistTheme(nextTheme);
        });
    });

    window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_KEY || event.key === null) {
            applyTheme(getStoredTheme() || getSystemTheme());
        }
    });

    const colorSchemePreference = window.matchMedia?.('(prefers-color-scheme: dark)');

    colorSchemePreference?.addEventListener?.('change', () => {
        if (!getStoredTheme()) {
            applyTheme(getSystemTheme());
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTheme, { once: true });
} else {
    initializeTheme();
}
