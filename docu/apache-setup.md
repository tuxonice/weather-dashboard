# Apache Setup Guide

This guide explains how to configure Apache for the Weather Dashboard on a live server.

## Prerequisites

- Apache 2.4+ with `mod_rewrite` enabled
- PHP 8.4 with FPM or mod_php
- All files deployed to a web-accessible directory (e.g., `/var/www/weather-dashboard`)

## Enable Required Apache Modules

```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

## Virtual Host Configuration

Create a new virtual host file (e.g., `/etc/apache2/sites-available/weather-dashboard.conf`):

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    ServerAdmin admin@yourdomain.com

    # Set document root to the public directory
    DocumentRoot /var/www/weather-dashboard/public

    # Enable .htaccess overrides and necessary options
    <Directory /var/www/weather-dashboard/public>
        AllowOverride All
        Require all granted

        # Ensure .htaccess is processed
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteBase /
        </IfModule>
    </Directory>

    # Deny access to parent directories
    <Directory /var/www/weather-dashboard>
        Require all denied
    </Directory>

    # Security headers
    <IfModule mod_headers.c>
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "DENY"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
        # Content-Security-Policy is set by the application (SecurityHeadersSubscriber);
        # do not add one here, it would conflict with the app's policy.
        # HSTS: only enable once the site is served over HTTPS on this vhost.
        Header always set Strict-Transport-Security "max-age=31536000"
    </IfModule>

    # Logging
    # No vhost access or error log: they would store visitor IP addresses
    # (personal data under GDPR). See docu/eu-compliance-audit.md.
    # Note: without ErrorLog, Apache falls back to the server-wide log
    # (/var/log/apache2/error.log); set LogLevel and that log's retention
    # on the server accordingly.

    # PHP-FPM Configuration (if using PHP-FPM)
    <IfModule mod_proxy_fcgi.c>
        <FilesMatch "\.php$">
            SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost/"
        </FilesMatch>
    </IfModule>

    # Gzip compression (optional but recommended)
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
    </IfModule>
</VirtualHost>
```

### Alternative: If Using mod_php Instead of PHP-FPM

If you're using `mod_php` instead of FPM, replace the `<IfModule mod_proxy_fcgi.c>` section with:

```apache
# No special configuration needed for mod_php
# The .htaccess rules will handle PHP routing
```

## Enable the Virtual Host

```bash
# Enable the site
sudo a2ensite weather-dashboard.conf

# Disable default site (optional)
sudo a2dissite 000-default.conf

# Test Apache configuration
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2
```

## Directory Setup

```bash
# Navigate to your web directory
cd /var/www/weather-dashboard

# Set proper permissions
sudo chown -R www-data:www-data .
sudo chmod 755 .
sudo chmod 755 public
sudo chmod 644 public/*
sudo chmod 755 var
sudo chmod 755 src
sudo chmod 755 templates
sudo chmod 755 translations

# Create cache directories if they don't exist
mkdir -p var/cache/ipma
mkdir -p var/cache/twig
mkdir -p var/log
sudo chown -R www-data:www-data var
sudo chmod -R 755 var
```

## Environment Configuration

Create `/var/www/weather-dashboard/.env` with production settings:

```bash
APP_ENV=prod
APP_DEBUG=0
```

**Important:** Do NOT set `APP_DEBUG=1` in production. This will expose sensitive information in error messages.

## SSL/HTTPS Configuration (Recommended)

To use Let's Encrypt for free SSL certificates:

```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache

# Generate certificate and update Apache config
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Auto-renewal is configured automatically
sudo systemctl status certbot.timer
```

The virtual host configuration will be automatically updated with HTTPS redirect.

## Verify Routing

1. **Test front controller routing:**
   ```bash
   curl -I http://yourdomain.com/locations
   # Should return 200 OK, not 404
   ```

2. **Test 404 handling:**
   ```bash
   curl -I http://yourdomain.com/nonexistent-page
   # Should return 200 OK with custom error page
   ```

3. **Check application logs for errors** (see "View Application Logs" below)

## Troubleshooting

### "403 Forbidden" or ".htaccess not allowed"

**Solution:** Ensure `AllowOverride All` is set in the VirtualHost configuration.

### "404 Not Found" on all routes except index.php

**Solution:** 
1. Check if `mod_rewrite` is enabled: `sudo apache2ctl -M | grep rewrite`
2. Enable it if missing: `sudo a2enmod rewrite`
3. Restart Apache: `sudo systemctl restart apache2`

### Routes redirect to index.php but page shows 404

**Solution:** Check the `.htaccess` file in `public/` directory. It should contain the routing rules. Verify:
```bash
cat /var/www/weather-dashboard/public/.htaccess
```

### PHP files not executing

**Solution:** Ensure PHP handler is configured (either FPM or mod_php).

For FPM, verify:
```bash
sudo apache2ctl -M | grep proxy_fcgi
```

### Permission denied errors in logs

**Solution:** Ensure www-data owns the files:
```bash
sudo chown -R www-data:www-data /var/www/weather-dashboard
```

## Performance Optimization

### Enable Gzip Compression

Already included in the VirtualHost template above. Verify it's working:

```bash
curl -I -H "Accept-Encoding: gzip" http://yourdomain.com
# Look for: Content-Encoding: gzip
```

### Enable Browser Caching

Add this to your `.htaccess` file for static assets:

```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
    ExpiresByType application/manifest+json "access plus 1 month"
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>
```

## Maintenance

### View Application Logs

```bash
# Application cache errors (if configured)
tail -f /var/www/weather-dashboard/var/log/ipma-cache.log

```

### Clear Cache

```bash
sudo rm -rf /var/www/weather-dashboard/var/cache/*
sudo chown -R www-data:www-data /var/www/weather-dashboard/var
```

### Update Application

```bash
cd /var/www/weather-dashboard
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo systemctl restart apache2
```