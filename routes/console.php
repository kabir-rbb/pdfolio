<?php

use App\Services\Pdf\PdfProcessor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pdfolio:prune {--hours=2 : Delete files older than this many hours}', function () {
    $hours = (int) $this->option('hours');
    $threshold = now()->subHours($hours)->timestamp;
    $base = storage_path('app/pdfolio');

    if (! is_dir($base)) {
        $this->info('No temporary directory found.');

        return;
    }

    $deleteRecursive = function (string $dir) use (&$deleteRecursive): void {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $deleteRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    };

    $count = 0;
    $items = glob($base.'/*') ?: [];
    foreach ($items as $item) {
        if (basename($item) === '.gitignore') {
            continue;
        }
        if (filemtime($item) < $threshold) {
            if (is_dir($item)) {
                $deleteRecursive($item);
            } else {
                @unlink($item);
            }
            $count++;
        }
    }

    $this->info("Pruned {$count} orphaned items older than {$hours} hours.");
})->purpose('Clean up abandoned PDF work directories and temporary files')->hourly();

Artisan::command('pdfolio:check', function (PdfProcessor $pdf) {
    $this->info('Checking PDFolio system dependencies...'.PHP_EOL);

    $results = $pdf->checkEnvironment();
    $rows = [];
    $allOk = true;

    foreach ($results as $item) {
        $status = $item['available'] ? '<info>✓ Available</info>' : '<error>✗ Missing</error>';
        if (! $item['available']) {
            $allOk = false;
        }
        $rows[] = [$item['name'], $status, $item['binary'], $item['package']];
    }

    $this->table(['Engine', 'Status', 'Resolved Binary', 'System Package'], $rows);

    $storagePath = storage_path('app/pdfolio');
    $isWritable = is_writable(storage_path('app')) || (is_dir($storagePath) && is_writable($storagePath));
    $this->line('');
    $this->line('Storage directory ('.$storagePath.'): '.($isWritable ? '<info>Writable</info>' : '<error>Not Writable</error>'));

    if ($allOk && $isWritable) {
        $this->info(PHP_EOL.'All dependencies are installed and ready!');
    } else {
        $this->warn(PHP_EOL.'Some dependencies are missing. On Ubuntu/Debian, run:');
        $this->comment('sudo apt install ghostscript qpdf poppler-utils libreoffice ocrmypdf tesseract-ocr');
    }
})->purpose('Check availability of PDF processing engines and system tools');
