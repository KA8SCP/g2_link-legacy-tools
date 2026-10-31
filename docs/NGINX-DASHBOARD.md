# Dashboard-only Nginx/PHP-FPM routing

This is the recipe applied and syntax-checked at VE7VIC, an AlmaLinux 9.8 site
with Nginx serving /var/www/html and proxying Icom WebApp through 5000/5001.
Review your actual server blocks, document root, PHP-FPM upstream/socket and
worker access before using it. This is an administrator procedure, not an
automatic installer modification.

A running PHP-FPM pool does not configure Nginx to execute PHP. VE7VIC's HTML-only
index rules produced 403 for /dashboard/. Enabling directory browsing is not a fix.

## Back up and configure

Preserve /etc/nginx/nginx.conf outside the web tree before editing. Create
/etc/nginx/g2-dashboard.locations containing:

```nginx
location = /dashboard {
    return 301 /dashboard/;
}

location = /dashboard/index.php {
    try_files $uri =404;
    include /etc/nginx/fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass php-fpm;
}

location ^~ /dashboard/ {
    index index.php;
    autoindex off;
    try_files $uri $uri/ =404;

    location ~* \.php(?:/|$) {
        return 404;
    }
}
```

The existing VE7VIC HTTP-context upstream named php-fpm points to
unix:/run/php-fpm/www.sock. Its pool runs as apache and permits apache/nginx
socket access. Substitute your site's actual upstream and settings; do not
create a duplicate upstream or guess a different PHP instance.

Inside each existing HTTP and HTTPS server block serving the dashboard, after
its root directive, add:

```nginx
include /etc/nginx/g2-dashboard.locations;
```

Keep the existing location / and named Icom proxy locations unchanged. Do not
place bare location blocks in a conf.d file included at HTTP scope. The exact
entry-point location executes only index.php; other PHP dependencies in the
dashboard subtree are denied direct requests. Static assets are served normally.

## Validate and apply

```bash
nginx -t && systemctl reload nginx
systemctl --no-pager -l status nginx
```

Stop on validation/reload failure; restore the saved configuration if necessary
and validate it before reloading. Do not disable SELinux or change certificate
settings to resolve a directory-index error.

Run the dashboard installer with the correct local site verification URL, for
example http://127.0.0.1/dashboard/index.php. Verify /dashboard/ and the intended
HTTPS hostname after installation, along with the existing Icom application.
Ensure direct requests to dashboard.local.php return 404 and never source.
No dashboard files after rollback can legitimately produce 404 until installation.

Keep this include in future configuration maintenance: an external certificate
renewal/configuration generator may overwrite nginx.conf. The dashboard updater
does not manage that external tool.

See [PHP runtime readiness](INSTALL.md#web-runtime-readiness-before-installation)
and [QSO logging](INSTALL.md#g2_link-activity-logging-and-history-retention).
