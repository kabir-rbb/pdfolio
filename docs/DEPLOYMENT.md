# PDFolio — Deployment Guide

Production deployment with **nginx + PHP-FPM + HTTPS**, after completing
`docs/INSTALLATION.md`. All examples assume the app lives in
`/var/www/pdfolio` and your domain is `pdf.example.com`.

---

## 1. Install nginx

```bash
sudo apt install -y nginx
```

## 2. Create the nginx site

Create `/etc/nginx/sites-available/pdfolio`:

```nginx
server {
    listen 80;
    server_name pdf.example.com;

    root /var/www/pdfolio/public;
    index index.php;

    # Match the app's 100 MB per-file upload cap
    client_max_body_size 220m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Block dotfiles (keep .well-known for Let's Encrypt)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable it:

```bash
sudo ln -s /etc/nginx/sites-available/pdfolio /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Point your DNS `A` record for `pdf.example.com` at the server's IP, then open
the firewall:

```bash
sudo ufw allow 'Nginx Full'
sudo ufw delete allow 'Nginx HTTP'   # optional cleanup
sudo ufw enable                       # if not already enabled
```

## 3. HTTPS with Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d pdf.example.com
```

Certbot edits the nginx config for you and sets up automatic renewal. Test
renewal with:

```bash
sudo certbot renew --dry-run
```

Update `APP_URL` in `.env` to `https://pdf.example.com` if you have not
already.

## 4. Production caching

```bash
cd /var/www/pdfolio
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Visit `https://pdf.example.com` — you should see the PDFolio home page.

## 5. (Recommended) Restrict access

PDFolio has no user accounts by design. If the server is reachable from the
public internet, put it behind authentication:

**HTTP basic auth (quick):**

```bash
sudo apt install -y apache2-utils
sudo htpasswd -c /etc/nginx/.htpasswd pdfolio
```

Then add inside the nginx `server` block:

```nginx
auth_basic "PDFolio";
auth_basic_user_file /etc/nginx/.htpasswd;
```

Reload nginx. Or keep the server on a private network / VPN / reverse-proxy
SSO instead.

## 6. Updating PDFolio

```bash
cd /var/www/pdfolio
git pull                                      # or unzip the new release over it
composer install --no-dev --optimize-autoloader
npm install && npm run build                  # only if the frontend changed
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.3-fpm nginx
```

## 7. Alternative: quick internal-only run

For a LAN-only instance without nginx:

```bash
cd /var/www/pdfolio
php -d upload_max_filesize=100M -d post_max_size=220M \
    artisan serve --host=0.0.0.0 --port=8000
```

To keep it running after logout, create
`/etc/systemd/system/pdfolio.service`:

```ini
[Unit]
Description=PDFolio dev server
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/pdfolio
ExecStart=/usr/bin/php -d upload_max_filesize=100M -d post_max_size=220M /var/www/pdfolio/artisan serve --host=0.0.0.0 --port=8000
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now pdfolio
```

## 8. Troubleshooting

| Symptom | Likely cause & fix |
|---|---|
| Uploads over ~2 MB fail | php.ini limits not applied — check `php -i \| grep upload_max_filesize`, restart PHP-FPM |
| nginx returns `413 Request Entity Too Large` | `client_max_body_size` too small in the server block |
| 500 error / blank page | Check `storage/logs/laravel.log`; set `APP_DEBUG=true` temporarily |
| "Failed to open stream" on storage | `sudo chown -R www-data:www-data storage bootstrap/cache` |
| OCR fails with "language not installed" | Install the matching `tesseract-ocr-<lang>` pack |
| OCR fails with "osd" error | Install `tesseract-ocr-osd` (needed by auto-rotate) |
| Office/PDF→Word conversion fails | LibreOffice needs a writable `$HOME`; ensure the PHP-FPM user can write to `/tmp`, or run `sudo -u www-data soffice --headless --terminate_after_init` once to create its profile |
| Everything slow on first request | Run the `config:cache` / `route:cache` / `view:cache` commands |

## 9. Health checklist after deploy

- [ ] Home page loads over HTTPS
- [ ] Merge two PDFs end-to-end
- [ ] Upload a ~50 MB file (limits working)
- [ ] Run OCR on a scanned PDF
- [ ] `storage/app/pdfolio/` contains no leftover files after a request
