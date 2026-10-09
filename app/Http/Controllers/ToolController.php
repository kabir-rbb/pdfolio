<?php

namespace App\Http\Controllers;

use App\Services\Pdf\PdfProcessor;
use App\Support\ToolRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ToolController extends Controller
{
    private const MAX_FILE_KB = 102400; // 100 MB per file

    public function handle(Request $request, string $tool, PdfProcessor $pdf)
    {
        $config = ToolRegistry::get($tool);
        abort_if($config === null, 404);

        $workDir = storage_path('app/pdfolio/'.Str::uuid());
        if (! mkdir($workDir, 0775, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Could not create a working directory.');
        }

        try {
            return $this->dispatch($tool, $request, $pdf, $config, $workDir);
        } catch (ValidationException $e) {
            $this->cleanup($workDir);

            throw $e;
        } catch (RuntimeException $e) {
            $this->cleanup($workDir);

            Log::warning("Tool [{$tool}] error: ".$e->getMessage());

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->cleanup($workDir);

            report($e);

            return response()->json([
                'message' => 'An error occurred while processing the document: '.$e->getMessage(),
            ], 500);
        }
    }

    private function dispatch(string $tool, Request $request, PdfProcessor $pdf, array $config, string $workDir)
    {
        switch ($tool) {
            case 'merge':
                $files = $this->storeFiles($request, $config, $workDir, 'files', ['mimes:pdf']);
                $base = $this->baseFileName($request, 'files');
                $out = $workDir.'/'.$base.'_merged.pdf';
                $pdf->merge($files, $out);

                return $this->download($out, $base.'_merged.pdf', $workDir);

            case 'split':
                [$file] = $this->storeFiles($request, $config, $workDir, 'file', ['mimes:pdf']);
                $base = $this->baseFileName($request, 'file');
                $data = $request->validate([
                    'mode' => ['required', 'in:merge,separate,all'],
                    'ranges' => ['nullable', 'string', 'max:200'],
                ]);
                $ranges = $data['mode'] === 'all'
                    ? []
                    : $pdf->parseRanges($data['ranges'] ?? '', $pdf->pageCount($file));
                $outputs = $pdf->split($file, $ranges, $data['mode'], $workDir);

                if ($data['mode'] === 'merge') {
                    return $this->download($outputs[0], $base.'_split.pdf', $workDir);
                }
                $zip = $pdf->zip($outputs, $workDir.'/'.$base.'_split.zip');

                return $this->download($zip, $base.'_split.zip', $workDir);

            case 'compress':
                [$file] = $this->storeFiles($request, $config, $workDir, 'file', ['mimes:pdf']);
                $base = $this->baseFileName($request, 'file');
                $data = $request->validate(['level' => ['required', 'in:screen,ebook,printer']]);
                $out = $workDir.'/'.$base.'_compressed.pdf';
                $pdf->compress($file, $data['level'], $out);

                return $this->download($out, $base.'_compressed.pdf', $workDir);

            case 'rotate':
                [$file] = $this->storeFiles($request, $config, $workDir, 'file', ['mimes:pdf']);
                $base = $this->baseFileName($request, 'file');
                $data = $request->validate(['degrees' => ['required', 'in:90,180,270']]);
                $out = $workDir.'/'.$base.'_rotated.pdf';
                $pdf->rotate($file, (int) $data['degrees'], $out);

                return $this->download($out, $base.'_rotated.pdf', $workDir);

            case 'watermark':
                [$file] = $this->storeFiles($request, $config, $workDir, 'file', ['mimes:pdf']);
                $base = $this->baseFileName($request, 'file');
                $data = $request->validate([
                    'text' => ['required', 'string', 'max:100'],
                    'position' => ['required', 'in:diagonal,top,bottom'],
                    'size' => ['required', 'integer', 'min:8', 'max:144'],
                    'shade' => ['required', 'in:light,medium,dark'],
                ]);
                $out = $workDir.'/'.$base.'_watermarked.pdf';
                $pdf->watermark($file, $data['text'], $data['position'], (int) $data['size'], $data['shade'], $out);

                return $this->download($out, $base.'_watermarked.pdf', $workDir);

            case 'page-numbers':
                [$file] = $this->storeFiles($request, $config, $workDir, 'file', ['mimes:pdf']);
                $base = $this->baseFileName($request, 'file');
                $data = $request->validate([
                    'position' => ['required', 'in:top-left,top-center,top-right,bottom-left,bottom-center,bottom-right'],
                    'format' => ['required', 'in:n,n_of_total'],
                    'start' => ['required', 'integer', 'min:0', 'max:100000'],
                ]);
                $out = $workDir.'/'.$base.'_numbered.pdf';
                $pdf->pageNumbers($file, $data['position'], $data['format'], (int) $data['start'], $out);

                return $this->download($out, $base.'_numbered.pdf', $workDir);

            case 'pdf-to-images':
                [$file] = $this->storeFiles($request, $config, $workDir, 'file', ['mimes:pdf']);
                $base = $this->baseFileName($request, 'file');
                $data = $request->validate([
                    'format' => ['required', 'in:jpg,png'],
                    'dpi' => ['required', 'in:72,150,300'],
                ]);
                $images = $pdf->pdfToImages($file, $data['format'], (int) $data['dpi'], $workDir);
                if (count($images) === 1) {
                    return $this->download($images[0], $base.'_page.'.$data['format'], $workDir);
                }
                $zip = $pdf->zip($images, $workDir.'/'.$base.'_images.zip');

                return $this->download($zip, $base.'_images.zip', $workDir);

            case 'images-to-pdf':
                $files = $this->storeFiles($request, $config, $workDir, 'files', ['mimes:jpg,jpeg,png,gif']);
                $base = $this->baseFileName($request, 'files');
                $out = $workDir.'/'.$base.'_converted.pdf';
                $pdf->imagesToPdf($files, $out);

                return $this->download($out, $base.'_converted.pdf', $workDir);

            case 'extract-text':
                [$file] = $this->storeFiles($request, $config, $workDir, 'file', ['mimes:pdf']);
                $base = $this->baseFileName($request, 'file');
                $data = $request->validate(['layout' => ['required', 'in:yes,no']]);
                $out = $workDir.'/'.$base.'_text.txt';
                $pdf->extractText($file, $data['layout'] === 'yes', $out);

                return $this->download($out, $base.'_text.txt', $workDir);

            case 'pdf-to-word':
                [$file] = $this->storeFiles($request, $config, $workDir, 'file', ['mimes:pdf']);
                $base = $this->baseFileName($request, 'file');
                $produced = $pdf->pdfToWord($file, $workDir);

                return $this->download($produced, $base.'.docx', $workDir);

            case 'office-to-pdf':
                [$file] = $this->storeFiles($request, $config, $workDir, 'file', ['mimes:doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,rtf,csv,txt']);
                $base = $this->baseFileName($request, 'file');
                $produced = $pdf->officeToPdf($file, $workDir);

                return $this->download($produced, $base.'.pdf', $workDir);

            default:
                abort(404);
        }
    }

    /**
     * Get a sanitized base name from the first uploaded file in the request.
     */
    private function baseFileName(Request $request, string $field = 'file'): string
    {
        $file = $request->file($field);
        if (is_array($file)) {
            $file = $file[0] ?? null;
        }

        if ($file instanceof UploadedFile) {
            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $clean = trim(preg_replace('/[^\w\-]+/u', '_', $name) ?: '', '_');

            return $clean !== '' ? $clean : 'document';
        }

        return 'document';
    }

    /**
     * Validate and move the uploaded file(s) into the work directory.
     *
     * @return array<int, string> stored paths, in upload order
     */
    private function storeFiles(Request $request, array $config, string $workDir, string $field, array $extraRules): array
    {
        $multiple = $config['multiple'];
        $min = $config['minFiles'];

        $rules = $multiple
            ? [
                $field => ['required', 'array', 'min:'.$min, 'max:50'],
                $field.'.*' => array_merge(['file', 'max:'.self::MAX_FILE_KB], $extraRules),
            ]
            : [$field => array_merge(['required', 'file', 'max:'.self::MAX_FILE_KB], $extraRules)];

        $request->validate($rules);

        $uploads = $multiple ? $request->file($field) : [$request->file($field)];
        $paths = [];
        foreach ($uploads as $i => $upload) {
            $path = $workDir.'/upload-'.$i.'.'.strtolower($upload->getClientOriginalExtension() ?: 'bin');
            $upload->move(dirname($path), basename($path));
            $paths[] = $path;
        }

        return $paths;
    }

    private function download(string $path, string $name, string $workDir): BinaryFileResponse
    {
        // Move the deliverable out of the work dir, then delete the work dir
        $final = storage_path('app/pdfolio/out-'.Str::uuid().'-'.$name);
        if (! @rename($path, $final)) {
            copy($path, $final);
            @unlink($path);
        }
        $this->cleanup($workDir);

        // Guarantee deletion as soon as the response finishes or if script terminates/aborts
        register_shutdown_function(function () use ($final): void {
            if (file_exists($final)) {
                @unlink($final);
            }
        });

        // Opportunistically prune any stale abandoned files older than 10 minutes
        $this->pruneStaleFiles();

        return response()->download($final, $name, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Purge abandoned output or temporary directories older than 10 minutes.
     */
    private function pruneStaleFiles(): void
    {
        $base = storage_path('app/pdfolio');
        if (! is_dir($base)) {
            return;
        }

        $threshold = time() - 600; // 10 minutes
        $items = glob($base.'/*') ?: [];
        foreach ($items as $item) {
            if (basename($item) === '.gitignore') {
                continue;
            }
            if (filemtime($item) < $threshold) {
                if (is_dir($item)) {
                    $this->cleanup($item);
                } else {
                    @unlink($item);
                }
            }
        }
    }

    public function cleanup(string $workDir): void
    {
        if (! is_dir($workDir)) {
            return;
        }

        $items = scandir($workDir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $workDir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->cleanup($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($workDir);
    }
}
