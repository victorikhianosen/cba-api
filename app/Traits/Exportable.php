<?php

declare(strict_types=1);

namespace App\Traits;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\LazyCollection;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Shared CSV/PDF download helpers for any admin report/listing controller.
trait Exportable
{
    // Streams straight from the DB cursor through SimpleExcelWriter to the response —
    // never holds more than one row in memory, so this scales to millions of records
    // (unlike collecting into an array first, which a plain fputcsv loop still risks
    // if the caller passes an eager-loaded collection instead of a cursor).
    protected function exportCsv(string $filename, LazyCollection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $filename) {
            $writer = SimpleExcelWriter::streamDownload($filename);

            foreach ($rows as $row) {
                $writer->addRow($row);
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // Same streaming approach as exportCsv() — SimpleExcelWriter picks the XLSX writer
    // itself based on the .xlsx extension in $filename.
    protected function exportExcel(string $filename, LazyCollection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $filename) {
            $writer = SimpleExcelWriter::streamDownload($filename);

            foreach ($rows as $row) {
                $writer->addRow($row);
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // PDF is for a human to read/print, not a data dump — cap it hard and point large
    // exports at CSV instead. $rows must already be materialized (array/Collection).
    protected const PDF_EXPORT_ROW_LIMIT = 5000;

    /**
     * @param array<string, mixed> $data
     */
    protected function exportPdf(string $view, array $data, string $filename)
    {
        return Pdf::loadView($view, $data)->setPaper('a4', 'landscape')->download($filename);
    }
}
