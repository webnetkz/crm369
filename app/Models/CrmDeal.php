<?php

namespace App\Models;

use Database\Factories\CrmDealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crm_funnel_id
 * @property int $crm_funnel_stage_id
 * @property int|null $responsible_user_id
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property string $title
 * @property string|null $company_name
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property float|null $amount
 * @property string|null $currency
 * @property Carbon|null $expected_close_at
 * @property string|null $description
 * @property array<string, mixed>|null $custom_fields
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'crm_funnel_id',
    'crm_funnel_stage_id',
    'responsible_user_id',
    'created_by_user_id',
    'updated_by_user_id',
    'title',
    'company_name',
    'contact_name',
    'contact_phone',
    'contact_email',
    'amount',
    'currency',
    'expected_close_at',
    'description',
    'custom_fields',
    'sort_order',
])]
class CrmDeal extends Model
{
    /** @use HasFactory<CrmDealFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_close_at' => 'date',
            'custom_fields' => 'array',
            'sort_order' => 'integer',
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
     * @return BelongsTo<CrmFunnelStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmFunnelStage::class, 'crm_funnel_stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
