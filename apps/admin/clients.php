<?php
/**
 * Client Management - Add, Edit, Delete, and List Clients
 *
 * Thin controller: dispatches POST actions to ClientService and renders
 * the clients list with an inline add/edit form.
 */

require __DIR__ . '/../../sso/sso_include.php';
pulse_require_admin();

require_once __DIR__ . '/../../src/Service/ClientService.php';

$service = new ClientService();
$message = '';
$messageType = '';

// ----------------------------------------------------------------------
// Handle POST actions
// ----------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !auth_verify_csrf($_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $result = null;
        $logoFile = $_FILES['client_logo'] ?? null;

        switch ($action) {
            case 'add':
                $result = $service->createClient($_POST, $logoFile);
                break;
            case 'edit':
                $result = $service->updateClient($_POST['id'] ?? 0, $_POST, $logoFile);
                break;
            case 'delete':
                $result = $service->deleteClient($_POST['id'] ?? 0);
                break;
            default:
                $message = 'Unknown action.';
                $messageType = 'error';
        }

        if ($result !== null) {
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }
    }
}

// ----------------------------------------------------------------------
// Fetch data for display
// ----------------------------------------------------------------------

$editClient = isset($_GET['edit']) ? $service->getClientById($_GET['edit']) : null;
$clients = $service->getAll();
$csrfToken = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clients - Admin - PluseHours</title>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/clients.css') ?>">
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
            <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Add/Edit Client Form -->
            <div class="card">
                <div class="card-header">
                    <h3><?= $editClient ? 'Edit Client' : 'Add New Client' ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                        <input type="hidden" name="action" value="<?= $editClient ? 'edit' : 'add' ?>">
                        <?php if ($editClient): ?>
                        <input type="hidden" name="id" value="<?= (int) $editClient['id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="name">Client Name *</label>
                            <input type="text" id="name" name="name" required
                                   value="<?= $editClient ? htmlspecialchars($editClient['name']) : '' ?>"
                                   placeholder="Enter client name">
                        </div>

                        <div class="form-group">
                            <label for="client_color">Client Color</label>
                            <input type="color" id="client_color" name="client_color"
                                   value="<?= $editClient ? htmlspecialchars($editClient['client_color']) : '#3b82f6' ?>">
                            <small class="field-hint">
                                Choose a color to identify this client in the app
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="client_logo">Client Logo</label>
                            <input type="file" id="client_logo" name="client_logo" accept="image/*">
                            <small class="field-hint">
                                Upload a logo image (JPG, PNG, GIF, or SVG)
                            </small>
                            <?php if ($editClient && !empty($editClient['client_logo'])): ?>
                            <div class="client-logo-current">
                                <strong>Current logo:</strong><br>
                                <img src="<?= url($editClient['client_logo']) ?>" alt="Current logo">
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="active" name="active"
                                       <?= ($editClient && $editClient['active']) || !$editClient ? 'checked' : '' ?>>
                                <label for="active">Active</label>
                            </div>
                            <small class="field-hint">
                                Inactive clients won't appear in time tracking
                            </small>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?= $editClient ? 'Update Client' : 'Add Client' ?>
                            </button>
                            <?php if ($editClient): ?>
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
                                    <td><?= (int) $client['id'] ?></td>
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
                                        <span class="text-muted">No logo</span>
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
                                            <a href="?edit=<?= (int) $client['id'] ?>" class="btn btn-primary btn-small">Edit</a>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete this client?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
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
