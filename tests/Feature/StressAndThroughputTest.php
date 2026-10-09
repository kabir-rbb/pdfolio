<?php

namespace Tests\Feature;

use FPDF;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StressAndThroughputTest extends TestCase
{
    private function createFakePdf(string $name = 'test.pdf', int $pages = 1): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'stress_');
        $pdf = new FPDF;
        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->Cell(40, 10, "Stress Page {$i}");
        }
        $pdf->Output('F', $tempPath);

        return new UploadedFile($tempPath, $name, 'application/pdf', null, true);
    }

    public function test_high_throughput_merge_stress(): void
    {
        $count = 20;
        $startTime = microtime(true);
        $initialDirs = glob(storage_path('app/pdfolio/*')) ?: [];

        for ($i = 0; $i < $count; $i++) {
            $pdf1 = $this->createFakePdf("doc1_{$i}.pdf", 1);
            $pdf2 = $this->createFakePdf("doc2_{$i}.pdf", 2);

            $response = $this->post('/api/tools/merge', [
                'files' => [$pdf1, $pdf2],
            ]);

            $response->assertStatus(200);
            $response->assertHeader('content-type', 'application/pdf');
            $this->assertGreaterThan(0, filesize($response->getFile()->getPathname()));
        }

        $duration = microtime(true) - $startTime;
        $opsPerSec = round($count / $duration, 2);

        $this->assertGreaterThan(0, $opsPerSec);

        // Ensure all working directories created during stress are cleaned up
        $finalDirs = array_filter(glob(storage_path('app/pdfolio/*')) ?: [], 'is_dir');
        $initialDirSet = array_filter($initialDirs, 'is_dir');
        $leakedDirs = array_diff($finalDirs, $initialDirSet);

        $this->assertEmpty($leakedDirs, 'No temporary working directories should leak during high throughput.');
    }

    public function test_mixed_workload_stress(): void
    {
        $operations = 15;
        $initialDirs = glob(storage_path('app/pdfolio/*')) ?: [];

        for ($i = 0; $i < $operations; $i++) {
            $pdf = $this->createFakePdf("mixed_{$i}.pdf", 2);

            // Watermark
            $resWatermark = $this->post('/api/tools/watermark', [
                'file' => $pdf,
                'text' => "CONFIDENTIAL {$i}",
                'position' => 'diagonal',
                'size' => 28,
                'shade' => 'medium',
            ]);
            $resWatermark->assertStatus(200);

            // Page numbers
            $pdf2 = $this->createFakePdf("numbered_{$i}.pdf", 3);
            $resNumbers = $this->post('/api/tools/page-numbers', [
                'file' => $pdf2,
                'position' => 'bottom-center',
                'format' => 'n_of_total',
                'start' => 1,
            ]);
            $resNumbers->assertStatus(200);

            // Split
            $pdf3 = $this->createFakePdf("split_{$i}.pdf", 2);
            $resSplit = $this->post('/api/tools/split', [
                'file' => $pdf3,
                'mode' => 'merge',
                'ranges' => '1-2',
            ]);
            $resSplit->assertStatus(200);
        }

        $finalDirs = array_filter(glob(storage_path('app/pdfolio/*')) ?: [], 'is_dir');
        $initialDirSet = array_filter($initialDirs, 'is_dir');
        $leakedDirs = array_diff($finalDirs, $initialDirSet);

        $this->assertEmpty($leakedDirs, 'No directories leaked during mixed stress workload.');
    }
}
