<?php
/**
 * User Management - Add, Edit, Delete, and List Users
 * 
 * Allows administrators to manage user accounts including
 * email, names, passwords, roles, and active status.
 */

require __DIR__ . '/../../auth/include/auth_include.php';
auth_init();
auth_require_admin();

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
        
        // ADD NEW USER
        if ($action === 'add') {
            $email = trim($_POST['email'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role = $_POST['role'] ?? 'User';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Validate
            if (empty($email)) {
                $message = 'Email is required.';
                $message_type = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Invalid email format.';
                $message_type = 'error';
            } elseif (empty($first_name)) {
                $message = 'First name is required.';
                $message_type = 'error';
            } elseif (empty($last_name)) {
                $message = 'Last name is required.';
                $message_type = 'error';
            } elseif (empty($password)) {
                $message = 'Password is required.';
                $message_type = 'error';
            } elseif (!in_array($role, ['Admin', 'User'])) {
                $message = 'Invalid role selected.';
                $message_type = 'error';
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()['count'] > 0) {
                    $message = 'Email already exists. Please use a different email.';
                    $message_type = 'error';
                } else {
                    // Hash password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$email, $password_hash, $first_name, $last_name, $role, $is_active]);
                        $message = 'User added successfully!';
                        $message_type = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error adding user: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
            }
        }
        
        // EDIT USER
        elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role = $_POST['role'] ?? 'User';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($email)) {
                $message = 'Email is required.';
                $message_type = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Invalid email format.';
                $message_type = 'error';
            } elseif (empty($first_name)) {
                $message = 'First name is required.';
                $message_type = 'error';
            } elseif (empty($last_name)) {
                $message = 'Last name is required.';
                $message_type = 'error';
            } elseif ($id <= 0) {
                $message = 'Invalid user ID.';
                $message_type = 'error';
            } elseif (!in_array($role, ['Admin', 'User'])) {
                $message = 'Invalid role selected.';
                $message_type = 'error';
            } else {
                // Check if email is taken by another user
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()['count'] > 0) {
                    $message = 'Email already exists. Please use a different email.';
                    $message_type = 'error';
                } else {
                    try {
                        // Update user - with or without password change
                        if (!empty($password)) {
                            // Update with new password
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET email = ?, password_hash = ?, first_name = ?, last_name = ?, role = ?, is_active = ? WHERE id = ?");
                            $stmt->execute([$email, $password_hash, $first_name, $last_name, $role, $is_active, $id]);
                        } else {
                            // Update without changing password
                            $stmt = $pdo->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, role = ?, is_active = ? WHERE id = ?");
                            $stmt->execute([$email, $first_name, $last_name, $role, $is_active, $id]);
                        }
                        
                        $message = 'User updated successfully!';
                        $message_type = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error updating user: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
            }
        }
        
        // DELETE USER
        elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                $message = 'Invalid user ID.';
                $message_type = 'error';
            } elseif ($id === intval($user['id'])) {
                $message = 'You cannot delete your own account.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'User deleted successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting user: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

// ============================================================================
// Get User for Editing
// ============================================================================

$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch();
}

// ============================================================================
// Fetch All Users
// ============================================================================

$stmt = $pdo->query("SELECT * FROM users ORDER BY last_name ASC, first_name ASC");
$users = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
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
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Add/Edit User Form -->
            <div class="card">
                <div class="card-header">
                    <h3><?= $edit_user ? 'Edit User' : 'Add New User' ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                        <input type="hidden" name="action" value="<?= $edit_user ? 'edit' : 'add' ?>">
                        <?php if ($edit_user): ?>
                        <input type="hidden" name="id" value="<?= $edit_user['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?= $edit_user ? htmlspecialchars($edit_user['email']) : '' ?>"
                                   placeholder="user@example.com">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required 
                                       value="<?= $edit_user ? htmlspecialchars($edit_user['first_name']) : '' ?>"
                                       placeholder="John">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" required 
                                       value="<?= $edit_user ? htmlspecialchars($edit_user['last_name']) : '' ?>"
                                       placeholder="Doe">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password <?= $edit_user ? '(leave blank to keep current)' : '*' ?></label>
                            <input type="text" id="password" name="password" 
                                   <?= $edit_user ? '' : 'required' ?>
                                   placeholder="<?= $edit_user ? 'Enter new password to change' : 'Enter password' ?>">
                            <small style="display: block; margin-top: 0.25rem; color: #6b7280;">
                                <?= $edit_user ? 'Only enter a password if you want to change it' : 'Minimum 6 characters recommended' ?>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="User" <?= ($edit_user && $edit_user['role'] === 'User') ? 'selected' : '' ?>>User</option>
                                <option value="Admin" <?= ($edit_user && $edit_user['role'] === 'Admin') ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <small style="display: block; margin-top: 0.25rem; color: #6b7280;">
                                Admins have full system access; Users can only track time
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" 
                                       <?= ($edit_user && $edit_user['is_active']) || !$edit_user ? 'checked' : '' ?>>
                                <label for="is_active">Active</label>
                            </div>
                            <small style="display: block; margin-top: 0.25rem; color: #6b7280;">
                                Inactive users cannot log in to the system
                            </small>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?= $edit_user ? 'Update User' : 'Add User' ?>
                            </button>
                            <?php if ($edit_user): ?>
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
                                    <td><?= $u['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <?php if ($u['role'] === 'Admin'): ?>
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
                                        <span style="color: #9ca3af;">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?= $u['id'] ?>" class="btn btn-primary btn-small">Edit</a>
                                            <?php if (intval($u['id']) !== intval($user['id'])): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                            </form>
                                            <?php else: ?>
                                            <span style="color: #9ca3af; font-size: 0.875rem;">(You)</span>
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
