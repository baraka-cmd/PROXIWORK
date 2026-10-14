import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const charts = document.querySelectorAll('[data-revenue-chart]');
    const numberFormat = new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    charts.forEach((canvas) => {
        let payload;
        try {
            payload = JSON.parse(canvas.dataset.chartPayload || '{}');
        } catch {
            const fallback = canvas.closest('[data-chart-container]');
            if (fallback) fallback.textContent = 'Les données du graphique sont momentanément indisponibles.';
            return;
        }

        if (!Array.isArray(payload.labels) || !Array.isArray(payload.values) || payload.labels.length === 0) {
            const container = canvas.closest('[data-chart-container]');
            if (container) container.innerHTML = '<p class="revenue-empty">Aucune donnée comptabilisée pour cette période.</p>';
            return;
        }

        new Chart(canvas, {
            type: canvas.dataset.chartType === 'bar' ? 'bar' : 'line',
            data: {
                labels: payload.labels,
                datasets: [{
                    label: payload.label || 'Revenus nets',
                    data: payload.values,
                    borderColor: '#0f766e',
                    backgroundColor: canvas.dataset.chartType === 'bar' ? '#0f766e' : 'rgba(15,118,110,.12)',
                    borderWidth: 2,
                    fill: canvas.dataset.chartType !== 'bar',
                    tension: .28,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    borderRadius: canvas.dataset.chartType === 'bar' ? 5 : 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + numberFormat.format(context.parsed.y) + ' ' + (payload.currency || ''),
                        },
                    },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: (value) => numberFormat.format(value) } },
                    x: { grid: { display: false } },
                },
            },
        });
    });
});
