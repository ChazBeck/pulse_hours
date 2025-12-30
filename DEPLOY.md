# cPanel Deployment Guide

## Quick Setup

### 1. Create Database in cPanel

1. Login to cPanel
2. Go to **MySQL® Databases**
3. Create new database: `yourusername_plusehours`
4. Create new database user with strong password
5. Add user to database with **ALL PRIVILEGES**
6. Note your credentials:
   - Database name: `yourusername_plusehours`
   - Username: `yourusername_dbuser`
   - Password: (your chosen password)
   - Host: `localhost`

7. Import database:
   - Go to **phpMyAdmin**
   - Select your database
   - Click **Import** tab
   - Upload `database/setup_database.sql`
   - Click **Go**

### 2. Set Up Git Deployment

1. In cPanel, go to **Git™ Version Control**
2. Click **Create**
3. Fill in details:
   - **Clone URL**: `https://github.com/YOUR_USERNAME/plusehours.git`
   - **Repository Path**: `/home/username/public_html` (or subdirectory like `/home/username/public_html/plusehours`)
   - **Repository Name**: `plusehours`
4. Click **Create**

**Note**: The `.cpanel.yml` file will automatically:
- Create required directories (`logs/`, `uploads/logos/`)
- Set proper file permissions
- Copy config templates on first deployment

### 3. Configure Application

After deployment, edit config files via cPanel File Manager or SSH:

#### a) Edit `config/app_config.php`:

```php
define('APP_ENV', 'production');  // Change from 'development'
define('BASE_URL', '/');           // Or '/subfolder/' if not at root
```

#### b) Edit `config/db_config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'yourusername_dbuser');     // Your cPanel database user
define('DB_PASS', 'your_secure_password');     // Your database password
define('DB_NAME', 'yourusername_plusehours');  // Your database name
```

#### c) Set file permissions (if not auto-set):

```bash
chmod 600 config/app_config.php
chmod 600 config/db_config.php
chmod 755 logs
chmod 755 uploads/logos
```

### 4. First Login

1. Visit your site: `https://yourdomain.com/auth/login.php`
2. Login with default credentials:
   - Email: `admin@plusehours.com`
   - Password: `Admin123!`

3. **Immediately change the admin password:**
   - Go to Admin → Users
   - Click Edit on admin user
   - Set a strong password
   - Save

### 5. Enable Auto-Deployment (Optional)

To automatically pull updates when you push to GitHub:

#### In cPanel:

1. Go to **Git™ Version Control**
2. Click **Manage** on your repository
3. Scroll to **Deployment**
4. Copy the **Deploy Webhook URL**

#### In GitHub:

1. Go to your repository settings
2. Click **Webhooks** → **Add webhook**
3. Paste the webhook URL from cPanel
4. Content type: `application/json`
5. Select: "Just the push event"
6. Click **Add webhook**

Now every time you push to `main`, cPanel will automatically pull the changes!

## Updating Your Site

### Option 1: Manual Pull (if auto-deploy not enabled)

1. Login to cPanel
2. Go to **Git™ Version Control**
3. Click **Manage** on your repository
4. Click **Pull or Deploy** → **Update from Remote**

### Option 2: Auto-Deploy (if webhook enabled)

Just push changes from your local machine:

```bash
git add .
git commit -m "Your update message"
git push origin main
```

The site updates automatically within seconds!

## Important Notes

### Config Files Are Protected

Your `config/app_config.php` and `config/db_config.php` are in `.gitignore`, so they **won't be overwritten** during updates. This is intentional - your production credentials stay safe!

### What Gets Deployed

✅ **Included in deployment:**
- All PHP application files
- CSS/JavaScript assets
- `.htaccess` security files
- Database schema (in `/database/`)
- Documentation files

❌ **NOT included (stays on server):**
- `config/app_config.php` (your production config)
- `config/db_config.php` (your database credentials)
- `logs/*` (your error logs)
- `uploads/logos/*` (user-uploaded client logos)

### Directory Permissions

The `.cpanel.yml` file automatically sets these permissions:
- Config files: `600` (owner read/write only)
- Logs: `755` (writable by PHP)
- Uploads: `755` (writable by PHP)
- .htaccess files: `644` (readable by server)

### Troubleshooting

**White screen / 500 error:**
- Check `logs/db_errors.log` for database issues
- Verify database credentials in `config/db_config.php`
- Ensure `.htaccess` files aren't conflicting with server config

**Can't upload logos:**
- Check `uploads/logos/` has 755 permissions
- Verify PHP has write access
- Check `php.ini` file upload settings

**Session issues:**
- Check `SESSION_COOKIE_SECURE` in `config/app_config.php`
- Must be `false` for HTTP, `true` for HTTPS
- Ensure session directory is writable

**CSS not loading:**
- Check `BASE_URL` in `config/app_config.php`
- Should match your deployment path
- Use browser dev tools to check file paths

## Database Backups

Set up automatic backups in cPanel:

1. Go to **Backup Wizard**
2. Choose **Full Backup** or **Partial Backup** → Databases
3. Download or set up automated backups to remote storage

Or use mysqldump via SSH:
```bash
mysqldump -u yourusername_dbuser -p yourusername_plusehours > backup.sql
```

## Production Checklist

Before going live:

- [ ] Database imported successfully
- [ ] Config files updated with production values
- [ ] `APP_ENV` set to `production`
- [ ] `BASE_URL` matches your domain
- [ ] Default admin password changed
- [ ] HTTPS enabled (`SESSION_COOKIE_SECURE = true`)
- [ ] Auto-deployment webhook configured (optional)
- [ ] Database backups enabled
- [ ] Test login functionality
- [ ] Test pulse check-in flow
- [ ] Test hours entry and summary
- [ ] Test admin panel features
- [ ] Verify client logo uploads work

## Support

If you encounter issues:
1. Check `/logs/db_errors.log` on the server
2. Check cPanel error logs
3. Verify all configuration values
4. Ensure database permissions are correct
5. Contact your hosting provider for server-specific issues
