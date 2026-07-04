<?php

namespace App\Models;

use Database\Factories\BusinessProcessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $trigger_type
 * @property string $trigger_event
 * @property bool $is_active
 * @property int $version
 * @property Carbon|null $last_published_at
 * @property array{
 *     viewport: array{width: int, height: int},
 *     lanes: array<int, array{id: string, title: string, color: string}>,
 *     nodes: array<int, array{
 *         id: string,
 *         type: string,
 *         lane_id: string,
 *         label: string,
 *         description: string|null,
 *         x: int,
 *         y: int,
 *         config: array<string, mixed>
 *     }>,
 *     edges: array<int, array{id: string, source: string, target: string, label: string|null, condition: string|null}>
 * } $definition
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'created_by_user_id',
    'updated_by_user_id',
    'name',
    'slug',
    'description',
    'trigger_type',
    'trigger_event',
    'is_active',
    'version',
    'last_published_at',
    'definition',
])]
class BusinessProcess extends Model
{
    public const string TRIGGER_TYPE_MANUAL = 'manual';

    public const string TRIGGER_TYPE_DOMAIN_EVENT = 'domain_event';

    public const string TRIGGER_TYPE_API_EVENT = 'api_event';

    public const string TRIGGER_TYPE_SCHEDULE = 'schedule';

