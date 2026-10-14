<?php

namespace App\Services;

use Com\Tecnick\Pdf\Page\Unit;
use Com\Tecnick\Pdf\Tcpdf;

final class ReportPdfService
{
    public function render(string $html): string
    {
        $fontDirectory = resource_path('pdf-fonts');
        if (! is_dir($fontDirectory.'/dejavu')) {
            throw new \RuntimeException('ملفات خط PDF غير موجودة.');
        }

        if (! defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', $fontDirectory);
        }

        $pdf = new Tcpdf(
            unit: Unit::Millimeter,
            isunicode: true,
            subsetfont: true,
            compress: true,
            fileOptions: ['allowedPaths' => [$fontDirectory]],
        );
        $pdf->enableDefaultPageContent(false);
        $font = $pdf->font->insert($pdf->pon, 'dejavusans', '', 10);
        $pdf->addPage();
        $pdf->page->addContent($font['out']);
        $pdf->setRTL(false);
        $pdf->addHTMLCell($html, 12, 12, 186);

        return $pdf->getOutPDFString();
    }
}
