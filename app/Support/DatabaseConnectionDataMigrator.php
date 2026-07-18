<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class DatabaseConnectionDataMigrator
{
    /**
     * @param  array<int, string>  $excludedTables
     * @return array<string, int>
     */
    public function migrate(
        string $sourceConnection,
        string $targetConnection,
        bool $truncateTarget = true,
        array $excludedTables = [],
        bool $verifyTableCounts = true,
    ): array {
        if ($sourceConnection === $targetConnection) {
            throw new InvalidArgumentException('The source and target database connections must be different.');
        }

        $tables = $this->tablesFor($sourceConnection)
            ->reject(fn (string $table): bool => in_array($table, $excludedTables, true))
            ->values();

        if ($tables->isEmpty()) {
            return [];
        }

        $targetTables = $this->tablesFor($targetConnection);
        $missingTargetTables = $tables->diff($targetTables)->values();

        if ($missingTargetTables->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'The target connection [%s] is missing table(s): %s.',
                $targetConnection,
                $missingTargetTables->implode(', '),
            ));
        }

        $orderedTables = $this->sortTablesByDependencies($sourceConnection, $tables);

        $copiedRowsByTable = DB::connection($targetConnection)->transaction(function () use (
            $sourceConnection,
            $targetConnection,
            $truncateTarget,
            $orderedTables,
        ): array {
            if ($truncateTarget) {
                $this->clearTargetTables($targetConnection, $orderedTables->reverse()->values());
            }

            $copiedRowsByTable = [];

            foreach ($orderedTables as $table) {
                $copiedRowsByTable[$table] = $this->copyTable($sourceConnection, $targetConnection, $table);
            }

            return $copiedRowsByTable;
        });

        $this->resetSequences($targetConnection, $orderedTables);

        if ($verifyTableCounts) {
            $this->assertTableCountsMatch($sourceConnection, $targetConnection, $orderedTables, $copiedRowsByTable);
        }

        return $copiedRowsByTable;
    }

    public function synchronizeSequences(string $connection): int
    {
        return $this->resetSequences($connection, $this->tablesFor($connection));
    }

    /**
     * @return Collection<int, non-empty-string>
     */
    private function tablesFor(string $connection): Collection
    {
        return collect(Schema::connection($connection)->getTableListing(schemaQualified: false))
            ->filter(fn (string $table): bool => $table !== '')
            ->values();
    }

    /**
     * @param  Collection<int, non-empty-string>  $tables
     * @return Collection<int, non-empty-string>
     */
    private function sortTablesByDependencies(string $connection, Collection $tables): Collection
    {
        $dependencies = $tables->mapWithKeys(function (string $table) use ($connection, $tables): array {
            $foreignTables = collect(Schema::connection($connection)->getForeignKeys($table))
                ->pluck('foreign_table')
                ->filter(fn (mixed $foreignTable): bool => is_string($foreignTable))
                ->filter(fn (string $foreignTable): bool => $foreignTable !== $table)
                ->filter(fn (string $foreignTable): bool => $tables->contains($foreignTable))
                ->unique()
                ->values()
                ->all();

            return [$table => $foreignTables];
        });

        $orderedTables = collect();
        $visited = [];
        $visiting = [];

        $visit = function (string $table) use (&$visit, &$visited, &$visiting, $dependencies, $orderedTables): void {
            if (($visited[$table] ?? false) === true) {
                return;
            }

            if (($visiting[$table] ?? false) === true) {
                return;
            }

            $visiting[$table] = true;

            foreach ($dependencies->get($table, []) as $dependency) {
                $visit($dependency);
            }

            $visiting[$table] = false;
            $visited[$table] = true;
            $orderedTables->push($table);
        };

        foreach ($tables as $table) {
            $visit($table);
        }

        return $orderedTables->values();
    }

    /**
     * @param  Collection<int, non-empty-string>  $tables
     */
    private function clearTargetTables(string $connection, Collection $tables): void
    {
        $schema = Schema::connection($connection);
        $driver = DB::connection($connection)->getDriverName();

        if ($driver === 'pgsql') {
            foreach ($tables as $table) {
                $wrappedTable = DB::connection($connection)->getQueryGrammar()->wrapTable($table);

                DB::connection($connection)->statement("TRUNCATE TABLE {$wrappedTable} RESTART IDENTITY CASCADE");
            }

            return;
        }

        $schema->disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                DB::connection($connection)->table($table)->delete();
            }
        } finally {
            $schema->enableForeignKeyConstraints();
        }
    }

    private function copyTable(string $sourceConnection, string $targetConnection, string $table): int
    {
        $query = DB::connection($sourceConnection)->table($table);

        if (Schema::connection($sourceConnection)->hasColumn($table, 'id')) {
            $query->orderBy('id');
        }

        $buffer = [];
        $copiedRows = 0;

        foreach ($query->cursor() as $row) {
            $buffer[] = (array) $row;

            if (count($buffer) < 500) {
                continue;
            }

            DB::connection($targetConnection)->table($table)->insert($buffer);
            $copiedRows += count($buffer);
            $buffer = [];
        }

        if ($buffer !== []) {
            DB::connection($targetConnection)->table($table)->insert($buffer);
            $copiedRows += count($buffer);
        }

        return $copiedRows;
    }

    /**
     * @param  Collection<int, non-empty-string>  $tables
     */
    private function resetSequences(string $connection, Collection $tables): int
    {
        if (DB::connection($connection)->getDriverName() !== 'pgsql') {
            return 0;
        }

        $synchronizedSequences = 0;
        $schema = Schema::connection($connection);

        foreach ($tables as $table) {
            if (! $schema->hasColumn($table, 'id')) {
                continue;
            }

            $sequenceResult = DB::connection($connection)->selectOne(
                "select pg_get_serial_sequence(?, 'id') as sequence_name",
                [$table],
            );
            $sequenceName = is_object($sequenceResult)
                ? ($sequenceResult->sequence_name ?? null)
                : null;

            if (! is_string($sequenceName) || $sequenceName === '') {
                continue;
            }

            try {
                $maxId = DB::connection($connection)->table($table)->max('id');
            } catch (QueryException) {
                continue;
            }

            if (! is_numeric($maxId)) {
                DB::connection($connection)->select(
                    'select setval(cast(? as regclass), 1, false)',
                    [$sequenceName],
                );

                $synchronizedSequences++;

                continue;
            }

            DB::connection($connection)->select(
                'select setval(cast(? as regclass), ?, true)',
                [$sequenceName, (int) $maxId],
            );
            $synchronizedSequences++;
        }

        return $synchronizedSequences;
    }

    /**
     * @param  Collection<int, non-empty-string>  $tables
     * @param  array<string, int>  $copiedRowsByTable
     */
    private function assertTableCountsMatch(
        string $sourceConnection,
        string $targetConnection,
        Collection $tables,
        array $copiedRowsByTable,
    ): void {
        foreach ($tables as $table) {
            $sourceCount = DB::connection($sourceConnection)->table($table)->count();
            $targetCount = DB::connection($targetConnection)->table($table)->count();
            $copiedRows = $copiedRowsByTable[$table] ?? 0;

            if ($sourceCount !== $targetCount || $sourceCount !== $copiedRows) {
                throw new RuntimeException(sprintf(
                    'Table [%s] verification failed. Source: %d row(s), target: %d row(s), copied: %d row(s).',
                    $table,
                    $sourceCount,
                    $targetCount,
                    $copiedRows,
                ));
            }
        }
    }
}
