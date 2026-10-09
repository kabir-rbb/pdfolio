<?php

namespace Tests\Feature;

use App\Services\Pdf\PdfProcessor;
use FPDF;
use Illuminate\Http\UploadedFile;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ToolApiTest extends TestCase
{
    private function createFakePdf(string $name = 'test.pdf', int $pages = 1): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
        $pdf = new FPDF;
        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->Cell(40, 10, "Test Page {$i}");
        }
        $pdf->Output('F', $tempPath);

        return new UploadedFile($tempPath, $name, 'application/pdf', null, true);
    }

    private function createFakeImage(string $name = 'test.jpg'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'img_').'.jpg';
        $img = imagecreatetruecolor(50, 50);
        $color = imagecolorallocate($img, 0, 150, 255);
        imagefill($img, 0, 0, $color);
        imagejpeg($img, $tempPath);
        imagedestroy($img);

        return new UploadedFile($tempPath, $name, 'image/jpeg', null, true);
    }

    /* ----------------------------------------------------------------- */
    /*  Tool Registry Endpoint */
    /* ----------------------------------------------------------------- */

    public function test_it_returns_all_tools_from_registry(): void
    {
        $response = $this->getJson('/api/tools');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'slug',
                'title',
                'description',
                'icon',
                'color',
                'multiple',
                'accept',
                'acceptLabel',
                'minFiles',
                'action',
                'options',
            ],
        ]);
        $this->assertCount(11, $response->json());
    }

    public function test_removed_tools_return_404(): void
    {
        $this->postJson('/api/tools/protect')->assertStatus(404);
        $this->postJson('/api/tools/unlock')->assertStatus(404);
        $this->postJson('/api/tools/ocr')->assertStatus(404);
    }

    public function test_unknown_tool_returns_404(): void
    {
        $response = $this->postJson('/api/tools/non-existent-tool');

        $response->assertStatus(404);
    }

    /* ----------------------------------------------------------------- */
    /*  Validation Tests */
    /* ----------------------------------------------------------------- */

    public function test_merge_validation_requires_at_least_two_files(): void
    {
        $response = $this->postJson('/api/tools/merge');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['files']);

        $singlePdf = $this->createFakePdf();
        $responseSingle = $this->post('/api/tools/merge', [
            'files' => [$singlePdf],
        ], ['Accept' => 'application/json']);
        $responseSingle->assertStatus(422);
        $responseSingle->assertJsonValidationErrors(['files']);
    }

    public function test_split_validation(): void
    {
        $response = $this->postJson('/api/tools/split');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);

        $pdf = $this->createFakePdf();
        $responseNoMode = $this->post('/api/tools/split', [
            'file' => $pdf,
        ], ['Accept' => 'application/json']);
        $responseNoMode->assertStatus(422);
        $responseNoMode->assertJsonValidationErrors(['mode']);

        $pdf2 = $this->createFakePdf();
        $responseInvalidMode = $this->post('/api/tools/split', [
            'file' => $pdf2,
            'mode' => 'invalid-mode',
        ], ['Accept' => 'application/json']);
        $responseInvalidMode->assertStatus(422);
        $responseInvalidMode->assertJsonValidationErrors(['mode']);
    }

    public function test_compress_validation(): void
    {
        $response = $this->postJson('/api/tools/compress');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);

        $pdf = $this->createFakePdf();
        $responseInvalid = $this->post('/api/tools/compress', [
            'file' => $pdf,
            'level' => 'ultra-super',
        ], ['Accept' => 'application/json']);
        $responseInvalid->assertStatus(422);
        $responseInvalid->assertJsonValidationErrors(['level']);
    }

    public function test_rotate_validation(): void
    {
        $response = $this->postJson('/api/tools/rotate');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);

        $pdf = $this->createFakePdf();
        $responseInvalid = $this->post('/api/tools/rotate', [
            'file' => $pdf,
            'degrees' => '45',
        ], ['Accept' => 'application/json']);
        $responseInvalid->assertStatus(422);
        $responseInvalid->assertJsonValidationErrors(['degrees']);
    }

    public function test_watermark_validation(): void
    {
        $response = $this->postJson('/api/tools/watermark');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);

        $pdf = $this->createFakePdf();
        $responseNoOptions = $this->post('/api/tools/watermark', [
            'file' => $pdf,
        ], ['Accept' => 'application/json']);
        $responseNoOptions->assertStatus(422);
        $responseNoOptions->assertJsonValidationErrors(['text', 'position', 'size', 'shade']);
    }

    public function test_page_numbers_validation(): void
    {
        $response = $this->postJson('/api/tools/page-numbers');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);

        $pdf = $this->createFakePdf();
        $responseNoOptions = $this->post('/api/tools/page-numbers', [
            'file' => $pdf,
        ], ['Accept' => 'application/json']);
        $responseNoOptions->assertStatus(422);
        $responseNoOptions->assertJsonValidationErrors(['position', 'format', 'start']);
    }

    public function test_office_to_pdf_validation_requires_file(): void
    {
        $response = $this->postJson('/api/tools/office-to-pdf');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_office_to_pdf_validation_rejects_disallowed_file_types(): void
    {
        $fakeScript = UploadedFile::fake()->create('script.php', 10, 'text/x-php');
        $response = $this->post('/api/tools/office-to-pdf', [
            'file' => $fakeScript,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /* ----------------------------------------------------------------- */
    /*  End-to-End Processing Tests (Pure PHP Engine) */
    /* ----------------------------------------------------------------- */

    public function test_merge_endpoint_end_to_end(): void
    {
        $pdf1 = $this->createFakePdf('doc1.pdf', 1);
        $pdf2 = $this->createFakePdf('doc2.pdf', 2);

        $response = $this->post('/api/tools/merge', [
            'files' => [$pdf1, $pdf2],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('pdfolio-merged.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_split_endpoint_merge_mode_end_to_end(): void
    {
        $pdf = $this->createFakePdf('doc.pdf', 3);

        $response = $this->post('/api/tools/split', [
            'file' => $pdf,
            'mode' => 'merge',
            'ranges' => '1-2',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('pdfolio-split.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_split_endpoint_all_mode_returns_zip(): void
    {
        $pdf = $this->createFakePdf('doc.pdf', 2);

        $response = $this->post('/api/tools/split', [
            'file' => $pdf,
            'mode' => 'all',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');
        $this->assertStringContainsString('pdfolio-split.zip', (string) $response->headers->get('content-disposition'));
    }

    public function test_watermark_endpoint_end_to_end(): void
    {
        $pdf = $this->createFakePdf('doc.pdf', 1);

        $response = $this->post('/api/tools/watermark', [
            'file' => $pdf,
            'text' => 'TOP SECRET',
            'position' => 'diagonal',
            'size' => 36,
            'shade' => 'medium',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('pdfolio-watermarked.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_page_numbers_endpoint_end_to_end(): void
    {
        $pdf = $this->createFakePdf('doc.pdf', 2);

        $response = $this->post('/api/tools/page-numbers', [
            'file' => $pdf,
            'position' => 'bottom-center',
            'format' => 'n_of_total',
            'start' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('pdfolio-numbered.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_images_to_pdf_endpoint_end_to_end(): void
    {
        $img1 = $this->createFakeImage('photo1.jpg');
        $img2 = $this->createFakeImage('photo2.jpg');

        $response = $this->post('/api/tools/images-to-pdf', [
            'files' => [$img1, $img2],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('pdfolio-images.pdf', (string) $response->headers->get('content-disposition'));
    }

    /* ----------------------------------------------------------------- */
    /*  Office to PDF & External Tool Tests */
    /* ----------------------------------------------------------------- */

    public function test_office_to_pdf_returns_friendly_error_when_libreoffice_fails(): void
    {
        $mock = Mockery::mock(PdfProcessor::class);
        $mock->shouldReceive('officeToPdf')
            ->once()
            ->andThrow(new RuntimeException('LibreOffice is not installed or not found on the server. Please install it (e.g. "sudo apt install libreoffice" on Ubuntu/Debian).'));
        $this->app->instance(PdfProcessor::class, $mock);

        $fakeDoc = UploadedFile::fake()->create('report.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->post('/api/tools/office-to-pdf', [
            'file' => $fakeDoc,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'LibreOffice is not installed or not found on the server. Please install it (e.g. "sudo apt install libreoffice" on Ubuntu/Debian).',
        ]);
    }

    public function test_office_to_pdf_success_with_mocked_processor(): void
    {
        $mock = Mockery::mock(PdfProcessor::class);
        $mock->shouldReceive('officeToPdf')
            ->once()
            ->andReturnUsing(function (string $file, string $workDir) {
                // Simulate producing a PDF in the work directory
                $pdfPath = $workDir.'/output.pdf';
                $pdf = new FPDF;
                $pdf->AddPage();
                $pdf->SetFont('Helvetica', 'B', 12);
                $pdf->Cell(40, 10, 'Converted Office Doc');
                $pdf->Output('F', $pdfPath);

                return $pdfPath;
            });
        $this->app->instance(PdfProcessor::class, $mock);

        $fakeDoc = UploadedFile::fake()->create('quarterly-report.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->post('/api/tools/office-to-pdf', [
            'file' => $fakeDoc,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('quarterly-report.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_work_directory_is_cleaned_up_after_successful_response(): void
    {
        $pdf1 = $this->createFakePdf('test1.pdf', 1);
        $pdf2 = $this->createFakePdf('test2.pdf', 1);

        $existingDirs = array_filter(glob(storage_path('app/pdfolio/*')) ?: [], 'is_dir');

        $response = $this->post('/api/tools/merge', [
            'files' => [$pdf1, $pdf2],
        ]);

        $response->assertStatus(200);

        $currentDirs = array_filter(glob(storage_path('app/pdfolio/*')) ?: [], 'is_dir');
        $newDirs = array_diff($currentDirs, $existingDirs);
        $this->assertEmpty($newDirs, 'New work directories should be cleaned up after processing.');
    }
}
