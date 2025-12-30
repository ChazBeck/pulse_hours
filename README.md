# PluseHours - Time Tracking Application

A comprehensive PHP/MySQL time tracking application with pulse check-ins, hours logging, and administrative management tools.

## Features

- **User Authentication**: Secure login with role-based access (Admin/User)
- **Pulse Check-in**: Weekly mood and workload tracking (1-5 scale)
- **Hours Entry**: Log time by client/project/task with weekly organization
- **Weekly Summaries**: Visual reports of pulse ratings and hours breakdown
- **Admin Panel**: Complete management interface for clients, users, projects, tasks, and templates
- **Project Templates**: Reusable project structures with automatic task generation
- **Hours Log**: View, edit, and delete hours entries with filtering capabilities

## Tech Stack

- **Backend**: PHP 8.2+
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Server**: Apache 2.4+ with mod_rewrite
- **Authentication**: Custom PHP session-based auth with CSRF protection

## Requirements

- PHP 8.2 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache 2.4+ with mod_rewrite enabled
- PDO MySQL extension
- GD extension (for image handling)

## Installation

### Local Development (XAMPP/MAMP)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/YOUR_USERNAME/plusehours.git
   cd plusehours
   ```

2. **Create database:**
   ```bash
   mysql -u root -p < database/setup_database.sql
   ```

3. **Configure application:**
   ```bash
   cp config/app_config.example.php config/app_config.php
   cp config/db_config.example.php config/db_config.php
   ```
   
   Edit `config/db_config.php` with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'plusehours');
   ```

4. **Set base URL** in `config/app_config.php`:
   ```php
   define('BASE_URL', '/plusehours/'); // Or '/' if at document root
   ```

5. **Access the application:**
   - Navigate to `http://localhost/plusehours/` (or your local URL)
   - Login with default admin: `admin@plusehours.com` / `Admin123!`
   - **Change this password immediately!**

### Production Deployment (cPanel)

1. **Create MySQL database** in cPanel:
   - Database name: `plusehours`
   - Import `database/setup_database.sql`
   - Create database user with full privileges

2. **Set up Git Version Control** in cPanel:
   - Go to Git™ Version Control
   - Click "Create"
   - Clone URL: `https://github.com/YOUR_USERNAME/plusehours.git`
   - Repository Path: `/home/username/public_html` (or subdirectory)
   - Branch: `main`

3. **Configure for production:**
   
   SSH into your server or use cPanel File Manager:
   
   ```bash
   cd /home/username/public_html
   
   # Copy config templates
   cp config/app_config.example.php config/app_config.php
   cp config/db_config.example.php config/db_config.php
   
   # Edit configs with production values
   nano config/app_config.php
   nano config/db_config.php
   ```
   
   In `config/app_config.php`:
   ```php
   define('APP_ENV', 'production');
   define('BASE_URL', '/'); // Or '/subfolder/' if in subdirectory
   ```
   
   In `config/db_config.php`:
   ```php
   define('DB_HOST', 'localhost'); // Or your DB host
   define('DB_USER', 'your_cpanel_db_user');
   define('DB_PASS', 'your_secure_password');
   define('DB_NAME', 'your_cpanel_db_name');
   ```

4. **Set file permissions:**
   ```bash
   chmod 600 config/app_config.php
   chmod 600 config/db_config.php
   chmod 755 logs
   chmod 755 uploads/logos
   ```

5. **Change default admin password:**
   - Login at `https://yourdomain.com/auth/login.php`
   - Go to Admin → Users
   - Edit the admin user and set a strong password

6. **Enable auto-deployment** (optional):
   - In cPanel Git Version Control, click "Manage"
   - Enable "Pull on Deploy"
   - Add webhook URL to your GitHub repository settings

## Project Structure

```
plusehours/
├── apps/
│   ├── admin/           # Admin panel pages
│   │   ├── clients.php
│   │   ├── users.php
│   │   ├── projects.php
│   │   ├── tasks.php
│   │   ├── project-templates.php
│   │   ├── hours-log.php
│   │   └── _admin_nav.php
│   ├── pulse.php        # Pulse check-in
│   ├── hours.php        # Hours entry
│   └── summary.php      # Weekly summary
├── auth/
│   ├── include/
│   │   └── auth_include.php  # Authentication library
│   ├── login.php
│   ├── logout.php
│   └── 403.php
├── config/
│   ├── app_config.php   # Application settings (not in repo)
│   ├── db_config.php    # Database credentials (not in repo)
│   └── .htaccess        # Deny web access
├── database/
│   └── setup_database.sql
├── includes/
│   ├── date_helpers.php      # Shared date functions
│   └── file_upload.php       # Centralized uploads
├── assets/
│   ├── admin-styles.css
│   ├── pulse-styles.css
│   ├── hours-styles.css
│   └── summary-styles.css
├── logs/                # Error logs (not in repo)
└── uploads/             # User uploads (not in repo)
    └── logos/
```

## Security Features

- ✅ CSRF protection on all forms
- ✅ Session timeout (24 hours of inactivity)
- ✅ Password hashing with `password_hash()`
- ✅ Prepared statements (no SQL injection)
- ✅ `.htaccess` protection for sensitive directories
- ✅ Input validation and sanitization
- ✅ HttpOnly session cookies
- ✅ Session regeneration every 5 minutes
- ✅ Error logging without exposing details

## Documentation

- **[AUTHENTICATION.md](AUTHENTICATION.md)**: How to integrate authentication
- **[PRODUCTION.md](PRODUCTION.md)**: Production deployment checklist
- **[DEPLOY.md](DEPLOY.md)**: cPanel-specific deployment guide

## Default Credentials

**⚠️ IMPORTANT**: Change these immediately after installation!

- Email: `admin@plusehours.com`
- Password: `Admin123!`

## Updating (cPanel)

Once set up with Git Version Control in cPanel:

1. Make changes locally and push to GitHub:
   ```bash
   git add .
   git commit -m "Your changes"
   git push origin main
   ```

2. In cPanel Git Version Control:
   - Click "Manage" on your repository
   - Click "Pull or Deploy" → "Update from Remote"
   
   Or enable auto-deployment via webhook.

**Note**: Your `config/` files won't be overwritten during updates (they're in `.gitignore`).

## License

Private/Proprietary - Not for public distribution

## Support

For issues or questions, contact the development team.
