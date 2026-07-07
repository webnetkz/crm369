<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class CompanyStructureData
{
    /**
     * @return array{
     *     stats: array{total_users: int, root_users: int, managers: int},
     *     roots: array<int, array<string, mixed>>
     * }
     */
    public function pageData(): array
    {
        return $this->structurePayload($this->users());
    }

    /**
     * @return array{
     *     stats: array{total_users: int, root_users: int, managers: int},
     *     roots: array<int, array<string, mixed>>
     * }
     */
    public function apiIndexData(): array
    {
        return $this->pageData();
    }

    /**
     * @return array{data: array<string, mixed>, ancestors: array<int, array<string, mixed>>}
     */
    public function apiShowData(User $user): array
    {
        $users = $this->users();
        $usersById = $users->keyBy('id');
        $childrenByManager = $this->childrenByManager($users, $usersById);
        $resolvedUser = $usersById->get($user->id);

        abort_unless($resolvedUser instanceof User, 404);

        return [
            'data' => $this->node($resolvedUser, $usersById, $childrenByManager),
            'ancestors' => $this->ancestors($resolvedUser, $usersById),
        ];
    }

    /**
     * @return array{
     *     stats: array{total_users: int, root_users: int, managers: int},
     *     roots: array<int, array<string, mixed>>
     * }
     */
    public function webhookData(): array
    {
        return $this->pageData();
    }

    /**
     * @return Collection<int, User>
     */
    private function users(): Collection
    {
        return User::query()
            ->select([
                'id',
                'name',
                'last_name',
                'email',
                'avatar_path',
                'avatar_scale',
                'position',
                'manager_id',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->orderBy('name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{
     *     stats: array{total_users: int, root_users: int, managers: int},
     *     roots: array<int, array<string, mixed>>
     * }
     */
    private function structurePayload(Collection $users): array
    {
        $usersById = $users->keyBy('id');
        $childrenByManager = $this->childrenByManager($users, $usersById);
        /** @var Collection<int, User> $roots */
        $roots = $childrenByManager->get('root', collect());

        return [
            'stats' => [
                'total_users' => $users->count(),
                'root_users' => $roots->count(),
                'managers' => collect($childrenByManager->all())
                    ->except('root')
                    ->filter(fn (Collection $children): bool => $children->isNotEmpty())
                    ->count(),
            ],
            'roots' => $roots
                ->map(fn (User $root): array => $this->node($root, $usersById, $childrenByManager))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, User>  $usersById
     * @return Collection<int|string, Collection<int, User>>
     */
    private function childrenByManager(Collection $users, Collection $usersById): Collection
    {
        return $users->groupBy(
            fn (User $user): int|string => $this->normalizedManagerId($user, $usersById) ?? 'root',
        );
    }

    /**
     * @param  Collection<int, User>  $usersById
     * @param  Collection<int|string, Collection<int, User>>  $childrenByManager
     * @param  array<int, int>  $path
     * @return array<string, mixed>
     */
    private function node(
        User $user,
        Collection $usersById,
        Collection $childrenByManager,
        array $path = [],
    ): array {
        /** @var Collection<int, User> $children */
        $children = $childrenByManager
            ->get($user->id, collect())
            ->reject(fn (User $child): bool => in_array($child->id, [...$path, $user->id], true))
            ->values();

        $manager = $this->resolvedManager($user, $usersById);

        return [
            ...$this->summary($user),
            'manager_id' => $manager?->id,
            'manager' => $manager ? $this->summary($manager) : null,
            'subordinates_count' => $children->count(),
            'subordinates' => $children
                ->map(fn (User $child): array => $this->summary($child))
                ->values()
                ->all(),
            'children' => $children
                ->map(fn (User $child): array => $this->node($child, $usersById, $childrenByManager, [...$path, $user->id]))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, User>  $usersById
     * @return array<int, array<string, mixed>>
     */
    private function ancestors(User $user, Collection $usersById): array
    {
        $ancestors = [];
        $manager = $this->resolvedManager($user, $usersById);
        $visitedIds = [$user->id];

        while ($manager instanceof User && ! in_array($manager->id, $visitedIds, true)) {
            array_unshift($ancestors, $this->summary($manager));
            $visitedIds[] = $manager->id;
            $manager = $this->resolvedManager($manager, $usersById);
        }

        return $ancestors;
    }

    /**
     * @param  Collection<int, User>  $usersById
     */
    private function resolvedManager(User $user, Collection $usersById): ?User
    {
        $managerId = $this->normalizedManagerId($user, $usersById);

        if ($managerId === null) {
            return null;
        }

        $manager = $usersById->get($managerId);

        return $manager instanceof User ? $manager : null;
    }

    /**
     * @param  Collection<int, User>  $usersById
     */
    private function normalizedManagerId(User $user, Collection $usersById): ?int
    {
        if (! is_int($user->manager_id) || $user->manager_id === $user->id) {
            return null;
        }

        return $usersById->has($user->manager_id) ? $user->manager_id : null;
    }

    /**
     * @return array{id: int, name: string, last_name: string|null, full_name: string, email: string, avatar: string|null, avatar_scale: float, position: string|null, is_active: bool}
     */
    private function summary(User $user): array
    {
        $fullName = trim($user->name.' '.($user->last_name ?? ''));

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'full_name' => $fullName !== '' ? $fullName : $user->email,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'avatar_scale' => $user->avatar_scale,
            'position' => $user->position,
            'is_active' => $user->is_active,
        ];
    }
}
