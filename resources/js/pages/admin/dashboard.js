document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.admin-dashboard-period').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (!button) return;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    });
});