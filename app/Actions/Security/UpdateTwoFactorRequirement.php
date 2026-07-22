<?php

namespace App\Actions\Security;

use App\Models\SystemSecuritySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;

class UpdateTwoFactorRequirement
{
    public function execute(User $actor, bool $enabled): SystemSecuritySetting
    {
        if ($enabled && ! Features::canManageTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'enabled' => __('ui.system_security.two_factor_feature_disabled'),
            ]);
        }

        if ($enabled && ! $actor->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'enabled' => __('ui.system_security.enable_your_two_factor_first'),
            ]);
        }

        return DB::transaction(function () use ($actor, $enabled): SystemSecuritySetting {
            $setting = SystemSecuritySetting::query()->lockForUpdate()->first()
                ?? new SystemSecuritySetting;

            $setting->forceFill([
                'requires_two_factor_authentication' => $enabled,
                'enforced_at' => $enabled ? ($setting->enforced_at ?? now()) : null,
                'updated_by_user_id' => $actor->id,
            ])->save();

            SystemSecuritySetting::forgetCachedRequirement();

            return $setting->refresh();
        });
    }
}
