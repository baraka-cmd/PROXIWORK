document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    const closeSidebar = (sidebar) => {
        if (!sidebar) {
            return;
        }

        sidebar.classList.remove('is-open');
        sidebar.setAttribute('aria-hidden', 'true');
        body.classList.remove('sidebar-is-open');

        document.querySelectorAll('[data-mobile-menu-toggle]').forEach((toggle) => {
            toggle.setAttribute('aria-expanded', 'false');
        });
    };

    const openSidebar = (sidebar, toggle) => {
        sidebar.classList.add('is-open');
        sidebar.setAttribute('aria-hidden', 'false');
        body.classList.add('sidebar-is-open');

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
    };

    document.querySelectorAll('[data-mobile-menu-toggle]').forEach((toggle) => {
        const targetSelector = toggle.getAttribute('data-mobile-menu-toggle');
        const sidebar = targetSelector ? document.querySelector(targetSelector) : null;

        if (!sidebar) {
            return;
        }

        toggle.addEventListener('click', () => {
            if (sidebar.classList.contains('is-open')) {
                closeSidebar(sidebar);
            } else {
                openSidebar(sidebar, toggle);
            }
        });
    });

    document.querySelectorAll('[data-sidebar-overlay], [data-sidebar-close]').forEach((control) => {
        control.addEventListener('click', () => {
            const sidebar = document.querySelector('[data-dashboard-sidebar]');

            if (sidebar) {
                closeSidebar(sidebar);
            }
        });
    });

    const currentPath = window.location.pathname.replace(/\/$/, '') || '/';

    document.querySelectorAll('[data-nav-link]').forEach((link) => {
        const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/$/, '') || '/';

        const exactMatch = link.hasAttribute('data-nav-exact') && currentPath === linkPath;
        const prefixMatch = !link.hasAttribute('data-nav-exact') && linkPath !== '/' && currentPath.startsWith(linkPath);
        const rootMatch = linkPath === '/' && currentPath === '/';

        if (exactMatch || prefixMatch || rootMatch) {
            link.classList.add('is-active');
            link.setAttribute('aria-current', 'page');
        }
    });

    document.querySelectorAll('[data-alert-dismiss]').forEach((dismissButton) => {
        dismissButton.addEventListener('click', () => {
            const alert = dismissButton.closest('[role="alert"]');

            if (!alert) {
                return;
            }

            alert.setAttribute('hidden', '');
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        const targetId = toggle.getAttribute('data-password-toggle');
        const input = targetId ? document.getElementById(targetId) : null;
        const icon = toggle.querySelector('i');

        if (!input) {
            return;
        }

        toggle.addEventListener('click', () => {
            const isPassword = input.type === 'password';

            input.type = isPassword ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(isPassword));
            toggle.setAttribute('aria-label', isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe');

            if (icon) {
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            }
        });
    });
});
