<?php

namespace App\Models;

use Database\Factories\OneCIntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $product
 * @property string $transport
 * @property bool $is_enabled
 * @property string|null $base_url
 * @property string $api_path
 * @property string $auth_type
 * @property string|null $username
 * @property string|null $password
 * @property string|null $token
 * @property bool $verify_tls
 * @property int $connect_timeout_seconds
 * @property int $request_timeout_seconds
 * @property bool $import_enabled
 * @property bool $export_enabled
 * @property bool $schedule_enabled
 * @property int $sync_interval_minutes
 * @property string|null $sync_window_start
 * @property string|null $sync_window_end
 * @property int $batch_size
 * @property string $default_sync_mode
 * @property string $conflict_strategy
 * @property bool $stop_on_error
 * @property bool $dry_run
 * @property array<string, array{enabled: bool, direction: string, source_of_truth: string}>|null $entities
 * @property Carbon|null $enabled_at
 * @property Carbon|null $disabled_at
 * @property Carbon|null $last_tested_at
 * @property bool|null $last_test_succeeded
 * @property int|null $last_test_duration_ms
 * @property string|null $last_test_message
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $last_successful_sync_at
 * @property string|null $last_sync_status
 * @property string|null $last_sync_message
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'product',
    'transport',
    'is_enabled',
    'base_url',
    'api_path',
    'auth_type',
    'username',
    'password',
    'token',
    'verify_tls',
    'connect_timeout_seconds',
    'request_timeout_seconds',
    'import_enabled',
    'export_enabled',
    'schedule_enabled',
    'sync_interval_minutes',
    'sync_window_start',
    'sync_window_end',
    'batch_size',
    'default_sync_mode',
    'conflict_strategy',
    'stop_on_error',
    'dry_run',
    'entities',
    'enabled_at',
    'disabled_at',
    'last_tested_at',
    'last_test_succeeded',
    'last_test_duration_ms',
    'last_test_message',
    'last_sync_at',
    'last_successful_sync_at',
    'last_sync_status',
    'last_sync_message',
    'updated_by_user_id',
])]
#[Hidden(['password', 'token'])]
class OneCIntegration extends Model
{
    /** @use HasFactory<OneCIntegrationFactory> */
    use HasFactory;

    public const string PRODUCT_ERP = 'erp';

    public const string PRODUCT_ENTERPRISE_MANAGEMENT = 'enterprise_management';

    public const string PRODUCT_ACCOUNTING = 'accounting';

    public const string TRANSPORT_ODATA = 'odata';

    public const string TRANSPORT_HTTP_SERVICE = 'http_service';

    public const string AUTH_BASIC = 'basic';

    public const string AUTH_BEARER = 'bearer';

    public const string AUTH_NONE = 'none';

    public const string DIRECTION_IMPORT = 'import';

    public const string DIRECTION_EXPORT = 'export';

    public const string DIRECTION_BIDIRECTIONAL = 'bidirectional';

    public const string SOURCE_ONE_C = 'one_c';

    public const string SOURCE_CRM = 'crm';

    public const string SOURCE_NEWEST = 'newest';

    public const string SYNC_MODE_INCREMENTAL = 'incremental';

    public const string SYNC_MODE_FULL = 'full';

    public const string CONFLICT_ONE_C_WINS = 'one_c_wins';

    public const string CONFLICT_CRM_WINS = 'crm_wins';

    public const string CONFLICT_NEWEST_WINS = 'newest_wins';

    public const string CONFLICT_SKIP = 'skip';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'product' => self::PRODUCT_ENTERPRISE_MANAGEMENT,
        'transport' => self::TRANSPORT_ODATA,
        'is_enabled' => false,
        'api_path' => '/odata/standard.odata',
        'auth_type' => self::AUTH_BASIC,
        'verify_tls' => true,
        'connect_timeout_seconds' => 5,
        'request_timeout_seconds' => 30,
        'import_enabled' => true,
        'export_enabled' => false,
        'schedule_enabled' => false,
        'sync_interval_minutes' => 60,
        'batch_size' => 100,
        'default_sync_mode' => self::SYNC_MODE_INCREMENTAL,
        'conflict_strategy' => self::CONFLICT_ONE_C_WINS,
        'stop_on_error' => true,
        'dry_run' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'password' => 'encrypted',
            'token' => 'encrypted',
            'verify_tls' => 'boolean',
            'connect_timeout_seconds' => 'integer',
            'request_timeout_seconds' => 'integer',
            'import_enabled' => 'boolean',
            'export_enabled' => 'boolean',
            'schedule_enabled' => 'boolean',
            'sync_interval_minutes' => 'integer',
            'batch_size' => 'integer',
            'stop_on_error' => 'boolean',
            'dry_run' => 'boolean',
            'entities' => 'array',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'last_test_succeeded' => 'boolean',
            'last_test_duration_ms' => 'integer',
            'last_sync_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
            'updated_by_user_id' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function products(): array
    {
        return [
            self::PRODUCT_ERP,
            self::PRODUCT_ENTERPRISE_MANAGEMENT,
            self::PRODUCT_ACCOUNTING,
        ];
    }

    /**
     * @return list<string>
     */
    public static function transports(): array
    {
        return [self::TRANSPORT_ODATA, self::TRANSPORT_HTTP_SERVICE];
    }

    /**
     * @return list<string>
     */
    public static function authTypes(): array
    {
        return [self::AUTH_BASIC, self::AUTH_BEARER, self::AUTH_NONE];
    }

    /**
     * @return list<string>
     */
    public static function directions(): array
    {
        return [self::DIRECTION_IMPORT, self::DIRECTION_EXPORT, self::DIRECTION_BIDIRECTIONAL];
    }

