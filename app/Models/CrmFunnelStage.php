<?php

namespace App\Models;

use Database\Factories\CrmFunnelStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crm_funnel_id
 * @property string $name
 * @property string|null $color
 * @property string $type
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['crm_funnel_id', 'name', 'color', 'type', 'sort_order'])]
class CrmFunnelStage extends Model
{
    public const string TYPE_OPEN = 'open';

    public const string TYPE_WON = 'won';

    public const string TYPE_LOST = 'lost';

    /** @use HasFactory<CrmFunnelStageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableTypes(): array
    {
        return [
            self::TYPE_OPEN,
            self::TYPE_WON,
            self::TYPE_LOST,
        ];
    }

    /**
     * @return BelongsTo<CrmFunnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(CrmFunnel::class, 'crm_funnel_id');
    }

    /**
     * @return HasMany<CrmDeal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class)
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');
    }
}
