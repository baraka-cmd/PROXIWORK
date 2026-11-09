document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.admin-layout [data-dashboard-sidebar]');
    const nav = sidebar?.querySelector('[data-admin-navigation]');
    const pin = nav?.querySelector('[data-admin-pin]');
    const content = document.querySelector('.admin-layout .dashboard-content');
    const overlay = document.querySelector('.admin-layout [data-sidebar-overlay]');
    const mobileToggle = document.querySelector('.admin-layout [data-mobile-menu-toggle="#dashboard-sidebar"]');
    const desktopViewport = window.matchMedia('(min-width: 1024px)');
    const storageKey = 'proxiwork.admin.sidebar.pinned';

    if (!sidebar || !nav || !content) {
        return;
    }

    const setGroupState = (group, expanded) => {
        const toggle = group.querySelector('[data-admin-group-toggle]');
        const children = group.querySelector('.admin-nav-group__items');

        if (!toggle || !children) {
            return;
        }

        toggle.setAttribute('aria-expanded', String(expanded));
        children.hidden = !expanded;
    };

    nav.querySelectorAll('[data-admin-group-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const currentGroup = toggle.closest('[data-admin-group]');
            const children = currentGroup?.querySelector('.admin-nav-group__items');

            if (!currentGroup || !children) {
                return;
            }

            const shouldExpand = toggle.getAttribute('aria-expanded') !== 'true';

            if (shouldExpand) {
                nav.querySelectorAll('[data-admin-group]').forEach((group) => {
                    if (group !== currentGroup) {
                        setGroupState(group, false);
                    }
                });
            }

            setGroupState(currentGroup, shouldExpand);
        });
    });

    const readPinnedPreference = () => {
        try {
            return window.localStorage.getItem(storageKey) === 'true';
        } catch {
            return false;
        }
    };

    const applyPinnedState = (pinned) => {
        const shouldPin = desktopViewport.matches && pinned;

        sidebar.classList.toggle('is-pinned', shouldPin);
        content.classList.toggle('is-admin-sidebar-pinned', shouldPin);

        if (pin) {
            pin.setAttribute('aria-pressed', String(shouldPin));
            pin.setAttribute('aria-label', shouldPin ? 'Désépingler la barre' : 'Épingler la barre développée');
            pin.title = shouldPin ? 'Désépingler la barre' : 'Épingler la barre développée';

            const label = pin.querySelector('span');
            if (label) {
                label.textContent = shouldPin ? 'Barre épinglée' : 'Épingler la barre';
            }
        }
    };

    let pinnedPreference = readPinnedPreference();
    applyPinnedState(pinnedPreference);

    pin?.addEventListener('click', () => {
        pinnedPreference = !sidebar.classList.contains('is-pinned');

        try {
            window.localStorage.setItem(storageKey, String(pinnedPreference));
        } catch {
            // La navigation reste utilisable même si le stockage local est indisponible.
        }

        applyPinnedState(pinnedPreference);
    });

    desktopViewport.addEventListener('change', () => {
        applyPinnedState(pinnedPreference);
        syncOverlay();
    });

    function syncOverlay() {
        overlay?.classList.toggle('is-visible', sidebar.classList.contains('is-open'));
    }

    mobileToggle?.addEventListener('click', syncOverlay);
    overlay?.addEventListener('click', () => window.requestAnimationFrame(syncOverlay));
    sidebar.querySelector('[data-sidebar-close]')?.addEventListener('click', () => window.requestAnimationFrame(syncOverlay));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            window.requestAnimationFrame(syncOverlay);
        }
    });

    syncOverlay();
});
