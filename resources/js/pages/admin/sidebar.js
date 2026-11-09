document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.admin-layout [data-dashboard-sidebar]');
    const nav = sidebar?.querySelector('[data-admin-navigation]');
    const pin = nav?.querySelector('[data-admin-pin]');
    const content = document.querySelector('.admin-layout .dashboard-content');

    if (!sidebar || !nav || !pin || !content) {
        return;
    }

    nav.querySelectorAll('[data-admin-group-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const group = toggle.closest('[data-admin-group]');
            const children = group?.querySelector('.admin-nav-group__items');
            const expanded = toggle.getAttribute('aria-expanded') === 'true';

            if (!group || !children) {
                return;
            }

            toggle.setAttribute('aria-expanded', String(!expanded));
            children.hidden = expanded;
        });
    });

    pin.addEventListener('click', () => {
        const pinned = pin.getAttribute('aria-pressed') !== 'true';
        pin.setAttribute('aria-pressed', String(pinned));
        pin.setAttribute('aria-label', pinned ? 'Désépingler la barre' : 'Épingler la barre développée');
        pin.title = pinned ? 'Désépingler la barre' : 'Épingler la barre';
        pin.querySelector('span').textContent = pinned ? 'Barre épinglée' : 'Épingler la barre';
        sidebar.classList.toggle('is-pinned', pinned);
        content.classList.toggle('is-admin-sidebar-pinned', pinned);
    });
});
