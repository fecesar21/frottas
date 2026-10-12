<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;

/**
 * Gera os PDFs de relatório (papel timbrado em resources/views/relatorios/pdf)
 * e numera as páginas no canto direito do rodapé. A numeração é feita no
 * canvas porque o dompdf não resolve counter(pages) via CSS.
 */
class RelatorioPdf
{
    public static function make(string $view, array $dados): DomPdfWrapper
    {
        $pdf = Pdf::loadView($view, $dados);
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fonte = $dompdf->getFontMetrics()->getFont('helvetica');

        // Coordenadas em pt (A4 = 595 x 842), alinhado à linha do rodapé.
        $canvas->page_text(510, 819, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fonte, 6, [0.61, 0.64, 0.69]);

        return $pdf;
    }
}
