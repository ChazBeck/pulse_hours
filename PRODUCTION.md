# Production Deployment Guide

## Quick Production Setup

### 1. Update Configuration Files

Edit `/config/app_config.php`:

```php
// Change these lines:
define('APP_ENV', 'production');        // Was: 'development'
define('BASE_URL', '/');                // Was: '/plusehours/'
```

Edit `/config/db_config.php`:

```php
// Update database credentials:
define('DB_HOST', 'your-db-host');
define('DB_USER', 'your-db-user');
define('DB_PASS', 'your-secure-password');
define('DB_NAME', 'plusehours');
```

### 2. Security Setup

**Create logs directory:**
```bash
mkdir logs
chmod 755 logs
```

**Secure sensitive files:**
```bash
chmod 600 config/db_config.php
chmod 600 config/app_config.php
```

**Add .htaccess to config folder:**
```apache
# Deny all access to config files
<Files "*">
    Require all denied
</Files>
```

### 3. Database Setup

1. Import `/database/setup_database.sql` on production server
2. Change default admin password immediately after first login
3. Run:
```sql
DELETE FROM users WHERE email='admin@plusehours.com';
```
Then create your own admin user with a secure password.

### 4. File Structure

**Production structure matches AUTHENTICATION.md:**
```
/
├── auth/
│   ├── include/
│   │   └── auth_include.php
│   ├── login.php
│   ├── logout.php
│   └── 403.php
├── apps/
│   └── admin/
│       ├── index.php
│       └── clients.php
├── assets/
│   └── admin-styles.css
├── config/
│   ├── app_config.php
│   ├── db_config.php
├── database/
│   └── setup_database.sql
└── uploads/
    └── logos/
```

### 5. SSL/HTTPS Setup

If using HTTPS (recommended), update `/config/app_config.php`:

```php
define('SESSION_COOKIE_SECURE', true);
```

Update BASE_URL to use https:
```php
define('BASE_URL', 'https://yourdomain.com/');
```

### 6. Post-Deployment Checklist

- [ ] Updated `APP_ENV` to 'production'
- [ ] Updated `BASE_URL` to your domain
- [ ] Updated database credentials
- [ ] Changed default admin password
- [ ] Created logs directory
- [ ] Secured config files (chmod 600)
- [ ] Added .htaccess to config folder
- [ ] Tested login functionality
- [ ] Tested client management
- [ ] Verified file uploads work
- [ ] Enabled HTTPS (SESSION_COOKIE_SECURE)
- [ ] Removed or secured test files (test.php, debug.php)

### 7. Clean Up Development Files

Remove development/testing files:
```bash
rm test.php
rm apps/admin/debug.php
rm apps/auth/.old/  # if exists
```

### 8. Timezone Configuration

Edit `/config/app_config.php`:
```php
date_default_timezone_set('Your/Timezone');  // e.g., 'America/New_York'
```

## That's It!

With these changes, your application will:
- Use production-safe error handling
- Work with your domain's base URL
- Follow the structure documented in AUTHENTICATION.md
- Be ready for secure production use

All redirect URLs and asset paths are now configured through `BASE_URL` and will automatically adjust when you change it.
