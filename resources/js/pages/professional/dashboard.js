import Chart from 'chart.js/auto';

const root = document.querySelector('[data-professional-dashboard]');
const payload = document.getElementById('professional-dashboard-chart-data');

if (root && payload) {
    let dashboardCharts = {};

    try {
        dashboardCharts = JSON.parse(payload.textContent || '{}');
    } catch {
        dashboardCharts = {};
    }

    const styles = getComputedStyle(root);
    const primary = styles.getPropertyValue('--color-primary').trim() || '#3155a6';
    const success = styles.getPropertyValue('--color-success').trim() || '#25835a';
    const warning = styles.getPropertyValue('--color-warning').trim() || '#b7791f';
    const info = styles.getPropertyValue('--color-info').trim() || '#2878a8';
    const muted = styles.getPropertyValue('--color-text-muted').trim() || '#6b7280';
    const border = styles.getPropertyValue('--color-border').trim() || '#e5e7eb';
    const palette = [primary, success, warning, info, '#7c5cc4', '#c05b73'];

    Chart.defaults.font.family = 'Poppins, system-ui, sans-serif';
    Chart.defaults.color = muted;
    Chart.defaults.responsive = true;
    Chart.defaults.maintainAspectRatio = false;

    const readableDate = (label) => {
        if (!/^\d{4}-\d{2}(-\d{2})?$/.test(label)) {
            return label;
        }

        const date = new Date(label.length === 7 ? `${label}-01T12:00:00` : `${label}T12:00:00`);
        return new Intl.DateTimeFormat(document.documentElement.lang || 'fr-FR', {
            day: label.length === 10 ? '2-digit' : undefined,
            month: 'short',
        }).format(date);
    };

    const commonOptions = {
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } },
            tooltip: { padding: 10, displayColors: true },
        },
        scales: {
            x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
            y: { beginAtZero: true, grid: { color: border }, ticks: { precision: 0 } },
        },
    };

    const activity = dashboardCharts.activity;
    const activityCanvas = root.querySelector('[data-chart="activity"]');
    if (activityCanvas && activity) {
        new Chart(activityCanvas, {
            type: 'bar',
            data: {
                labels: (activity.labels || []).map(readableDate),
                datasets: [
                    { label: 'Demandes reçues', data: activity.requests || [], backgroundColor: primary, borderRadius: 4 },
                    { label: 'Commandes créées', data: activity.orders || [], backgroundColor: info, borderRadius: 4 },
                    { label: 'Prestations terminées', data: activity.completed || [], backgroundColor: success, borderRadius: 4 },
                ],
            },
            options: {
                ...commonOptions,
                scales: { ...commonOptions.scales, y: { ...commonOptions.scales.y, suggestedMax: 1 } },
            },
        });
    }

    const revenueCanvases = root.querySelectorAll('[data-chart="revenue"]');
    revenueCanvases.forEach((canvas) => {
        const currency = canvas.dataset.currency;
        const series = (dashboardCharts.revenue?.series || []).find((item) => item.label === currency);
        if (!series) return;

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: (dashboardCharts.revenue.labels || []).map(readableDate),
                datasets: [{
                    label: `Revenu net (${currency})`,
                    data: series.data || [],
                    borderColor: primary,
                    backgroundColor: primary,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    tension: 0.25,
                    fill: false,
                }],
            },
            options: {
                ...commonOptions,
                plugins: { ...commonOptions.plugins, legend: { display: false } },
                scales: {
                    ...commonOptions.scales,
                    y: {
                        ...commonOptions.scales.y,
                        ticks: {
                            callback(value) {
                                return new Intl.NumberFormat(document.documentElement.lang || 'fr-FR', {
                                    maximumFractionDigits: 2,
                                }).format(value);
                            },
                        },
                    },
                },
            },
        });
    });

    const ordersCanvas = root.querySelector('[data-chart="orders"]');
    const distribution = (dashboardCharts.order_distribution || []).filter((item) => Number(item.count) > 0);
    if (ordersCanvas && distribution.length) {
        new Chart(ordersCanvas, {
            type: 'doughnut',
            data: {
                labels: distribution.map((item) => item.label),
                datasets: [{
                    data: distribution.map((item) => Number(item.count)),
                    backgroundColor: palette,
                    borderColor: styles.getPropertyValue('--color-surface').trim() || '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 5,
                }],
            },
            options: {
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } },
                    tooltip: { padding: 10 },
                },
                onClick(_event, elements) {
                    const element = elements[0];
                    if (!element) return;
                    const status = distribution[element.index]?.status;
                    if (!status) return;
                    const ordersUrl = root.dataset.ordersUrl;
                    if (!ordersUrl) return;
                    const destination = new URL(ordersUrl, window.location.origin);
                    destination.searchParams.set('status', status);
                    window.location.assign(destination.toString());
                },
            },
        });
    }

    const servicesCanvas = root.querySelector('[data-chart="services"]');
    const services = dashboardCharts.top_services || [];
    if (servicesCanvas && services.length) {
        new Chart(servicesCanvas, {
            type: 'bar',
            data: {
                labels: services.map((item) => item.label),
                datasets: [{
                    label: 'Demandes reçues',
                    data: services.map((item) => Number(item.count)),
                    backgroundColor: services.map((_item, index) => palette[index % palette.length]),
                    borderRadius: 4,
                }],
            },
            options: {
                ...commonOptions,
                indexAxis: 'y',
                plugins: {
                    ...commonOptions.plugins,
                    legend: { display: false },
                    tooltip: { callbacks: { label: (context) => `${context.parsed.x} demande(s)` } },
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: border }, ticks: { precision: 0 } },
                    y: { grid: { display: false }, ticks: { autoSkip: false } },
                },
            },
        });
    }
}
