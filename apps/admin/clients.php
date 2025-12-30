<?php
/**
 * Client Management - Add, Edit, Delete, and List Clients
 * 
 * Allows administrators to manage client records including
 * names, colors, logos, and active status for time tracking.
 */

require __DIR__ . '/../../auth/include/auth_include.php';
auth_init();
auth_require_admin();

require_once __DIR__ . '/../../includes/file_upload.php';

require_once __DIR__ . '/../../config/db_config.php';
$pdo = get_db_connection();

$user = auth_get_user();
$message = '';
$message_type = '';

// ============================================================================
// Handle Form Submissions
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !auth_verify_csrf($_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        // ADD NEW CLIENT
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $color = trim($_POST['client_color'] ?? '#3b82f6');
            $active = isset($_POST['active']) ? 1 : 0;
            $logo = '';
            
            // Validate
            if (empty($name)) {
                $message = 'Client name is required.';
                $message_type = 'error';
            } else {
                // Handle logo upload using centralized handler
                if (isset($_FILES['client_logo']) && $_FILES['client_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_result = handle_logo_upload($_FILES['client_logo']);
                    if ($upload_result['success']) {
                        $logo = $upload_result['relative_path'];
                    } else {
                        $message = 'Logo upload error: ' . $upload_result['error'];
                        $message_type = 'error';
                    }
                }
                
                if ($message_type !== 'error') {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO clients (name, client_color, client_logo, active) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $color, $logo, $active]);
                        $message = 'Client added successfully!';
                        $message_type = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error adding client: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
            }
        }
        
        // EDIT CLIENT
        elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $color = trim($_POST['client_color'] ?? '#3b82f6');
            $active = isset($_POST['active']) ? 1 : 0;
            
            if (empty($name)) {
                $message = 'Client name is required.';
                $message_type = 'error';
            } elseif ($id <= 0) {
                $message = 'Invalid client ID.';
                $message_type = 'error';
            } else {
                // Get current logo
                $stmt = $pdo->prepare("SELECT client_logo FROM clients WHERE id = ?");
                $stmt->execute([$id]);
                $current_client = $stmt->fetch();
                $logo = $current_client['client_logo'] ?? '';
                
                // Handle logo upload using centralized handler
                if (isset($_FILES['client_logo']) && $_FILES['client_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_result = handle_logo_upload($_FILES['client_logo']);
                    if ($upload_result['success']) {
                        // Delete old logo if exists
                        if (!empty($logo)) {
                            delete_uploaded_file($logo);
                        }
                        $logo = $upload_result['relative_path'];
                    } else {
                        $message = 'Logo upload error: ' . $upload_result['error'];
                        $message_type = 'error';
                    }
                }
                
                if ($message_type !== 'error') {
                    try {
                        $stmt = $pdo->prepare("UPDATE clients SET name = ?, client_color = ?, client_logo = ?, active = ? WHERE id = ?");
                        $stmt->execute([$name, $color, $logo, $active, $id]);
                        $message = 'Client updated successfully!';
                        $message_type = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error updating client: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
            }
        }
        
        // DELETE CLIENT
        elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                $message = 'Invalid client ID.';
                $message_type = 'error';
            } else {
                try {
                    // Get logo to delete
                    $stmt = $pdo->prepare("SELECT client_logo FROM clients WHERE id = ?");
                    $stmt->execute([$id]);
                    $client = $stmt->fetch();
                    
                    // Delete client
                    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Delete logo file if exists
                    if (!empty($client['client_logo']) && file_exists(__DIR__ . '/../../' . $client['client_logo'])) {
                        unlink(__DIR__ . '/../../' . $client['client_logo']);
                    }
                    
                    $message = 'Client deleted successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting client: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

// ============================================================================
// Get Client for Editing
// ============================================================================

$edit_client = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_client = $stmt->fetch();
}

// ============================================================================
// Fetch All Clients
// ============================================================================

$stmt = $pdo->query("SELECT * FROM clients ORDER BY name ASC");
$clients = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clients - Admin - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>

    <main class="admin-content">
        <div class="container">
            <div class="page-header">
                <h2>Manage Clients</h2>
                <p>Add, edit, or remove clients for time tracking.</p>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Add/Edit Client Form -->
            <div class="card">
                <div class="card-header">
                    <h3><?= $edit_client ? 'Edit Client' : 'Add New Client' ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                        <input type="hidden" name="action" value="<?= $edit_client ? 'edit' : 'add' ?>">
                        <?php if ($edit_client): ?>
                        <input type="hidden" name="id" value="<?= $edit_client['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="name">Client Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?= $edit_client ? htmlspecialchars($edit_client['name']) : '' ?>"
                                   placeholder="Enter client name">
                        </div>
                        
                        <div class="form-group">
                            <label for="client_color">Client Color</label>
                            <input type="color" id="client_color" name="client_color" 
                                   value="<?= $edit_client ? htmlspecialchars($edit_client['client_color']) : '#3b82f6' ?>">
                            <small style="display: block; margin-top: 0.25rem; color: #6b7280;">
                                Choose a color to identify this client in the app
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="client_logo">Client Logo</label>
                            <input type="file" id="client_logo" name="client_logo" accept="image/*">
                            <small style="display: block; margin-top: 0.25rem; color: #6b7280;">
                                Upload a logo image (JPG, PNG, GIF, or SVG)
                            </small>
                            <?php if ($edit_client && !empty($edit_client['client_logo'])): ?>
                            <div style="margin-top: 0.5rem;">
                                <strong>Current logo:</strong><br>
                                <img src="<?= url($edit_client['client_logo']) ?>" 
                                     alt="Current logo" class="client-logo-preview" 
                                     style="max-width: 100px; margin-top: 0.5rem; border: 1px solid #e5e7eb; padding: 0.5rem;">
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="active" name="active" 
                                       <?= ($edit_client && $edit_client['active']) || !$edit_client ? 'checked' : '' ?>>
                                <label for="active">Active</label>
                            </div>
                            <small style="display: block; margin-top: 0.25rem; color: #6b7280;">
                                Inactive clients won't appear in time tracking
                            </small>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?= $edit_client ? 'Update Client' : 'Add Client' ?>
                            </button>
                            <?php if ($edit_client): ?>
                            <a href="clients.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Clients List -->
            <div class="card">
                <div class="card-header">
                    <h3>All Clients (<?= count($clients) ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($clients)): ?>
                    <div class="empty-state">
                        <p>No clients found. Add your first client above!</p>
                    </div>
                    <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Color</th>
                                    <th>Logo</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?= $client['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($client['name']) ?></strong></td>
                                    <td>
                                        <div class="client-color-box" 
                                             style="background-color: <?= htmlspecialchars($client['client_color']) ?>;"
                                             title="<?= htmlspecialchars($client['client_color']) ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($client['client_logo'])): ?>
                                        <img src="<?= url($client['client_logo']) ?>"
                                             alt="Logo" class="client-logo-preview">
                                        <?php else: ?>
                                        <span style="color: #9ca3af;">No logo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($client['active']): ?>
                                        <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($client['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?= $client['id'] ?>" class="btn btn-primary btn-small">Edit</a>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete this client?');">
                                                <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $client['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
