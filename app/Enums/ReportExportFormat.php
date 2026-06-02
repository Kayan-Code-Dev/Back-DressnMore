<?php

namespace App\Enums;

enum ReportExportFormat: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';
    case PDF = 'pdf';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
