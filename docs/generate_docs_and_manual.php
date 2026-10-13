<?php

/**
 * PDFolio — Publication-Quality PDF Documentation & User Manual Generator
 * Generates:
 *   1. docs/PDFolio_Technical_Documentation.pdf
 *   2. docs/PDFolio_User_Manual.pdf
 */

require __DIR__ . '/../vendor/autoload.php';

class PdfDocumentBase extends FPDF
{
    public string $docTitle = '';
    public string $docSubtitle = '';
    public string $docCategory = '';
    public string $docAuthor = 'Kabir Maharjan | RBBL';
    public bool $isCover = true;

    function Header()
    {
        if ($this->isCover) {
            return;
        }

        // Running top header
        $this->SetY(10);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139); // Slate 500
        $this->Cell(100, 5, $this->encode($this->docTitle), 0, 0, 'L');
        $this->Cell(80, 5, $this->encode($this->docSubtitle), 0, 1, 'R');

        // Thin horizontal separator rule
        $this->SetDrawColor(226, 232, 240); // Slate 200
        $this->SetLineWidth(0.3);
        $this->Line(15, 16, 195, 16);
        $this->Ln(4);
    }

    function Footer()
    {
        if ($this->isCover) {
            return;
        }

        // Running bottom footer
        $this->SetY(-15);
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.3);
        $this->Line(15, 282, 195, 282);

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(120, 8, $this->encode("PDFolio — By Kabir Maharjan © 2026 | RBBL"), 0, 0, 'L');
        $this->Cell(60, 8, $this->encode("Page " . $this->PageNo() . " of {nb}"), 0, 0, 'R');
    }

    public function encode(string $text): string
    {
        // Convert UTF-8 characters to ISO-8859-1 for standard FPDF core fonts
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    public function checkPageOverflow(float $heightNeeded): void
    {
        if ($this->GetY() + $heightNeeded > 270) {
            $this->AddPage();
        }
    }

    public function renderCover(string $title, string $subtitle, string $badge, array $meta): void
    {
        $this->isCover = true;
        $this->AddPage();

        // Top colored accent bar
        $this->SetFillColor(37, 99, 235); // Brand Blue #2563eb
        $this->Rect(15, 15, 180, 4, 'F');

        // Badge pill
        $this->SetY(25);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetFillColor(238, 242, 255); // Indigo 50
        $this->SetTextColor(67, 56, 202); // Indigo 700
        $this->Cell(75, 7, $this->encode("  " . strtoupper($badge) . "  "), 0, 1, 'L', true);

        // Main Title
        $this->Ln(6);
        $this->SetFont('Helvetica', 'B', 24);
        $this->SetTextColor(15, 23, 42); // Slate 900
        $this->MultiCell(180, 10, $this->encode($title), 0, 'L');

        // Subtitle
        $this->Ln(2);
        $this->SetFont('Helvetica', '', 12);
        $this->SetTextColor(71, 85, 105); // Slate 600
        $this->MultiCell(180, 6, $this->encode($subtitle), 0, 'L');

        // Divider Rule
        $this->Ln(6);
        $this->SetDrawColor(203, 213, 225); // Slate 300
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(6);

        // Metadata Info Box
        $this->SetFillColor(248, 250, 252); // Slate 50
        $this->SetDrawColor(226, 232, 240); // Slate 200
        $this->Rect(15, $this->GetY(), 180, 36, 'DF');

        $boxY = $this->GetY() + 4;
        $this->SetY($boxY);

        foreach ($meta as $label => $value) {
            $this->SetX(20);
            $this->SetFont('Helvetica', 'B', 8.5);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(38, 5.5, $this->encode(strtoupper($label) . ":"), 0, 0);

            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(30, 41, 59);
            $this->Cell(120, 5.5, $this->encode($value), 0, 1);
        }

        $this->SetY($boxY + 38);
        $this->Ln(8);

        $this->isCover = false;
    }

    public function h1(string $title): void
    {
        $this->checkPageOverflow(25);
        $this->Ln(4);

        // Blue accent marker
        $y = $this->GetY();
        $this->SetFillColor(37, 99, 235);
        $this->Rect(15, $y, 3, 7.5, 'F');

        $this->SetX(21);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(15, 23, 42); // Slate 900
        $this->Cell(174, 7.5, $this->encode($title), 0, 1, 'L');
        $this->Ln(2);
    }

    public function h2(string $title): void
    {
        $this->checkPageOverflow(18);
        $this->Ln(2);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(30, 41, 59); // Slate 800
        $this->Cell(0, 6, $this->encode($title), 0, 1, 'L');
        $this->Ln(1);
    }

    public function h3(string $title): void
    {
        $this->checkPageOverflow(14);
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->SetTextColor(51, 65, 85); // Slate 700
        $this->Cell(0, 5, $this->encode($title), 0, 1, 'L');
    }

    public function paragraph(string $text): void
    {
        $this->checkPageOverflow(12);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(51, 65, 85);
        $this->MultiCell(180, 4.8, $this->encode($text), 0, 'L');
        $this->Ln(2);
    }

    public function bullet(string $title, string $desc = ''): void
    {
        $this->checkPageOverflow(10);
        $this->SetX(18);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(37, 99, 235);
        $this->Cell(4, 4.8, chr(149), 0, 0); // Bullet symbol

        $this->SetTextColor(30, 41, 59);
        $this->Write(4.8, $this->encode($title . " "));

        if ($desc !== '') {
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(71, 85, 105);
            $this->Write(4.8, $this->encode($desc));
        }

        $this->Ln(5);
    }

    public function callout(string $type, string $title, string $message): void
    {
        $this->checkPageOverflow(26);

        $colors = match ($type) {
            'warning' => ['bg' => [254, 242, 242], 'border' => [239, 68, 68], 'text' => [153, 27, 27]], // Red
            'tip' => ['bg' => [240, 253, 244], 'border' => [34, 197, 94], 'text' => [22, 101, 52]], // Green
            'important' => ['bg' => [255, 251, 235], 'border' => [245, 158, 11], 'text' => [146, 64, 14]], // Amber
            default => ['bg' => [239, 246, 255], 'border' => [59, 130, 246], 'text' => [30, 64, 175]], // Blue
        };

        $yStart = $this->GetY();
        $this->SetFont('Helvetica', '', 8.5);
        
        // Approximate height calculation
        $lines = ceil(strlen($message) / 100) + 1;
        $boxHeight = max(16, ($lines * 4.4) + 9);

        // Box background & left colored accent border
        $this->SetFillColor(...$colors['bg']);
        $this->Rect(15, $yStart, 180, $boxHeight, 'F');

        $this->SetFillColor(...$colors['border']);
        $this->Rect(15, $yStart, 2.5, $boxHeight, 'F');

        $this->SetY($yStart + 2.5);
        $this->SetX(20);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetTextColor(...$colors['text']);
        $this->Cell(170, 4.5, $this->encode(strtoupper($type) . ": " . $title), 0, 1);

        $this->SetX(20);
        $this->SetFont('Helvetica', '', 8.5);
        $this->MultiCell(172, 4.2, $this->encode($message), 0, 'L');

        $this->SetY($yStart + $boxHeight + 3);
    }

    public function codeBlock(string $code): void
    {
        $lines = explode("\n", trim($code));
        $height = (count($lines) * 4.2) + 6;
        $this->checkPageOverflow($height);

        $yStart = $this->GetY();
        $this->SetFillColor(30, 41, 59); // Slate 800 dark background
        $this->Rect(15, $yStart, 180, $height, 'F');

        $this->SetY($yStart + 3);
        $this->SetFont('Courier', '', 8);
        $this->SetTextColor(241, 245, 249); // White/slate-100

        foreach ($lines as $line) {
            $this->SetX(18);
            $this->Cell(174, 4.2, $this->encode($line), 0, 1);
        }

        $this->SetY($yStart + $height + 3);
    }

    public function table(array $headers, array $rows, array $widths, array $aligns = []): void
    {
        $this->checkPageOverflow(14 + (count($rows) * 6));

        // Header
        $this->SetFillColor(15, 23, 42); // Slate 900
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 8);

        $this->SetX(15);
        foreach ($headers as $i => $h) {
            $align = $aligns[$i] ?? 'L';
            $this->Cell($widths[$i], 6.5, $this->encode($h), 1, 0, $align, true);
        }
        $this->Ln();

        // Rows
        $this->SetFont('Helvetica', '', 8);
        foreach ($rows as $rowIndex => $row) {
            $this->checkPageOverflow(7);
            $bg = ($rowIndex % 2 === 0) ? [255, 255, 255] : [248, 250, 252];
            $this->SetFillColor(...$bg);
            $this->SetTextColor(30, 41, 59);
            $this->SetDrawColor(226, 232, 240);

            $this->SetX(15);
            foreach ($row as $colIndex => $val) {
                $align = $aligns[$colIndex] ?? 'L';
                $this->Cell($widths[$colIndex], 6, $this->encode($val), 1, 0, $align, true);
            }
            $this->Ln();
        }

        $this->Ln(3);
    }
}

