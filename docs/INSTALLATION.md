# PDFolio — Server Installation Guide

This guide installs PDFolio on a fresh **Ubuntu 24.04 LTS** server. Notes for
Amazon Linux / RHEL / Fedora are included where commands differ.

---

## 1. Server requirements

| Requirement | Minimum | Recommended |
|---|---|---|
| OS | Ubuntu 22.04+ / Debian 12+ / RHEL-family 9+ | Ubuntu 24.04 LTS |
| CPU | 1 vCPU | 2 vCPU |
| RAM | 1 GB | 2 GB (LibreOffice + OCR are memory-hungry) |
| Disk | 5 GB free | 10 GB |
| PHP | 8.3 | 8.3+ with FPM |
| Web server | nginx or Apache | nginx + PHP-FPM |
| Access | SSH with sudo | — |

### PHP extensions

`cli fpm mbstring xml zip intl gd curl sqlite3` (plus `mysql`/`pgsql` only if
you later add a database-backed feature).

### System packages (the PDF engines)

| Package | Used for |
|---|---|
| `ghostscript` | Compress PDF |
| `qpdf` | Rotate, Protect, Unlock |
| `poppler-utils` | PDF → Images, Extract Text |
| `libreoffice` | Office → PDF, PDF → Word |
| `tesseract-ocr` + `ocrmypdf` | OCR PDF |
| `tesseract-ocr-osd` | OCR auto-rotate (required for that option) |
| `tesseract-ocr-<lang>` | OCR languages beyond English |

---

## 2. Update the system

```bash
sudo apt update && sudo apt upgrade -y
```

## 3. Install PHP 8.3

Ubuntu 24.04 ships PHP 8.3 in its default repositories:

```bash
sudo apt install -y php8.3-cli php8.3-fpm php8.3-mbstring php8.3-xml \
    php8.3-zip php8.3-intl php8.3-gd php8.3-curl php8.3-sqlite3
```

On older Ubuntu/Debian releases, add Ondřej's repository first:

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
# then run the php8.3 install command above
```

Verify:

```bash
php -v
```

## 4. Install Composer

```bash
curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

## 5. Install Node.js (optional — build-time only)

Prebuilt frontend assets ship with the project, so Node is only needed if you
want to modify and rebuild the UI:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v
```

## 6. Install the PDF engines

```bash
sudo apt install -y ghostscript qpdf poppler-utils libreoffice \
    ocrmypdf tesseract-ocr tesseract-ocr-osd
```

Amazon Linux / Fedora:

```bash
sudo dnf install -y ghostscript qpdf poppler-utils libreoffice \
    tesseract tesseract-osd
pip3 install ocrmypdf
```

### OCR language packs

Tesseract ships with English only. Install every language you want to offer in
the OCR tool:

```bash
# Debian / Ubuntu
sudo apt install -y tesseract-ocr-nep tesseract-ocr-hin \
    tesseract-ocr-spa tesseract-ocr-fra tesseract-ocr-deu

# Fedora family
sudo dnf install -y tesseract-langpack-nep tesseract-langpack-hin \
    tesseract-langpack-spa tesseract-langpack-fra tesseract-langpack-deu
```

Check what is installed:

```bash
tesseract --list-langs
```

### Ghostscript AppArmor permission (Ubuntu)

On Ubuntu systems, AppArmor confines `/usr/bin/gs` and denies it from reading or writing files under `/var/www` (`apparmor="DENIED"` in `dmesg`), which causes Ghostscript to fail with:
`**** Could not open the file ... **** Unable to open the initial device, quitting.`

Disable the restrictive AppArmor profile for Ghostscript:

```bash
sudo apt install -y apparmor-utils
sudo aa-disable /usr/bin/gs
```

## 7. Install PDFolio

```bash
cd /var/www
sudo git clone <your-repository-url> pdfolio
# — or copy the zip —
# sudo unzip pdfolio.zip -d /var/www && sudo mv /var/www/pdfolio-main /var/www/pdfolio

cd /var/www/pdfolio
composer install --no-dev --optimize-autoloader

cp .env.example .env
php artisan key:generate
```

Edit `.env` for production:

```ini
APP_NAME=PDFolio
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pdf.example.com
```

Rebuild the frontend only if you changed it (assets are prebuilt):

```bash
npm install
npm run build
```

Set ownership and permissions:

```bash
sudo chown -R www-data:www-data /var/www/pdfolio/storage /var/www/pdfolio/bootstrap/cache
sudo chmod -R 775 /var/www/pdfolio/storage /var/www/pdfolio/bootstrap/cache
```

## 8. Raise PHP and Web Server (Nginx) upload limits

PDFolio accepts uploads up to 100 MB per file (and up to 220 MB for multi-file merges). Both PHP and your web server must allow these sizes.

### PHP limits

Edit `/etc/php/8.3/fpm/php.ini` (and `cli/php.ini` if you run artisan jobs):

```ini
upload_max_filesize = 100M
post_max_size       = 220M
max_file_uploads    = 60
memory_limit        = 512M
max_execution_time  = 300
```

Apply:

```bash
sudo systemctl restart php8.3-fpm
```

### Nginx upload limit (`client_max_body_size`)

By default, Nginx limits uploads to **1 MB**. Uploads larger than 1 MB will be blocked with `413 Request Entity Too Large` (`client intended to send too large body` in Nginx error logs).

In your Nginx site configuration (`/etc/nginx/sites-available/pdfolio` or `/etc/nginx/nginx.conf`), set `client_max_body_size` inside the `server` block:

```nginx
server {
    ...
    # Allow uploads up to 220M (at least 100M required)
    client_max_body_size 220M;
    ...
}
```

Reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Continue to `docs/DEPLOYMENT.md` to put the app behind nginx with HTTPS.
