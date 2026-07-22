<?php

namespace Database\Factories;

use App\Models\SystemSecurityAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSecurityAudit>
 */
class SystemSecurityAuditFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performed_by_user_id' => User::factory(),
            'score' => 100,
            'risk_level' => 'protected',
            'passed_count' => 1,
            'warning_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'total_count' => 1,
            'checks' => [[
                'key' => 'debug_mode',
                'category' => 'runtime',
                'status' => 'passed',
                'severity' => 'critical',
                'weight' => 10,
                'earned' => 10,
                'meta' => [],
            ]],
            'manual_answers' => [
                'backups_verified' => true,
                'infrastructure_patched' => true,
                'privileged_access_reviewed' => true,
                'security_headers_configured' => true,
                'incident_plan_ready' => true,
                'secrets_rotated' => true,
            ],
            'duration_ms' => 150,
            'checked_at' => now(),
        ];
    }
}
