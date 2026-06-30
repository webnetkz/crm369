<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAppearanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Appearance', [
            'settings' => [
                'background_color' => $user->background_color,
                'background_image_url' => $user->background_image,
                'background_blur' => $user->background_blur,
            ],
        ]);
    }

    public function update(UpdateAppearanceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $backgroundImagePath = $user->background_image_path;

        if ($request->boolean('remove_background_image') && $backgroundImagePath) {
            Storage::disk('public')->delete($backgroundImagePath);
            $backgroundImagePath = null;
        }

        if ($request->hasFile('background_image')) {
            if ($backgroundImagePath) {
                Storage::disk('public')->delete($backgroundImagePath);
            }

            $backgroundImagePath = $request->file('background_image')->store('backgrounds/'.$user->id, 'public');
        }

        $user->update([
            'background_color' => $request->validated('background_color'),
            'background_image_path' => $backgroundImagePath,
            'background_blur' => $request->validated('background_blur'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.settings.appearance_updated_success'),
        ]);

        return to_route('appearance.edit');
    }
}
