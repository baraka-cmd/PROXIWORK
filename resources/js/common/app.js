document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const compactViewport = window.matchMedia('(max-width: 1023px)');
    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    const getFocusableElements = (container) => (
        Array.from(container.querySelectorAll(focusableSelector))
            .filter((element) => !element.hasAttribute('hidden'))
            .filter((element) => element.getAttribute('aria-hidden') !== 'true')
    );

    const syncBodyScrollLock = () => {
        const hasOpenNavigation = document.querySelector(
            '[data-mobile-menu-toggle][aria-expanded="true"]',
        );

        body.classList.toggle('navigation-is-open', Boolean(hasOpenNavigation));
    };

    const setToggleState = (toggle, isOpen) => {
        if (!toggle) {
            return;
        }

        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute(
            'aria-label',
            isOpen ? 'Fermer le menu' : 'Ouvrir le menu',
        );

        const icon = toggle.querySelector('i');

        if (icon) {
            icon.classList.toggle('fa-bars', !isOpen);
            icon.classList.toggle('fa-xmark', isOpen);
        }
    };

    const closeNavigation = (navigation, toggle, { restoreFocus = true } = {}) => {
        if (!navigation) {
            return;
        }

        const activeElement = document.activeElement;

        if (
            restoreFocus
            && toggle
            && activeElement
            && navigation.contains(activeElement)
        ) {
            toggle.focus();
        }

        navigation.classList.remove('is-open');
        navigation.setAttribute('aria-hidden', 'true');

        setToggleState(toggle, false);
        syncBodyScrollLock();
    };

    const openNavigation = (navigation, toggle) => {
        if (!navigation || !compactViewport.matches) {
            return;
        }

        navigation.classList.add('is-open');
        navigation.setAttribute('aria-hidden', 'false');
        setToggleState(toggle, true);
        syncBodyScrollLock();

        const focusableElements = getFocusableElements(navigation);

        if (focusableElements.length > 0) {
            requestAnimationFrame(() => {
                focusableElements[0].focus();
            });
        }
    };

    const navigationControllers = [];

    document.querySelectorAll('[data-mobile-menu-toggle]').forEach((toggle) => {
        const targetSelector = toggle.getAttribute('data-mobile-menu-toggle');
        const navigation = targetSelector
            ? document.querySelector(targetSelector)
            : null;

        if (!navigation) {
            return;
        }

        const controller = {
            navigation,
            toggle,
            isOpen: () => toggle.getAttribute('aria-expanded') === 'true',
            close: (options = {}) => closeNavigation(navigation, toggle, options),
            open: () => openNavigation(navigation, toggle),
        };

        navigationControllers.push(controller);

        const shouldStartClosed = compactViewport.matches;

        navigation.classList.toggle('is-open', false);
        navigation.setAttribute('aria-hidden', String(shouldStartClosed));

        if (shouldStartClosed) {
            setToggleState(toggle, false);
        }

        toggle.addEventListener('click', () => {
            if (controller.isOpen()) {
                controller.close();
            } else {
                controller.open();
            }
        });

        navigation.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');

            if (!link || !compactViewport.matches) {
                return;
            }

            controller.close({ restoreFocus: false });
        });

        navigation.addEventListener('keydown', (event) => {
            if (!controller.isOpen()) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                controller.close();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusableElements = getFocusableElements(navigation);

            if (focusableElements.length === 0) {
                event.preventDefault();
                controller.close();
                return;
            }

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (event.shiftKey && document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            } else if (!event.shiftKey && document.activeElement === lastElement) {
                event.preventDefault();
                firstElement.focus();
            }
        });
    });

    document.querySelectorAll('[data-sidebar-overlay], [data-sidebar-close]').forEach((control) => {
        control.addEventListener('click', () => {
            const sidebar = control.closest('.dashboard-shell')?.querySelector('[data-dashboard-sidebar]')
                || document.querySelector('[data-dashboard-sidebar]');

            if (!sidebar) {
                return;
            }

            const toggle = document.querySelector(
                '[data-mobile-menu-toggle][aria-controls="' + sidebar.id + '"]',
            );

            closeNavigation(sidebar, toggle);
        });
    });

    const syncResponsiveNavigation = () => {
        navigationControllers.forEach(({ navigation, toggle, isOpen, close }) => {
            if (!compactViewport.matches) {
                close({ restoreFocus: false });
                navigation.setAttribute('aria-hidden', navigation.hasAttribute('data-dashboard-sidebar') ? 'false' : 'true');
                return;
            }

            if (!isOpen()) {
                navigation.setAttribute('aria-hidden', 'true');
                setToggleState(toggle, false);
            }
        });

        syncBodyScrollLock();
    };

    compactViewport.addEventListener('change', syncResponsiveNavigation);
    syncResponsiveNavigation();

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const openController = navigationControllers.find((controller) => controller.isOpen());

        if (openController) {
            event.preventDefault();
            openController.close();
        }
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
