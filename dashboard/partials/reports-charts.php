<?php /* Shared Chart.js renderer for reports.php (normal + print modes). */ ?>
<script>
function renderReportCharts(data) {
    if (typeof Chart === 'undefined' || !data) return;

    // --- Revenue vs Profit (line) ---
    var rpCanvas = document.getElementById('revenueProfitChart');
    if (rpCanvas && data.rp && data.rp.labels && data.rp.labels.length) {
        new Chart(rpCanvas, {
            type: 'line',
            data: {
                labels: data.rp.labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: data.rp.revenue,
                        borderColor: '#9aa9bd',
                        backgroundColor: 'rgba(154, 169, 189, 0.08)',
                        tension: 0.4,
                        borderWidth: 4,
                        pointRadius: 4,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#9aa9bd',
                        pointBorderWidth: 2,
                        fill: false
                    },
                    {
                        label: 'Profit',
                        data: data.rp.profit,
                        borderColor: '#f5b301',
                        backgroundColor: 'rgba(245, 179, 1, 0.08)',
                        tension: 0.4,
                        borderWidth: 4,
                        pointRadius: 4,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#f5b301',
                        pointBorderWidth: 2,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#eef1f4' }, border: { display: false } }
                }
            }
        });
    }

    // --- Sales by Product (doughnut) ---
    var spCanvas = document.getElementById('salesProductChart');
    if (spCanvas && data.sales && data.sales.values && data.sales.values.length) {
        new Chart(spCanvas, {
            type: 'doughnut',
            data: {
                labels: data.sales.labels,
                datasets: [{
                    data: data.sales.values,
                    backgroundColor: data.sales.colors,
                    borderWidth: 0,
                    spacing: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { display: false } }
            }
        });
    }

    // --- Monthly Target vs Actual (bar) ---
    var mtCanvas = document.getElementById('monthlyTargetChart');
    if (mtCanvas && data.monthly && data.monthly.labels && data.monthly.labels.length) {
        new Chart(mtCanvas, {
            type: 'bar',
            data: {
                labels: data.monthly.labels,
                datasets: [
                    {
                        label: 'Actual',
                        data: data.monthly.actual,
                        backgroundColor: '#9aa9bd',
                        borderRadius: 5,
                        maxBarThickness: 18
                    },
                    {
                        label: 'Target',
                        data: data.monthly.target,
                        backgroundColor: '#f5b301',
                        borderRadius: 5,
                        maxBarThickness: 18
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle', font: { size: 11 } }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#eef1f4' }, border: { display: false } }
                }
            }
        });
    }
}
</script>
