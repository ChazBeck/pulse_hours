<?php
/**
 * User Management - Add, Edit, Delete, and List Users
 *
 * Thin controller: dispatches POST actions to UserService and renders
 * the user list with an inline add/edit form.
 */

require __DIR__ . '/../../sso/sso_include.php';
pulse_require_admin();

require_once __DIR__ . '/../../src/Service/UserService.php';

$service = new UserService();
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

        switch ($action) {
            case 'add':
                $result = $service->createUser($_POST);
                break;
            case 'edit':
                $result = $service->updateUser($_POST['id'] ?? 0, $_POST);
                break;
            case 'delete':
                $result = $service->deleteUser($_POST['id'] ?? 0, $user['id']);
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

$editUser = isset($_GET['edit']) ? $service->getUserById($_GET['edit']) : null;
$users = $service->getAll();
$csrfToken = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin - PluseHours</title>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/users.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>

    <main class="admin-content">
        <div class="container">
            <div class="page-header">
                <h2>Manage Users</h2>
                <p>Add, edit, or remove user accounts and manage their roles.</p>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Add/Edit User Form -->
            <div class="card">
                <div class="card-header">
                    <h3><?= $editUser ? 'Edit User' : 'Add New User' ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                        <input type="hidden" name="action" value="<?= $editUser ? 'edit' : 'add' ?>">
                        <?php if ($editUser): ?>
                        <input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required
                                   value="<?= $editUser ? htmlspecialchars($editUser['email']) : '' ?>"
                                   placeholder="user@example.com">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required
                                       value="<?= $editUser ? htmlspecialchars($editUser['first_name']) : '' ?>"
                                       placeholder="John">
                            </div>

                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" required
                                       value="<?= $editUser ? htmlspecialchars($editUser['last_name']) : '' ?>"
                                       placeholder="Doe">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password <?= $editUser ? '(leave blank to keep current)' : '*' ?></label>
                            <input type="text" id="password" name="password"
                                   <?= $editUser ? '' : 'required' ?>
                                   placeholder="<?= $editUser ? 'Enter new password to change' : 'Enter password' ?>">
                            <small class="field-hint">
                                <?= $editUser ? 'Only enter a password if you want to change it' : 'Minimum 6 characters recommended' ?>
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <?php foreach (UserRole::all() as $roleValue): ?>
                                <option value="<?= htmlspecialchars($roleValue) ?>"
                                        <?= ($editUser && $editUser['role'] === $roleValue) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($roleValue) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-hint">
                                Admins have full system access; Users can only track time
                            </small>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active"
                                       <?= ($editUser && $editUser['is_active']) || !$editUser ? 'checked' : '' ?>>
                                <label for="is_active">Active</label>
                            </div>
                            <small class="field-hint">
                                Inactive users cannot log in to the system
                            </small>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?= $editUser ? 'Update User' : 'Add User' ?>
                            </button>
                            <?php if ($editUser): ?>
                            <a href="users.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users List -->
            <div class="card">
                <div class="card-header">
                    <h3>All Users (<?= count($users) ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <p>No users found. Add your first user above!</p>
                    </div>
                    <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= (int) $u['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <?php if ($u['role'] === UserRole::ADMIN): ?>
                                        <span class="badge badge-orange">Admin</span>
                                        <?php else: ?>
                                        <span class="badge badge-teal">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['last_login'])): ?>
                                        <?= date('M j, Y g:i A', strtotime($u['last_login'])) ?>
                                        <?php else: ?>
                                        <span class="text-never">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?= (int) $u['id'] ?>" class="btn btn-primary btn-small">Edit</a>
                                            <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                            </form>
                                            <?php else: ?>
                                            <span class="text-muted">(You)</span>
                                            <?php endif; ?>
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
