@php
    $trend = $charts['collectionsTrend'];
    $loanStatus = $charts['loanStatus'];
    $applicationStatus = $charts['applicationStatus'];
    $branchPortfolio = $charts['branchPortfolio'] ?? null;
@endphp

<div class="charts-grid">
    <div class="card chart-card">
        <div class="card-head">
            <h2><span class="material-symbols-outlined"
                    aria-hidden="true">trending_up</span>{{ __('Collections trend (last 14 days)') }}</h2>
        </div>
        <div class="card-body"><canvas id="chart-collections-trend"></canvas></div>
    </div>
    <div class="card chart-card">
        <div class="card-head">
            <h2><span class="material-symbols-outlined"
                    aria-hidden="true">donut_small</span>{{ __('Loan status breakdown') }}</h2>
        </div>
        <div class="card-body">
            @if (empty($loanStatus['labels']))
                <div class="chart-empty"><span class="material-symbols-outlined"
                        aria-hidden="true">bar_chart</span><span>{{ __('No chart data available yet.') }}</span></div>
            @else
                <canvas id="chart-loan-status"></canvas>
            @endif
        </div>
    </div>
</div>

<div class="charts-grid even">
    <div class="card chart-card">
        <div class="card-head">
            <h2><span class="material-symbols-outlined"
                    aria-hidden="true">bar_chart</span>{{ __('Application pipeline') }}</h2>
        </div>
        <div class="card-body">
            @if (empty($applicationStatus['labels']))
                <div class="chart-empty"><span class="material-symbols-outlined"
                        aria-hidden="true">bar_chart</span><span>{{ __('No chart data available yet.') }}</span></div>
            @else
                <canvas id="chart-application-status"></canvas>
            @endif
        </div>
    </div>
    @if ($branchPortfolio !== null)
        <div class="card chart-card">
            <div class="card-head">
                <h2><span class="material-symbols-outlined"
                        aria-hidden="true">account_balance</span>{{ __('Portfolio by branch') }}</h2>
            </div>
            <div class="card-body">
                @if (empty($branchPortfolio['labels']))
                    <div class="chart-empty"><span class="material-symbols-outlined"
                            aria-hidden="true">bar_chart</span><span>{{ __('No chart data available yet.') }}</span>
                    </div>
                @else
                    <canvas id="chart-branch-portfolio"></canvas>
                @endif
            </div>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    (() => {
        const chartData = @json($charts);
        let charts = null;

        const palette = ['#005c2d', '#c69a28', '#0b6139', '#8a6612', '#68736b', '#b42318', '#0a4b2e'];

        const buildCharts = () => {
            if (charts || typeof Chart === 'undefined') return;
            charts = [];

            const trendCanvas = document.getElementById('chart-collections-trend');
            if (trendCanvas) {
                charts.push(new Chart(trendCanvas, {
                    type: 'line',
                    data: {
                        labels: chartData.collectionsTrend.labels,
                        datasets: [{
                                label: @json(__('Expected')),
                                data: chartData.collectionsTrend.expected,
                                borderColor: '#c69a28',
                                backgroundColor: 'rgba(198,154,40,.12)',
                                tension: .35,
                                fill: true
                            },
                            {
                                label: @json(__('Collected')),
                                data: chartData.collectionsTrend.collected,
                                borderColor: '#005c2d',
                                backgroundColor: 'rgba(0,92,45,.12)',
                                tension: .35,
                                fill: true
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    },
                }));
            }

            const statusCanvas = document.getElementById('chart-loan-status');
            if (statusCanvas) {
                charts.push(new Chart(statusCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: chartData.loanStatus.labels,
                        datasets: [{
                            data: chartData.loanStatus.data,
                            backgroundColor: palette
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    },
                }));
            }

            const applicationCanvas = document.getElementById('chart-application-status');
            if (applicationCanvas) {
                charts.push(new Chart(applicationCanvas, {
                    type: 'bar',
                    data: {
                        labels: chartData.applicationStatus.labels,
                        datasets: [{
                            label: @json(__('Applications')),
                            data: chartData.applicationStatus.data,
                            backgroundColor: '#005c2d',
                            borderRadius: 6
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    },
                }));
            }

            const branchCanvas = document.getElementById('chart-branch-portfolio');
            if (branchCanvas) {
                charts.push(new Chart(branchCanvas, {
                    type: 'bar',
                    data: {
                        labels: chartData.branchPortfolio.labels,
                        datasets: [{
                            label: @json(__('Portfolio by branch')),
                            data: chartData.branchPortfolio.data,
                            backgroundColor: '#c69a28',
                            borderRadius: 6
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true
                            }
                        }
                    },
                }));
            }
        };

        const activeTab = document.querySelector('.dash-tab.active')?.dataset.tab;
        if (activeTab === 'charts') buildCharts();

        window.addEventListener('vati:tab-shown', event => {
            if (event.detail.tab === 'charts') buildCharts();
        });
    })();
</script>
