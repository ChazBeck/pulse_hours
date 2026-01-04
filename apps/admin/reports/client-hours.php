<?php
/**
 * Reports - Client Hours
 * 
 * Hours distribution by client and worker showing team member contributions to each client.
 */

require __DIR__ . '/../../../auth/include/auth_include.php';
auth_init();
auth_require_admin();

require_once __DIR__ . '/../../../includes/date_helpers.php';

$pdo = get_db_connection();
$user = auth_get_user();

// Load repositories
require_once __DIR__ . '/../../../src/Repository/autoload.php';
$hoursRepo = new HoursRepository($pdo);
$clientRepo = new ClientRepository($pdo);

// Get selected week or default to current week
$selectedWeek = isset($_GET['week']) ? $_GET['week'] : date('o-W');

// Get week dates (returns DateTime objects)
$weekDates = get_week_dates($selectedWeek);
$startDate = $weekDates['start'];
$endDate = $weekDates['end'];

// Generate list of last 12 weeks for dropdown
$weekOptions = [];
$currentDate = new DateTime();
for ($i = 0; $i < 12; $i++) {
    $tempDate = (clone $currentDate)->modify("-$i weeks");
    $isoYear = $tempDate->format('o');
    $isoWeek = $tempDate->format('W');
    $yw = sprintf('%s-%s', $isoYear, $isoWeek);
    $weekOptions[] = [
        'value' => $yw,
        'label' => format_week_range($yw)
    ];
}

// Get all active clients
$clients = $clientRepo->getActive();

// Separate internal and external clients
$externalClients = array_filter($clients, function($c) { return !$c['is_internal']; });
$internalClients = array_filter($clients, function($c) { return $c['is_internal']; });

// Get hours per user per client using repository
$hoursData = $hoursRepo->getHoursByUserAndClient($selectedWeek);

// Get total hours per client (for aggregate chart) using repository
$clientTotals = $hoursRepo->getTotalsByClient($selectedWeek);

// Separate internal and external totals
$externalTotals = array_filter($clientTotals, function($c) { return !$c['is_internal']; });
$internalTotals = array_filter($clientTotals, function($c) { return $c['is_internal']; });

// Organize data by user
$userHours = [];
$allUsers = [];
foreach ($hoursData as $row) {
    $userId = $row['user_id'];
    $clientId = $row['client_id'];
    
    if (!isset($allUsers[$userId])) {
        $allUsers[$userId] = $row['first_name'] . ' ' . $row['last_name'];
    }
    
    if (!isset($userHours[$userId])) {
        $userHours[$userId] = [];
    }
    
    $userHours[$userId][$clientId] = (float)$row['total_hours'];
}

// Build chart data
$userLabels = array_values($allUsers);
$clientDatasets = [];

// Default color palette for clients without colors
$defaultColors = [
    'rgba(247, 148, 29, 0.8)',   // Veerless orange
    'rgba(99, 102, 241, 0.8)',   // indigo
    'rgba(16, 185, 129, 0.8)',   // green
    'rgba(245, 158, 11, 0.8)',   // amber
    'rgba(239, 68, 68, 0.8)',    // red
    'rgba(139, 92, 246, 0.8)',   // purple
    'rgba(59, 130, 246, 0.8)',   // blue
    'rgba(236, 72, 153, 0.8)',   // pink
    'rgba(20, 184, 166, 0.8)',   // teal
    'rgba(156, 163, 175, 0.8)'   // gray
];

foreach ($clients as $index => $client) {
    $clientId = $client['id'];
    $clientData = [];
    
    // Get hours for each user for this client
    foreach (array_keys($allUsers) as $userId) {
        $clientData[] = $userHours[$userId][$clientId] ?? 0;
    }
    
    // Use client's defined color or fall back to default palette
    $color = $client['client_color'] 
        ? $client['client_color'] 
        : $defaultColors[$index % count($defaultColors)];
    
    $clientDatasets[] = [
        'label' => $client['name'],
        'data' => $clientData,
        'backgroundColor' => $color
    ];
}

// Build data for total hours by client chart
$clientNames = [];
$clientHours = [];
$clientColors = [];

