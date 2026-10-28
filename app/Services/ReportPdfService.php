<?php

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class ReportPdfService
{
    public function render(string $html, string $orientation = 'P'): string
    {
        $tempDir = storage_path('framework/cache/mpdf');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $orientation === 'L' ? 'A4-L' : 'A4',
            'orientation' => $orientation,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => 'dejavusans',
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_left' => 10,
            'margin_right' => 10,
            'tempDir' => $tempDir,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
