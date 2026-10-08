const dashboard = document.querySelector('[data-client-dashboard]');

if (dashboard) {
    dashboard.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            link.setAttribute('aria-busy', 'true');
        });
    });
}
