<?php

namespace App\Services\Pdf;

use FPDF;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * All PDF manipulation logic. Pure-PHP work (merge/split/watermark/numbers,
 * images→PDF) uses FPDF/FPDI; heavier lifting (compression, rotation,
 * encryption, rasterization, text extraction, office conversion) is delegated
 * to Ghostscript, qpdf, Poppler and LibreOffice through the CLI.
 */
class PdfProcessor
{
    /* ----------------------------------------------------------------- */
    /*  Merge */
    /* ----------------------------------------------------------------- */

    public function merge(array $files, string $out): void
    {
        $pdf = new Fpdi;

        foreach ($files as $file) {
            $pageCount = $pdf->setSourceFile($file);
            for ($page = 1; $page <= $pageCount; $page++) {
                $this->importPageInto($pdf, $file, $page);
            }
        }

        $pdf->Output('F', $out);
    }

    /* ----------------------------------------------------------------- */
    /*  Split */
    /* ----------------------------------------------------------------- */

    /**
     * @param  array<int, array{0:int,1:int}>  $ranges
     * @return array<int, string> list of generated PDF paths
     */
    public function split(string $file, array $ranges, string $mode, string $workDir): array
    {
        if ($mode === 'all') {
            $probe = new Fpdi;
            $pageCount = $probe->setSourceFile($file);
            $ranges = array_map(fn (int $p) => [$p, $p], range(1, $pageCount));
            $mode = 'separate';
        }

        if (empty($ranges)) {
            throw new RuntimeException('No valid page ranges were provided.');
        }

        $outputs = [];

        if ($mode === 'merge') {
            $out = $workDir.'/split-'.$this->rangeLabel($ranges).'.pdf';
            $pdf = new Fpdi;
            foreach ($ranges as [$start, $end]) {
                for ($page = $start; $page <= $end; $page++) {
                    $this->importPageInto($pdf, $file, $page);
                }
            }
            $pdf->Output('F', $out);
            $outputs[] = $out;
        } else {
            foreach ($ranges as [$start, $end]) {
                $out = $workDir.'/pages-'.$start.'-'.$end.'.pdf';
                $pdf = new Fpdi;
                for ($page = $start; $page <= $end; $page++) {
                    $this->importPageInto($pdf, $file, $page);
                }
                $pdf->Output('F', $out);
                $outputs[] = $out;
            }
        }

        return $outputs;
    }

    /* ----------------------------------------------------------------- */
    /*  Compress (Ghostscript) */
    /* ----------------------------------------------------------------- */

    public function compress(string $file, string $level, string $out): void
    {
        $gs = $this->ghostscriptBinary();

        $this->run([
            $gs, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/'.$level,
            '-dNOPAUSE', '-dBATCH', '-dNOSAFER',
            '-sOutputFile='.$out, $file,
        ]);

        if (! file_exists($out) || filesize($out) === 0) {
            throw new RuntimeException('Compression failed to produce a file.');
        }
    }

    /* ----------------------------------------------------------------- */
    /*  Rotate (qpdf) */
    /* ----------------------------------------------------------------- */

    public function rotate(string $file, int $degrees, string $out): void
    {
        $this->run(['qpdf', $file, $out, '--rotate=+'.$degrees.':1-z']);
    }

    /* ----------------------------------------------------------------- */
    /*  Protect / Unlock (qpdf) */
    /* ----------------------------------------------------------------- */

    public function protect(string $file, string $password, string $out): void
    {
        $owner = bin2hex(random_bytes(12));
        $this->run(['qpdf', '--encrypt', $password, $owner, '256', '--', $file, $out]);
    }

    public function unlock(string $file, ?string $password, string $out): void
    {
        $cmd = ['qpdf'];
        if ($password !== null && $password !== '') {
            $cmd[] = '--password='.$password;
        }
        $cmd[] = '--decrypt';
        $cmd[] = $file;
        $cmd[] = $out;
        $this->run($cmd, 'Could not unlock this PDF — the password may be incorrect.');
    }

    /* ----------------------------------------------------------------- */
    /*  Watermark (FPDI) */
    /* ----------------------------------------------------------------- */

