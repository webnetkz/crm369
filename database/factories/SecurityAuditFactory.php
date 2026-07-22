<?php

namespace Database\Factories;

use App\Models\SecurityAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityAudit>
 */
class SecurityAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'score' => 100,
            'risk_level' => 'protected',
            'passed_count' => 1,
            'warning_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'total_count' => 1,
            'checks' => [[
                'key' => 'email_verified',
                'category' => 'identity',
                'status' => 'passed',
                'severity' => 'critical',
                'weight' => 15,
                'earned' => 15,
                'meta' => [],
            ]],
            'manual_answers' => [
                'unique_password' => true,
                'recovery_codes_stored' => true,
                'sessions_reviewed' => true,
                'device_protected' => true,
                'phishing_ready' => true,
            ],
            'checked_at' => now(),
        ];
    }
}
