<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Contact;
use App\Models\CrmDeal;
use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use App\Models\PortalForm;
use App\Models\PortalFormSubmission;
use App\Models\PortalSetting;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use App\Models\User;
use App\Support\DashboardConfiguration;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** @var list<string>|null */
    private ?array $tableNames = null;

    public function __invoke(
        Request $request,
        DashboardConfiguration $dashboardConfiguration,
    ): Response {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $enabledModules = PortalSetting::current()->enabledModules();
        $configuration = $dashboardConfiguration->normalize($user->dashboard_configuration);
        $activeDashboard = $dashboardConfiguration->activeDashboard($configuration);

        return Inertia::render('Dashboard', [
            'dashboardStats' => $this->buildDashboardStats($user, $enabledModules, $activeDashboard['period']),
            'dashboardConfiguration' => $configuration,
        ]);
    }

    /**
     * @param  array<int, string>  $enabledModules
     * @return array<string, mixed>
     */
    private function buildDashboardStats(User $user, array $enabledModules, int $period): array
    {
        $counts = $this->buildCounts($user, $enabledModules);
        $activity = $this->buildActivity($user, $enabledModules, $period);

        return [
            'eyebrow' => __('ui.dashboard.eyebrow'),
            'subtitle' => __('ui.dashboard.subtitle', ['count' => count($enabledModules)]),
            'cards' => $this->buildCards($user, $enabledModules, $counts),
            'donuts' => $this->buildDonuts($enabledModules, $counts),
            'activity' => $activity,
            'bars' => $this->buildModuleBars($enabledModules, $counts),
            'radar' => $this->buildRadar($enabledModules, $counts),
            'highlights' => $this->buildHighlights($enabledModules, $counts, $activity),
        ];
    }

    /**
     * @param  array<int, string>  $enabledModules
     * @return array<string, mixed>
     */
    private function buildCounts(User $user, array $enabledModules): array
    {
        $completedTaskStatuses = ProjectTaskStage::completedSlugs();
        $counts = [
            'users_total' => 0,
            'users_active' => 0,
            'projects_total' => 0,
            'projects_archived' => 0,
            'tasks_total' => 0,
            'tasks_completed' => 0,
            'tasks_overdue' => 0,
            'contacts_total' => 0,
            'contacts_person' => 0,
            'contacts_company' => 0,
            'forms_total' => 0,
            'forms_active' => 0,
            'submissions_total' => 0,
            'conversations_total' => 0,
            'messages_total' => 0,
            'deals_total' => 0,
            'deals_open' => 0,
            'deals_won' => 0,
            'deals_lost' => 0,
            'task_status_counts' => [],
        ];

        if ($user->canViewUsers() && $this->hasTable('users')) {
            $counts['users_total'] = User::query()->count();
            $counts['users_active'] = User::query()->where('is_active', true)->count();
        }

        if (in_array('projects', $enabledModules, true) && $this->hasTable('projects')) {
            $projectsQuery = Project::query()->visibleTo($user);

            $counts['projects_total'] = (clone $projectsQuery)->count();
            $counts['projects_archived'] = (clone $projectsQuery)
                ->where('is_archived', true)
                ->count();
        }

        if (in_array('projects', $enabledModules, true) && $this->hasTable('project_tasks')) {
            $tasksQuery = ProjectTask::query()->visibleTo($user);

            $counts['tasks_total'] = (clone $tasksQuery)->count();
            $counts['tasks_completed'] = (clone $tasksQuery)
                ->whereIn('status', $completedTaskStatuses)
                ->count();
            $counts['tasks_overdue'] = (clone $tasksQuery)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereNotIn('status', $completedTaskStatuses)
                ->count();
            $counts['task_status_counts'] = (clone $tasksQuery)
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn (mixed $value): int => (int) $value)
                ->all();
        }

        if (in_array('contacts', $enabledModules, true) && $user->canAccessContacts() && $this->hasTable('contacts')) {
            $contactsQuery = Contact::query()->visibleTo($user);

            $counts['contacts_total'] = (clone $contactsQuery)->count();
            $counts['contacts_person'] = (clone $contactsQuery)
                ->where('type', Contact::TYPE_PERSON)
                ->count();
            $counts['contacts_company'] = (clone $contactsQuery)
                ->where('type', Contact::TYPE_COMPANY)
                ->count();
        }

        if (in_array('forms', $enabledModules, true) && $this->hasTable('portal_forms')) {
            $counts['forms_total'] = PortalForm::query()->count();
            $counts['forms_active'] = PortalForm::query()->where('is_active', true)->count();
        }

        if (in_array('forms', $enabledModules, true) && $this->hasTable('portal_form_submissions')) {
            $counts['submissions_total'] = PortalFormSubmission::query()->count();
        }

        if (in_array('chats', $enabledModules, true) && $this->hasTable('chat_conversations')) {
            $counts['conversations_total'] = $this->accessibleConversationQuery($user)->count();
        }

        if (in_array('chats', $enabledModules, true) && $this->hasTable('chat_messages')) {
            $counts['messages_total'] = $this->accessibleMessageQuery($user)->count();
        }

        if (
            in_array('funnels', $enabledModules, true)
            && $user->canAccessFunnels()
            && $this->hasTable('crm_deals')
            && $this->hasTable('crm_funnel_stages')
        ) {
            $funnelIds = CrmFunnel::query()
                ->visibleTo($user)
                ->pluck('id');

            if ($funnelIds->isNotEmpty()) {
                $counts['deals_total'] = CrmDeal::query()
                    ->whereIn('crm_funnel_id', $funnelIds)
                    ->count();

                $dealsByStageType = DB::table('crm_deals')
                    ->join('crm_funnel_stages', 'crm_funnel_stages.id', '=', 'crm_deals.crm_funnel_stage_id')
                    ->whereIn('crm_deals.crm_funnel_id', $funnelIds->all())
                    ->selectRaw('crm_funnel_stages.type as type, count(*) as aggregate')
                    ->groupBy('crm_funnel_stages.type')
                    ->pluck('aggregate', 'type');

                $counts['deals_open'] = (int) ($dealsByStageType[CrmFunnelStage::TYPE_OPEN] ?? 0);
                $counts['deals_won'] = (int) ($dealsByStageType[CrmFunnelStage::TYPE_WON] ?? 0);
                $counts['deals_lost'] = (int) ($dealsByStageType[CrmFunnelStage::TYPE_LOST] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * @param  array<int, string>  $enabledModules
     * @param  array<string, mixed>  $counts
     * @return array<int, array{title: string, value: string, helper: string, icon: string}>
     */
    private function buildCards(User $user, array $enabledModules, array $counts): array
    {
        $cards = [];

        if ($user->canViewUsers() && $this->hasTable('users')) {
            $cards[] = [
                'title' => __('ui.dashboard.cards.team'),
                'value' => Number::format($counts['users_total']),
                'helper' => __('ui.dashboard.cards.team_hint', [
                    'active' => Number::format($counts['users_active']),
                    'inactive' => Number::format(max(0, $counts['users_total'] - $counts['users_active'])),
                ]),
                'icon' => 'users',
            ];
        }

        if (in_array('projects', $enabledModules, true) && $this->hasTable('projects')) {
            $cards[] = [
                'title' => __('ui.dashboard.cards.projects'),
                'value' => Number::format($counts['projects_total']),
                'helper' => __('ui.dashboard.cards.projects_hint', [
                    'active' => Number::format(max(0, $counts['projects_total'] - $counts['projects_archived'])),
                    'archived' => Number::format($counts['projects_archived']),
                ]),
                'icon' => 'folder',
            ];
        }

        if (in_array('projects', $enabledModules, true) && $this->hasTable('project_tasks')) {
            $cards[] = [
                'title' => __('ui.dashboard.cards.tasks'),
                'value' => Number::format($counts['tasks_total']),
                'helper' => __('ui.dashboard.cards.tasks_hint', [
                    'completed' => Number::format($counts['tasks_completed']),
                    'overdue' => Number::format($counts['tasks_overdue']),
                ]),
                'icon' => 'clipboard',
            ];
        }

        if (in_array('chats', $enabledModules, true) && $this->hasTable('chat_messages')) {
            $cards[] = [
                'title' => __('ui.dashboard.cards.chat'),
                'value' => Number::format($counts['messages_total']),
                'helper' => __('ui.dashboard.cards.chat_hint', [
                    'conversations' => Number::format($counts['conversations_total']),
                    'messages' => Number::format($counts['messages_total']),
                ]),
                'icon' => 'messages',
            ];
        }

        if (in_array('forms', $enabledModules, true) && $this->hasTable('portal_forms')) {
            $cards[] = [
                'title' => __('ui.dashboard.cards.forms'),
                'value' => Number::format($counts['submissions_total']),
                'helper' => __('ui.dashboard.cards.forms_hint', [
                    'active' => Number::format($counts['forms_active']),
                    'submissions' => Number::format($counts['submissions_total']),
                ]),
                'icon' => 'layout',
            ];
        }

        if (in_array('contacts', $enabledModules, true) && $user->canAccessContacts() && $this->hasTable('contacts')) {
            $cards[] = [
                'title' => __('ui.dashboard.cards.contacts'),
                'value' => Number::format($counts['contacts_total']),
                'helper' => __('ui.dashboard.cards.contacts_hint', [
                    'people' => Number::format($counts['contacts_person']),
                    'companies' => Number::format($counts['contacts_company']),
                ]),
                'icon' => 'contact',
            ];
        }

        if (
            in_array('funnels', $enabledModules, true)
            && $user->canAccessFunnels()
            && $this->hasTable('crm_deals')
            && $this->hasTable('crm_funnel_stages')
        ) {
            $cards[] = [
                'title' => __('ui.dashboard.cards.deals'),
                'value' => Number::format($counts['deals_total']),
                'helper' => __('ui.dashboard.cards.deals_hint', [
                    'open' => Number::format($counts['deals_open']),
                    'won' => Number::format($counts['deals_won']),
                ]),
                'icon' => 'currency',
            ];
        }

        return $cards;
    }

    /**
     * @param  array<int, string>  $enabledModules
     * @param  array<string, mixed>  $counts
     * @return array<int, array<string, mixed>>
     */
    private function buildDonuts(array $enabledModules, array $counts): array
    {
        $donuts = [];

        if (in_array('projects', $enabledModules, true) && $this->hasTable('project_tasks')) {
            $segments = collect($this->taskStageDefinitions())
                ->map(fn (array $stage, int $index): array => [
                    'label' => $stage['label'],
                    'value' => (int) ($counts['task_status_counts'][$stage['key']] ?? 0),
                    'color' => $this->chartColor($index),
                ])
                ->values()
                ->all();

            $donuts[] = [
                'title' => __('ui.dashboard.donuts.tasks.title'),
                'subtitle' => __('ui.dashboard.donuts.tasks.subtitle'),
                'total' => $counts['tasks_total'],
                'totalLabel' => __('ui.dashboard.donuts.tasks.total_label'),
                'highlight' => Number::format($counts['tasks_completed']),
                'highlightLabel' => __('ui.dashboard.donuts.tasks.highlight_label'),
                'segments' => $segments,
            ];
        }

        if (in_array('projects', $enabledModules, true) && $this->hasTable('projects')) {
            $donuts[] = [
                'title' => __('ui.dashboard.donuts.projects.title'),
                'subtitle' => __('ui.dashboard.donuts.projects.subtitle'),
                'total' => $counts['projects_total'],
                'totalLabel' => __('ui.dashboard.donuts.projects.total_label'),
                'highlight' => Number::format(max(0, $counts['projects_total'] - $counts['projects_archived'])),
                'highlightLabel' => __('ui.dashboard.donuts.projects.highlight_label'),
                'segments' => [
                    [
                        'label' => __('ui.dashboard.segments.active'),
                        'value' => max(0, $counts['projects_total'] - $counts['projects_archived']),
                        'color' => $this->chartColor(1),
                    ],
                    [
                        'label' => __('ui.dashboard.segments.archived'),
                        'value' => $counts['projects_archived'],
                        'color' => $this->chartColor(4),
                    ],
                ],
            ];
        }

        if (in_array('forms', $enabledModules, true) && $this->hasTable('portal_forms')) {
            $donuts[] = [
                'title' => __('ui.dashboard.donuts.forms.title'),
                'subtitle' => __('ui.dashboard.donuts.forms.subtitle'),
                'total' => $counts['forms_total'],
                'totalLabel' => __('ui.dashboard.donuts.forms.total_label'),
                'highlight' => Number::format($counts['forms_active']),
                'highlightLabel' => __('ui.dashboard.donuts.forms.highlight_label'),
                'segments' => [
                    [
                        'label' => __('ui.dashboard.segments.active'),
                        'value' => $counts['forms_active'],
                        'color' => $this->chartColor(0),
                    ],
                    [
                        'label' => __('ui.dashboard.segments.inactive'),
                        'value' => max(0, $counts['forms_total'] - $counts['forms_active']),
                        'color' => $this->chartColor(3),
                    ],
                ],
            ];
        }

        if (in_array('contacts', $enabledModules, true) && $this->hasTable('contacts') && ($counts['contacts_total'] > 0 || $counts['contacts_person'] > 0 || $counts['contacts_company'] > 0)) {
            $donuts[] = [
                'title' => __('ui.dashboard.donuts.contacts.title'),
                'subtitle' => __('ui.dashboard.donuts.contacts.subtitle'),
                'total' => $counts['contacts_total'],
                'totalLabel' => __('ui.dashboard.donuts.contacts.total_label'),
                'highlight' => Number::format($counts['contacts_company']),
                'highlightLabel' => __('ui.dashboard.donuts.contacts.highlight_label'),
                'segments' => [
                    [
                        'label' => __('ui.dashboard.segments.people'),
                        'value' => $counts['contacts_person'],
                        'color' => $this->chartColor(2),
                    ],
                    [
                        'label' => __('ui.dashboard.segments.companies'),
                        'value' => $counts['contacts_company'],
                        'color' => $this->chartColor(4),
                    ],
                ],
            ];
        }

        if (in_array('funnels', $enabledModules, true) && $this->hasTable('crm_deals') && $counts['deals_total'] > 0) {
            $donuts[] = [
                'title' => __('ui.dashboard.donuts.deals.title'),
                'subtitle' => __('ui.dashboard.donuts.deals.subtitle'),
                'total' => $counts['deals_total'],
                'totalLabel' => __('ui.dashboard.donuts.deals.total_label'),
                'highlight' => Number::format($counts['deals_won']),
                'highlightLabel' => __('ui.dashboard.donuts.deals.highlight_label'),
                'segments' => [
                    [
                        'label' => __('ui.dashboard.segments.open'),
                        'value' => $counts['deals_open'],
                        'color' => $this->chartColor(0),
                    ],
                    [
                        'label' => __('ui.dashboard.segments.won'),
                        'value' => $counts['deals_won'],
                        'color' => $this->chartColor(1),
                    ],
                    [
                        'label' => __('ui.dashboard.segments.lost'),
                        'value' => $counts['deals_lost'],
                        'color' => $this->chartColor(4),
                    ],
                ],
            ];
        }

        return $donuts;
    }

    /**
     * @param  array<int, string>  $enabledModules
     * @return array<string, mixed>
     */
    private function buildActivity(User $user, array $enabledModules, int $period): array
    {
        $start = now()->startOfDay()->subDays($period - 1);
        $end = now()->endOfDay();
        $labels = collect(range(0, $period - 1))
            ->map(fn (int $offset): string => $start->copy()->addDays($offset)->translatedFormat('d M'))
            ->all();
        $series = [];

        if (in_array('projects', $enabledModules, true) && $this->hasTable('project_tasks')) {
            $series[] = [
                'label' => __('ui.dashboard.activity.tasks'),
                'values' => $this->dailySeries(ProjectTask::query()->visibleTo($user), $start, $end),
                'color' => $this->chartColor(0),
            ];
        }

        if (in_array('chats', $enabledModules, true) && $this->hasTable('chat_messages')) {
            $series[] = [
                'label' => __('ui.dashboard.activity.messages'),
                'values' => $this->dailySeries($this->accessibleMessageQuery($user), $start, $end),
                'color' => $this->chartColor(1),
            ];
        }

        if (in_array('forms', $enabledModules, true) && $this->hasTable('portal_form_submissions')) {
            $series[] = [
                'label' => __('ui.dashboard.activity.submissions'),
                'values' => $this->dailySeries(PortalFormSubmission::query(), $start, $end),
                'color' => $this->chartColor(3),
            ];
        }

        if (in_array('contacts', $enabledModules, true) && $user->canAccessContacts() && $this->hasTable('contacts')) {
            $series[] = [
                'label' => __('ui.dashboard.activity.contacts'),
                'values' => $this->dailySeries(Contact::query()->visibleTo($user), $start, $end),
                'color' => $this->chartColor(4),
            ];
        }

        return [
            'title' => __('ui.dashboard.activity.title', ['days' => $period]),
            'subtitle' => __('ui.dashboard.activity.subtitle', ['days' => $period]),
            'labels' => $labels,
            'series' => $series,
        ];
    }

    /**
     * @param  array<int, string>  $enabledModules
     * @param  array<string, mixed>  $counts
     * @return array<string, mixed>
     */
    private function buildModuleBars(array $enabledModules, array $counts): array
    {
        $items = collect([
            in_array('projects', $enabledModules, true) ? [
                'label' => __('ui.projects.title'),
                'value' => $counts['projects_total'],
            ] : null,
            in_array('projects', $enabledModules, true) ? [
                'label' => __('ui.dashboard.activity.tasks'),
                'value' => $counts['tasks_total'],
            ] : null,
            in_array('chats', $enabledModules, true) ? [
                'label' => __('ui.dashboard.activity.messages'),
                'value' => $counts['messages_total'],
            ] : null,
            in_array('forms', $enabledModules, true) ? [
                'label' => __('ui.forms.title'),
                'value' => $counts['submissions_total'],
            ] : null,
            in_array('contacts', $enabledModules, true) ? [
                'label' => __('ui.contacts.title'),
                'value' => $counts['contacts_total'],
            ] : null,
            in_array('funnels', $enabledModules, true) ? [
                'label' => __('ui.funnels.title'),
                'value' => $counts['deals_total'],
            ] : null,
        ])
            ->filter(fn (?array $item): bool => $item !== null)
            ->values()
            ->map(fn (array $item, int $index): array => [
                ...$item,
                'color' => $this->chartColor($index),
            ])
            ->all();

        return [
            'title' => __('ui.dashboard.bars.title'),
            'subtitle' => __('ui.dashboard.bars.subtitle'),
            'items' => $items,
        ];
    }

    /**
     * @param  array<int, string>  $enabledModules
     * @param  array<string, mixed>  $counts
     * @return array<string, mixed>
     */
    private function buildRadar(array $enabledModules, array $counts): array
    {
        $rawItems = collect([
            in_array('projects', $enabledModules, true) ? [
                'label' => __('ui.dashboard.radar.delivery'),
                'raw' => ($counts['projects_total'] * 2) + $counts['tasks_total'],
                'helper' => __('ui.dashboard.radar.delivery_helper', ['tasks' => Number::format($counts['tasks_total'])]),
            ] : null,
            in_array('chats', $enabledModules, true) ? [
                'label' => __('ui.dashboard.radar.collaboration'),
                'raw' => ($counts['conversations_total'] * 2) + $counts['messages_total'],
                'helper' => __('ui.dashboard.radar.collaboration_helper', ['messages' => Number::format($counts['messages_total'])]),
            ] : null,
            in_array('forms', $enabledModules, true) ? [
                'label' => __('ui.dashboard.radar.automation'),
                'raw' => ($counts['forms_active'] * 2) + $counts['submissions_total'],
                'helper' => __('ui.dashboard.radar.automation_helper', ['forms' => Number::format($counts['forms_active'])]),
            ] : null,
            in_array('contacts', $enabledModules, true) && $counts['contacts_total'] > 0 ? [
                'label' => __('ui.dashboard.radar.relationships'),
                'raw' => ($counts['contacts_company'] * 2) + $counts['contacts_total'],
                'helper' => __('ui.dashboard.radar.relationships_helper', ['contacts' => Number::format($counts['contacts_total'])]),
            ] : null,
            in_array('funnels', $enabledModules, true) && $counts['deals_total'] > 0 ? [
                'label' => __('ui.dashboard.radar.sales'),
                'raw' => ($counts['deals_open'] * 2) + ($counts['deals_won'] * 3) + $counts['deals_total'],
                'helper' => __('ui.dashboard.radar.sales_helper', ['deals' => Number::format($counts['deals_total'])]),
            ] : null,
            [
                'label' => __('ui.dashboard.radar.coverage'),
                'raw' => count($enabledModules) * 10,
                'helper' => __('ui.dashboard.radar.coverage_helper', ['modules' => Number::format(count($enabledModules))]),
            ],
        ])
            ->filter(fn (?array $item): bool => $item !== null)
            ->values();

        $maxValue = max(1, (int) $rawItems->max('raw'));

        return [
            'title' => __('ui.dashboard.radar.title'),
            'subtitle' => __('ui.dashboard.radar.subtitle'),
            'items' => $rawItems
                ->map(fn (array $item): array => [
                    'label' => $item['label'],
                    'value' => $item['raw'] > 0 ? max(18, (int) round(($item['raw'] / $maxValue) * 100)) : 0,
                    'helper' => $item['helper'],
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<int, string>  $enabledModules
     * @param  array<string, mixed>  $counts
     * @param  array<string, mixed>  $activity
     * @return array<int, array{label: string, value: string, helper: string}>
     */
    private function buildHighlights(array $enabledModules, array $counts, array $activity): array
    {
        $messagesThisWeek = collect($activity['series'])
            ->firstWhere('label', __('ui.dashboard.activity.messages'));
        $messagesTotal = is_array($messagesThisWeek)
            ? array_sum($messagesThisWeek['values'])
            : 0;

        return [
            [
                'label' => __('ui.dashboard.highlights.overdue'),
                'value' => Number::format($counts['tasks_overdue']),
                'helper' => __('ui.dashboard.highlights.overdue_helper'),
            ],
            [
                'label' => __('ui.dashboard.highlights.forms'),
                'value' => Number::format($counts['forms_active']),
                'helper' => __('ui.dashboard.highlights.forms_helper'),
            ],
            [
                'label' => __('ui.dashboard.highlights.messages'),
                'value' => Number::format($messagesTotal),
                'helper' => __('ui.dashboard.highlights.messages_helper'),
            ],
            [
                'label' => __('ui.dashboard.highlights.modules'),
                'value' => Number::format(count($enabledModules)),
                'helper' => __('ui.dashboard.highlights.modules_helper'),
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function taskStageDefinitions(): array
    {
        if (! $this->hasTable('project_task_stages')) {
            return collect(ProjectTaskStage::defaultStages())
                ->map(fn (array $stage): array => [
                    'key' => $stage['slug'],
                    'label' => __('ui.projects.status_'.$stage['slug']),
                ])
                ->all();
        }

        return ProjectTaskStage::query()
            ->ordered()
            ->get()
            ->map(fn (ProjectTaskStage $stage): array => [
                'key' => $stage->slug,
                'label' => $stage->displayName(),
            ])
            ->all();
    }

    /**
     * @return Builder<ChatConversation>
     */
    private function accessibleConversationQuery(User $user): Builder
    {
        return ChatConversation::query()
            ->where(function (Builder $conversationQuery) use ($user): void {
                $conversationQuery
                    ->where(function (Builder $directQuery) use ($user): void {
                        $directQuery
                            ->where('type', ChatConversation::TYPE_DIRECT)
                            ->whereHas('participants', fn (Builder $participantQuery): Builder => $participantQuery->where('user_id', $user->id));
                    })
                    ->orWhere(function (Builder $taskQuery) use ($user): void {
                        $taskQuery
                            ->where('type', ChatConversation::TYPE_TASK)
                            ->whereHas('task', fn (Builder $projectTaskQuery): Builder => $projectTaskQuery->visibleTo($user));
                    })
                    ->orWhere(function (Builder $generalQuery): void {
                        $generalQuery->general();
                    });
            });
    }

    /**
     * @return Builder<ChatMessage>
     */
    private function accessibleMessageQuery(User $user): Builder
    {
        return ChatMessage::query()
            ->whereHas('conversation', fn (Builder $conversationQuery): Builder => $conversationQuery
                ->where(function (Builder $nestedQuery) use ($user): void {
                    $nestedQuery
                        ->where(function (Builder $directQuery) use ($user): void {
                            $directQuery
                                ->where('type', ChatConversation::TYPE_DIRECT)
                                ->whereHas('participants', fn (Builder $participantQuery): Builder => $participantQuery->where('user_id', $user->id));
                        })
                        ->orWhere(function (Builder $taskQuery) use ($user): void {
                            $taskQuery
                                ->where('type', ChatConversation::TYPE_TASK)
                                ->whereHas('task', fn (Builder $projectTaskQuery): Builder => $projectTaskQuery->visibleTo($user));
                        })
                        ->orWhere(function (Builder $generalQuery): void {
                            $generalQuery->general();
                        });
                }));
    }

    /**
     * @return array<int, int>
     */
    private function dailySeries(Builder $query, CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = (clone $query)
            ->selectRaw('date(created_at) as day, count(*) as aggregate')
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->groupBy(DB::raw('date(created_at)'))
            ->pluck('aggregate', 'day');

        return collect(range(0, (int) $start->diffInDays($end)))
            ->map(function (int $offset) use ($rows, $start): int {
                $day = $start->copy()->addDays($offset)->toDateString();

                return (int) ($rows[$day] ?? 0);
            })
            ->all();
    }

    private function chartColor(int $index): string
    {
        return match ($index % 5) {
            0 => 'var(--color-chart-1)',
            1 => 'var(--color-chart-2)',
            2 => 'var(--color-chart-3)',
            3 => 'var(--color-chart-4)',
            default => 'var(--color-chart-5)',
        };
    }

    private function hasTable(string $table): bool
    {
        $this->tableNames ??= array_values(Schema::getTableListing(null, false));

        return in_array($table, $this->tableNames, true);
    }
}