    public function watermark(string $file, string $text, string $position, int $size, string $shade, string $out): void
    {
        $gray = match ($shade) {
            'medium' => 150,
            'dark' => 90,
            default => 195,
        };

        $size = max(8, min(144, $size));
        $pdf = new RotatableFpdi;
        $pageCount = $pdf->setSourceFile($file);

        for ($page = 1; $page <= $pageCount; $page++) {
            $tpl = $pdf->importPage($page);
            $dim = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($dim['orientation'], [$dim['width'], $dim['height']]);
            $pdf->useTemplate($tpl);

            $pdf->SetFont('Helvetica', 'B', $size);
            $pdf->SetTextColor($gray, $gray, $gray);

            if ($position === 'diagonal') {
                $angle = atan2($dim['height'], $dim['width']) * 180 / M_PI;
                $cx = $dim['width'] / 2;
                $cy = $dim['height'] / 2;
                $textWidth = $pdf->GetStringWidth($text);
                $pdf->rotatedText($cx - $textWidth / 2, $cy, $text, $angle);
            } else {
                $y = $position === 'top' ? 25 : $dim['height'] - 20;
                $textWidth = $pdf->GetStringWidth($text);
                $pdf->Text(($dim['width'] - $textWidth) / 2, $y, $text);
            }
        }

        $pdf->Output('F', $out);
    }

    /* ----------------------------------------------------------------- */
    /*  Page numbers (FPDI) */
    /* ----------------------------------------------------------------- */

    public function pageNumbers(string $file, string $position, string $format, int $start, string $out): void
    {
        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($file);

        for ($page = 1; $page <= $pageCount; $page++) {
            $tpl = $pdf->importPage($page);
            $dim = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($dim['orientation'], [$dim['width'], $dim['height']]);
            $pdf->useTemplate($tpl);

            $number = $start + $page - 1;
            $label = $format === 'n_of_total'
                ? $number.' / '.($start + $pageCount - 1)
                : (string) $number;

            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetTextColor(70, 70, 70);

            $margin = 10;
            $isTop = str_starts_with($position, 'top');
            $y = $isTop ? $margin + 4 : $dim['height'] - $margin;
            $textWidth = $pdf->GetStringWidth($label);

            $x = match (true) {
                str_ends_with($position, 'left') => $margin,
                str_ends_with($position, 'right') => $dim['width'] - $margin - $textWidth,
                default => ($dim['width'] - $textWidth) / 2,
            };

            $pdf->Text($x, $y, $label);
        }

        $pdf->Output('F', $out);
    }

    /* ----------------------------------------------------------------- */
    /*  PDF → images (Poppler) */
    /* ----------------------------------------------------------------- */

    /** @return array<int, string> */
    public function pdfToImages(string $file, string $format, int $dpi, string $workDir): array
    {
        $prefix = $workDir.'/page';
        $flag = $format === 'png' ? '-png' : '-jpeg';
        $this->run(['pdftoppm', $flag, '-r', (string) $dpi, $file, $prefix]);

        $images = glob($workDir.'/page*.'.$format);
        if (empty($images)) {
            throw new RuntimeException('No images were produced from this PDF.');
        }
        sort($images);

        return $images;
    }

    /* ----------------------------------------------------------------- */
    /*  Images → PDF (FPDF) */
    /* ----------------------------------------------------------------- */

    public function imagesToPdf(array $images, string $out): void
    {
        $pdf = new FPDF;

        foreach ($images as $image) {
            $info = @getimagesize($image);
            if ($info === false) {
                throw new RuntimeException('One of the uploaded files is not a valid image.');
            }
            [$widthPx, $heightPx] = $info;
            $widthMm = $widthPx * 25.4 / 96;
            $heightMm = $heightPx * 25.4 / 96;
            $orientation = $widthPx > $heightPx ? 'L' : 'P';

            $pdf->AddPage($orientation, [$widthMm, $heightMm]);
            $pdf->Image($image, 0, 0, $widthMm, $heightMm);
        }

        $pdf->Output('F', $out);
    }

    /* ----------------------------------------------------------------- */
    /*  Extract text (Poppler) */
    /* ----------------------------------------------------------------- */

    public function extractText(string $file, bool $keepLayout, string $out): void
    {
        $cmd = ['pdftotext'];
        if ($keepLayout) {
            $cmd[] = '-layout';
        }
        $cmd[] = $file;
        $cmd[] = $out;
        $this->run($cmd);
    }

    /* ----------------------------------------------------------------- */
    /*  Office → PDF (LibreOffice) */
    /* ----------------------------------------------------------------- */

    /* ----------------------------------------------------------------- */
    /*  Office → PDF (LibreOffice) */
    /* ----------------------------------------------------------------- */

