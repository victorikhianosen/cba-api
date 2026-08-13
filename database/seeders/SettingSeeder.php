<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    private const SETTINGS = [
        [
            'key'      => 'security.session.session_lock_enabled',
            'category' => 'security',
            'label'    => 'Session Lock Enabled',
            'type'     => 'boolean',
            'value'    => '1',
        ],
        [
            'key'      => 'security.session.session_lock_timeout',
            'category' => 'security',
            'label'    => 'Session Lock Timeout',
            'type'     => 'integer',
            'value'    => '7200',
        ],
        [
            'key'      => 'security.authentication.google_auth_unlock',
            'category' => 'security',
            'label'    => 'Google Auth Unlock',
            'type'     => 'boolean',
            'value'    => '1',
        ],
        [
            'key'      => 'system.company.name',
            'category' => 'system',
            'label'    => 'Company Name',
            'type'     => 'string',
            'value'    => 'BankPro',
        ],
        [
            'key'      => 'system.company.logo',
            'category' => 'system',
            'label'    => 'Company Logo',
            'type'     => 'image',
            'value'    => null,
        ],
        [
            'key'         => 'notifications.cron_reports.recipients',
            'category'    => 'notifications',
            'label'       => 'Cron Report Recipients',
            'description' => 'Email addresses that receive the full PDF report after each scheduled job (loan overdue processing, investment interest) finishes running.',
            'type'        => 'json',
            'value'       => '["test@example.com"]',
        ],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