// =========================================================================
// 1. GENERATE TECHNICAL DOCUMENTATION PDF
// =========================================================================

echo "Generating PDFolio_Technical_Documentation.pdf...\n";

$techDoc = new PdfDocumentBase();
$techDoc->AliasNbPages();
$techDoc->docTitle = "PDFolio — Technical Architecture & Server Installation Guide";
$techDoc->docSubtitle = "Enterprise Self-Hosted Document Suite";

$techDoc->renderCover(
    "PDFolio — Technical & Architecture Guide",
    "Complete specification of system architecture, hardware requirements, CLI engines, zero-retention storage security, and production deployment.",
    "Technical Documentation",
    [
        "Author" => "Kabir Maharjan",
        "Organization" => "RBBL",
        "Stack" => "Laravel 12 (PHP 8.3) | Vue.js 3 | Tailwind CSS 4",
        "Engines" => "QPDF, Ghostscript, Poppler-utils, LibreOffice, OCRmyPDF, FPDI",
        "Version" => "2.0 Production Ready (October 2026)",
    ]
);

$techDoc->h1("1. Architectural Overview & Philosophy");
$techDoc->paragraph(
    "PDFolio is an enterprise-grade, self-hosted web application engineered to replace third-party cloud PDF utilities. Designed for institutions handling sensitive corporate, legal, and financial files, all document processing is executed entirely within your own infrastructure."
);
$techDoc->bullet("Stateless Request Architecture:", "The server retains no customer documents across sessions. Uploaded files are immediately processed in isolated sandbox directories and purged upon delivery.");
$techDoc->bullet("Hybrid Engine Pipeline:", "Pure-PHP libraries (FPDF / FPDI) handle rapid in-memory tasks, while specialized system utilities (QPDF, Ghostscript, Poppler, LibreOffice) handle heavy file manipulation.");
$techDoc->bullet("Dynamic Schema-Driven Registry:", "All application tools are declared in a centralized registry (ToolRegistry). The frontend dynamically compiles tool views, inputs, and validation criteria via the REST API.");