    /** @use HasFactory<BusinessProcessFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
            'last_published_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableTriggerTypes(): array
    {
        return [
            self::TRIGGER_TYPE_MANUAL,
            self::TRIGGER_TYPE_DOMAIN_EVENT,
            self::TRIGGER_TYPE_API_EVENT,
            self::TRIGGER_TYPE_SCHEDULE,
        ];
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

    /**
     * @return array{
     *     viewport: array{width: int, height: int},
     *     lanes: array<int, array{id: string, title: string, color: string}>,
     *     nodes: array<int, array{
     *         id: string,
     *         type: string,
     *         lane_id: string,
     *         label: string,
     *         description: string|null,
     *         x: int,
     *         y: int,
     *         config: array<string, mixed>
     *     }>,
     *     edges: array<int, array{id: string, source: string, target: string, label: string|null, condition: string|null}>
     * }
     */
    public static function defaultDefinition(): array
    {
        return [
            'viewport' => [
                'width' => 1200,
                'height' => 680,
            ],
            'lanes' => [
                [
                    'id' => 'intake',
                    'title' => __('ui.business_processes.default_lane_intake'),
                    'color' => '#DDEAFE',
                ],
                [
                    'id' => 'processing',
                    'title' => __('ui.business_processes.default_lane_processing'),
                    'color' => '#E5F8EA',
                ],
                [
                    'id' => 'delivery',
                    'title' => __('ui.business_processes.default_lane_delivery'),
                    'color' => '#FFF1D6',
                ],
            ],
            'nodes' => [
                [
                    'id' => 'node_start',
                    'type' => 'startEvent',
                    'lane_id' => 'intake',
                    'label' => __('ui.business_processes.default_node_start'),
                    'description' => null,
                    'x' => 80,
                    'y' => 110,
                    'config' => [],
                ],
                [
                    'id' => 'node_end',
                    'type' => 'endEvent',
                    'lane_id' => 'delivery',
                    'label' => __('ui.business_processes.default_node_end'),
                    'description' => null,
                    'x' => 900,
                    'y' => 110,
                    'config' => [],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge_start_end',
                    'source' => 'node_start',
                    'target' => 'node_end',
                    'label' => null,
                    'condition' => null,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $definition
     * @return array{
     *     viewport: array{width: int, height: int},
     *     lanes: array<int, array{id: string, title: string, color: string}>,
     *     nodes: array<int, array{
     *         id: string,
     *         type: string,
     *         lane_id: string,
     *         label: string,
     *         description: string|null,
     *         x: int,
     *         y: int,
     *         config: array<string, mixed>
     *     }>,
     *     edges: array<int, array{id: string, source: string, target: string, label: string|null, condition: string|null}>
     * }
     */
    public static function normalizeDefinition(?array $definition): array
    {
        $defaults = self::defaultDefinition();
        $viewport = is_array($definition['viewport'] ?? null) ? $definition['viewport'] : [];
        $lanes = is_array($definition['lanes'] ?? null) ? $definition['lanes'] : $defaults['lanes'];
        $nodes = is_array($definition['nodes'] ?? null) ? $definition['nodes'] : $defaults['nodes'];
        $edges = is_array($definition['edges'] ?? null) ? $definition['edges'] : $defaults['edges'];

        return [
            'viewport' => [
                'width' => max(900, (int) ($viewport['width'] ?? $defaults['viewport']['width'])),
                'height' => max(540, (int) ($viewport['height'] ?? $defaults['viewport']['height'])),
            ],
            'lanes' => collect($lanes)
                ->filter(fn (mixed $lane): bool => is_array($lane))
                ->map(function (array $lane, int $index): array {
                    $id = trim((string) ($lane['id'] ?? 'lane_'.$index));
                    $title = trim((string) ($lane['title'] ?? ''));

                    return [
                        'id' => $id !== '' ? $id : 'lane_'.$index,
                        'title' => $title !== '' ? $title : __('ui.business_processes.default_lane_processing'),
                        'color' => self::normalizeColor($lane['color'] ?? null, '#E2E8F0'),
                    ];
                })
                ->unique('id')
                ->values()
                ->all(),
            'nodes' => collect($nodes)
                ->filter(fn (mixed $node): bool => is_array($node))
                ->map(function (array $node, int $index): array {
                    $id = trim((string) ($node['id'] ?? 'node_'.$index));
                    $config = is_array($node['config'] ?? null) ? $node['config'] : [];

                    return [
                        'id' => $id !== '' ? $id : 'node_'.$index,
                        'type' => trim((string) ($node['type'] ?? 'task')),
                        'lane_id' => trim((string) ($node['lane_id'] ?? 'processing')),
                        'label' => trim((string) ($node['label'] ?? '')) ?: __('ui.business_processes.default_node_task'),
                        'description' => self::normalizeNullableString($node['description'] ?? null),
                        'x' => max(0, (int) ($node['x'] ?? 0)),
                        'y' => max(0, (int) ($node['y'] ?? 0)),
                        'config' => [
                            'code' => self::normalizeNullableString($config['code'] ?? null),
                            'action_key' => self::normalizeNullableString($config['action_key'] ?? null),
                            'condition_expression' => self::normalizeNullableString($config['condition_expression'] ?? null),
                            'notes' => self::normalizeNullableString($config['notes'] ?? null),
                            'input_mapping' => self::normalizeNullableString($config['input_mapping'] ?? null),
                            'output_mapping' => self::normalizeNullableString($config['output_mapping'] ?? null),
                            'retry_limit' => max(0, (int) ($config['retry_limit'] ?? 0)),
                            'timeout_seconds' => max(5, (int) ($config['timeout_seconds'] ?? 30)),
                        ],
                    ];
                })
                ->unique('id')
                ->values()
                ->all(),
            'edges' => collect($edges)
                ->filter(fn (mixed $edge): bool => is_array($edge))
                ->map(function (array $edge, int $index): array {
                    $id = trim((string) ($edge['id'] ?? 'edge_'.$index));

                    return [
                        'id' => $id !== '' ? $id : 'edge_'.$index,
                        'source' => trim((string) ($edge['source'] ?? '')),
                        'target' => trim((string) ($edge['target'] ?? '')),
                        'label' => self::normalizeNullableString($edge['label'] ?? null),
                        'condition' => self::normalizeNullableString($edge['condition'] ?? null),
                    ];
                })
                ->filter(fn (array $edge): bool => $edge['source'] !== '' && $edge['target'] !== '')
                ->unique('id')
                ->values()
                ->all(),
        ];
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));

        return $normalized !== '' ? $normalized : null;
    }

    private static function normalizeColor(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $value = trim($value);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? $value : $fallback;
    }
}
