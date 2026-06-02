<?php

namespace Tests\Feature;

class HrSettingsApiTest extends TenantHrTestCase
{
    public function test_get_defaults_and_update_settings(): void
    {
        $user = $this->createTenantUserWithPermissions([
            'hr.settings.view',
            'hr.settings.update',
        ]);
        $headers = $this->authHeaders($user);

        $this->getJson('/api/tenant/hr/settings', $headers)
            ->assertOk()
            ->assertJsonPath('data.attendance_rules.grace_minutes_default', 10)
            ->assertJsonPath('data.payroll_rules.overtime_rate_multiplier', 1.5)
            ->assertJsonPath('data.leave_rules.allow_half_day', true)
            ->assertJsonPath('data.document_rules.expiry_alert_days', 30);

        $this->putJson('/api/tenant/hr/settings', [
            'settings' => [
                'document_rules' => [
                    'expiry_alert_days' => 45,
                ],
            ],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.document_rules.expiry_alert_days', 45);
    }
}
