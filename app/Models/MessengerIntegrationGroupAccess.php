<?php

namespace App\Models;

use Database\Factories\MessengerIntegrationGroupAccessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $messenger_integration_id
 * @property int $user_group_id
 * @property string $access_level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['messenger_integration_id', 'user_group_id', 'access_level'])]
class MessengerIntegrationGroupAccess extends Model
{
    /** @use HasFactory<MessengerIntegrationGroupAccessFactory> */
    use HasFactory;

    /**
     * @return array<int, string>
     */
    public static function assignableAccessLevels(): array
    {
        return [
            MessengerIntegration::ACCESS_NONE,
            MessengerIntegration::ACCESS_VIEW,
            MessengerIntegration::ACCESS_REPLY,
        ];
    }

    /**
     * @return array<string, array{label_key: string, description_key: string}>
     */
    public static function accessDefinitions(): array
    {
        return [
            MessengerIntegration::ACCESS_NONE => [
                'label_key' => 'ui.integrations.access_none',
                'description_key' => 'ui.integrations.access_none_description',
            ],
            MessengerIntegration::ACCESS_VIEW => [
                'label_key' => 'ui.integrations.access_view',
                'description_key' => 'ui.integrations.access_view_description',
            ],
            MessengerIntegration::ACCESS_REPLY => [
                'label_key' => 'ui.integrations.access_reply',
                'description_key' => 'ui.integrations.access_reply_description',
            ],
        ];
    }

    /**
     * @return BelongsTo<MessengerIntegration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(MessengerIntegration::class, 'messenger_integration_id');
    }

    /**
     * @return BelongsTo<UserGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }
}
