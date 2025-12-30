<?php
/**
 * Login Page for PluseHours
 * 
 * Handles user authentication with email and password.
 * Redirects to dashboard or requested page after successful login.
 */

require __DIR__ . '/include/auth_include.php';
auth_init();

// Redirect to appropriate page if already logged in
if (auth_is_logged_in()) {
    $user = auth_get_user();
    if ($user['role'] === 'admin') {
        header('Location: ' . url('apps/admin/'));
    } else {
        header('Location: ' . url('apps/pulse.php'));
    }
    exit;
}

// Initialize variables
$error_message = '';
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!auth_verify_csrf($csrf_token)) {
        $error_message = 'Invalid form submission. Please try again.';
    } else {
        // Get form data
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($email) || empty($password)) {
            $error_message = 'Please enter both email and password.';
        } else {
            // Attempt login
            $result = auth_login($email, $password);
            
            if ($result['success']) {
                // Login successful - redirect based on role
                $user = auth_get_user();
                if ($user['role'] === 'admin') {
                    $default_redirect = url('apps/admin/');
                } else {
                    $default_redirect = url('apps/pulse.php');
                }
                
                $redirect_url = $_SESSION['redirect_after_login'] ?? $default_redirect;
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect_url);
                exit;
            } else {
                // Login failed
                $error_message = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <style>
        /* Additional login-specific styles */
        body {
            background-color: #FFFFFF;
        }
        
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-box {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 8px rgba(4, 53, 70, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: var(--text-primary);
            font-family: 'Archivo', sans-serif;
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: var(--text-secondary);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--gray-700);
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .error-message {
            background-color: #fee;
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
            padding: 12px 16px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px 16px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-login:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-login:active {
            transform: translateY(1px);
        }
        
        .login-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 14px;
            color: var(--gray-600);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>PluseHours</h1>
                <p>Sign in to your account</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?= htmlspecialchars($email) ?>"
                        required 
                        autofocus
                        autocomplete="email"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        autocomplete="current-password"
                    >
                </div>
                
                <button type="submit" class="btn-login">Sign In</button>
            </form>
            
            <div class="login-footer">
                <p>&copy; <?= date('Y') ?> PluseHours. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
