<?php

namespace App\Support\Reports;

use App\Enums\ReportExportFormat;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabularExport
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<int|string|float|null>>  $rows
     */
    public static function download(
        ?string $format,
        string $basename,
        string $title,
        array $headers,
        array $rows,
        array $meta = [],
    ): StreamedResponse|Response {
        $resolved = ReportExportFormat::tryFrom(strtolower(trim((string) ($format ?: 'csv'))))
            ?? ReportExportFormat::CSV;

        return ReportExporter::download($resolved, $basename, $title, $headers, $rows, $meta);
    }
}
