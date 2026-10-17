const STORAGE_KEY = 'proxiwork-theme';
const root = document.documentElement;
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');

const readPreference = () => {
    try {
        const preference = window.localStorage.getItem(STORAGE_KEY);
        return preference === 'light' || preference === 'dark' ? preference : null;
    } catch {
        return null;
    }
};

const applyTheme = (theme) => {
    const isDark = theme === 'dark';
    root.dataset.theme = isDark ? 'dark' : 'light';

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        const icon = toggle.querySelector('[data-theme-icon]');
        const label = toggle.querySelector('[data-theme-label]');
        const actionLabel = isDark ? 'Activer le mode clair' : 'Activer le mode sombre';

        toggle.setAttribute('aria-label', actionLabel);
        toggle.setAttribute('aria-pressed', String(isDark));
        toggle.setAttribute('title', actionLabel);

        if (icon) {
            icon.classList.toggle('fa-sun', isDark);
            icon.classList.toggle('fa-moon', !isDark);
        }

        if (label) {
            label.textContent = isDark ? 'Mode clair' : 'Mode sombre';
        }
    });
};

const preference = readPreference();
applyTheme(preference ?? (systemTheme.matches ? 'dark' : 'light'));

document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try {
            window.localStorage.setItem(STORAGE_KEY, nextTheme);
        } catch {
            // The theme remains usable for this page when storage is unavailable.
        }
        applyTheme(nextTheme);
    });
});

systemTheme.addEventListener('change', (event) => {
    if (readPreference() === null) {
        applyTheme(event.matches ? 'dark' : 'light');
    }
});
