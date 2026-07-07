<?php

namespace App\Models;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Database\Factories\EquipmentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $qr_code
 * @property string $status
 * @property int|null $issued_to_user_id
 * @property int|null $responsible_user_id
 * @property int $created_by_user_id
 * @property int $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'qr_code',
    'status',
    'issued_to_user_id',
    'responsible_user_id',
    'created_by_user_id',
    'updated_by_user_id',
])]
class EquipmentItem extends Model
{
    private const int QR_CODE_SIZE = 192;

    private const int QR_CODE_MARGIN = 4;

    private const int QR_BRAND_WIDTH = 84;

    private const int QR_BRAND_HEIGHT = 28;

    private const int QR_BRAND_RADIUS = 8;

    private const int QR_BRAND_FONT_SIZE = 15;

    public const string STATUS_ON_BALANCE = 'on_balance';

    public const string STATUS_ISSUED = 'issued';

    public const string STATUS_MAINTENANCE = 'maintenance';

    public const string STATUS_REPAIR = 'repair';

    public const string STATUS_WRITTEN_OFF = 'written_off';

    /** @use HasFactory<EquipmentItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_to_user_id' => 'integer',
            'responsible_user_id' => 'integer',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }

    /**
     * @return array<string, array{label_key: string, description_key: string}>
     */
    public static function statusDefinitions(): array
    {
        return [
            self::STATUS_ON_BALANCE => [
                'label_key' => 'ui.equipment.statuses.on_balance',
                'description_key' => 'ui.equipment.status_descriptions.on_balance',
            ],
            self::STATUS_ISSUED => [
                'label_key' => 'ui.equipment.statuses.issued',
                'description_key' => 'ui.equipment.status_descriptions.issued',
            ],
            self::STATUS_MAINTENANCE => [
                'label_key' => 'ui.equipment.statuses.maintenance',
                'description_key' => 'ui.equipment.status_descriptions.maintenance',
            ],
            self::STATUS_REPAIR => [
                'label_key' => 'ui.equipment.statuses.repair',
                'description_key' => 'ui.equipment.status_descriptions.repair',
            ],
            self::STATUS_WRITTEN_OFF => [
                'label_key' => 'ui.equipment.statuses.written_off',
                'description_key' => 'ui.equipment.status_descriptions.written_off',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableStatuses(): array
    {
        return array_keys(self::statusDefinitions());
    }

    public static function generateQrCode(): string
    {
        return 'EQ-'.mb_strtoupper((string) Str::ulid());
    }

    public function qrCodeSvg(): string
    {
        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(
                    self::QR_CODE_SIZE,
                    self::QR_CODE_MARGIN,
                    null,
                    null,
                    Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(15, 23, 42)),
                ),
                new SvgImageBackEnd
            )
        ))->writeString($this->qr_code, ecLevel: ErrorCorrectionLevel::H());

        return $this->injectBrandLabel(trim(substr($svg, strpos($svg, "\n") + 1)));
    }

    public function qrCodeSvgDataUri(): string
    {
        return 'data:image/svg+xml;utf8,'.rawurlencode($this->qrCodeSvg());
    }

    private function injectBrandLabel(string $svg): string
    {
        $brandX = (self::QR_CODE_SIZE - self::QR_BRAND_WIDTH) / 2;
        $brandY = (self::QR_CODE_SIZE - self::QR_BRAND_HEIGHT) / 2;
        $textX = self::QR_CODE_SIZE / 2;
        $textY = ($brandY + (self::QR_BRAND_HEIGHT / 2)) + 5;

        $overlay = sprintf(
            '<g aria-label="CRM369 mark">'
                .'<rect x="%d" y="%d" width="%d" height="%d" rx="%d" fill="#ffffff" stroke="#0f172a" stroke-width="1.5" />'
                .'<text x="%d" y="%d" text-anchor="middle" font-family="Instrument Sans, Arial, sans-serif" font-size="%d" font-weight="700" letter-spacing="0.8" fill="#0f172a">CRM369</text>'
            .'</g>',
            $brandX,
            $brandY,
            self::QR_BRAND_WIDTH,
            self::QR_BRAND_HEIGHT,
            self::QR_BRAND_RADIUS,
            $textX,
            $textY,
            self::QR_BRAND_FONT_SIZE,
        );

        return str_replace('</svg>', $overlay.'</svg>', $svg);
    }

    /**
     * @param  Builder<EquipmentItem>  $query
     * @return Builder<EquipmentItem>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc('updated_at')
            ->orderBy('name')
            ->orderByDesc('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
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
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