    public function officeToPdf(string $file, string $workDir): string
    {
        if (! $this->isLibreOfficeAvailable()) {
            throw new RuntimeException(
                'LibreOffice is not installed or not found on the server. '
                .'Please install it (e.g. "sudo apt install libreoffice" on Ubuntu/Debian).'
            );
        }

        $bin = $this->libreOfficeBinary();
        $profileDir = str_replace('\\', '/', $workDir).'/lo_profile';
        $fileUri = 'file:///'.ltrim(str_replace('\\', '/', $profileDir), '/');

        $this->run([
            $bin,
            '-env:UserInstallation='.$fileUri,
            '--headless',
            '--norestore',
            '--convert-to', 'pdf', '--outdir', $workDir, $file,
        ], 'LibreOffice could not convert this document.', 180, $workDir, [
            'HOME' => $workDir,
            'TMPDIR' => $workDir,
        ]);

        $expectedPdf = $workDir.'/'.pathinfo($file, PATHINFO_FILENAME).'.pdf';
        if (file_exists($expectedPdf) && filesize($expectedPdf) > 0) {
            return $expectedPdf;
        }

        $produced = glob($workDir.'/*.pdf') ?: [];
        if (! empty($produced) && filesize($produced[0]) > 0) {
            return $produced[0];
        }

        throw new RuntimeException('The conversion produced no PDF file.');
    }

    /* ----------------------------------------------------------------- */
    /*  PDF → Word (LibreOffice Writer PDF import filter) */
    /* ----------------------------------------------------------------- */

    public function pdfToWord(string $file, string $workDir): string
    {
        if (! $this->isLibreOfficeAvailable()) {
            throw new RuntimeException(
                'LibreOffice is not installed or not found on the server. '
                .'Please install it (e.g. "sudo apt install libreoffice" on Ubuntu/Debian).'
            );
        }

        $bin = $this->libreOfficeBinary();
        $profileDir = str_replace('\\', '/', $workDir).'/lo_profile';
        $fileUri = 'file:///'.ltrim(str_replace('\\', '/', $profileDir), '/');

        $this->run([
            $bin,
            '-env:UserInstallation='.$fileUri,
            '--headless',
            '--norestore',
            '--infilter=writer_pdf_import',
            '--convert-to', 'docx', '--outdir', $workDir, $file,
        ], 'LibreOffice could not convert this PDF to Word.', 180, $workDir, [
            'HOME' => $workDir,
            'TMPDIR' => $workDir,
        ]);

        $expectedDocx = $workDir.'/'.pathinfo($file, PATHINFO_FILENAME).'.docx';
        if (file_exists($expectedDocx) && filesize($expectedDocx) > 0) {
            return $expectedDocx;
        }

        $produced = glob($workDir.'/*.docx') ?: [];
        if (! empty($produced) && filesize($produced[0]) > 0) {
            return $produced[0];
        }

        throw new RuntimeException('The conversion produced no Word document.');
    }

    /* ----------------------------------------------------------------- */
    /*  OCR (OCRmyPDF + Tesseract) */
    /* ----------------------------------------------------------------- */

    public function ocr(string $file, string $language, bool $skipText, bool $rotatePages, string $out): void
    {
        $cmd = ['ocrmypdf', '-l', $language, '--output-type', 'pdf'];
        if ($skipText) {
            $cmd[] = '--skip-text';
        }
        if ($rotatePages) {
            $cmd[] = '--rotate-pages';
        }
        $cmd[] = $file;
        $cmd[] = $out;

        $this->run(
            $cmd,
            'OCR failed — the document may already contain text, or the selected language data is not installed on the server.',
            600
        );

        if (! file_exists($out) || filesize($out) === 0) {
            throw new RuntimeException('OCR produced no output file.');
        }
    }

    /* ----------------------------------------------------------------- */
    /*  Helpers */
    /* ----------------------------------------------------------------- */