$techDoc->h1("2. Hardware & Operating System Requirements");
$techDoc->paragraph("The following sizing specifications ensure smooth parallel document operations across concurrent users:");

$techDoc->table(
    ["Component", "Minimum Requirement", "Recommended (Production)", "Notes"],
    [
        ["Operating System", "Ubuntu 22.04 LTS / Debian 12", "Ubuntu 24.04 LTS", "RHEL 9 / Amazon Linux 2023 supported"],
        ["CPU", "1 vCPU (2.0 GHz+)", "2 vCPU - 4 vCPU", "OCR and conversions scale across cores"],
        ["System Memory", "1 GB RAM", "2 GB - 4 GB RAM", "LibreOffice and OCRmyPDF require buffer RAM"],
        ["Disk Space", "5 GB Free Storage", "20 GB Fast NVMe / SSD", "Used for ephemeral request scratchpads"],
        ["PHP Runtime", "PHP 8.3", "PHP 8.3+ FPM", "cli, fpm, mbstring, xml, zip, intl, gd, curl"]
    ],
    [32, 45, 45, 58]
);

$techDoc->h1("3. Core Document Engines & Tool Mapping");
$techDoc->paragraph("PDFolio utilizes dedicated Linux CLI tools tuned for speed and precision:");

$techDoc->table(
    ["Engine Binary", "System Package", "Managed Operations", "Key Technical Role"],
    [
        ["qpdf", "qpdf", "Split PDF, Rotate PDF", "Lossless page extraction, orientation, stripping unreferenced streams"],
        ["gs (Ghostscript)", "ghostscript", "Compress PDF", "PDFwrite engine: downsamples bitmaps and recompresses streams"],
        ["pdftoppm", "poppler-utils", "PDF to Images", "Sub-pixel PDF rendering to high-res JPG/PNG raster graphics"],
        ["pdftotext", "poppler-utils", "Extract Text", "Extracts unicode text streams with column alignment preservation"],
        ["soffice", "libreoffice", "Office to PDF", "Headless conversion of DOCX, XLSX, PPTX, ODF, CSV to standard PDF"],
        ["ocrmypdf", "ocrmypdf + tesseract", "OCR PDF", "Generates searchable invisible text layer over scanned image pages"],
        ["FPDI / FPDF", "Composer vendor", "Merge, Watermark, Numbers", "Pure-PHP vector composition, stamping, and pagination headers"]
    ],
    [28, 32, 45, 75]
);

