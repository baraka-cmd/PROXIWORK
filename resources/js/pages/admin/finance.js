import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-finance-charts]');
    if (!root) return;

    let chartData;
    try {
        chartData = JSON.parse(root.dataset.financeCharts ?? '{}');
    } catch {
        return;
    }

    root.querySelectorAll('[data-finance-chart]').forEach((canvas) => {
        const currency = canvas.dataset.financeChart;
        const values = chartData.currencies?.[currency];
        if (!values) return;

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: chartData.labels ?? [],
                datasets: [
                    {
                        label: 'Paiements confirmés',
                        data: values.payments ?? [],
                        borderColor: '#0f766e',
                        backgroundColor: 'rgba(15, 118, 110, .10)',
                        fill: true,
                        tension: .25,
                    },
                    {
                        label: 'Commissions comptabilisées',
                        data: values.commissions ?? [],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, .08)',
                        fill: false,
                        tension: .25,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + Number(context.parsed.y ?? 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency,
                        },
                    },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: (value) => Number(value).toLocaleString('fr-FR') } },
                },
            },
        });
    });
});
