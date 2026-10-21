<?php

namespace App\Support;

use App\Http\Requests\StorePortalFormRequest;
use App\Models\PortalForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Visibility and persistence of portal forms shared by the web and native mobile clients.
 */
class PortalFormManager
{
    public const int RECENT_SUBMISSIONS_LIMIT = 25;

    public function __construct(private readonly PortalFormSubmissionManager $submissionManager) {}

    /**
     * Forms the user may manage (super administrators see every form, other users their own forms)
     * with the relations and counters the forms page needs, most recently updated first.
     *
     * @return Builder<PortalForm>
     */
    public function visibleFormsQuery(User $user): Builder
    {
        return PortalForm::query()
            ->with([
                'owner:id,name,last_name,email',
                'targetUser:id,name,last_name,email',
                'fields',
            ])
            ->withCount(['fields', 'submissions'])
            ->withMax('submissions', 'created_at')
            ->when(
                ! $user->isSuperAdmin(),
                fn (Builder $query) => $query->where('owner_user_id', $user->id),
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /**
     * The form loaded like the forms page loads it, or a 404 response when the user may not manage it.
     */
    public function findVisibleForm(User $user, int $portalFormId): PortalForm
    {
        return $this->visibleFormsQuery($user)->findOrFail($portalFormId);
    }

    /**
     * Load the most recent submissions of the form (with their target users) as its submissions relation.
     */
    public function loadRecentSubmissions(PortalForm $form): PortalForm
    {
        return $form->setRelation(
            'submissions',
            $form->submissions()
                ->with(['targetUser:id,name,last_name,email'])
                ->latest('id')
                ->limit(self::RECENT_SUBMISSIONS_LIMIT)
                ->get(),
        );
    }

    /**
     * Create a form owned by the user together with its fields and a fresh public link token.
     */
    public function create(StorePortalFormRequest $request, User $owner): PortalForm
    {
        return DB::transaction(function () use ($request, $owner): PortalForm {
            $form = PortalForm::query()->create([
                'owner_user_id' => $owner->id,
                'target_user_id' => (int) $request->validated('target_user_id'),
                'name' => (string) $request->validated('name'),
                'description' => $request->validated('description'),
                'submission_mode' => (string) $request->validated('submission_mode'),
                'public_token' => $this->newPublicToken(),
                'is_active' => $request->boolean('is_active'),
                'style_settings' => $request->styleSettings(),
                'completion_settings' => $request->completionSettings(),
            ]);

            $this->submissionManager->syncFields($form, $request->fieldRows());

            return $form;
        });
    }

    /**
     * Update the form settings and synchronize its fields.
     */
    public function update(StorePortalFormRequest $request, PortalForm $form): PortalForm
    {
        DB::transaction(function () use ($request, $form): void {
            $form->update([
                'target_user_id' => (int) $request->validated('target_user_id'),
                'name' => (string) $request->validated('name'),
                'description' => $request->validated('description'),
                'submission_mode' => (string) $request->validated('submission_mode'),
                'is_active' => $request->boolean('is_active'),
                'style_settings' => $request->styleSettings(),
                'completion_settings' => $request->completionSettings(),
            ]);

            $this->submissionManager->syncFields($form, $request->fieldRows());
        });

        return $form;
    }

    private function newPublicToken(): string
    {
        do {
            $token = Str::random(32);
        } while (PortalForm::query()->where('public_token', $token)->exists());

        return $token;
    }
}
