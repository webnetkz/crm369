<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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

    public function destroy(Request $request): Response
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if (! is_numeric($impersonatorId)) {
            return Inertia::location($this->redirectUrlAfterImpersonation($request->user()));
        }

        $request->session()->forget('impersonator_id');

        $impersonator = User::query()->find((int) $impersonatorId);

        if (! $impersonator) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return Inertia::location(route('login'));
        }

        Auth::loginUsingId($impersonator->id);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.admin.impersonation_ended_success')]);

        return Inertia::location($this->redirectUrlAfterImpersonation($impersonator));
    }

    private function redirectUrlAfterImpersonation(?User $user): string
    {
        return $user?->canViewUsers()
            ? route('settings.users.index')
            : route('dashboard');
    }
}
