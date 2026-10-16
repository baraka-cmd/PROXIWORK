const dashboard = document.querySelector('[data-client-dashboard]');

// Native navigation already exposes its own progress state. Marking links busy
// on click can leave assistive technology reporting a permanent busy state
// when navigation is interrupted, so this page deliberately adds no fake state.
if (dashboard) {
    dashboard.querySelectorAll('a[aria-busy="true"]').forEach((link) => {
        link.removeAttribute('aria-busy');
    });
}
