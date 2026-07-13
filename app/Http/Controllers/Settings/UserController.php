<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FilterUsersIndexRequest;
use App\Http\Requests\Settings\ImportUsersRequest;
use App\Http\Requests\Settings\ResetManagedUserPasswordRequest;
use App\Http\Requests\Settings\StoreManagedUserRequest;
use App\Http\Requests\Settings\UpdateManagedUserProfileRequest;
use App\Http\Requests\Settings\UpdateUserActivationRequest;
use App\Http\Requests\Settings\UpdateUserGroupMembershipRequest;
use App\Http\Requests\Settings\UpdateUserTableColumnsRequest;
use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\SystemNotification;
use App\Support\CsvDelimiter;
use App\Support\ManagedUserProfileData;
use App\Support\PaginationData;
use App\Support\PerPageOptions;
use App\Support\UserCsvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(FilterUsersIndexRequest $request, ManagedUserProfileData $managedUserProfileData): Response
    {
        $viewer = $request->user();
        $canManageUsers = $viewer?->can('manage-users') ?? false;
        $filters = $request->filters();

        $users = User::query()
            ->with([
                'group:id,name',
                'manager:id,name,last_name,middle_name,email,position,avatar_path,avatar_scale,is_active',
                'subordinates:id,name,last_name,middle_name,email,position,avatar_path,avatar_scale,is_active,manager_id',
                ...$this->issuedEquipmentRelations(),
            ])
            ->select([
                'id',
                'name',
                'last_name',
                'middle_name',
                'email',
                'phone',
                'position',
                'manager_id',
                'email_verified_at',
                'avatar_path',
                'avatar_scale',
                'user_group_id',
                'is_active',
                'deactivated_at',
                'created_at',
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['group'] === 'none', fn ($query) => $query->whereNull('user_group_id'))
            ->when(
                $filters['group'] !== '' && $filters['group'] !== 'none',
                fn ($query) => $query->where('user_group_id', (int) $filters['group']),
            )
            ->when($filters['registered_from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['registered_from']))
            ->when($filters['registered_to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['registered_to']))
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (User $user): array => [
                ...$managedUserProfileData->serialize($user),
                'can_be_impersonated' => $user->canBeImpersonatedBy($viewer),
            ]);

        return Inertia::render('settings/Users', [
            'can' => [
                'manage_users' => $canManageUsers,
                'manage_activation' => $viewer?->can('manage-user-activation') ?? false,
                'manage_accounts' => $viewer?->can('manage-user-accounts') ?? false,
                'impersonate_users' => $viewer?->can('impersonate-users') ?? false,
            ],
            'filters' => $filters,
            'users' => PaginationData::from($users),
            'groups' => $canManageUsers
                ? UserGroup::query()
                    ->select(['id', 'name'])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (UserGroup $group): array => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'display_name' => $group->displayName(),
                    ])
                    ->values()
                : [],
            'perPageOptions' => PerPageOptions::allowed(),
            'visibleUserTableColumns' => $this->visibleUserTableColumns($viewer),
            'managerOptions' => $managedUserProfileData->managerOptions($viewer),
        ]);
    }

    public function updateTableColumns(UpdateUserTableColumnsRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && $this->canPersistVisibleUserTableColumns()) {
            $user->forceFill([
                'visible_user_table_columns' => $request->visibleColumns(),
            ])->save();
        }

        return back();
    }

    public function show(User $user, ManagedUserProfileData $managedUserProfileData): JsonResponse
    {
        return response()->json([
            'data' => $managedUserProfileData->serialize($user->load([
                'group:id,name',
                'manager:id,name,last_name,middle_name,email,position,avatar_path,avatar_scale,is_active',
                'subordinates:id,name,last_name,middle_name,email,position,avatar_path,avatar_scale,is_active,manager_id',
                ...$this->issuedEquipmentRelations(),
            ])),
            'canEdit' => $managedUserProfileData->canEdit(request()->user(), $user),
        ]);
    }

    public function exportCsv(Request $request, UserCsvService $userCsvService): StreamedResponse
    {
        abort_unless($request->user()?->can('view-users') ?? false, 403);

        $users = User::query()
            ->with(['group:id,name', 'manager:id,email'])
            ->orderBy('name')
            ->orderBy('last_name')
            ->get();

        return $userCsvService->download(
            $users,
            'users-'.now()->format('Y-m-d-H-i-s').'.csv',
            $this->resolveCsvDelimiter($request),
        );
    }

    public function downloadCsvTemplate(Request $request, UserCsvService $userCsvService): StreamedResponse
    {
        abort_unless($request->user()?->can('manage-user-accounts') ?? false, 403);

        return $userCsvService->downloadTemplate(
            'users-template-'.now()->format('Y-m-d-H-i-s').'.csv',
            $this->resolveCsvDelimiter($request),
        );
    }

    public function importCsv(ImportUsersRequest $request, UserCsvService $userCsvService): RedirectResponse
    {
        $importedCount = $userCsvService->import(
            $request->uploadedFile(),
            $request->delimiter(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.admin.csv_import_success', ['count' => $importedCount]),
        ]);

        return back();
    }

    public function store(StoreManagedUserRequest $request): RedirectResponse
    {
        $user = new User([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'language' => PortalSetting::current()->defaultLanguage(),
            'has_selected_language' => false,
        ]);

        $user->email_verified_at = $request->emailVerified() ? now() : null;
        $user->save();
        $user->notify($this->systemNotification(
            $user,
            'ui.notifications.account_created_title',
            'ui.notifications.account_created_message',
            route('profile.edit'),
            'ui.notifications.open_profile',
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.admin.user_created_success')]);

        return back();
    }

    public function updateProfile(UpdateManagedUserProfileRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->fill(Arr::only($validated, ['name', 'last_name', 'middle_name', 'email', 'phone', 'position', 'manager_id']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back();
    }

    public function updateGroup(UpdateUserGroupMembershipRequest $request, User $user): RedirectResponse
    {
        $groupId = $request->userGroupId();

        $user->update([
            'user_group_id' => $groupId,
        ]);

        $user->notify($this->systemNotification(
            $user,
            'ui.notifications.group_updated_title',
            'ui.notifications.group_updated_message',
            route('profile.edit'),
            'ui.notifications.open_profile',
            ['group' => $this->groupNameForLocale($groupId, $user->resolvedLanguage())],
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.admin.group_updated_success')]);

        return back();
    }

    public function updateActivation(UpdateUserActivationRequest $request, User $user): RedirectResponse
    {
        if ($user->is($request->user()) || $user->isSuperAdmin()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('ui.admin.user_activation_denied')]);

            return back();
        }

        $isActive = $request->isActive();

        $user->update([
            'is_active' => $isActive,
            'deactivated_at' => $isActive ? null : now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isActive
                ? __('ui.admin.user_activated_success')
                : __('ui.admin.user_deactivated_success'),
        ]);

        return back();
    }

    public function resetPassword(ResetManagedUserPasswordRequest $request, User $user): RedirectResponse
    {
        if ($user->is($request->user()) || ($user->isSuperAdmin() && ! $request->user()?->isSuperAdmin())) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('ui.admin.password_reset_denied')]);

            return back();
        }

        $user->update([
            'password' => $request->validated('password'),
        ]);
        $user->notify($this->systemNotification(
            $user,
            'ui.notifications.password_reset_title',
            'ui.notifications.password_reset_message',
            route('security.edit'),
            'ui.notifications.open_security',
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.admin.password_reset_success')]);

        return back();
    }

    private function systemNotification(
        User $user,
        string $titleKey,
        string $messageKey,
        ?string $actionUrl = null,
        ?string $actionLabelKey = null,
        array $replace = [],
    ): SystemNotification {
        return new SystemNotification(
            title: Lang::get($titleKey, $replace, $user->resolvedLanguage()),
            message: Lang::get($messageKey, $replace, $user->resolvedLanguage()),
            actionUrl: $actionUrl,
            actionLabel: $actionLabelKey ? Lang::get($actionLabelKey, [], $user->resolvedLanguage()) : null,
        );
    }

    private function groupNameForLocale(?int $groupId, string $locale): string
    {
        if ($groupId === null) {
            return Lang::get('ui.admin.simple_user', [], $locale);
        }

        $group = UserGroup::query()->find($groupId);

        if (! $group) {
            return Lang::get('ui.admin.simple_user', [], $locale);
        }

        return $group->name === UserGroup::ADMINISTRATORS_NAME
            ? Lang::get('ui.admin.administrators_group', [], $locale)
            : $group->name;
    }

    /**
     * @return array<int, string>
     */
    private function visibleUserTableColumns(?User $user): array
    {
        if (! $user || ! $this->canPersistVisibleUserTableColumns()) {
            return [];
        }

        return $user->visibleUserTableColumns();
    }

    private function canPersistVisibleUserTableColumns(): bool
    {
        return Schema::hasColumn('users', 'visible_user_table_columns');
    }

    private function resolveCsvDelimiter(Request $request): string
    {
        $request->validate([
            'delimiter' => ['nullable', 'string', 'max:10'],
        ]);

        $delimiter = CsvDelimiter::normalize($request->input('delimiter'));

        abort_if($delimiter === null, 422, __('ui.admin.csv_delimiter_invalid'));

        return $delimiter;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function issuedEquipmentRelations(): array
    {
        if (! PortalSetting::current()->isModuleEnabled('equipment') || ! Schema::hasTable('equipment_items')) {
            return [];
        }

        return [
            'issuedEquipmentItems' => fn ($query) => $query
                ->select([
                    'id',
                    'name',
                    'qr_code',
                    'status',
                    'issued_to_user_id',
                    'responsible_user_id',
                    'updated_at',
                ])
                ->with('responsibleUser:id,name,last_name,email')
                ->ordered(),
        ];
    }
}
