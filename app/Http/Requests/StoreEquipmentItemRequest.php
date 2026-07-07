<?php

namespace App\Http\Requests;

use App\Models\EquipmentItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class StoreEquipmentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user() !== null) || $this->attributes->has('portal_webhook');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'qr_code' => $this->normalizeNullableString($this->input('qr_code')),
            'status' => is_string($this->input('status')) && trim($this->input('status')) !== ''
                ? trim($this->input('status'))
                : EquipmentItem::STATUS_ON_BALANCE,
            'issued_to_user_id' => is_numeric($this->input('issued_to_user_id'))
                ? (int) $this->input('issued_to_user_id')
                : null,
            'responsible_user_id' => is_numeric($this->input('responsible_user_id'))
                ? (int) $this->input('responsible_user_id')
                : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'qr_code' => ['nullable', 'string', 'max:64', $this->qrCodeUniqueRule()],
            'status' => ['required', 'string', Rule::in(EquipmentItem::availableStatuses())],
            'issued_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateStatusContext($validator)];
    }

    /**
     * @return array{name: string, qr_code: string, status: string, issued_to_user_id: int|null, responsible_user_id: int|null}
     */
    public function payload(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'qr_code' => $this->validated('qr_code') ?: EquipmentItem::generateQrCode(),
            'status' => (string) $this->validated('status'),
            'issued_to_user_id' => $this->issuedToUserId(),
            'responsible_user_id' => $this->responsibleUserId(),
        ];
    }

    protected function qrCodeUniqueRule(): Unique
    {
        return Rule::unique('equipment_items', 'qr_code');
    }

    protected function issuedToUserId(): ?int
    {
        $issuedToUserId = $this->validated('issued_to_user_id');

        return is_numeric($issuedToUserId) ? (int) $issuedToUserId : null;
    }

    protected function responsibleUserId(): ?int
    {
        $responsibleUserId = $this->validated('responsible_user_id');

        return is_numeric($responsibleUserId) ? (int) $responsibleUserId : null;
    }

    private function validateStatusContext(Validator $validator): void
    {
        $status = $this->validated('status');
        $issuedToUserId = $this->issuedToUserId();

        if ($status === EquipmentItem::STATUS_ISSUED && $issuedToUserId === null) {
            $validator->errors()->add('issued_to_user_id', __('ui.equipment.validation_issued_to_user_required'));
        }

        if ($status !== EquipmentItem::STATUS_ISSUED && $issuedToUserId !== null) {
            $validator->errors()->add('issued_to_user_id', __('ui.equipment.validation_issued_to_user_forbidden'));
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
