<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ReportDateRange
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{from: string, to: string}
     */
    public static function resolve(array $filters): array
    {
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        if ($dateFrom !== '' && $dateTo !== '') {
            return ['from' => $dateFrom, 'to' => $dateTo];
        }

        $now = CarbonImmutable::now();
        $period = (string) ($filters['period'] ?? 'month');

        return match ($period) {
            'today' => [
                'from' => $now->toDateString(),
                'to' => $now->toDateString(),
            ],
            'week' => [
                'from' => $now->startOfWeek()->toDateString(),
                'to' => $now->endOfWeek()->toDateString(),
            ],
            'last_week' => [
                'from' => $now->subWeek()->startOfWeek()->toDateString(),
                'to' => $now->subWeek()->endOfWeek()->toDateString(),
            ],
            'year' => [
                'from' => $now->startOfYear()->toDateString(),
                'to' => $now->endOfYear()->toDateString(),
            ],
            'last_month' => [
                'from' => $now->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'to' => $now->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            default => [
                'from' => $now->startOfMonth()->toDateString(),
                'to' => $now->endOfMonth()->toDateString(),
            ],
        };
    }

    /**
     * @param  array{from: string, to: string}  $range
     * @return array{from: string, to: string}
     */
    public static function previous(array $range): array
    {
        $from = CarbonImmutable::parse($range['from']);
        $to = CarbonImmutable::parse($range['to']);
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($days - 1);

        return [
            'from' => $previousFrom->toDateString(),
            'to' => $previousTo->toDateString(),
        ];
    }
}
