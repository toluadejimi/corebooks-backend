<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfRenderer
{
    public function renderBinary(string $html, string $paper = 'A4', string $orientation = 'portrait'): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return $dompdf->output();
    }

    public function downloadResponse(string $html, string $filename, string $paper = 'A4', string $orientation = 'portrait'): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create temp file for PDF.');
        }

        file_put_contents($tmp, $this->renderBinary($html, $paper, $orientation));

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
}
