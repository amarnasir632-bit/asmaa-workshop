<?php

namespace App\Services;

use Com\Tecnick\Pdf\Page\Unit;
use Com\Tecnick\Pdf\Tcpdf;

final class ReportPdfService
{
    public function render(string $html, string $orientation = 'P'): string
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
            fileOptions: ['allowedPaths' => [$fontDirectory, public_path()]],
        );
        $pdf->enableDefaultPageContent(false);
        $font = $pdf->font->insert($pdf->pon, 'dejavusans', '', 10);
        $pdf->addPage(['orientation' => $orientation]);
        $pdf->page->addContent($font['out']);
        $pdf->setRTL(false);

        $html = $this->reverseTableColumnsForRtl($html);

        $pdf->addHTMLCell($html, 12, 12, $orientation === 'L' ? 273 : 186);

        return $pdf->getOutPDFString();
    }

    /**
     * tc-lib-pdf iterates HTML table cells strictly left-to-right regardless of dir="rtl".
     * Inverting cell order per row ensures Arabic reading flow (right-to-left) matches column headers.
     */
    private function reverseTableColumnsForRtl(string $html): string
    {
        if (! str_contains($html, '<table') || ! str_contains($html, '<tr')) {
            return $html;
        }

        try {
            $dom = new \DOMDocument;
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            libxml_clear_errors();

            foreach ($dom->getElementsByTagName('tr') as $tr) {
                $cells = [];
                foreach ($tr->childNodes as $node) {
                    if ($node->nodeType === XML_ELEMENT_NODE && in_array(strtolower($node->nodeName), ['th', 'td'], true)) {
                        $cells[] = $node;
                    }
                }
                if (count($cells) > 1) {
                    for ($i = count($cells) - 1; $i >= 0; $i--) {
                        $tr->appendChild($cells[$i]);
                    }
                }
            }

            $output = $dom->saveHTML();
            $output = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $output);

            return html_entity_decode($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } catch (\Throwable) {
            return $html;
        }
    }
}
