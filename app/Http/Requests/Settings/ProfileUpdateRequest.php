<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Support\ApiRequestContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        $lastName = $this->input('last_name');
        $phone = $this->input('phone');

        $this->merge([
            'last_name' => is_string($lastName) && trim($lastName) !== ''
                ? trim($lastName)
                : null,
            'phone' => $this->normalizeKazakhstanPhone(is_string($phone) ? $phone : null),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules(ApiRequestContext::subject($this)->id);
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
}