$techDoc->h1("4. Server Installation & Configuration");
$techDoc->paragraph("Follow these exact commands to configure an Ubuntu 24.04 LTS host:");

$techDoc->h2("Step 1: System Packages & Document Engines");
$techDoc->codeBlock(
    "sudo apt update && sudo apt upgrade -y\n" .
    "sudo apt install -y php8.3-cli php8.3-fpm php8.3-mbstring php8.3-xml \\\n" .
    "    php8.3-zip php8.3-intl php8.3-gd php8.3-curl php8.3-sqlite3 \\\n" .
    "    ghostscript qpdf poppler-utils libreoffice \\\n" .
    "    ocrmypdf tesseract-ocr tesseract-ocr-osd apparmor-utils"
);

$techDoc->h2("Step 2: Ghostscript AppArmor Security Exception");
$techDoc->paragraph("Ubuntu confines Ghostscript via AppArmor, preventing it from accessing files under /var/www. Disable the restriction to allow clean PDF compression:");
$techDoc->codeBlock("sudo aa-disable /usr/bin/gs");

$techDoc->h2("Step 3: Web Server & PHP-FPM Upload Tuning");
$techDoc->paragraph("PDFolio accommodates documents up to 100 MB per file (and 220 MB total batch size). Update /etc/php/8.3/fpm/php.ini:");
$techDoc->codeBlock(
    "upload_max_filesize = 100M\n" .
    "post_max_size       = 220M\n" .
    "max_file_uploads    = 60\n" .
    "memory_limit        = 512M\n" .
    "max_execution_time  = 300"
);
$techDoc->paragraph("In Nginx site configuration (/etc/nginx/sites-available/pdfolio), enforce client upload bounds:");
$techDoc->codeBlock("client_max_body_size 220M;");

$techDoc->h1("5. Storage Security & Zero-Retention Lifecycle");
$techDoc->paragraph(
    "Data privacy is enforced by design. When an end-user uploads files, PDFolio creates a cryptographically unique directory in storage/app/pdfolio/{uuid}/. As soon as the deliverable file is generated and streamed back to the browser, PHP's register_shutdown_function triggers an immediate disk purge."
);
$techDoc->callout(
    "important",
    "Automated Pruning Daemon",
    "To guard against aborted connections or power interruptions, PDFolio schedules an hourly maintenance worker (php artisan pdfolio:prune) that wipes any orphaned scratch files older than 3 hours."
);

$techDoc->h1("6. Troubleshooting & System Diagnostics");
$techDoc->paragraph("PDFolio includes an automated self-diagnostic tool to verify your environment:");
$techDoc->codeBlock("php artisan pdfolio:check");

$techDoc->table(
    ["Issue Symptom", "Root Cause", "Definitive Solution"],
    [
        ["HTTP 413 Payload Too Large", "Nginx upload threshold exceeded", "Add 'client_max_body_size 220M;' to the Nginx server block"],
        ["Ghostscript: Unable to open device", "Ubuntu AppArmor confinement active", "Run 'sudo aa-disable /usr/bin/gs' and restart PHP-FPM"],
        ["LibreOffice: source file not loaded", "Installed via Snap sandbox isolation", "Remove Snap ('sudo snap remove libreoffice') and install via APT"],
        ["Failed to open stream (Permission)", "Incorrect ownership on storage folder", "Run 'sudo chown -R www-data:www-data storage bootstrap/cache'"],
        ["Large split PDF file sizes", "Legacy parser copying dead objects", "Resolved in v2.0: QPDF automatically strips unreferenced streams"]
    ],
    [48, 52, 80]
);

