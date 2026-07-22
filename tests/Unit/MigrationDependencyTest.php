<?php

use Illuminate\Support\Str;

test('production installer resolves every migration table dependency', function () {
    $projectRoot = dirname(__DIR__, 2);
    $migrationPaths = glob($projectRoot.'/database/migrations/*.php');

    expect($migrationPaths)->toBeArray()->not->toBeEmpty();

    sort($migrationPaths, SORT_STRING);

    $createdTablesByMigration = [];
    $referencedTablesByMigration = [];
    $requiredTablesByMigration = [];
    $parseErrors = [];

    foreach ($migrationPaths as $migrationPath) {
        $migration = basename($migrationPath);
        $source = file_get_contents($migrationPath);

        expect($source)->toBeString();

        preg_match_all("~Schema::create\\(\\s*'([^']+)'~", $source, $createdTableMatches);
        preg_match_all("~Schema::(?:table|rename)\\(\\s*'([^']+)'~", $source, $schemaTableMatches);
        preg_match_all("~DB::table\\(\\s*'([^']+)'~", $source, $databaseTableMatches);
        preg_match_all(
            "~\\\$table->foreignId\\(\\s*'([^']+)'\\s*\\)([^;]*?)->constrained\\(\\s*(?:'([^']+)')?~s",
            $source,
            $foreignIdMatches,
            PREG_SET_ORDER,
        );
        preg_match_all(
            "~\\\$table->foreign\\([^;]+?->on\\(\\s*'([^']+)'~s",
            $source,
            $explicitForeignKeyMatches,
            PREG_SET_ORDER,
        );

        $constrainedCount = substr_count($source, '->constrained(');

        if ($constrainedCount !== count($foreignIdMatches)) {
            $parseErrors[] = "{$migration}: parsed ".count($foreignIdMatches)." of {$constrainedCount} constrained foreign keys";
        }

        $referencedTables = array_map(
            static function (array $match): string {
                if (($match[3] ?? '') !== '') {
                    return $match[3];
                }

                return Str::plural(Str::beforeLast($match[1], '_id'));
            },
            $foreignIdMatches,
        );

        foreach ($explicitForeignKeyMatches as $match) {
            $referencedTables[] = $match[1];
        }

        $createdTablesByMigration[$migration] = array_values(array_unique($createdTableMatches[1]));
        $referencedTablesByMigration[$migration] = array_values(array_unique($referencedTables));
        $requiredTablesByMigration[$migration] = array_values(array_unique([
            ...$schemaTableMatches[1],
            ...$databaseTableMatches[1],
        ]));
    }

    $installer = file_get_contents($projectRoot.'/scripts/install-ubuntu.sh');

    expect($installer)->toBeString();

    preg_match('~migration_dependency_options=\((.*?)\n\)~s', $installer, $dependencyBlockMatch);

    expect($dependencyBlockMatch)->toHaveCount(2);

    preg_match_all(
        "~'--path=database/migrations/([^']+)'~",
        $dependencyBlockMatch[1],
        $dependencyMigrationMatches,
    );

    $dependencyMigrations = $dependencyMigrationMatches[1];
    $sortedDependencyMigrations = $dependencyMigrations;
    sort($sortedDependencyMigrations, SORT_STRING);

    expect($dependencyMigrations)->toBe($sortedDependencyMigrations);

    $availableTables = [];
    $appliedMigrations = [];
    $dependencyErrors = [];

    $applyMigration = static function (string $migration, string $phase) use (
        &$availableTables,
        &$appliedMigrations,
        &$dependencyErrors,
        $createdTablesByMigration,
        $referencedTablesByMigration,
        $requiredTablesByMigration,
    ): void {
        if (! array_key_exists($migration, $createdTablesByMigration)) {
            $dependencyErrors[] = "{$phase}: missing migration {$migration}";

            return;
        }

        $createdTables = $createdTablesByMigration[$migration];

        foreach ([
            ...$referencedTablesByMigration[$migration],
            ...$requiredTablesByMigration[$migration],
        ] as $requiredTable) {
            if (! isset($availableTables[$requiredTable]) && ! in_array($requiredTable, $createdTables, true)) {
                $dependencyErrors[] = "{$phase}: {$migration} requires missing table {$requiredTable}";
            }
        }

        foreach ($createdTables as $createdTable) {
            $availableTables[$createdTable] = $migration;
        }

        $appliedMigrations[$migration] = true;
    };

    foreach ($dependencyMigrations as $dependencyMigration) {
        $applyMigration($dependencyMigration, 'dependency preflight');
    }

    foreach (array_map('basename', $migrationPaths) as $migration) {
        if (! isset($appliedMigrations[$migration])) {
            $applyMigration($migration, 'general migration pass');
        }
    }

    expect($parseErrors)->toBe([])
        ->and($dependencyErrors)->toBe([]);
});
