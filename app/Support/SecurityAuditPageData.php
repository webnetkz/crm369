<?php

namespace App\Support;

use App\Models\SecurityAudit;
use App\Models\User;

class SecurityAuditPageData
{
    /**
     * @return array{
     *     latest: ?array<string, mixed>,
     *     history: array<int, array<string, mixed>>,
     *     manualDefaults: array<string, bool>
     * }
     */
    public function forUser(User $user): array
    {
        $audits = SecurityAudit::query()
            ->whereBelongsTo($user)
            ->latest('checked_at')
            ->limit(6)
            ->get();
        $latest = $audits->first();

        return [
            'latest' => $latest ? $this->serializeAudit($latest, includeChecks: true) : null,
            'history' => $audits
                ->map(fn (SecurityAudit $audit): array => $this->serializeAudit($audit))
                ->values()
                ->all(),
            'manualDefaults' => $latest?->manual_answers ?? [
                'unique_password' => false,
                'recovery_codes_stored' => false,
                'sessions_reviewed' => false,
                'device_protected' => false,
                'phishing_ready' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAudit(SecurityAudit $audit, bool $includeChecks = false): array
    {
        $data = [
            'id' => $audit->id,
            'score' => $audit->score,
            'risk_level' => $audit->risk_level,
            'passed_count' => $audit->passed_count,
            'warning_count' => $audit->warning_count,
            'failed_count' => $audit->failed_count,
            'skipped_count' => $audit->skipped_count,
            'total_count' => $audit->total_count,
            'checked_at' => $audit->checked_at->toISOString(),
            'checked_at_diff' => $audit->checked_at->diffForHumans(),
        ];

        if ($includeChecks) {
            $data['checks'] = $audit->checks;
        }

        return $data;
    }
}
