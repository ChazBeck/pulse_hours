<?php
/**
 * Reports - Pulse & Workload Trends
 * 
 * Team pulse and workload analytics over time.
 */

require __DIR__ . '/../../../auth/include/auth_include.php';
auth_init();
auth_require_admin();

require_once __DIR__ . '/../../../includes/date_helpers.php';

$pdo = get_db_connection();
$user = auth_get_user();

// Load PulseRepository
require_once __DIR__ . '/../../../src/Repository/autoload.php';
$pulseRepo = new PulseRepository($pdo);

// Get last 8 weeks of pulse data
$weeks = 8;
$endDate = new DateTime();
$startDate = (clone $endDate)->modify('-' . ($weeks * 7) . ' days');

// Build list of year-weeks for last 8 weeks
$yearWeeks = [];
$weekLabels = [];
$dt = clone $startDate;
for ($w = 0; $w < $weeks; $w++) {
    $isoYear = $dt->format('o');
    $isoWeek = $dt->format('W');
    $yw = sprintf('%s-%s', $isoYear, $isoWeek);
    $yearWeeks[] = $yw;
    $weekLabels[] = format_week_range($yw);
    $dt->modify('+7 days');
}

// Get average pulse per week using repository
$pulseByWeek = $pulseRepo->getAverageByWeeks('pulse', $yearWeeks);

// Fill in data for all weeks (null if no data)
$chartDataAvg = [];
foreach ($yearWeeks as $yw) {
    $chartDataAvg[] = $pulseByWeek[$yw] ?? null;
}

// Get individual user pulse data using repository
$userPulseData = $pulseRepo->getByUserAndWeeks('pulse', $yearWeeks);

// Get average workload per week using repository
$workloadByWeek = $pulseRepo->getAverageByWeeks('work_load', $yearWeeks);

// Fill in data for all weeks (null if no data)
$chartDataWorkloadAvg = [];
foreach ($yearWeeks as $yw) {
    $chartDataWorkloadAvg[] = $workloadByWeek[$yw] ?? null;
}

