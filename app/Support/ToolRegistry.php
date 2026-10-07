<?php

namespace App\Support;

/**
 * Central registry describing every PDF tool: metadata used to render the
 * UI and the option schema consumed by both the backend and the frontend.
 */
class ToolRegistry
{
    public static function all(): array
    {
        return array_values(self::map());
    }

    public static function get(string $slug): ?array
    {
        return self::map()[$slug] ?? null;
    }

    private static function map(): array
    {
        return [
            'merge' => [
                'slug' => 'merge',
                'title' => 'Merge PDF',
                'description' => 'Combine multiple PDF files into a single document, in the order you choose.',
                'icon' => 'merge',
                'color' => 'brand',
                'multiple' => true,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'PDF files',
                'minFiles' => 2,
                'action' => 'Merge PDFs',
                'options' => [],
            ],
            'split' => [
                'slug' => 'split',
                'title' => 'Split PDF',
                'description' => 'Extract page ranges from a PDF, or break every page into its own file.',
                'icon' => 'split',
                'color' => 'tangerine',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Split PDF',
                'options' => [
                    ['name' => 'mode', 'label' => 'Split mode', 'type' => 'select', 'default' => 'merge', 'choices' => [
                        ['value' => 'merge', 'label' => 'Extract ranges into one PDF'],
                        ['value' => 'separate', 'label' => 'Each range as a separate PDF (ZIP)'],
                        ['value' => 'all', 'label' => 'Every page as a separate PDF (ZIP)'],
                    ]],
                    ['name' => 'ranges', 'label' => 'Page ranges (e.g. 1-3,5,8-10)', 'type' => 'text', 'default' => '1-3', 'placeholder' => '1-3,5,8-10', 'showIf' => ['field' => 'mode', 'values' => ['merge', 'separate']]],
                ],
            ],
            'compress' => [
                'slug' => 'compress',
                'title' => 'Compress PDF',
                'description' => 'Shrink file size while keeping the best possible quality.',
                'icon' => 'compress',
                'color' => 'mint',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Compress PDF',
                'options' => [
                    ['name' => 'level', 'label' => 'Compression level', 'type' => 'select', 'default' => 'ebook', 'choices' => [
                        ['value' => 'screen', 'label' => 'Extreme — smallest size (72 dpi)'],
                        ['value' => 'ebook', 'label' => 'Recommended — good quality (150 dpi)'],
                        ['value' => 'printer', 'label' => 'Light — high quality (300 dpi)'],
                    ]],
                ],
            ],
            'rotate' => [
                'slug' => 'rotate',
                'title' => 'Rotate PDF',
                'description' => 'Rotate every page of your PDF by 90, 180 or 270 degrees.',
                'icon' => 'rotate',
                'color' => 'brand',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Rotate PDF',
                'options' => [
                    ['name' => 'degrees', 'label' => 'Rotation', 'type' => 'select', 'default' => '90', 'choices' => [
                        ['value' => '90', 'label' => '90° clockwise'],
                        ['value' => '180', 'label' => '180° upside down'],
                        ['value' => '270', 'label' => '270° counter-clockwise'],
                    ]],
                ],
            ],
            'watermark' => [
                'slug' => 'watermark',
                'title' => 'Watermark PDF',
                'description' => 'Stamp a text watermark over every page of your document.',
                'icon' => 'watermark',
                'color' => 'tangerine',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Add watermark',
                'options' => [
                    ['name' => 'text', 'label' => 'Watermark text', 'type' => 'text', 'default' => 'CONFIDENTIAL', 'placeholder' => 'e.g. CONFIDENTIAL'],
                    ['name' => 'position', 'label' => 'Position', 'type' => 'select', 'default' => 'diagonal', 'choices' => [
                        ['value' => 'diagonal', 'label' => 'Diagonal across the page'],
                        ['value' => 'top', 'label' => 'Top center'],
                        ['value' => 'bottom', 'label' => 'Bottom center'],
                    ]],
                    ['name' => 'size', 'label' => 'Font size', 'type' => 'number', 'default' => '48'],
                    ['name' => 'shade', 'label' => 'Intensity', 'type' => 'select', 'default' => 'light', 'choices' => [
                        ['value' => 'light', 'label' => 'Light'],
                        ['value' => 'medium', 'label' => 'Medium'],
                        ['value' => 'dark', 'label' => 'Dark'],
                    ]],
                ],
            ],
            'page-numbers' => [
                'slug' => 'page-numbers',
                'title' => 'Page Numbers',
                'description' => 'Add page numbers to your PDF, exactly where you want them.',
                'icon' => 'numbers',
                'color' => 'mint',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Add page numbers',
                'options' => [
                    ['name' => 'position', 'label' => 'Position', 'type' => 'select', 'default' => 'bottom-center', 'choices' => [
                        ['value' => 'top-left', 'label' => 'Top left'],
                        ['value' => 'top-center', 'label' => 'Top center'],
                        ['value' => 'top-right', 'label' => 'Top right'],
                        ['value' => 'bottom-left', 'label' => 'Bottom left'],
                        ['value' => 'bottom-center', 'label' => 'Bottom center'],
                        ['value' => 'bottom-right', 'label' => 'Bottom right'],
                    ]],
                    ['name' => 'format', 'label' => 'Format', 'type' => 'select', 'default' => 'n', 'choices' => [
                        ['value' => 'n', 'label' => '1, 2, 3 …'],
                        ['value' => 'n_of_total', 'label' => '1 / 12, 2 / 12 …'],
                    ]],
                    ['name' => 'start', 'label' => 'Start numbering at', 'type' => 'number', 'default' => '1'],
                ],
            ],
            'protect' => [
                'slug' => 'protect',
                'title' => 'Protect PDF',
                'description' => 'Encrypt your PDF with AES-256 and a password of your choice.',
                'icon' => 'lock',
                'color' => 'brand',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Protect PDF',
                'options' => [
                    ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'default' => '', 'placeholder' => 'Choose a strong password'],
                ],
            ],
            'unlock' => [
                'slug' => 'unlock',
                'title' => 'Unlock PDF',
                'description' => 'Remove password protection from a PDF you own.',
                'icon' => 'unlock',
                'color' => 'tangerine',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Unlock PDF',
                'options' => [
                    ['name' => 'password', 'label' => 'Current password (if required)', 'type' => 'password', 'default' => '', 'placeholder' => 'Leave empty to try without one'],
                ],
            ],
            'pdf-to-images' => [
                'slug' => 'pdf-to-images',
                'title' => 'PDF to Images',
                'description' => 'Turn each page of your PDF into a JPG or PNG image.',
                'icon' => 'image',
                'color' => 'mint',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Convert to images',
                'options' => [
                    ['name' => 'format', 'label' => 'Image format', 'type' => 'select', 'default' => 'jpg', 'choices' => [
                        ['value' => 'jpg', 'label' => 'JPG — smaller files'],
                        ['value' => 'png', 'label' => 'PNG — lossless quality'],
                    ]],
                    ['name' => 'dpi', 'label' => 'Resolution', 'type' => 'select', 'default' => '150', 'choices' => [
                        ['value' => '72', 'label' => '72 dpi — screen'],
                        ['value' => '150', 'label' => '150 dpi — standard'],
                        ['value' => '300', 'label' => '300 dpi — print'],
                    ]],
                ],
            ],
            'images-to-pdf' => [
                'slug' => 'images-to-pdf',
                'title' => 'Images to PDF',
                'description' => 'Convert JPG, PNG or GIF images into a single PDF document.',
                'icon' => 'images',
                'color' => 'brand',
                'multiple' => true,
                'accept' => '.jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif',
                'acceptLabel' => 'image files (JPG, PNG, GIF)',
                'minFiles' => 1,
                'action' => 'Convert to PDF',
                'options' => [],
            ],
            'extract-text' => [
                'slug' => 'extract-text',
                'title' => 'Extract Text',
                'description' => 'Pull all selectable text out of a PDF into a plain text file.',
                'icon' => 'text',
                'color' => 'tangerine',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Extract text',
                'options' => [
                    ['name' => 'layout', 'label' => 'Preserve layout', 'type' => 'select', 'default' => 'yes', 'choices' => [
                        ['value' => 'yes', 'label' => 'Yes — keep columns and spacing'],
                        ['value' => 'no', 'label' => 'No — raw reading order'],
                    ]],
                ],
            ],
            'pdf-to-word' => [
                'slug' => 'pdf-to-word',
                'title' => 'PDF to Word',
                'description' => 'Turn a PDF into an editable Word document (.docx).',
                'icon' => 'word',
                'color' => 'tangerine',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Convert to Word',
                'options' => [],
            ],
            'ocr' => [
                'slug' => 'ocr',
                'title' => 'OCR PDF',
                'description' => 'Make scanned PDFs searchable and selectable with a text layer.',
                'icon' => 'scan',
                'color' => 'brand',
                'multiple' => false,
                'accept' => '.pdf,application/pdf',
                'acceptLabel' => 'a PDF file',
                'minFiles' => 1,
                'action' => 'Run OCR',
                'options' => [
                    ['name' => 'language', 'label' => 'Document language', 'type' => 'select', 'default' => 'eng', 'choices' => [
                        ['value' => 'eng', 'label' => 'English'],
                        ['value' => 'nep', 'label' => 'Nepali'],
                        ['value' => 'hin', 'label' => 'Hindi'],
                        ['value' => 'spa', 'label' => 'Spanish'],
                        ['value' => 'fra', 'label' => 'French'],
                        ['value' => 'deu', 'label' => 'German'],
                    ]],
                    ['name' => 'skip_text', 'label' => 'Pages that already have text', 'type' => 'select', 'default' => 'yes', 'choices' => [
                        ['value' => 'yes', 'label' => 'Leave them untouched'],
                        ['value' => 'no', 'label' => 'Fail if the PDF already has text'],
                    ]],
                    ['name' => 'rotate_pages', 'label' => 'Auto-rotate sideways pages', 'type' => 'select', 'default' => 'yes', 'choices' => [
                        ['value' => 'yes', 'label' => 'Yes — fix page orientation'],
                        ['value' => 'no', 'label' => 'No — keep as scanned'],
                    ]],
                ],
            ],
            'office-to-pdf' => [
                'slug' => 'office-to-pdf',
                'title' => 'Office to PDF',
                'description' => 'Convert Word, Excel and PowerPoint documents to PDF.',
                'icon' => 'office',
                'color' => 'mint',
                'multiple' => false,
                'accept' => '.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.rtf,.csv,.txt',
                'acceptLabel' => 'an Office document',
                'minFiles' => 1,
                'action' => 'Convert to PDF',
                'options' => [],
            ],
        ];
    }
}
