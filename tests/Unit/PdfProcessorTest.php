<?php

namespace Tests\Unit;

use App\Services\Pdf\PdfProcessor;
use FPDF;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class PdfProcessorTest extends TestCase
{
    private string $tempDir;

    private PdfProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new PdfProcessor;
        $this->tempDir = sys_get_temp_dir().'/pdfolio_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupDir($this->tempDir);
        parent::tearDown();
    }

    private function cleanupDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->cleanupDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function createSamplePdf(int $pages = 1): string
    {
        $pdf = new FPDF;
        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 16);
            $pdf->Cell(40, 10, "Page {$i}");
        }
        $path = $this->tempDir.'/sample_'.uniqid().'.pdf';
        $pdf->Output('F', $path);

        return $path;
    }

    private function createSampleImage(string $format = 'jpg'): string
    {
        $image = imagecreatetruecolor(100, 100);
        $bg = imagecolorallocate($image, 255, 0, 0);
        imagefill($image, 0, 0, $bg);

        $path = $this->tempDir.'/image_'.uniqid().'.'.$format;
        if ($format === 'png') {
            imagepng($image, $path);
        } else {
            imagejpeg($image, $path);
        }
        imagedestroy($image);

        return $path;
    }

    public function test_parse_ranges_with_valid_input(): void
    {
        $ranges = $this->processor->parseRanges('1-3,5,8-10', 10);

        $this->assertSame([
            [1, 3],
            [5, 5],
            [8, 10],
        ], $ranges);
    }

    public function test_parse_ranges_clamps_to_max_page(): void
    {
        $ranges = $this->processor->parseRanges('1-5', 3);

        $this->assertSame([[1, 3]], $ranges);
    }

    public function test_parse_ranges_throws_on_invalid_string(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid range "abc"');

        $this->processor->parseRanges('abc', 10);
    }

    public function test_parse_ranges_throws_when_start_exceeds_max_page(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside the document');

        $this->processor->parseRanges('15-20', 10);
    }

    public function test_parse_ranges_throws_when_start_is_less_than_one(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside the document');

        $this->processor->parseRanges('0-5', 10);
    }

    public function test_parse_ranges_throws_when_end_is_less_than_start(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside the document');

        $this->processor->parseRanges('5-2', 10);
    }

    public function test_page_count_returns_correct_number(): void
    {
        $pdfPath = $this->createSamplePdf(3);

        $this->assertSame(3, $this->processor->pageCount($pdfPath));
    }

    public function test_merge_combines_multiple_pdfs(): void
    {
        $pdf1 = $this->createSamplePdf(2);
        $pdf2 = $this->createSamplePdf(1);
        $out = $this->tempDir.'/merged.pdf';

        $this->processor->merge([$pdf1, $pdf2], $out);

        $this->assertFileExists($out);
        $this->assertSame(3, $this->processor->pageCount($out));
    }

    public function test_split_merge_mode(): void
    {
        $pdf = $this->createSamplePdf(4);
        $ranges = [[1, 2], [4, 4]];

        $outputs = $this->processor->split($pdf, $ranges, 'merge', $this->tempDir);

        $this->assertCount(1, $outputs);
        $this->assertFileExists($outputs[0]);
        $this->assertSame(3, $this->processor->pageCount($outputs[0]));
    }

    public function test_split_separate_mode(): void
    {
        $pdf = $this->createSamplePdf(4);
        $ranges = [[1, 2], [3, 4]];

        $outputs = $this->processor->split($pdf, $ranges, 'separate', $this->tempDir);

        $this->assertCount(2, $outputs);
        $this->assertFileExists($outputs[0]);
        $this->assertFileExists($outputs[1]);
        $this->assertSame(2, $this->processor->pageCount($outputs[0]));
        $this->assertSame(2, $this->processor->pageCount($outputs[1]));
    }

    public function test_split_all_mode(): void
    {
        $pdf = $this->createSamplePdf(3);

        $outputs = $this->processor->split($pdf, [], 'all', $this->tempDir);

        $this->assertCount(3, $outputs);
        foreach ($outputs as $out) {
            $this->assertFileExists($out);
            $this->assertSame(1, $this->processor->pageCount($out));
        }
    }

    public function test_watermark_applies_text_to_pdf(): void
    {
        $pdf = $this->createSamplePdf(2);
        $out = $this->tempDir.'/watermarked.pdf';

        $this->processor->watermark($pdf, 'CONFIDENTIAL', 'diagonal', 48, 'light', $out);

        $this->assertFileExists($out);
        $this->assertGreaterThan(0, filesize($out));
        $this->assertSame(2, $this->processor->pageCount($out));
    }

    public function test_watermark_supports_top_and_bottom_positions(): void
    {
        $pdf = $this->createSamplePdf(1);
        $outTop = $this->tempDir.'/top.pdf';
        $outBottom = $this->tempDir.'/bottom.pdf';

        $this->processor->watermark($pdf, 'HEADER', 'top', 24, 'dark', $outTop);
        $this->processor->watermark($pdf, 'FOOTER', 'bottom', 24, 'medium', $outBottom);

        $this->assertFileExists($outTop);
        $this->assertFileExists($outBottom);
    }

    public function test_page_numbers_adds_numbers_to_pdf(): void
    {
        $pdf = $this->createSamplePdf(3);
        $out = $this->tempDir.'/numbered.pdf';

        $this->processor->pageNumbers($pdf, 'bottom-center', 'n_of_total', 1, $out);

        $this->assertFileExists($out);
        $this->assertGreaterThan(0, filesize($out));
        $this->assertSame(3, $this->processor->pageCount($out));
    }

    public function test_images_to_pdf_creates_pdf_from_images(): void
    {
        $img1 = $this->createSampleImage('jpg');
        $img2 = $this->createSampleImage('png');
        $out = $this->tempDir.'/images.pdf';

        $this->processor->imagesToPdf([$img1, $img2], $out);

        $this->assertFileExists($out);
        $this->assertSame(2, $this->processor->pageCount($out));
    }

    public function test_zip_compresses_files(): void
    {
        $file1 = $this->tempDir.'/f1.txt';
        $file2 = $this->tempDir.'/f2.txt';
        file_put_contents($file1, 'hello');
        file_put_contents($file2, 'world');

        $zipPath = $this->tempDir.'/archive.zip';
        $this->processor->zip([$file1, $file2], $zipPath);

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $this->assertSame(2, $zip->numFiles);
        $this->assertSame('hello', $zip->getFromName('f1.txt'));
        $this->assertSame('world', $zip->getFromName('f2.txt'));
        $zip->close();
    }

    public function test_check_environment_returns_engine_metadata(): void
    {
        $env = $this->processor->checkEnvironment();

        $this->assertIsArray($env);
        $this->assertArrayHasKey('ghostscript', $env);
        $this->assertArrayHasKey('qpdf', $env);
        $this->assertArrayHasKey('poppler', $env);
        $this->assertArrayHasKey('libreoffice', $env);

        foreach ($env as $engine) {
            $this->assertArrayHasKey('name', $engine);
            $this->assertArrayHasKey('available', $engine);
            $this->assertArrayHasKey('binary', $engine);
            $this->assertArrayHasKey('package', $engine);
            $this->assertIsBool($engine['available']);
        }
    }

    public function test_office_to_pdf_throws_descriptive_exception_when_libreoffice_unavailable(): void
    {
        $mock = $this->getMockBuilder(PdfProcessor::class)
            ->onlyMethods(['isLibreOfficeAvailable'])
            ->getMock();

        $mock->method('isLibreOfficeAvailable')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LibreOffice is not installed or not found on the server');

        $mock->officeToPdf($this->tempDir.'/fake.docx', $this->tempDir);
    }

    public function test_pdf_to_word_throws_descriptive_exception_when_libreoffice_unavailable(): void
    {
        $mock = $this->getMockBuilder(PdfProcessor::class)
            ->onlyMethods(['isLibreOfficeAvailable'])
            ->getMock();

        $mock->method('isLibreOfficeAvailable')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LibreOffice is not installed or not found on the server');

        $mock->pdfToWord($this->tempDir.'/fake.pdf', $this->tempDir);
    }
}
