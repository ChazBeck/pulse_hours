# Authentication Integration Guide

## Adding Authentication to Your Apps

### Basic Authentication (Require Login)

Add these 3 lines at the top of any PHP file that needs authentication:

```php
<?php
require __DIR__ . '/../auth/include/auth_include.php';
auth_init();
auth_require_login();
```

**What each line does:**
- `require __DIR__ . '/../auth/include/auth_include.php';` - Loads the authentication library
- `auth_init();` - Starts the session and validates it
- `auth_require_login();` - Redirects to login page if user is not logged in

### Getting User Information

After authentication, get the current user's data:

```php
$user = auth_get_user();

// Access user properties:
echo $user['email'];       // User's email address
echo $user['first_name'];  // First name
echo $user['last_name'];   // Last name
echo $user['role'];        // 'Admin' or 'User'
echo $user['is_active'];   // 1 or 0
```

### Admin-Only Pages

For pages that should only be accessible to administrators:

```php
<?php
require __DIR__ . '/../auth/include/auth_include.php';
auth_init();
auth_require_admin();  // Only allows users with 'Admin' role
```

This will show a 403 error page if a non-admin tries to access the page.

### Checking User Role

To conditionally show content based on role:

```php
$user = auth_get_user();
$is_admin = $user && ($user['role'] === 'Admin');

if ($is_admin) {
    // Show admin-only content
}
```

### Complete Example

Here's a complete example for a new app page:

```php
<?php
require __DIR__ . '/../auth/include/auth_include.php';
auth_init();
auth_require_login();
$user = auth_get_user();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>My App</title>
  <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
<?php include __DIR__ . '/../_header.php'; ?>
<main class="hero">
  <div class="container">
    <h1>Welcome, <?= htmlspecialchars($user['first_name']) ?>!</h1>
    <p>Your app content goes here.</p>
  </div>
</main>
</body>
</html>
```

### Logging Out

Users can log out by visiting: `/apps/auth/logout.php`

Or create a logout link:
```php
<a href="/apps/auth/logout.php">Logout</a>
```

### Available Auth Functions

- `auth_init()` - Initialize session
- `auth_require_login()` - Require user to be logged in
- `auth_require_admin()` - Require user to be admin
- `auth_is_logged_in()` - Returns true/false if user is logged in
- `auth_get_user()` - Returns current user array or null
- `auth_logout()` - Log out current user
- `auth_csrf_token()` - Generate CSRF token for forms
- `auth_verify_csrf($token)` - Verify CSRF token

### CSRF Protection for Forms

All forms that modify data should include CSRF protection:

```php
<form method="post">
  <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(auth_csrf_token())?>">
  <!-- Your form fields -->
  <button type="submit">Submit</button>
</form>
```

Then verify on submission:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!auth_verify_csrf($token)) {
        die('Invalid form submission (CSRF)');
    }
    // Process form...
}
```