$techDocPath = __DIR__ . '/PDFolio_Technical_Documentation.pdf';
$techDoc->Output('F', $techDocPath);
echo "Created: {$techDocPath}\n";

// =========================================================================
// 2. GENERATE USER MANUAL PDF
// =========================================================================

echo "Generating PDFolio_User_Manual.pdf...\n";

$manual = new PdfDocumentBase();
$manual->AliasNbPages();
$manual->docTitle = "PDFolio — Official User Guide & Operations Manual";
$manual->docSubtitle = "Self-Hosted PDF Processing Platform";

$manual->renderCover(
    "PDFolio — User Guide & Operations Manual",
    "Comprehensive walkthrough of all 10 document utilities, file upload rules, configuration parameters, and daily productivity best practices.",
    "User Manual",
    [
        "Author" => "Kabir Maharjan",
        "Organization" => "RBBL",
        "Audience" => "Staff, Department Operators, and Administrative Users",
        "Tools Included" => "10 Dedicated PDF & Office Utilities",
        "Date" => "October 2026",
    ]
);

$manual->h1("1. Welcome to PDFolio");
$manual->paragraph(
    "PDFolio is your organization's private, all-in-one PDF toolkit. It empowers you to merge, split, compress, protect, watermark, reorient, rasterize, and convert corporate documents without uploading confidential data to external internet websites."
);
$manual->bullet("100% Privacy Guarantee:", "Your documents never travel outside the internal corporate network. Processing occurs locally and securely.");
$manual->bullet("No Page Restrictions:", "Work with large documents and multi-page batches without arbitrary limits or account subscriptions.");
$manual->bullet("Zero Residual Storage:", "Files are permanently erased from the server immediately after your download finishes.");

$manual->h1("2. Navigating the User Workspace");
$manual->paragraph("Using PDFolio involves a simple, uniform 3-step workflow across all tools:");
$manual->bullet("Step 1: Choose Your Utility", "Click any tool card on the main dashboard (e.g., Merge, Compress, Split, Rotate).");
$manual->bullet("Step 2: Add Your Files", "Drag documents directly into the dashed drop zone, or click to open your local file explorer. You can upload files up to 100 MB each.");
$manual->bullet("Step 3: Adjust Options & Convert", "Select your preferred settings (e.g., compression level, page range, watermark text) and click the primary action button. Your processed file will automatically download.");

$manual->h1("3. Comprehensive Tool-by-Tool Guide");

$manual->h2("Tool 1: Merge PDF (Combine Documents)");
$manual->paragraph("Combine two or more separate PDF documents into a single cohesive file in your exact required order.");
$manual->bullet("Ideal For:", "Assembling comprehensive reports, compiling loan packages, combining invoices with receipts.");
$manual->bullet("How to Reorder:", "Drag and drop the uploaded document cards left or right to re-arrange page order before clicking 'Merge PDFs'.");

$manual->h2("Tool 2: Split PDF (Extract Pages & Ranges)");
$manual->paragraph("Extract specific portions of a multi-page document into new files. Features three extraction modes:");
$manual->bullet("Merge Ranges into One File:", "Pulls your selected page ranges into a single new document (e.g., Pages 1-3 and 8-10).");
$manual->bullet("Separate PDFs per Range:", "Creates an individual PDF for each distinct range (e.g., '1-3' becomes one file, '4-8' becomes another). Delivered together in a ZIP file.");
$manual->bullet("Split Every Page:", "Breaks a large document into individual 1-page PDFs. Each page is cleanly extracted with zero excess file weight.");
$manual->callout("tip", "Syntax Tip for Page Ranges", "Enter comma-separated page ranges such as '1-4, 7, 10-15'. Spaces are optional.");

