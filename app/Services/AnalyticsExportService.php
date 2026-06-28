<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

            fputcsv($handle, [$report['title'] ?? 'Analytics Report']);
            fputcsv($handle, [$report['subtitle'] ?? '']);
            fputcsv($handle, ['Period', $report['period_label'] ?? '']);
            fputcsv($handle, ['Generated At', $report['generated_at'] ?? '']);
            fputcsv($handle, []);

            foreach ($report['sections'] ?? [] as $section) {
                fputcsv($handle, [$section['title'] ?? 'Section']);
                fputcsv($handle, $section['headers'] ?? []);

                foreach ($section['rows'] ?? [] as $row) {
                    fputcsv($handle, $row);
                }

                fputcsv($handle, []);
            }

            fclose($handle);
        }, "{$filenameBase}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadXlsx(array $report, string $filenameBase): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Analytics');

            $rowIndex = 1;
            $sheet->setCellValue("A{$rowIndex}", $report['title'] ?? 'Analytics Report');
            $rowIndex++;
            $sheet->setCellValue("A{$rowIndex}", $report['subtitle'] ?? '');
            $rowIndex++;
            $sheet->setCellValue("A{$rowIndex}", 'Period');
            $sheet->setCellValue("B{$rowIndex}", $report['period_label'] ?? '');
            $rowIndex++;
            $sheet->setCellValue("A{$rowIndex}", 'Generated At');
            $sheet->setCellValue("B{$rowIndex}", $report['generated_at'] ?? '');
            $rowIndex += 2;

            foreach ($report['sections'] ?? [] as $section) {
                $sheet->setCellValue("A{$rowIndex}", $section['title'] ?? 'Section');
                $rowIndex++;

                $headers = $section['headers'] ?? [];
                foreach ($headers as $columnIndex => $header) {
                    $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex, $header);
                }
                $rowIndex++;

                foreach ($section['rows'] ?? [] as $row) {
                    foreach ($row as $columnIndex => $value) {
                        $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex, $value);
                    }
                    $rowIndex++;
                }

                $rowIndex++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, "{$filenameBase}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
