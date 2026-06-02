<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrSetting;

class HrSettingService
{
    public const KEY_ATTENDANCE_RULES = 'attendance_rules';

    public const KEY_PAYROLL_RULES = 'payroll_rules';

    public const KEY_LEAVE_RULES = 'leave_rules';

    public const KEY_DOCUMENT_RULES = 'document_rules';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function defaultSettings(): array
    {
        return [
            self::KEY_ATTENDANCE_RULES => [
                'grace_minutes_default' => 10,
                'late_deduction_enabled' => false,
                'overtime_enabled' => true,
            ],
            self::KEY_PAYROLL_RULES => [
                'working_days_in_month' => 'calendar_exclude_friday',
                'late_deduction_per_minute' => 0,
                'overtime_rate_multiplier' => 1.5,
                'unpaid_leave_deducts_daily_rate' => true,
                'absence_deducts_daily_rate' => true,
            ],
            self::KEY_LEAVE_RULES => [
                'allow_half_day' => true,
                'require_manager_note_on_reject' => false,
            ],
            self::KEY_DOCUMENT_RULES => [
                'expiry_alert_days' => 30,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $defaults = $this->defaultSettings();
        $stored = HrSetting::query()->pluck('value', 'key')->all();

        $merged = [];
        foreach ($defaults as $key => $defaultValue) {
            $merged[$key] = array_merge($defaultValue, is_array($stored[$key] ?? null) ? $stored[$key] : []);
        }

        return $merged;
    }

    /**
     * @param  array<string, array<string, mixed>>  $settings
     * @return array<string, array<string, mixed>>
     */
    public function update(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $defaults = $this->defaultSettings()[$key] ?? [];
            if ($defaults === [] && ! in_array($key, array_keys($this->defaultSettings()), true)) {
                continue;
            }

            HrSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => array_merge($defaults, $value)],
            );
        }

        return $this->all();
    }

    public function documentExpiryAlertDays(): int
    {
        $settings = $this->all();

        return (int) ($settings[self::KEY_DOCUMENT_RULES]['expiry_alert_days'] ?? 30);
    }
}
