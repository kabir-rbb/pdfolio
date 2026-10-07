<?php

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

    $count = 0;
    $items = glob($base . '/*') ?: [];
    foreach ($items as $item) {
        if (basename($item) === '.gitignore') {
            continue;
        }
        if (filemtime($item) < $threshold) {
            if (is_dir($item)) {
                $files = glob($item . '/*') ?: [];
                foreach ($files as $f) {
                    @unlink($f);
                }
                @rmdir($item);
            } else {
                @unlink($item);
            }
            $count++;
        }
    }

    $this->info("Pruned {$count} orphaned items older than {$hours} hours.");
})->purpose('Clean up abandoned PDF work directories and temporary files')->hourly();

