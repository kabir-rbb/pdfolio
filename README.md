# PDFolio



**Every PDF tool you need — running on your own server. No limits, no third parties, no accounts.**

PDFolio is a self-hosted alternative to iLovePDF-style services, built with
**Laravel**, **Vue.js 3** and **Tailwind CSS**. All processing happens on your
own machine, so your documents never leave your infrastructure.

![Stack](https://img.shields.io/badge/Laravel-13-red) ![Stack](https://img.shields.io/badge/Vue-3-green) ![Stack](https://img.shields.io/badge/Tailwind-4-blue)

## Tools included

| Tool | What it does | Engine |
|---|---|---|
| **Merge PDF** | Combine many PDFs into one, in your chosen order | FPDI (PHP) |
| **Split PDF** | Extract ranges into one PDF, separate PDFs, or one file per page | FPDI (PHP) |
| **Compress PDF** | Three quality levels (screen / ebook / printer) | Ghostscript |
| **Rotate PDF** | Rotate all pages by 90°, 180° or 270° | qpdf |
| **Watermark PDF** | Text watermark: diagonal, top or bottom, with size & intensity | FPDI (PHP) |
| **Page Numbers** | Position, format (`1`, `1 / 12`) and starting number | FPDI (PHP) |
| **Protect PDF** | AES-256 password encryption | qpdf |
| **Unlock PDF** | Remove a known password | qpdf |
| **PDF to Images** | Each page → JPG or PNG at 72/150/300 dpi (ZIP) | Poppler |
| **PDF to Word** | PDF → editable .docx, preserving layout | LibreOffice |
| **OCR PDF** | Add a searchable text layer to scanned PDFs | OCRmyPDF + Tesseract |
| **Images to PDF** | JPG / PNG / GIF images → one PDF | FPDF (PHP) |
| **Extract Text** | Pull all text into a `.txt`, optionally keeping layout | Poppler |
| **Office to PDF** | Word, Excel, PowerPoint, ODF, RTF, CSV, TXT → PDF | LibreOffice |

## Server requirements

- **PHP 8.3+** with extensions: `mbstring xml zip intl gd curl pdo sqlite3`
- **Composer** 2.x
- **Node.js** 20+ and npm (only needed to build the frontend assets)
- System packages:

```bash
# Ubuntu / Debian
sudo apt install ghostscript qpdf poppler-utils libreoffice ocrmypdf tesseract-ocr

# Amazon Linux / RHEL / Fedora
sudo dnf install ghostscript qpdf poppler-utils libreoffice tesseract tesseract-osd
pip3 install ocrmypdf
```

**OCR language data** — Tesseract ships with English only. Install extra
languages for the OCR tool's language selector, e.g.:

```bash
sudo apt install tesseract-ocr-nep tesseract-ocr-hin   # Debian/Ubuntu
sudo dnf install tesseract-langpack-nep tesseract-langpack-hin   # Fedora-family
```

The OCR auto-rotate option needs the `osd` traineddata (package
`tesseract-osd` / `tesseract-langpack-osd`).

## Documentation

- **[docs/INSTALLATION.md](docs/INSTALLATION.md)** — full server requirements and step-by-step installation
- **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** — production deployment with nginx, HTTPS, updates and troubleshooting

## Installation

```bash
git clone <your-repo> pdfolio   # or copy this folder to your server
cd pdfolio

# 1. Backend dependencies
composer install --no-dev --optimize-autoloader

# 2. Environment
cp .env.example .env            # skip if .env already exists
php artisan key:generate        # skip if APP_KEY is already set

# 3. Frontend assets
npm install
npm run build

# 4. Storage permissions
chmod -R 775 storage bootstrap/cache
```

### Upload limits

Raise PHP's upload limits to match the app's 100 MB per-file cap:

```ini
; php.ini (or /etc/php.d/pdfolio.ini)
upload_max_filesize = 100M
post_max_size       = 220M
max_file_uploads    = 60
```

If you serve through **nginx**, also set `client_max_body_size 220m;` in the
server block.

## Running

### Quick local run (development)

```bash
php -d upload_max_filesize=100M -d post_max_size=220M artisan serve
# open http://127.0.0.1:8000
```

### Production (nginx + PHP-FPM)

```nginx
server {
    listen 80;
    server_name pdf.example.com;
    root /var/www/pdfolio/public;
    client_max_body_size 220m;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Then cache the framework config:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## How it works

- `app/Support/ToolRegistry.php` — the single source of truth for every tool:
  name, description, icon, accepted files and the options form schema. The
  Vue frontend renders itself entirely from this registry via `GET /api/tools`.
- `app/Services/Pdf/PdfProcessor.php` — the processing engine. Pure-PHP
  operations use FPDF/FPDI; heavy lifting is delegated to Ghostscript, qpdf,
  Poppler and LibreOffice through Symfony Process.
- `app/Http/Controllers/ToolController.php` — validates uploads and options,
  runs the requested tool in a per-request work directory, and streams the
  result back as a download. Temp files are deleted after every request.
- PDF → Word keeps the original layout by placing text in editable Word
  frames; highly complex layouts may need touch-ups.
- `POST /api/tools/{tool}` — one endpoint per tool; the response is the
  processed file (PDF, TXT or ZIP), or a JSON error with a friendly message.

### Adding a new tool

1. Add an entry to `ToolRegistry` (slug, title, options…).
2. Add a `case` for the slug in `ToolController::dispatch()`.
3. Implement the logic in `PdfProcessor` (or a new service).
4. Add an icon to `resources/js/components/ToolIcon.vue`.

No frontend routing or page work needed — the UI picks it up automatically.

## Security & privacy notes

- Files are processed in a unique temp directory per request and deleted
  immediately after the download is sent.
- All endpoints are CSRF-protected; validation limits file types and sizes.
- Consider putting the app behind authentication (e.g. Laravel's HTTP basic
  auth or a reverse proxy SSO) if it is reachable from the public internet.

## License

MIT

