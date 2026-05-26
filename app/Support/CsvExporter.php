<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    /**
     * @param  list<string>  $headers
     * @param  list<array<int|string,mixed>>  $rows
     */
    public static function download(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
