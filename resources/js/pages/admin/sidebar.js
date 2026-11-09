document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.dashboard-layout [data-dashboard-sidebar]');
    const navigation = sidebar?.querySelector('[data-admin-navigation], .dashboard-sidebar__nav');
    const pinButton = navigation?.querySelector('[data-sidebar-pin], [data-admin-pin]');
    const content = document.querySelector('.dashboard-layout .dashboard-content');

    if (!sidebar || !navigation || !pinButton || !content) return;

    const desktopHover = window.matchMedia('(min-width: 1024px) and (hover: hover)');
    const isAdmin = document.body.classList.contains('admin-layout');
    const workspace = window.location.pathname.startsWith('/professional') ? 'professional' : 'client';
    const pinStorageKey = isAdmin ? 'proxiwork-admin-sidebar-pinned' : 'proxiwork-' + workspace + '-sidebar-pinned';
    const groups = Array.from(navigation.querySelectorAll('[data-admin-group]'));

    const setGroupExpanded = (group, expanded) => {
        const toggle = group.querySelector('[data-admin-group-toggle]');
        const items = group.querySelector('.admin-nav-group__items');
        if (!toggle || !items) return;
        toggle.setAttribute('aria-expanded', String(expanded));
        items.hidden = !expanded;
        group.classList.toggle('is-expanded', expanded);
    };

    const closeOtherGroups = (currentGroup) => {
        groups.forEach((group) => {
            if (group !== currentGroup) setGroupExpanded(group, false);
        });
    };

    const updatePinLabel = (pinned) => {
        const label = pinButton.querySelector('span');
        const text = pinned ? 'Barre épinglée' : 'Épingler la barre';
        const aria = pinned ? 'Désépingler la barre latérale' : 'Épingler la barre latérale';
        pinButton.setAttribute('aria-pressed', String(pinned));
        pinButton.setAttribute('aria-label', aria);
        pinButton.title = aria;
        if (label) label.textContent = text;
    };

    const applyPinnedState = (pinned) => {
        const shouldPin = desktopHover.matches && pinned;
        sidebar.classList.toggle('is-pinned', shouldPin);
        content.classList.toggle('is-admin-sidebar-pinned', isAdmin && shouldPin);
        content.classList.toggle('is-dashboard-sidebar-pinned', !isAdmin && shouldPin);
        updatePinLabel(shouldPin);
    };

    let savedPinned = false;
    try {
        savedPinned = window.localStorage.getItem(pinStorageKey) === 'true';
    } catch {
        savedPinned = false;
    }
    applyPinnedState(savedPinned);

    groups.forEach((group) => {
        const toggle = group.querySelector('[data-admin-group-toggle]');
        toggle?.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            if (!expanded) closeOtherGroups(group);
            setGroupExpanded(group, !expanded);
        });
    });

    pinButton.addEventListener('click', () => {
        savedPinned = !sidebar.classList.contains('is-pinned');
        try {
            window.localStorage.setItem(pinStorageKey, String(savedPinned));
        } catch {
            // La navigation reste utilisable si le stockage local est indisponible.
        }
        applyPinnedState(savedPinned);
    });

    const handleViewportChange = () => applyPinnedState(savedPinned);
    if (typeof desktopHover.addEventListener === 'function') {
        desktopHover.addEventListener('change', handleViewportChange);
    } else {
        desktopHover.addListener(handleViewportChange);
    }
});