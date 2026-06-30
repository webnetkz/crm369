<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserImpersonationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $impersonator = $request->user();

        abort_unless($impersonator?->can('impersonate-users') ?? false, 403);

        if ($request->session()->has('impersonator_id') || ! $impersonator || ! $impersonator->canImpersonate($user)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('ui.admin.impersonation_denied')]);

            return back();
        }

        Auth::loginUsingId($user->id);
        $request->session()->put('impersonator_id', $impersonator->id);
        $request->session()->regenerate();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.admin.impersonation_started_success', ['name' => $user->name]),
        ]);

        return to_route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        abort_unless(is_numeric($impersonatorId), 403);

        $impersonator = User::query()->find((int) $impersonatorId);

        if (! $impersonator) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login');
        }

        Auth::loginUsingId($impersonator->id);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.admin.impersonation_ended_success')]);

        return $impersonator->canViewUsers()
            ? to_route('settings.users.index')
            : to_route('dashboard');
    }
}
