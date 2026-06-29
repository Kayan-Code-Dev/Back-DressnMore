<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Setting;
use App\Support\PlanCurrency;

class AppSettingService
{
    public const KEY_APP_SETTINGS = 'app.settings';

    /**
     * @return array{timezone: string, currency: string}
     */
    public function defaults(): array
    {
        return [
            'timezone' => 'UTC',
            'currency' => 'USD',
        ];
    }

    /**
     * @return array{timezone: string, currency: string}
     */
    public function all(): array
    {
        $stored = Setting::query()->where('key', self::KEY_APP_SETTINGS)->value('value');

        return array_merge(
            $this->defaults(),
            is_array($stored) ? $stored : [],
        );
    }

    public function currency(): string
    {
        return PlanCurrency::normalizeTenant($this->all()['currency'] ?? 'USD');
    }

    /**
     * @return array{timezone: string, currency: string, currency_symbol: string, currency_label: string}
     */
    public function present(): array
    {
        $settings = $this->all();
        $currency = PlanCurrency::normalizeTenant($settings['currency'] ?? 'USD');

        return [
            'timezone' => (string) ($settings['timezone'] ?? 'UTC'),
            'currency' => $currency,
            'currency_symbol' => PlanCurrency::symbol($currency),
            'currency_label' => PlanCurrency::label($currency),
        ];
    }

    /**
     * @param  array{timezone?: string, currency?: string}  $data
     * @return array{timezone: string, currency: string, currency_symbol: string, currency_label: string}
     */
    public function update(array $data): array
    {
        $current = $this->all();

        if (array_key_exists('timezone', $data)) {
            $current['timezone'] = trim((string) $data['timezone']) ?: 'UTC';
        }

        if (array_key_exists('currency', $data)) {
            $current['currency'] = PlanCurrency::normalizeTenant((string) $data['currency']);
        }

        Setting::query()->updateOrCreate(
            ['key' => self::KEY_APP_SETTINGS],
            ['value' => $current],
        );

        return $this->present();
    }
}
