<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilitySetting;
use Illuminate\Database\Seeder;

class FacilitySettingsSeeder extends Seeder
{
    /**
     * Seed default facility settings for existing facilities.
     */
    public function run(): void
    {
        $facilities = Facility::all();

        foreach ($facilities as $facility) {
            $this->seedDefaultsForFacility($facility);
        }
    }

    protected function seedDefaultsForFacility(Facility $facility): void
    {
        $defaults = [
            'general' => [
                'display_name' => [
                    'value' => $facility->name,
                    'type' => 'string',
                    'description' => 'Display name for this facility',
                ],
            ],
            'email' => [
                'mail_driver' => [
                    'value' => 'smtp',
                    'type' => 'string',
                    'description' => 'Mail driver',
                ],
            ],
            'security' => [
                'password_min_length' => [
                    'value' => 8,
                    'type' => 'integer',
                    'description' => 'Minimum password length for staff accounts',
                ],
                'session_timeout_minutes' => [
                    'value' => 30,
                    'type' => 'integer',
                    'description' => 'Minutes of inactivity before auto logout',
                ],
            ],
            'notification' => [
                'enable_email_notifications' => [
                    'value' => true,
                    'type' => 'boolean',
                    'description' => 'Whether email notifications are enabled',
                ],
                'enable_in_app_notifications' => [
                    'value' => true,
                    'type' => 'boolean',
                    'description' => 'Whether in-app notifications are enabled',
                ],
            ],
            'database' => [
                'slow_query_threshold_ms' => [
                    'value' => 500,
                    'type' => 'integer',
                    'description' => 'Slow query threshold in milliseconds',
                ],
            ],
            'server' => [
                'maintenance_mode' => [
                    'value' => false,
                    'type' => 'boolean',
                    'description' => 'Whether the facility is in maintenance mode',
                ],
                'queue_concurrency' => [
                    'value' => 5,
                    'type' => 'integer',
                    'description' => 'Default queue worker concurrency',
                ],
                'log_retention_days' => [
                    'value' => 30,
                    'type' => 'integer',
                    'description' => 'How many days logs should be retained',
                ],
            ],
        ];

        foreach ($defaults as $category => $settings) {
            foreach ($settings as $key => $config) {
                FacilitySetting::firstOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'category' => $category,
                        'key' => $key,
                    ],
                    [
                        'value' => $config['value'],
                        'type' => $config['type'],
                        'description' => $config['description'],
                    ]
                );
            }
        }
    }
}


