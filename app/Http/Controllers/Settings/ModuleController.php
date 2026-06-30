<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateModuleSettingsRequest;
use App\Models\PortalSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function edit(): Response
    {
        $settings = PortalSetting::current();

        return Inertia::render('settings/Modules', [
            'modules' => collect(PortalSetting::availableModules())
                ->map(fn (array $definition, string $key): array => [
                    'key' => $key,
                    'title' => __($definition['title_key']),
                    'description' => __($definition['description_key']),
                    'is_enabled' => $settings->isModuleEnabled($key),
                ])
                ->values()
                ->all(),
            'disabledModules' => $settings->disabledModules(),
        ]);
    }

    public function update(UpdateModuleSettingsRequest $request): RedirectResponse
    {
        PortalSetting::current()->update([
            'disabled_modules' => $request->disabledModules(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.modules.updated_success')]);

        return back();
    }
}
