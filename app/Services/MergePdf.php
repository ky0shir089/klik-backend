<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class MergePdf
{
    /**
     * Create a new class instance.
     */
    private function normalizePdf(string $input): string
    {
        $output = storage_path('app/temp/' . uniqid() . '.pdf');

        if (!file_exists(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }

        exec(sprintf(
            'gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=%s %s',
            escapeshellarg($output),
            escapeshellarg($input)
        ));

        return file_exists($output)
            ? $output
            : $input;
    }

    public function mergePdf(array $files, string $outputPath)
    {
        $pdf = new Fpdi();

        foreach ($files as $file) {
            $file = $this->normalizePdf($file);
            
            $pageCount = $pdf->setSourceFile($file);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $template = $pdf->importPage($pageNo);

                $size = $pdf->getTemplateSize($template);

                $orientation = $size['width'] > $size['height']
                    ? 'L'
                    : 'P';

                $pdf->AddPage(
                    $orientation,
                    [$size['width'], $size['height']]
                );

                $pdf->useTemplate($template);
            }
        }

        $pdf->Output('F', $outputPath);

        return $outputPath;
    }
}
