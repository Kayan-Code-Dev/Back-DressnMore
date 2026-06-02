<?php

namespace App\Support\Reports;

use App\Enums\ReportExportFormat;
use App\Support\CsvExporter;
use Dompdf\Dompdf;
use Dompdf\Options;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExporter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function download(
        ReportExportFormat $format,
        string $basename,
        string $title,
        array $headers,
        array $rows,
        ?array $meta = null,
    ): StreamedResponse|Response {
        $filename = $basename.'.'.$format->value;

        return match ($format) {
            ReportExportFormat::CSV => CsvExporter::download($filename, $headers, $rows),
            ReportExportFormat::XLSX => self::xlsx($filename, $headers, $rows),
            ReportExportFormat::PDF => self::pdf($filename, $title, $headers, $rows, $meta),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    private static function xlsx(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $writer = new XlsxWriter;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($headers));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(
                    static fn ($value): string => is_scalar($value) ? (string) $value : json_encode($value),
                    $row,
                )));
            }
            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     * @param  array<string, mixed>|null  $meta
     */
    private static function pdf(
        string $filename,
        string $title,
        array $headers,
        array $rows,
        ?array $meta,
    ): Response {
        $html = view('reports.export-table', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'meta' => $meta ?? [],
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
