<?php
/**
 * Admin Dashboard - Main Admin Panel Home
 * 
 * Provides an overview and navigation hub for administrative functions
 * including client management, user management, and system settings.
 */

require __DIR__ . '/../../auth/include/auth_include.php';
auth_init();
auth_require_admin();

$user = auth_get_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>
    
    <main class="admin-content">
        <div class="container">
            <div class="page-header">
                <h2>Admin Dashboard</h2>
                <p>Welcome back, <?= htmlspecialchars($user['first_name']) ?>! Manage your time tracking system.</p>
            </div>

            <div class="dashboard-grid">
                <!-- Clients Management -->
                <div class="dashboard-card">
                    <h3>📋 Clients</h3>
                    <p>Manage client accounts, colors, and logos for time tracking.</p>
                    <a href="clients.php" class="btn btn-primary">Manage Clients &rarr;</a>
                </div>

                <!-- Users Management -->
                <div class="dashboard-card">
                    <h3>👥 Users</h3>
                    <p>Manage user accounts, roles, and permissions.</p>
                    <a href="users.php" class="btn btn-primary">Manage Users &rarr;</a>
                </div>

                <!-- Projects Management -->
                <div class="dashboard-card">
                    <h3>💼 Projects</h3>
                    <p>Organize and track projects for different clients.</p>
                    <a href="projects.php" class="btn btn-primary">Manage Projects &rarr;</a>
                </div>
                
                <!-- Project Templates Management -->
                <div class="dashboard-card">
                    <h3>📋 Project Templates</h3>
                    <p>Create reusable project templates with predefined tasks.</p>
                    <a href="project-templates.php" class="btn btn-primary">Manage Templates &rarr;</a>
                </div>

                <!-- Tasks Management -->
                <div class="dashboard-card">
                    <h3>✓ Tasks</h3>
                    <p>Manage tasks linked to projects and track their status.</p>
                    <a href="tasks.php" class="btn btn-primary">Manage Tasks &rarr;</a>
                </div>

                <!-- Hours Log -->
                <div class="dashboard-card">
                    <h3>⏱️ Hours Log</h3>
                    <p>View and edit all hours entries across all users.</p>
                    <a href="hours-log.php" class="btn btn-primary">View Hours Log &rarr;</a>
                </div>

                <!-- Reports (Placeholder) -->
                <div class="dashboard-card">
                    <h3>📊 Reports</h3>
                    <p>View time tracking reports and analytics.</p>
                    <a href="#" class="btn btn-secondary" onclick="alert('Coming soon!'); return false;">View Reports &rarr;</a>
                </div>

                <!-- Settings (Placeholder) -->
                <div class="dashboard-card">
                    <h3>⚙️ Settings</h3>
                    <p>Configure system settings and preferences.</p>
                    <a href="#" class="btn btn-secondary" onclick="alert('Coming soon!'); return false;">System Settings &rarr;</a>
                </div>

                <!-- Logs (Placeholder) -->
                <div class="dashboard-card">
                    <h3>📝 Activity Logs</h3>
                    <p>Review system activity and user actions.</p>
                    <a href="#" class="btn btn-secondary" onclick="alert('Coming soon!'); return false;">View Logs &rarr;</a>
                </div>
            </div>

            <!-- Quick Stats Section -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Overview</h3>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        require_once __DIR__ . '/../../config/db_config.php';
                        $pdo = get_db_connection();
                        
                        // Get stats
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
                        $active_users = $stmt->fetch()['count'];
                        
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM clients WHERE active = 1");
                        $active_clients = $stmt->fetch()['count'];
                        
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM sessions");
                        $active_sessions = $stmt->fetch()['count'];
                    ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: bold; color: #2563eb;"><?= $active_users ?></div>
                                <div style="color: #6b7280; margin-top: 0.5rem;">Active Users</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: bold; color: #10b981;"><?= $active_clients ?></div>
                                <div style="color: #6b7280; margin-top: 0.5rem;">Active Clients</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: bold; color: #f59e0b;"><?= $active_sessions ?></div>
                                <div style="color: #6b7280; margin-top: 0.5rem;">Active Sessions</div>
                            </div>
                        </div>
                    <?php
                    } catch (Exception $e) {
                        echo '<p style="color: #ef4444;">Unable to load statistics. Please check database connection.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
