<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsExportService
{
    public function download(array $report, string $format): Response|StreamedResponse
    {
        $filenameBase = $report['filename_base'] ?? 'analytics_report';

        return match ($format) {
            'pdf' => $this->downloadPdf($report, $filenameBase),
            'csv' => $this->downloadCsv($report, $filenameBase),
            'xlsx' => $this->downloadXlsx($report, $filenameBase),
            default => throw new \InvalidArgumentException('Unsupported export format.'),
        };
    }

    private function downloadPdf(array $report, string $filenameBase): Response
    {
        $pdf = Pdf::loadView('exports.analytics-report', ['report' => $report])
            ->setPaper('a4', 'portrait');

        return $pdf->download("{$filenameBase}.pdf");
    }

    private function downloadCsv(array $report, string $filenameBase): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            $this->writeSpreadsheetRows($report, function (array $row) use ($handle) {
                fputcsv($handle, $row);
            });

            fclose($handle);
        }, "{$filenameBase}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadXlsx(array $report, string $filenameBase): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $this->writeSpreadsheetRows($report, function (array $row) use ($writer) {
                $writer->addRow(Row::fromValues($row));
            });

            $writer->close();
        }, "{$filenameBase}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function writeSpreadsheetRows(array $report, callable $writeRow): void
    {
        $writeRow([$report['title'] ?? 'Analytics Report']);
        $writeRow([$report['subtitle'] ?? '']);
        $writeRow(['Period', $report['period_label'] ?? '']);
        $writeRow(['Generated At', $report['generated_at'] ?? '']);
        $writeRow([]);

        foreach ($report['sections'] ?? [] as $section) {
            $writeRow([$section['title'] ?? 'Section']);
            $writeRow($section['headers'] ?? []);

            foreach ($section['rows'] ?? [] as $row) {
                $writeRow($row);
            }

            $writeRow([]);
        }
    }
}
