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

Chart.register(CategoryScale, Legend, LineController, LineElement, LinearScale, PointElement, Tooltip);

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-analytics]');
    if (!root) return;

    const customButton = document.querySelector('[data-analytics-custom]');
    const customFields = document.querySelector('[data-analytics-custom-fields]');

    customButton?.addEventListener('click', () => {
        if (customFields) {
            customFields.hidden = false;
        }
    });

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

    const syncAnalyticsCharts = () => {
        const styles = getComputedStyle(root);
        const colors = {
            primary: styles.getPropertyValue('--color-primary').trim() || '#0f766e',
            success: styles.getPropertyValue('--color-success').trim() || '#15803d',
            warning: styles.getPropertyValue('--color-warning').trim() || '#92400e',
            info: styles.getPropertyValue('--color-info').trim() || '#0369a1',
            muted: styles.getPropertyValue('--color-text-muted').trim() || '#64748b',
            border: styles.getPropertyValue('--color-border').trim() || '#e2e8f0',
            surface: styles.getPropertyValue('--color-surface').trim() || '#ffffff',
            text: styles.getPropertyValue('--color-text').trim() || '#0f172a',
        };
        const palette = [colors.primary, colors.info, colors.success, colors.warning, '#a78bfa', '#fb7185'];

        Chart.defaults.color = colors.muted;
        Chart.defaults.borderColor = colors.border;

        Object.values(Chart.instances).forEach((chart) => {
            if (!root.contains(chart.canvas)) return;

            chart.options.color = colors.muted;
            Object.values(chart.options.scales || {}).forEach((scale) => {
                if (scale.ticks) scale.ticks.color = colors.muted;
                if (scale.grid) scale.grid.color = colors.border;
            });

            const legendLabels = chart.options.plugins?.legend?.labels;
            if (legendLabels) legendLabels.color = colors.muted;

            const tooltip = chart.options.plugins?.tooltip;
            if (tooltip) {
                tooltip.backgroundColor = colors.surface;
                tooltip.titleColor = colors.text;
                tooltip.bodyColor = colors.text;
                tooltip.borderColor = colors.border;
                tooltip.borderWidth = 1;
            }

            chart.data.datasets.forEach((item, index) => {
                item.borderColor = palette[index % palette.length];
                item.backgroundColor = palette[index % palette.length];
                item.pointBackgroundColor = palette[index % palette.length];
            });

            chart.update('none');
        });
    };

    document.addEventListener('proxiwork:theme-change', syncAnalyticsCharts);
    syncAnalyticsCharts();
});