// Get individual user workload data using repository
$userWorkloadData = $pulseRepo->getByUserAndWeeks('work_load', $yearWeeks);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse & Workload Reports - PluseHours</title>
    <?php include __DIR__ . '/../../../includes/head.php'; ?>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .reports-container {
            padding: 2rem;
        }
        
        .report-header {
            margin-bottom: 2rem;
        }
        
        .report-header h1 {
            color: var(--text-primary);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .breadcrumb {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .report-section {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .report-section h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../_header.php'; ?>
    <?php include __DIR__ . '/../_admin_nav.php'; ?>
    
    <main class="admin-content">
        <div class="reports-container">
            <div class="report-header">
                <h1>Pulse & Workload Trends</h1>
                <p>Team morale and workload intensity over the last 8 weeks</p>
            </div>
            
            <!-- Pulse Report -->
            <div class="report-section">
                <h2>Team Pulse Trend</h2>
                <p>Average team pulse rating (1-5 scale) showing overall team morale and satisfaction.</p>
                <div class="chart-container">
                    <canvas id="pulseChart"></canvas>
                </div>
            </div>
            
            <!-- Workload Report -->
            <div class="report-section">
                <h2>Team Workload Trend</h2>
                <p>Average team workload rating (1-10 scale) showing overall workload intensity and capacity.</p>
                <div class="chart-container">
                    <canvas id="workloadChart"></canvas>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Individual user colors (muted, semi-transparent)
        const userColors = [
            'rgba(99, 102, 241, 0.4)',   // indigo
            'rgba(16, 185, 129, 0.4)',   // green
            'rgba(245, 158, 11, 0.4)',   // amber
            'rgba(239, 68, 68, 0.4)',    // red
            'rgba(139, 92, 246, 0.4)',   // purple
            'rgba(59, 130, 246, 0.4)',   // blue
            'rgba(236, 72, 153, 0.4)',   // pink
            'rgba(20, 184, 166, 0.4)'    // teal
        ];
        
        // Pulse Chart
        const ctx = document.getElementById('pulseChart').getContext('2d');
        
        // Build datasets - individual users first (so they appear behind average)
        const datasets = [];
        
        <?php $colorIndex = 0; ?>
        <?php foreach ($userPulseData as $userId => $userData): ?>
        datasets.push({
            label: '<?= htmlspecialchars($userData['name']) ?>',
            data: <?= json_encode($userData['chartData']) ?>,
            borderColor: userColors[<?= $colorIndex % 8 ?>],
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [5, 5],
            tension: 0.3,
            fill: false,
            pointRadius: 2,
            pointHoverRadius: 5,
            pointBackgroundColor: userColors[<?= $colorIndex % 8 ?>],
            pointBorderColor: '#fff',
            pointBorderWidth: 1,
            hidden: true
        });
        <?php $colorIndex++; ?>
        <?php endforeach; ?>
        
        // Add average as the last dataset (appears on top)
        datasets.push({
            label: 'Team Average',
            data: <?= json_encode($chartDataAvg) ?>,
            borderColor: '#F7941D',
            backgroundColor: 'rgba(247, 148, 29, 0.1)',
            borderWidth: 4,
            tension: 0.3,
            fill: true,
            pointRadius: 6,
            pointHoverRadius: 8,
            pointBackgroundColor: '#F7941D',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        });
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($weekLabels) ?>,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        onClick: function(e, legendItem, legend) {
                            const index = legendItem.datasetIndex;
                            const chart = legend.chart;
                            const meta = chart.getDatasetMeta(index);
                            meta.hidden = meta.hidden === null ? !chart.data.datasets[index].hidden : null;
                            chart.update();
                        },
                        labels: {
                            font: {
                                size: 14,
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 14,
                            family: "'Poppins', sans-serif"
                        },
                        bodyFont: {
                            size: 13,
                            family: "'Poppins', sans-serif"
                        },
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Pulse: ' + (context.parsed.y !== null ? context.parsed.y.toFixed(2) : 'No data');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 1,
                        max: 5,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 12,
                                family: "'Poppins', sans-serif"
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        title: {
                            display: true,
                            text: 'Pulse Rating',
                            font: {
                                size: 14,
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 11,
                                family: "'Poppins', sans-serif"
                            },
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Workload Chart
        const ctxWorkload = document.getElementById('workloadChart').getContext('2d');
        
        // Build datasets for workload - individual users first (so they appear behind average)
        const datasetsWorkload = [];
        
        <?php $colorIndex = 0; ?>
        <?php foreach ($userWorkloadData as $userId => $userData): ?>
        datasetsWorkload.push({
            label: '<?= htmlspecialchars($userData['name']) ?>',
            data: <?= json_encode($userData['chartData']) ?>,
            borderColor: userColors[<?= $colorIndex % 8 ?>],
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [5, 5],
            tension: 0.3,
            fill: false,
            pointRadius: 2,
            pointHoverRadius: 5,
            pointBackgroundColor: userColors[<?= $colorIndex % 8 ?>],
            pointBorderColor: '#fff',
            pointBorderWidth: 1,
            hidden: true
        });
        <?php $colorIndex++; ?>
        <?php endforeach; ?>
        
        // Add average as the last dataset (appears on top)
        datasetsWorkload.push({
            label: 'Team Average',
            data: <?= json_encode($chartDataWorkloadAvg) ?>,
            borderColor: '#F7941D',
            backgroundColor: 'rgba(247, 148, 29, 0.1)',
            borderWidth: 4,
            tension: 0.3,
            fill: true,
            pointRadius: 6,
            pointHoverRadius: 8,
            pointBackgroundColor: '#F7941D',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        });
        
        new Chart(ctxWorkload, {
            type: 'line',
            data: {
                labels: <?= json_encode($weekLabels) ?>,
                datasets: datasetsWorkload
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        onClick: function(e, legendItem, legend) {
                            const index = legendItem.datasetIndex;
                            const chart = legend.chart;
                            const meta = chart.getDatasetMeta(index);
                            meta.hidden = meta.hidden === null ? !chart.data.datasets[index].hidden : null;
                            chart.update();
                        },
                        labels: {
                            font: {
                                size: 14,
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 14,
                            family: "'Poppins', sans-serif"
                        },
                        bodyFont: {
                            size: 13,
                            family: "'Poppins', sans-serif"
                        },
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Workload: ' + (context.parsed.y !== null ? context.parsed.y.toFixed(2) : 'No data');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 1,
                        max: 10,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 12,
                                family: "'Poppins', sans-serif"
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        title: {
                            display: true,
                            text: 'Workload Rating',
                            font: {
                                size: 14,
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 11,
                                family: "'Poppins', sans-serif"
                            },
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
