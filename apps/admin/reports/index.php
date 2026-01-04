<?php
/**
 * Reports - Dashboard
 * 
 * Main reports landing page with links to various analytics and visualizations.
 */

require __DIR__ . '/../../../auth/include/auth_include.php';
auth_init();
auth_require_admin();

$pdo = get_db_connection();
$user = auth_get_user();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
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
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .report-card {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .report-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .report-card h2 {
            color: var(--primary-color);
            font-size: 1.3rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .report-card .icon {
            font-size: 1.5rem;
        }
        
        .report-card p {
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        .report-card .arrow {
            margin-top: 1rem;
            color: var(--primary-color);
            font-weight: 500;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../_admin_nav.php'; ?>
    
    <main class="admin-content">
        <div class="reports-container">
            <div class="report-header">
                <h1>Reports & Analytics</h1>
                <p>Comprehensive insights into team performance, workload, and client activity</p>
            </div>
            
            <div class="reports-grid">
                <!-- Pulse & Workload Report -->
                <a href="<?= url('apps/admin/reports/pulse-workload.php') ?>" class="report-card">
                    <h2>
                        <span class="icon">📊</span>
                        Pulse & Workload
                    </h2>
                    <p>Track team morale and workload intensity over the last 8 weeks with trend analysis and individual breakdowns.</p>
                    <span class="arrow">View Report →</span>
                </a>
                
                <!-- Client Hours Report -->
                <a href="<?= url('apps/admin/reports/client-hours.php') ?>" class="report-card">
                    <h2>
                        <span class="icon">👥</span>
                        Client Hours
                    </h2>
                    <p>See how hours are distributed across clients and team members with stacked bar visualization.</p>
                    <span class="arrow">View Report →</span>
                </a>
                
                <!-- Placeholder for future reports -->
                <div class="report-card" style="opacity: 0.6; cursor: not-allowed;">
                    <h2>
                        <span class="icon">📈</span>
                        Project Analytics
                    </h2>
                    <p>Coming soon: Project progress, budget tracking, and completion forecasts.</p>
                </div>
                
                <div class="report-card" style="opacity: 0.6; cursor: not-allowed;">
                    <h2>
                        <span class="icon">⏱️</span>
                        Time Utilization
                    </h2>
                    <p>Coming soon: Billable vs non-billable hours, capacity planning, and efficiency metrics.</p>
                </div>
                
                <div class="report-card" style="opacity: 0.6; cursor: not-allowed;">
                    <h2>
                        <span class="icon">🎯</span>
                        Task Metrics
                    </h2>
                    <p>Coming soon: Task completion rates, velocity tracking, and bottleneck identification.</p>
                </div>
                
                <div class="report-card" style="opacity: 0.6; cursor: not-allowed;">
                    <h2>
                        <span class="icon">💰</span>
                        Financial Summary
                    </h2>
                    <p>Coming soon: Revenue projections, client profitability, and budget analysis.</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
