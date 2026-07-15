<?php

namespace App\Console\Commands;

use App\Support\DatabaseConnectionDataMigrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('app:database:migrate-connection-data
    {--from=sqlite : Source database connection}
    {--to=pgsql : Target database connection}
    {--skip-tables= : Comma-separated list of tables to skip}
    {--without-truncate : Keep existing rows in the target database}
    {--without-verify : Skip post-copy source / target row count verification}
    {--skip-migrate-target : Do not run migrations on the target connection before copying data}')]
#[Description('Copy application data from one configured database connection to another')]
class MigrateConnectionDataCommand extends Command
{
    public function __construct(
        private readonly DatabaseConnectionDataMigrator $migrator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourceConnection = (string) $this->option('from');
        $targetConnection = (string) $this->option('to');

        if ($sourceConnection === $targetConnection) {
            $this->error('The source and target database connections must be different.');

            return self::FAILURE;
        }

        try {
            DB::connection($sourceConnection)->getPdo();
            DB::connection($targetConnection)->getPdo();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('skip-migrate-target')) {
            $this->info("Running migrations on the [{$targetConnection}] connection...");

            $migrationResult = Artisan::call('migrate', [
                '--database' => $targetConnection,
                '--force' => true,
            ]);

            $this->output->write(Artisan::output());

            if ($migrationResult !== self::SUCCESS) {
                $this->error("Target migrations on [{$targetConnection}] failed.");

                return self::FAILURE;
            }
        }

        $skipTables = collect(explode(',', (string) $this->option('skip-tables')))
            ->map(fn (string $table): string => trim($table))
            ->filter()
            ->values()
            ->all();

        try {
            $this->info("Copying data from [{$sourceConnection}] to [{$targetConnection}]...");

            $copiedRowsByTable = $this->migrator->migrate(
                sourceConnection: $sourceConnection,
                targetConnection: $targetConnection,
                truncateTarget: ! $this->option('without-truncate'),
                excludedTables: $skipTables,
                verifyTableCounts: ! $this->option('without-verify'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'Copied rows'],
            collect($copiedRowsByTable)
                ->map(fn (int $copiedRows, string $table): array => [$table, $copiedRows])
                ->values()
                ->all(),
        );

        $this->info(sprintf(
            'Copied %d table(s) and %d row(s).',
            count($copiedRowsByTable),
            array_sum($copiedRowsByTable),
        ));

        return self::SUCCESS;
    }
}
