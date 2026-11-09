import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Legend, LineController, LineElement, LinearScale, PointElement, Tooltip);

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-admin-dashboard]');
    if (!root) return;

    const periodForm = document.querySelector('.admin-dashboard-period');
    const periodSelect = periodForm?.querySelector('[data-period-select]');
    const customRange = periodForm?.querySelector('[data-custom-range]');
    periodSelect?.addEventListener('change', () => {
        if (customRange) customRange.hidden = periodSelect.value !== 'custom';
        if (periodSelect.value !== 'custom') periodForm?.requestSubmit();
    });
    periodForm?.addEventListener('submit', () => {
        const button = periodForm.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }
    });

    let data;
    try {
        data = JSON.parse(root.dataset.charts ?? '{}');
    } catch {
        return;
    }

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' }, tooltip: { intersect: false } },
    };
    const growthCanvas = document.getElementById('admin-dashboard-growth');
    if (growthCanvas) {
        new Chart(growthCanvas, {
            type: 'line',
            data: { labels: data.labels ?? [], datasets: [
                { label: 'Clients', data: data.registrations?.clients ?? [], borderColor: '#0f766e', backgroundColor: '#0f766e', tension: 0.25 },
                { label: 'Professionnels', data: data.registrations?.professionals ?? [], borderColor: '#2563eb', backgroundColor: '#2563eb', tension: 0.25 },
            ] },
            options: { ...chartOptions, interaction: { mode: 'index', intersect: false }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }

    const orderCanvas = document.getElementById('admin-dashboard-orders');
    const orderEntries = data.orderStatuses ?? [];
    if (orderCanvas && orderEntries.length) {
        new Chart(orderCanvas, {
            type: 'doughnut',
            data: { labels: orderEntries.map(({ label }) => label), datasets: [{ data: orderEntries.map(({ value }) => value), backgroundColor: ['#0f766e', '#2563eb', '#f59e0b', '#dc2626', '#64748b', '#14b8a6', '#8b5cf6'] }] },
            options: { ...chartOptions, onClick: (_event, elements) => {
                const element = elements[0];
                if (element) {
                    const url = orderEntries[element.index]?.url;
                    if (url) window.location.assign(url);
                }
            } },
        });
    }

    const categoryCanvas = document.getElementById('admin-dashboard-categories');
    const categories = data.categories ?? [];
    if (categoryCanvas && categories.length) {
        new Chart(categoryCanvas, {
            type: 'bar',
            data: { labels: categories.map((category) => category.label), datasets: [{ label: 'Demandes', data: categories.map((category) => category.value), backgroundColor: '#0f766e', borderRadius: 6 }] },
            options: { ...chartOptions, indexAxis: 'y', plugins: { ...chartOptions.plugins, legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }, onClick: (_event, elements) => {
                const element = elements[0];
                if (element && categories[element.index]?.url) window.location.assign(categories[element.index].url);
            } },
        });
    }
});
