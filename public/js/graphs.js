(() => {
    const chartElement = document.getElementById('dsistats-chart');
    const messageElement = document.getElementById('dsistats-chart-message');
    const exportForm = document.getElementById('dsistats-graph-export-form');
    const chartImageInput = document.getElementById('dsistats-chart-image');

    if (!chartElement) {
        return;
    }

    const showMessage = (message) => {
        if (!messageElement) {
            return;
        }

        messageElement.textContent = message;
        messageElement.classList.remove('d-none');
    };

    let chartData = null;
    try {
        chartData = JSON.parse(chartElement.dataset.chart || '{}');
    } catch (error) {
        showMessage('Impossible de lire les données du graphique.');
        return;
    }

    if (!chartData || !Array.isArray(chartData.months) || !Array.isArray(chartData.series)) {
        showMessage('Données du graphique invalides.');
        return;
    }

    if (chartData.series.length === 0) {
        showMessage('Aucune donnée disponible pour les filtres sélectionnés.');
        return;
    }

    if (typeof window.echarts === 'undefined') {
        showMessage('ECharts n’est pas disponible sur cette page.');
        return;
    }

    const toDisplay = (value) => {
        if (value === null || value === undefined) {
            return 'N/A';
        }

        const totalSeconds = Math.round(value * 3600);
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        let label = `${hours}h ${String(minutes).padStart(2, '0')}m`;

        if (value >= 7) {
            label += ` (${(value / 7).toFixed(2).replace('.', ',')} j)`;
        }

        return label;
    };

    const series = chartData.series.map((serie) => {
        const config = {
            name: serie.label,
            type: 'line',
            connectNulls: false,
            showSymbol: true,
            data: serie.values,
        };

        if (serie.threshold_hours !== null) {
            config.markLine = {
                symbol: 'none',
                lineStyle: {
                    type: 'dashed',
                },
                label: {
                    formatter: `Objectif ${serie.threshold_hours}h`,
                },
                data: [
                    { yAxis: serie.threshold_hours },
                ],
            };
        }

        return config;
    });

    const chart = window.echarts.init(chartElement, null, { renderer: 'svg' });
    chart.setOption({
        tooltip: {
            trigger: 'axis',
            valueFormatter: (value) => toDisplay(value),
        },
        legend: {
            type: 'scroll',
        },
        grid: {
            left: 50,
            right: 30,
            top: 50,
            bottom: 40,
        },
        xAxis: {
            type: 'category',
            data: chartData.months,
        },
        yAxis: {
            type: 'value',
            name: 'Temps moyen (heures)',
            axisLabel: {
                formatter: '{value} h',
            },
        },
        series,
    });

    if (exportForm && chartImageInput) {
        exportForm.addEventListener('submit', () => {
            chartImageInput.value = chart.getDataURL({
                type: 'png',
                pixelRatio: 2,
                backgroundColor: '#ffffff',
            });
        });
    }

    window.addEventListener('resize', () => chart.resize());
})();