    /** @return array<int, string> */
    public function zip(array $files, string $zipPath): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP archive.');
        }
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        return $zipPath;
    }

    /**
     * Parse a ranges string like "1-3,5,8-10" into [[1,3],[5,5],[8,10]],
     * clamped to the document's page count.
     *
     * @return array<int, array{0:int,1:int}>
     */
    public function parseRanges(string $input, int $maxPage): array
    {
        $ranges = [];
        foreach (explode(',', $input) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d+)(?:-(\d+))?$/', $part, $m) !== 1) {
                throw new RuntimeException('Invalid range "'.$part.'". Use formats like 1-3,5,8-10.');
            }
            $start = (int) $m[1];
            $end = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $start;
            if ($start < 1 || $end < $start || $start > $maxPage) {
                throw new RuntimeException('Range "'.$part.'" is outside the document (1-'.$maxPage.').');
            }
            $ranges[] = [$start, min($end, $maxPage)];
        }

        return $ranges;
    }

    public function pageCount(string $file): int
    {
        $probe = new Fpdi;

        return $probe->setSourceFile($file);
    }

    private function importPageInto(Fpdi $pdf, string $file, int $page): void
    {
        $pdf->setSourceFile($file);
        $tpl = $pdf->importPage($page);
        $dim = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($dim['orientation'], [$dim['width'], $dim['height']]);
        $pdf->useTemplate($tpl);
    }

    /** @param array<int, array{0:int,1:int}> $ranges */
    private function rangeLabel(array $ranges): string
    {
        return implode('_', array_map(fn ($r) => $r[0].'-'.$r[1], $ranges));
    }

    /**
     * @param  array<int, string>  $cmd
     * @param  array<string, string>  $env
     */
    protected function run(array $cmd, ?string $friendlyError = null, int $timeout = 120, ?string $cwd = null, array $env = []): void
    {
        $process = new Process($cmd, $cwd, $env ?: null);
        $process->setInput('');
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException(
                ($friendlyError ?? 'The PDF engine reported an error.')
                .($detail !== '' ? ' ('.mb_strimwidth($detail, 0, 300).')' : '')
            );
        }
    }

    public function ghostscriptBinary(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['gswin64c', 'gswin32c', 'gs'] as $bin) {
                $process = new Process(['where', $bin]);
                $process->run();
                if ($process->isSuccessful()) {
                    return $bin;
                }
            }

            return 'gs';
        }

        foreach (['/usr/bin/gs', '/usr/local/bin/gs', '/bin/gs'] as $bin) {
            if (is_executable($bin)) {
                return $bin;
            }
        }

        return 'gs';
    }

    public function isGhostscriptAvailable(): bool
    {
        return $this->isBinaryAvailable($this->ghostscriptBinary());
    }

    public function libreOfficeBinary(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['soffice', 'libreoffice'] as $bin) {
                $process = new Process(['where', $bin]);
                $process->run();
                if ($process->isSuccessful()) {
                    $firstLine = trim(explode("\r\n", trim($process->getOutput()))[0] ?? '');
                    if ($firstLine !== '') {
                        return $firstLine;
                    }
                }
            }

            foreach ([
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            ] as $candidate) {
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }

            return 'soffice';
        }

        foreach ([
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/usr/local/bin/soffice',
            '/usr/local/bin/libreoffice',
            '/usr/lib/libreoffice/program/soffice',
            '/bin/soffice',
            '/bin/libreoffice',
        ] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        foreach (['soffice', 'libreoffice'] as $bin) {
            $process = new Process(['which', $bin]);
            $process->run();
            if ($process->isSuccessful()) {
                $firstLine = trim(explode("\n", trim($process->getOutput()))[0] ?? '');
                if ($firstLine !== '') {
                    return $firstLine;
                }
            }
        }

        return 'soffice';
    }

    public function isLibreOfficeAvailable(): bool
    {
        return $this->isBinaryAvailable($this->libreOfficeBinary());
    }

    public function isQpdfAvailable(): bool
    {
        return $this->isBinaryAvailable('qpdf');
    }

    public function isPopplerAvailable(): bool
    {
        return $this->isBinaryAvailable('pdftoppm') && $this->isBinaryAvailable('pdftotext');
    }

    public function isOcrAvailable(): bool
    {
        return $this->isBinaryAvailable('ocrmypdf');
    }

    private function isBinaryAvailable(string $binary): bool
    {
        if (file_exists($binary)) {
            return is_executable($binary) || PHP_OS_FAMILY === 'Windows';
        }

        $checkCmd = PHP_OS_FAMILY === 'Windows' ? ['where', $binary] : ['which', $binary];
        $process = new Process($checkCmd);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Diagnostic report of all engine dependencies.
     *
     * @return array<string, array{name: string, available: bool, binary: string, package: string}>
     */
    public function checkEnvironment(): array
    {
        return [
            'ghostscript' => [
                'name' => 'Ghostscript (compress)',
                'available' => $this->isGhostscriptAvailable(),
                'binary' => $this->ghostscriptBinary(),
                'package' => 'ghostscript',
            ],
            'qpdf' => [
                'name' => 'QPDF (rotate, protect, unlock)',
                'available' => $this->isQpdfAvailable(),
                'binary' => 'qpdf',
                'package' => 'qpdf',
            ],
            'poppler' => [
                'name' => 'Poppler (pdf-to-images, extract-text)',
                'available' => $this->isPopplerAvailable(),
                'binary' => 'pdftoppm, pdftotext',
                'package' => 'poppler-utils',
            ],
            'libreoffice' => [
                'name' => 'LibreOffice (office-to-pdf, pdf-to-word)',
                'available' => $this->isLibreOfficeAvailable(),
                'binary' => $this->libreOfficeBinary(),
                'package' => 'libreoffice',
            ],
            'ocrmypdf' => [
                'name' => 'OCRmyPDF + Tesseract (ocr)',
                'available' => $this->isOcrAvailable(),
                'binary' => 'ocrmypdf',
                'package' => 'ocrmypdf tesseract-ocr',
            ],
        ];
    }
}
