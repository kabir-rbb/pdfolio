<?php

use App\Services\Pdf\PdfProcessor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

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
        $this->comment('sudo apt install ghostscript qpdf poppler-utils libreoffice');
    }
})->purpose('Check availability of PDF processing engines and system tools');

Artisan::command('pdfolio:bench {--requests=20 : Total requests to simulate} {--tool=merge : Tool to benchmark (merge, watermark, page-numbers)}', function (PdfProcessor $pdf) {
    $requests = (int) $this->option('requests');
    $tool = (string) $this->option('tool');

    $this->info("Benchmarking PDFolio [{$tool}] with {$requests} operations...");

    $tmp = storage_path('app/pdfolio/bench-'.Str::uuid());
    @mkdir($tmp, 0775, true);

    // Create a 2-page test PDF
    $doc = $tmp.'/sample.pdf';
    $fpdf = new FPDF;
    $fpdf->AddPage();
    $fpdf->SetFont('Helvetica', 'B', 14);
    $fpdf->Cell(40, 10, 'Bench Page 1');
    $fpdf->AddPage();
    $fpdf->Cell(40, 10, 'Bench Page 2');
    $fpdf->Output('F', $doc);

    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    $success = 0;
    $errors = 0;

    $bar = $this->output->createProgressBar($requests);
    $bar->start();

    for ($i = 0; $i < $requests; $i++) {
        $out = $tmp."/out-{$i}.pdf";
        try {
            switch ($tool) {
                case 'watermark':
                    $pdf->watermark($doc, 'CONFIDENTIAL', 'diagonal', 36, 'light', $out);
                    break;
                case 'page-numbers':
                    $pdf->pageNumbers($doc, 'bottom-center', 'n_of_total', 1, $out);
                    break;
                case 'merge':
                default:
                    $pdf->merge([$doc, $doc], $out);
                    break;
            }

            if (file_exists($out) && filesize($out) > 0) {
                $success++;
            } else {
                $errors++;
            }
            @unlink($out);
        } catch (Throwable $e) {
            $errors++;
        }
        $bar->advance();
    }

    $bar->finish();
    $this->line('');

    $totalTime = microtime(true) - $startTime;
    $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
    $opsPerSec = $totalTime > 0 ? round($requests / $totalTime, 2) : 0;

    @unlink($doc);
    @rmdir($tmp);

    $this->table(
        ['Metric', 'Value'],
        [
            ['Total Operations', $requests],
            ['Successful', "<info>{$success}</info>"],
            ['Failed', $errors > 0 ? "<error>{$errors}</error>" : '<info>0</info>'],
            ['Total Time', round($totalTime, 3).' s'],
            ['Throughput', "{$opsPerSec} ops/sec"],
            ['Peak Memory', round($peakMemory, 2).' MB'],
        ]
    );

    if ($errors === 0) {
        $this->info('Throughput benchmark completed successfully!');
    } else {
        $this->error("Benchmark encountered {$errors} errors under load.");
    }
})->purpose('Simulate concurrent stress/throughput on PDF processing');
