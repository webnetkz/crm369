<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePortalSettingsRequest;
use App\Models\PortalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function edit(): Response
    {
        $settings = PortalSetting::current();

        return Inertia::render('settings/Portal', [
            'settings' => [
                'company_name' => $settings->companyName(),
                'logo_url' => $settings->logoUrl(),
                'default_language' => $settings->defaultLanguage(),
            ],
        ]);
    }

    public function update(UpdatePortalSettingsRequest $request): RedirectResponse
    {
        $settings = PortalSetting::current();
        $logoPath = $settings->logo_path;

        if ($request->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $request->file('logo')->store('portal', 'public');
        }

        $settings->update([
            'company_name' => $request->validated('company_name'),
            'logo_path' => $logoPath,
            'default_language' => $request->validated('default_language'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.portal.updated_success')]);

        return back();
    }
}
