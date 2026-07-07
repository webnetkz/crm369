<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateManagedUserProfileRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $targetUser = $this->route('user');
        $currentUser = $this->user();

        if (! $currentUser?->can('manage-user-accounts') || ! $targetUser instanceof User) {
            return false;
        }

        return ! ($targetUser->isSuperAdmin() && ! $currentUser->isSuperAdmin());
    }

    protected function prepareForValidation(): void
    {
        $lastName = $this->input('last_name');
        $phone = $this->input('phone');
        $position = $this->input('position');
        $managerId = $this->input('manager_id');

        $this->merge([
            'last_name' => is_string($lastName) && trim($lastName) !== ''
                ? trim($lastName)
                : null,
            'phone' => $this->normalizeKazakhstanPhone(is_string($phone) ? $phone : null),
            'position' => is_string($position) && trim($position) !== ''
                ? trim($position)
                : null,
            'manager_id' => is_numeric($managerId) ? (int) $managerId : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targetUser = $this->route('user');

        return [
            ...$this->profileRules($targetUser instanceof User ? $targetUser->id : null),
            'position' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $targetUser = $this->route('user');
                $managerId = $this->validated('manager_id');

                if (! $targetUser instanceof User || ! is_int($managerId)) {
                    return;
                }

                if ($managerId === $targetUser->id) {
                    $validator->errors()->add('manager_id', __('ui.admin.manager_cycle_error'));

                    return;
                }

                if ($this->managerCreatesCycle($targetUser, $managerId)) {
                    $validator->errors()->add('manager_id', __('ui.admin.manager_cycle_error'));
                }
            },
        ];
    }

    private function normalizeKazakhstanPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        } elseif ($digits[0] !== '7') {
            $digits = '7'.$digits;
        }

        $digits = substr($digits, 0, 11);

        if ($digits === '7' || strlen($digits) < 11) {
            return null;
        }

        return '+'.$digits;
    }

    private function managerCreatesCycle(User $targetUser, int $managerId): bool
    {
        /** @var Collection<int, int> $visitedIds */
        $visitedIds = collect();
        $cursorId = $managerId;

        while ($cursorId > 0 && ! $visitedIds->contains($cursorId)) {
            if ($cursorId === $targetUser->id) {
                return true;
            }

            $visitedIds->push($cursorId);

            $cursorId = (int) (User::query()
                ->whereKey($cursorId)
                ->value('manager_id') ?? 0);
        }

        return false;
    }
}
