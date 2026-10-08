import {
    CategoryScale,
    Chart,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(CategoryScale, Chart, Legend, LineController, LineElement, LinearScale, PointElement, Tooltip);

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-analytics]');
    if (!root) return;

    const data = JSON.parse(root.dataset.analytics);
    const labels = data.labels ?? [];

    const line = (id, datasets) => {
        const canvas = document.getElementById(id);
        if (!canvas) return;

        new Chart(canvas, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } },
            },
        });
    };

    const dataset = (label, values) => ({
        label,
        data: values,
        fill: false,
        tension: 0.25,
    });

    line('admin-analytics-growth', [
        dataset('Utilisateurs', data.activity.users),
        dataset('Professionnels', data.activity.professionals),
        dataset('Services', data.activity.services),
    ]);

    line('admin-analytics-operations', [
        dataset('Demandes', data.activity.requests),
        dataset('Commandes', data.activity.orders),
    ]);

    line('admin-analytics-support', [
        dataset('Tickets créés', data.activity.support_created),
        dataset('Tickets résolus', data.activity.support_resolved),
    ]);

    const currencies = new Set([
        ...Object.keys(data.financial.payment_volume ?? {}),
        ...Object.keys(data.financial.commissions ?? {}),
    ]);

    const financial = [];
    currencies.forEach((currency) => {
        financial.push(dataset('Paiements ' + currency, data.financial.payment_volume[currency] ?? []));
        financial.push(dataset('Commissions ' + currency, data.financial.commissions[currency] ?? []));
    });

    line('admin-analytics-finance', financial);
});