    /**
     * @return list<string>
     */
    public static function sourcesOfTruth(): array
    {
        return [self::SOURCE_ONE_C, self::SOURCE_CRM, self::SOURCE_NEWEST];
    }

    /**
     * @return list<string>
     */
    public static function syncModes(): array
    {
        return [self::SYNC_MODE_INCREMENTAL, self::SYNC_MODE_FULL];
    }

    /**
     * @return list<string>
     */
    public static function conflictStrategies(): array
    {
        return [
            self::CONFLICT_ONE_C_WINS,
            self::CONFLICT_CRM_WINS,
            self::CONFLICT_NEWEST_WINS,
            self::CONFLICT_SKIP,
        ];
    }

    /**
     * @return array<string, array{label_key: string, description_key: string, directions: list<string>, default_direction: string}>
     */
    public static function entityDefinitions(): array
    {
        return [
            'counterparties' => [
                'label_key' => 'ui.one_c.entities.counterparties',
                'description_key' => 'ui.one_c.entities.counterparties_description',
                'directions' => self::directions(),
                'default_direction' => self::DIRECTION_IMPORT,
            ],
            'nomenclature' => [
                'label_key' => 'ui.one_c.entities.nomenclature',
                'description_key' => 'ui.one_c.entities.nomenclature_description',
                'directions' => self::directions(),
                'default_direction' => self::DIRECTION_IMPORT,
            ],
            'warehouses' => [
                'label_key' => 'ui.one_c.entities.warehouses',
                'description_key' => 'ui.one_c.entities.warehouses_description',
                'directions' => [self::DIRECTION_IMPORT],
                'default_direction' => self::DIRECTION_IMPORT,
            ],
            'stocks' => [
                'label_key' => 'ui.one_c.entities.stocks',
                'description_key' => 'ui.one_c.entities.stocks_description',
                'directions' => [self::DIRECTION_IMPORT],
                'default_direction' => self::DIRECTION_IMPORT,
            ],
            'orders' => [
                'label_key' => 'ui.one_c.entities.orders',
                'description_key' => 'ui.one_c.entities.orders_description',
                'directions' => self::directions(),
                'default_direction' => self::DIRECTION_BIDIRECTIONAL,
            ],
            'invoices' => [
                'label_key' => 'ui.one_c.entities.invoices',
                'description_key' => 'ui.one_c.entities.invoices_description',
                'directions' => [self::DIRECTION_IMPORT, self::DIRECTION_BIDIRECTIONAL],
                'default_direction' => self::DIRECTION_IMPORT,
            ],
            'payments' => [
                'label_key' => 'ui.one_c.entities.payments',
                'description_key' => 'ui.one_c.entities.payments_description',
                'directions' => [self::DIRECTION_IMPORT],
                'default_direction' => self::DIRECTION_IMPORT,
            ],
            'employees' => [
                'label_key' => 'ui.one_c.entities.employees',
                'description_key' => 'ui.one_c.entities.employees_description',
                'directions' => [self::DIRECTION_IMPORT],
                'default_direction' => self::DIRECTION_IMPORT,
            ],
        ];
    }

    /**
     * @return array<string, array{enabled: bool, direction: string, source_of_truth: string}>
     */
    public static function normalizeEntities(mixed $entities): array
    {
        $requestedEntities = is_array($entities) ? $entities : [];

        return collect(self::entityDefinitions())
            ->mapWithKeys(function (array $definition, string $key) use ($requestedEntities): array {
                $requested = is_array($requestedEntities[$key] ?? null)
                    ? $requestedEntities[$key]
                    : [];
                $direction = is_string($requested['direction'] ?? null)
                    ? $requested['direction']
                    : $definition['default_direction'];
                $sourceOfTruth = is_string($requested['source_of_truth'] ?? null)
                    ? $requested['source_of_truth']
                    : self::SOURCE_ONE_C;

                return [$key => [
                    'enabled' => filter_var($requested['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                    'direction' => in_array($direction, $definition['directions'], true)
                        ? $direction
                        : $definition['default_direction'],
                    'source_of_truth' => in_array($sourceOfTruth, self::sourcesOfTruth(), true)
                        ? $sourceOfTruth
                        : self::SOURCE_ONE_C,
                ]];
            })
            ->all();
    }

    /**
     * @return array<string, array{enabled: bool, direction: string, source_of_truth: string}>
     */
    public function normalizedEntities(): array
    {
        return self::normalizeEntities($this->entities);
    }

    public function testUrl(): ?string
    {
        if (! is_string($this->base_url) || trim($this->base_url) === '') {
            return null;
        }

        $baseUrl = Str::of($this->base_url)->trim()->rtrim('/');
        $path = Str::of($this->api_path)->trim()->trim('/');
        $url = $path->isEmpty() ? $baseUrl : $baseUrl->append('/')->append($path);

        if ($this->transport === self::TRANSPORT_ODATA && ! $url->endsWith('$metadata')) {
            $url = $url->rtrim('/')->append('/$metadata');
        }

        return $url->toString();
    }

    public function hasRequiredCredentials(): bool
    {
        return match ($this->auth_type) {
            self::AUTH_BASIC => is_string($this->username)
                && trim($this->username) !== ''
                && is_string($this->password)
                && $this->password !== '',
            self::AUTH_BEARER => is_string($this->token) && $this->token !== '',
            self::AUTH_NONE => true,
            default => false,
        };
    }

    public function hasEnabledEntity(): bool
    {
        return collect($this->normalizedEntities())->contains(
            fn (array $entity): bool => $entity['enabled'],
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
