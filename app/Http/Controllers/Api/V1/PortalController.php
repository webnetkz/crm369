<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePortalSettingsRequest;
use App\Models\PortalSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = PortalSetting::current();

        return response()->json([
            'data' => [
                'company_name' => $settings->companyName(),
                'logo_url' => $settings->logoUrl(),
                'default_language' => $settings->defaultLanguage(),
            ],
        ]);
    }

    public function update(UpdatePortalSettingsRequest $request): JsonResponse
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

        return response()->json([
            'message' => __('ui.portal.updated_success'),
            'data' => [
                'company_name' => $settings->fresh()->companyName(),
                'logo_url' => $settings->fresh()->logoUrl(),
                'default_language' => $settings->fresh()->defaultLanguage(),
            ],
        ]);
    }
}
