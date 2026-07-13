<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

/**
 * Builds OpenAI-compatible tool schemas for the model.
 */
final class ToolSchemaBuilder
{
    /**
     * @return array OpenAI function definitions
     */
    public static function all(): array
    {
        return [
            self::revenueSummary(),
            self::activeReservations(),
            self::lateReturns(),
            self::pendingDeliveries(),
            self::activeCustomers(),
            self::inactiveDresses(),
            self::businessSnapshot(),
            self::businessHealth(),
            self::dailyBrief(),
            self::compareBusinessPeriods(),
        ];
    }

    private static function revenueSummary(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_revenue_summary',
                'description' => 'Get revenue and collection summary for a specific period. Returns total revenue, invoice count, and breakdown by type (rent, sell, tailoring).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'this_year'],
                            'description' => 'The time period to query',
                        ],
                    ],
                    'required' => ['period'],
                ],
            ],
        ];
    }

    private static function activeReservations(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_active_reservations',
                'description' => 'Get currently active rental reservations. Returns count, upcoming pickups, and reservation details.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
        ];
    }

    private static function lateReturns(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_late_returns',
                'description' => 'Get overdue rental returns. Returns count of late returns and details about which customers and dresses are overdue.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
        ];
    }

    private static function pendingDeliveries(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_pending_deliveries',
                'description' => 'Get pending deliveries scheduled for today or overdue. Returns count and delivery details.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
        ];
    }

    private static function activeCustomers(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_active_customers',
                'description' => 'Get customer statistics. Returns total customer count, new customers this month, and activity metrics.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
        ];
    }

    private static function inactiveDresses(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_inactive_dresses',
                'description' => 'Get dresses that have not been rented for a long time (inactive/stagnant inventory). Returns count and dress details.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
        ];
    }

    private static function businessSnapshot(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_business_snapshot',
                'description' => 'Get a comprehensive overview of the atelier including revenue, reservations, inventory, and customers. Use for broad questions like "how is business?"',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
        ];
    }

    private static function businessHealth(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_business_health',
                'description' => 'Get business health score with main strengths and risks. Returns a score out of 100 with analysis.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
        ];
    }

    private static function dailyBrief(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_daily_brief',
                'description' => 'Get today\'s business brief including revenue, new activity, and alerts. Use for "what should I focus on today?"',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
        ];
    }

    private static function compareBusinessPeriods(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'compare_business_periods',
                'description' => 'Compare two time periods (e.g., this month vs last month). Returns revenue, reservations, and customer comparison with percentage changes.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'current_period' => [
                            'type' => 'string',
                            'enum' => ['this_week', 'this_month', 'this_year'],
                            'description' => 'The current period to compare',
                        ],
                        'previous_period' => [
                            'type' => 'string',
                            'enum' => ['last_week', 'last_month', 'last_year'],
                            'description' => 'The previous period to compare against',
                        ],
                    ],
                    'required' => ['current_period', 'previous_period'],
                ],
            ],
        ];
    }
}
