<?php

if (!function_exists('loadPdfDependencies')) {
    function loadPdfDependencies(): void {
        if (class_exists('Dompdf\\Dompdf')) {
            return;
        }

        $autoload_path = dirname(__DIR__) . '/vendor/autoload.php';
        if (!file_exists($autoload_path)) {
            throw new RuntimeException('PDF library dependencies are missing. Please run composer install.');
        }

        require_once $autoload_path;

        if (!class_exists('Dompdf\\Dompdf')) {
            throw new RuntimeException('PDF library could not be loaded.');
        }
    }
}

if (!function_exists('exportHtmlAsPdf')) {
    function exportHtmlAsPdf(string $html, string $filename, array $options = []): void {
        loadPdfDependencies();

        $pdf_options = new Dompdf\Options();
        $pdf_options->set('isHtml5ParserEnabled', true);
        $pdf_options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf\Dompdf($pdf_options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($options['paper'] ?? 'A4', $options['orientation'] ?? 'portrait');
        $dompdf->render();

        $safe_filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        if ($safe_filename === null || $safe_filename === '') {
            $safe_filename = 'report';
        }

        if (strtolower(substr($safe_filename, -4)) !== '.pdf') {
            $safe_filename .= '.pdf';
        }

        $dompdf->stream($safe_filename, ['Attachment' => true]);
        exit;
    }
}
