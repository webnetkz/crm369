<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpsertBusinessProcessRequest;
use App\Models\BusinessProcess;
use App\Support\BusinessProcessCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BusinessProcessController extends Controller
{
    public function index(Request $request, BusinessProcessCatalog $catalog): Response
    {
        $processes = BusinessProcess::query()
            ->with([
                'creator:id,name,last_name,email',
                'updater:id,name,last_name,email',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $activeProcessId = $request->integer('process');
        $activeProcess = $activeProcessId > 0
            ? $processes->firstWhere('id', $activeProcessId)
            : $processes->first();

        return Inertia::render('settings/BusinessProcesses', [
            'summary' => [
                'total' => $processes->count(),
                'active' => $processes->where('is_active', true)->count(),
                'automated' => $processes->where('trigger_type', '!=', BusinessProcess::TRIGGER_TYPE_MANUAL)->count(),
                'codeNodes' => $processes
                    ->sum(fn (BusinessProcess $process): int => collect(data_get($process->definition, 'nodes', []))
                        ->where('type', 'codeTask')
                        ->count()),
            ],
            'processes' => $processes
                ->map(fn (BusinessProcess $process): array => $this->serializeProcessSummary($process))
                ->values()
                ->all(),
            'activeProcess' => $activeProcess ? $this->serializeProcessDetail($activeProcess) : null,
            'catalog' => [
                'triggerTypes' => $catalog->triggerTypes(),
                'triggerEvents' => $catalog->triggerEvents(),
                'nodeTypes' => $catalog->nodeTypes(),
                'apiActions' => $catalog->apiActions(),
                'templates' => $catalog->templates(),
            ],
            'defaults' => [
                'trigger_type' => BusinessProcess::TRIGGER_TYPE_MANUAL,
                'trigger_event' => 'manual.launch',
                'definition' => BusinessProcess::defaultDefinition(),
            ],
        ]);
    }

    public function store(UpsertBusinessProcessRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $process = BusinessProcess::query()->create([
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
            'name' => (string) $request->validated('name'),
            'slug' => $this->uniqueSlug((string) $request->validated('name')),
            'description' => $request->validated('description'),
            'trigger_type' => (string) $request->validated('trigger_type'),
            'trigger_event' => (string) $request->validated('trigger_event'),
            'is_active' => $request->boolean('is_active'),
            'version' => 1,
            'last_published_at' => now(),
            'definition' => $request->definition(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.business_processes.created_success')]);

        return to_route('settings.business-processes.index', ['process' => $process->id]);
    }

    public function update(
        UpsertBusinessProcessRequest $request,
        BusinessProcess $businessProcess,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $businessProcess->update([
            'updated_by_user_id' => $user->id,
            'name' => (string) $request->validated('name'),
            'slug' => $this->uniqueSlug((string) $request->validated('name'), $businessProcess->id),
            'description' => $request->validated('description'),
            'trigger_type' => (string) $request->validated('trigger_type'),
            'trigger_event' => (string) $request->validated('trigger_event'),
            'is_active' => $request->boolean('is_active'),
            'version' => $businessProcess->version + 1,
            'last_published_at' => now(),
            'definition' => $request->definition(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.business_processes.updated_success')]);

        return to_route('settings.business-processes.index', ['process' => $businessProcess->id]);
    }

    public function destroy(BusinessProcess $businessProcess): RedirectResponse
    {
        $businessProcess->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.business_processes.deleted_success')]);

        return to_route('settings.business-processes.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProcessSummary(BusinessProcess $process): array
    {
        return [
            'id' => $process->id,
            'name' => $process->name,
            'slug' => $process->slug,
            'description' => $process->description,
            'trigger_type' => $process->trigger_type,
            'trigger_event' => $process->trigger_event,
            'is_active' => $process->is_active,
            'version' => $process->version,
            'updated_at' => $process->updated_at?->toISOString(),
            'last_published_at' => $process->last_published_at?->toISOString(),
            'nodes_count' => count(data_get($process->definition, 'nodes', [])),
            'edges_count' => count(data_get($process->definition, 'edges', [])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProcessDetail(BusinessProcess $process): array
    {
        return [
            ...$this->serializeProcessSummary($process),
            'definition' => BusinessProcess::normalizeDefinition($process->definition),
            'creator' => $process->creator
                ? [
                    'id' => $process->creator->id,
                    'name' => $process->creator->name,
                    'email' => $process->creator->email,
                ]
                : null,
            'updater' => $process->updater
                ? [
                    'id' => $process->updater->id,
                    'name' => $process->updater->name,
                    'email' => $process->updater->email,
                ]
                : null,
        ];
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug !== '' ? $baseSlug : 'business-process';
        $suffix = 1;

        while (
            BusinessProcess::query()
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $suffix++;
            $slug = ($baseSlug !== '' ? $baseSlug : 'business-process').'-'.$suffix;
        }

        return $slug;
    }
}
