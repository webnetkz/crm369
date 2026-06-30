<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Requests\Settings\UpdateAppearanceRequest;
use App\Http\Requests\UpdateLanguageRequest;
use App\Http\Resources\ApiUserResource;
use App\Support\ApiRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = ApiRequestContext::subject($request);

        return response()->json([
            'data' => $this->resource($user->fresh('group')),
        ]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = ApiRequestContext::subject($request);
        $validated = $request->validated();

        $user->fill(Arr::only($validated, ['name', 'last_name', 'email', 'phone']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
        }

        foreach (['avatar_position_x', 'avatar_position_y', 'avatar_scale'] as $avatarSetting) {
            if (array_key_exists($avatarSetting, $validated)) {
                $user->{$avatarSetting} = $validated[$avatarSetting];
            }
        }

        $user->save();

        return response()->json([
            'message' => __('Profile updated.'),
            'data' => $this->resource($user->fresh('group')),
        ]);
    }

    public function updateLanguage(UpdateLanguageRequest $request): JsonResponse
    {
        $user = ApiRequestContext::subject($request);

        $user->update([
            'language' => $request->language(),
            'has_selected_language' => true,
        ]);

        return response()->json([
            'message' => __('ui.profile.language_updated'),
            'data' => $this->resource($user->fresh('group')),
        ]);
    }

    public function updateAppearance(UpdateAppearanceRequest $request): JsonResponse
    {
        $user = ApiRequestContext::subject($request);
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

        return response()->json([
            'message' => __('ui.settings.appearance_updated_success'),
            'data' => $this->resource($user->fresh('group')),
        ]);
    }

    private function resource($user): array
    {
        return (new ApiUserResource($user))->resolve();
    }
}