$manual->h2("Tool 3: Compress PDF (Reduce File Weight)");
$manual->paragraph("Shrink oversized PDF files for easy email distribution while preserving visual clarity.");
$manual->bullet("Screen Quality (72 DPI):", "Maximum file reduction. Best for quick email previews and web distribution.");
$manual->bullet("eBook Quality (150 DPI):", "Recommended balanced setting. Maintains crisp images and sharp text on high-res monitors.");
$manual->bullet("Print Quality (300 DPI):", "Light compression that maintains full graphic sharpness for physical printing.");

$manual->h2("Tool 4: Rotate PDF (Reorient Scans)");
$manual->paragraph("Fix documents scanned upside-down or sideways in bulk across all pages.");
$manual->bullet("Options:", "Rotate 90 degrees (Clockwise), 180 degrees (Upside Down), or 270 degrees (Counter-Clockwise).");

$manual->h2("Tool 5: Watermark PDF (Stamp Text)");
$manual->paragraph("Stamp custom text across every page to denote classification, draft status, or ownership.");
$manual->bullet("Placement Positions:", "Diagonal full-page banner (rotated across center), Top header, or Bottom footer.");
$manual->bullet("Shade & Intensity:", "Choose Light (subtle background stamp), Medium (standard notice), or Dark (prominent marker).");
$manual->bullet("Font Sizing:", "Adjustable slider from 8pt to 144pt to fit any document format.");

$manual->h2("Tool 6: Page Numbers (Pagination)");
$manual->paragraph("Apply professional pagination numbers to unnumbered PDF decks, reports, or legal transcripts.");
$manual->bullet("Numbering Formats:", "Single page number (e.g., '5') or Page of Total count (e.g., '5 / 42').");
$manual->bullet("Positioning:", "6 layout positions: Top-Left, Top-Center, Top-Right, Bottom-Left, Bottom-Center, Bottom-Right.");
$manual->bullet("Starting Number:", "Customize the starting index (useful when document begins on page 5 or roman numerals).");

$manual->h2("Tool 7: PDF to Images (Raster Export)");
$manual->paragraph("Export all pages of a PDF into individual standalone high-resolution images bundled inside a clean ZIP archive.");
$manual->bullet("Supported Formats:", "JPEG (optimized photographic file size) or PNG (lossless sharp vector lines).");
$manual->bullet("Resolution Modes:", "72 DPI (Standard Web), 150 DPI (Presentation Quality), or 300 DPI (Archival Print Grade).");

$manual->h2("Tool 8: Images to PDF (Photos to Document)");
$manual->paragraph("Convert digital photos, scanned paperwork, and screenshots (JPG, PNG, GIF) into a unified PDF file.");
$manual->bullet("Automatic Orientation:", "Page width and height are dynamically matched to each individual image dimension.");

$manual->h2("Tool 9: Extract Text (Plaintext Conversion)");
$manual->paragraph("Pull all textual content out of a PDF document into a clean, editable text file (.txt).");
$manual->bullet("Preserve Layout (Yes):", "Maintains whitespace columns, tabular figures, and spacing from the original layout.");
$manual->bullet("Raw Reading Order (No):", "Extracts continuous flow text, ideal for importing into spreadsheets or note apps.");

$manual->h2("Tool 10: Office to PDF (Document Conversion)");
$manual->paragraph("Convert office documents directly into standard PDFs with faithful font and styling preservation.");
$manual->bullet("Supported File Types:", "Word (.docx, .doc), Excel (.xlsx, .xls), PowerPoint (.pptx, .ppt), OpenDocument (.odt, .ods, .odp), Rich Text (.rtf), and CSV files.");

$manual->h1("4. Best Practices & Troubleshooting");
$manual->bullet("Batch Sizes:", "Upload up to 50 files at once, provided total combined size does not exceed 220 MB.");
$manual->bullet("Encrypted Documents:", "If a PDF has an active security password, unlock it before applying tools like Split, Compress, or Watermark.");
$manual->bullet("Corrupted Files:", "If a file fails to load, ensure it opens normally in your desktop viewer before uploading.");

$manualPath = __DIR__ . '/PDFolio_User_Manual.pdf';
$manual->Output('F', $manualPath);
echo "Created: {$manualPath}\n";

echo "Both PDFs generated successfully!\n";
