document.addEventListener('DOMContentLoaded', () => {
    const themeButtons = Array.from(document.querySelectorAll('[data-theme-toggle]'));
    const storageKey = 'proxiwork-theme';

    const getCurrentTheme = () => (
        document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light'
    );

    const updateThemeControls = (theme) => {
        const isDark = theme === 'dark';

        themeButtons.forEach((button) => {
            const label = isDark ? 'Activer le mode clair' : 'Activer le mode sombre';
            const icon = button.querySelector('[data-theme-icon]');
            const text = button.querySelector('[data-theme-label]');

            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', String(isDark));

            if (icon) {
                icon.classList.toggle('fa-sun', isDark);
                icon.classList.toggle('fa-moon', !isDark);
            }

            if (text) {
                text.textContent = isDark ? 'Mode clair' : 'Mode sombre';
            }
        });
    };

    const applyTheme = (theme, persist = false) => {
        const safeTheme = theme === 'dark' ? 'dark' : 'light';

        document.documentElement.dataset.theme = safeTheme;
        document.documentElement.style.colorScheme = safeTheme;
        updateThemeControls(safeTheme);

        if (persist) {
            try {
                window.localStorage.setItem(storageKey, safeTheme);
            } catch {
                // The theme still works for this page if storage is unavailable.
            }
        }
    };

    applyTheme(getCurrentTheme());

    themeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            applyTheme(getCurrentTheme() === 'dark' ? 'light' : 'dark', true);
        });
    });

    window.addEventListener('storage', (event) => {
        if (event.key !== storageKey && event.key !== null) {
            return;
        }

        applyTheme(event.newValue === 'dark' ? 'dark' : 'light');
    });
});
