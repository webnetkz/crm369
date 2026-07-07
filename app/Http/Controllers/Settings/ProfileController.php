<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\PortalSetting;
use App\Support\ManagedUserProfileData;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, ManagedUserProfileData $managedUserProfileData): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'issuedEquipment' => $user !== null
                && PortalSetting::current()->isModuleEnabled('equipment')
                && Schema::hasTable('equipment_items')
                ? $managedUserProfileData->serializeIssuedEquipment(
                    $user->load([
                        'issuedEquipmentItems:id,name,qr_code,status,issued_to_user_id,responsible_user_id,updated_at',
                        'issuedEquipmentItems.responsibleUser:id,name,last_name,email',
                    ]),
                )
                : [],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill(Arr::only($validated, ['name', 'last_name', 'middle_name', 'email', 'phone', 'position']));

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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }
}
