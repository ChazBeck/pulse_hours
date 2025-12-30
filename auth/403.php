<?php
/**
 * 403 Forbidden Error Page
 * 
 * Shown when a non-admin user attempts to access admin-only pages.
 */

require __DIR__ . '/include/auth_include.php';
auth_init();

$user = auth_get_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - PluseHours</title>
    <link rel="stylesheet" href="/assets/admin-styles.css">
    <style>
        .error-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: var(--gray-50);
            padding: 20px;
        }
        
        .error-box {
            text-align: center;
            max-width: 500px;
        }
        
        .error-icon {
            font-size: 80px;
            color: var(--danger-color);
            margin-bottom: 20px;
        }
        
        .error-code {
            font-size: 120px;
            font-weight: bold;
            color: var(--gray-300);
            line-height: 1;
            margin-bottom: 10px;
        }
        
        .error-title {
            font-size: 32px;
            color: var(--gray-900);
            margin-bottom: 16px;
        }
        
        .error-message {
            font-size: 18px;
            color: var(--gray-600);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .error-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background-color: var(--gray-300);
        }
        
        .user-info {
            margin-top: 40px;
            padding: 20px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }
        
        .user-info p {
            color: var(--gray-600);
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .user-info strong {
            color: var(--gray-800);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-box">
            <div class="error-icon">🚫</div>
            <div class="error-code">403</div>
            <h1 class="error-title">Access Denied</h1>
            <p class="error-message">
                You don't have permission to access this page. 
                This area is restricted to administrators only.
            </p>
            
            <div class="error-actions">
                <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
                <?php if ($user): ?>
                    <a href="/apps/admin/" class="btn btn-primary">Go to Dashboard</a>
                <?php else: ?>
                    <a href="/apps/auth/login.php" class="btn btn-primary">Login</a>
                <?php endif; ?>
            </div>
            
            <?php if ($user): ?>
                <div class="user-info">
                    <p><strong>Current User:</strong> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                    <p><strong>Role:</strong> <?= htmlspecialchars($user['role']) ?></p>
                    <p style="margin-top: 16px;">
                        <a href="/apps/auth/logout.php" style="color: var(--primary-color); text-decoration: none;">
                            Logout and sign in with a different account
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
