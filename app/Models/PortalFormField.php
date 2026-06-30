<?php

namespace App\Models;

use Database\Factories\PortalFormFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $portal_form_id
 * @property string $key
 * @property string $label
 * @property string $type
 * @property string|null $placeholder
 * @property bool $is_required
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['portal_form_id', 'key', 'label', 'type', 'placeholder', 'is_required', 'sort_order'])]
class PortalFormField extends Model
{
    public const string TYPE_TEXT = 'text';

    public const string TYPE_TEXTAREA = 'textarea';

    public const string TYPE_EMAIL = 'email';

    public const string TYPE_NUMBER = 'number';

    /** @use HasFactory<PortalFormFieldFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portal_form_id' => 'integer',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableTypes(): array
    {
        return [
            self::TYPE_TEXT,
            self::TYPE_TEXTAREA,
            self::TYPE_EMAIL,
            self::TYPE_NUMBER,
        ];
    }

    /**
     * @return BelongsTo<PortalForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(PortalForm::class, 'portal_form_id');
    }
}
