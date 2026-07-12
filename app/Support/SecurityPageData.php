<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserLoginActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SecurityPageData
{
    /**
     * @return array<int, array{
     *     id: string,
     *     ip_address: ?string,
     *     user_agent: ?string,
     *     browser: ?string,
     *     platform: ?string,
     *     device_type: string,
     *     device_label: string,
     *     is_current: bool,
     *     last_active_at: string,
     *     last_active_at_diff: string
     * }>
     */
    public function sessionsFor(User $user, string $currentSessionId): array
    {
        $table = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($table)) {
            return [];
        }

        $lastActivityThreshold = now()
            ->subMinutes((int) config('session.lifetime', 120))
            ->timestamp;

        return DB::table($table)
            ->select(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $lastActivityThreshold)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (object $session) use ($currentSessionId): array {
                $userAgent = $this->normalizeUserAgent($session->user_agent ?? null);
                $device = UserAgentDetails::from($userAgent);
                $lastActivity = CarbonImmutable::createFromTimestamp((int) $session->last_activity);

                return [
                    'id' => (string) $session->id,
                    'ip_address' => $this->normalizeString($session->ip_address ?? null),
                    'user_agent' => $userAgent,
                    'browser' => $device->browser,
                    'platform' => $device->platform,
                    'device_type' => $device->deviceType,
                    'device_label' => $this->deviceLabel($device),
                    'is_current' => (string) $session->id === $currentSessionId,
                    'last_active_at' => $lastActivity->toISOString(),
                    'last_active_at_diff' => $lastActivity->diffForHumans(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     ip_address: ?string,
     *     user_agent: ?string,
     *     browser: ?string,
     *     platform: ?string,
     *     device_type: string,
     *     device_label: string,
     *     is_new_device: bool,
     *     is_new_ip: bool,
     *     logged_in_at: string,
     *     logged_in_at_diff: string
     * }>
     */
    public function loginActivitiesFor(User $user, int $limit = 10): array
    {
        if (! Schema::hasTable('user_login_activities')) {
            return [];
        }

        return $user->loginActivities()
            ->select([
                'id',
                'ip_address',
                'user_agent',
                'browser',
                'platform',
                'device_type',
                'is_new_device',
                'is_new_ip',
                'logged_in_at',
            ])
            ->limit($limit)
            ->get()
            ->map(function (UserLoginActivity $activity): array {
                $device = new UserAgentDetails(
                    browser: $activity->browser,
                    platform: $activity->platform,
                    deviceType: $activity->device_type,
                    signature: '',
                );

                return [
                    'id' => $activity->id,
                    'ip_address' => $this->normalizeString($activity->ip_address),
                    'user_agent' => $this->normalizeUserAgent($activity->user_agent),
                    'browser' => $activity->browser,
                    'platform' => $activity->platform,
                    'device_type' => $activity->device_type,
                    'device_label' => $this->deviceLabel($device),
                    'is_new_device' => $activity->is_new_device,
                    'is_new_ip' => $activity->is_new_ip,
                    'logged_in_at' => $activity->logged_in_at->toISOString(),
                    'logged_in_at_diff' => $activity->logged_in_at->diffForHumans(),
                ];
            })
            ->values()
            ->all();
    }

    private function deviceLabel(UserAgentDetails $device): string
    {
        $parts = array_values(array_filter([
            $device->browser,
            $device->platform,
            $this->deviceTypeLabel($device->deviceType),
        ]));

        if ($parts === []) {
            return __('ui.security.unknown_device');
        }

        return implode(' · ', $parts);
    }

    private function deviceTypeLabel(string $deviceType): ?string
    {
        return match ($deviceType) {
            'desktop' => __('ui.security.device_type_desktop'),
            'mobile' => __('ui.security.device_type_mobile'),
            'tablet' => __('ui.security.device_type_tablet'),
            default => null,
        };
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function normalizeUserAgent(mixed $userAgent): ?string
    {
        if (! is_string($userAgent)) {
            return null;
        }

        $userAgent = Str::of($userAgent)
            ->squish()
            ->limit(1000, '')
            ->value();

        return $userAgent !== '' ? $userAgent : null;
    }
}
