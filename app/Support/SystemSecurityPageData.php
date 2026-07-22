<?php

namespace App\Support;

use App\Models\SystemSecurityAudit;
use App\Models\SystemSecuritySetting;
use App\Models\User;
use Laravel\Fortify\Features;

class SystemSecurityPageData
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $setting = SystemSecuritySetting::current()->load('updatedBy:id,name,last_name');
        $latest = SystemSecurityAudit::query()
            ->with('performedBy:id,name,last_name')
            ->latest('checked_at')
            ->first();
        $activeUsers = User::query()->where('is_active', true)->count();
        $protectedUsers = User::query()
            ->where('is_active', true)
            ->whereNotNull('two_factor_secret')
            ->whereNotNull('two_factor_confirmed_at')
            ->count();

        return [
            'policy' => [
                'enabled' => $setting->requires_two_factor_authentication,
                'featureAvailable' => Features::canManageTwoFactorAuthentication(),
                'activeUsers' => $activeUsers,
                'protectedUsers' => $protectedUsers,
                'pendingUsers' => max(0, $activeUsers - $protectedUsers),
                'coveragePercent' => $activeUsers > 0
                    ? (int) round(($protectedUsers / $activeUsers) * 100)
                    : 100,
                'enforcedAt' => $setting->enforced_at?->toIso8601String(),
                'updatedBy' => $setting->updatedBy === null ? null : [
                    'id' => $setting->updatedBy->id,
                    'name' => trim($setting->updatedBy->name.' '.($setting->updatedBy->last_name ?? '')),
                ],
            ],
            'audit' => [
                'latest' => $latest === null ? null : $this->audit($latest),
                'history' => SystemSecurityAudit::query()
                    ->with('performedBy:id,name,last_name')
                    ->latest('checked_at')
                    ->limit(8)
                    ->get()
                    ->map(fn (SystemSecurityAudit $audit): array => $this->audit($audit, false))
                    ->all(),
                'manualDefaults' => $this->manualDefaults($latest),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function audit(SystemSecurityAudit $audit, bool $includeChecks = true): array
    {
        $data = [
            'id' => $audit->id,
            'score' => $audit->score,
            'riskLevel' => $audit->risk_level,
            'passedCount' => $audit->passed_count,
            'warningCount' => $audit->warning_count,
            'failedCount' => $audit->failed_count,
            'skippedCount' => $audit->skipped_count,
            'totalCount' => $audit->total_count,
            'durationMs' => $audit->duration_ms,
            'checkedAt' => $audit->checked_at->toIso8601String(),
            'performedBy' => $audit->performedBy === null ? null : [
                'id' => $audit->performedBy->id,
                'name' => trim($audit->performedBy->name.' '.($audit->performedBy->last_name ?? '')),
            ],
        ];

        if ($includeChecks) {
            $data['checks'] = collect($audit->checks)->map(fn (array $check): array => [
                'key' => $check['key'],
                'category' => $check['category'],
                'status' => $check['status'],
                'severity' => $check['severity'],
                'meta' => $check['meta'] ?? [],
            ])->values()->all();
        }

        return $data;
    }

    /**
     * @return array<string, bool>
     */
    private function manualDefaults(?SystemSecurityAudit $latest): array
    {
        $defaults = [
            'backups_verified' => false,
            'infrastructure_patched' => false,
            'privileged_access_reviewed' => false,
            'security_headers_configured' => false,
            'incident_plan_ready' => false,
            'secrets_rotated' => false,
        ];

        return array_replace($defaults, $latest?->manual_answers ?? []);
    }
}