// Add external clients first
foreach ($externalTotals as $index => $client) {
    $clientNames[] = $client['client_name'];
    $clientHours[] = (float)$client['total_hours'];
    
    $color = $client['client_color'] 
        ? $client['client_color'] 
        : $defaultColors[$index % count($defaultColors)];
    $clientColors[] = $color;
}

// Add internal clients (Veerless items)
foreach ($internalTotals as $index => $client) {
    $clientNames[] = $client['client_name'];
    $clientHours[] = (float)$client['total_hours'];
    
    $color = $client['client_color'] 
        ? $client['client_color'] 
        : 'rgba(156, 163, 175, 0.8)'; // Gray for internal
    $clientColors[] = $color;
}

// Calculate totals
$externalHoursTotal = array_sum(array_column($externalTotals, 'total_hours'));
$internalHoursTotal = array_sum(array_column($internalTotals, 'total_hours'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Hours Report - PluseHours</title>
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
            height: 500px;
            margin-top: 1.5rem;
        }
        
        .date-range {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-style: italic;
        }
        
        .week-selector {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .week-selector label {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .week-selector select {
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
        }
        
        .week-selector select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../_header.php'; ?>
    <?php include __DIR__ . '/../_admin_nav.php'; ?>
    
    <main class="admin-content">
        <div class="reports-container">
            <div class="report-header">
                <h1>Client Hours Distribution</h1>
                <p>Hours worked per team member by client</p>
                
                <!-- Week Selector -->
                <div class="week-selector">
                    <label for="weekSelect">Select Week:</label>
                    <select id="weekSelect" onchange="window.location.href='?week=' + this.value">
                        <?php foreach ($weekOptions as $option): ?>
                            <option value="<?= htmlspecialchars($option['value']) ?>" <?= $option['value'] === $selectedWeek ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <p class="date-range">
                    <?= $startDate->format('M j, Y') ?> - <?= $endDate->format('M j, Y') ?>
                </p>
            </div>
            
            <!-- Client Hours Stacked Bar Chart -->
            <div class="report-section">
                <h2>Hours by Team Member & Client</h2>
                <p>Stacked horizontal bar chart showing how many hours each team member has worked on each client's projects.</p>
                <div class="chart-container">
                    <canvas id="clientHoursChart"></canvas>
                </div>
            </div>
            
            <!-- Total Hours by Client -->
            <div class="report-section">
                <h2>Total Hours by Client</h2>
                <p>
                    Total hours worked across all team members for each client this week.
                    <strong>Client Hours: <?= number_format($externalHoursTotal, 1) ?></strong> | 
                    <strong>Internal Hours: <?= number_format($internalHoursTotal, 1) ?></strong>
                </p>
                <div class="chart-container">
                    <canvas id="clientTotalsChart"></canvas>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        const ctx = document.getElementById('clientHoursChart').getContext('2d');
        
        const datasets = <?= json_encode($clientDatasets) ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($userLabels) ?>,
                datasets: datasets
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 13,
                                family: "'Poppins', sans-serif"
                            },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'rect'
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
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.parsed.x || 0;
                                return label + ': ' + value.toFixed(1) + ' hrs';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
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
                            text: 'Hours',
                            font: {
                                size: 14,
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            font: {
                                size: 12,
                                family: "'Poppins', sans-serif"
                            }
                        },
                        grid: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Team Members',
                            font: {
                                size: 14,
                                family: "'Poppins', sans-serif"
                            }
                        }
                    }
                }
            }
        });
        
        // Total Hours by Client Chart
        const ctxTotals = document.getElementById('clientTotalsChart').getContext('2d');
        
        new Chart(ctxTotals, {
            type: 'bar',
            data: {
                labels: <?= json_encode($clientNames) ?>,
                datasets: [{
                    label: 'Total Hours',
                    data: <?= json_encode($clientHours) ?>,
                    backgroundColor: <?= json_encode($clientColors) ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
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
                        callbacks: {
                            label: function(context) {
                                return 'Hours: ' + context.parsed.y.toFixed(1);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
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
                            text: 'Hours',
                            font: {
                                size: 14,
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12,
                                family: "'Poppins', sans-serif"
                            }
                        },
                        grid: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Clients',
                            font: {
                                size: 14,
                                family: "'Poppins', sans-serif"
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
