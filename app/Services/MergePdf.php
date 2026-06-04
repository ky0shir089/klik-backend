<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class MergePdf
{
    /**
     * Create a new class instance.
     */
    public function mergePdf(array $files, string $outputPath)
    {
        $pdf = new Fpdi();

        foreach ($files as $file) {
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
