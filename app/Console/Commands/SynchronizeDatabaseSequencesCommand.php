<?php

namespace App\Console\Commands;

use App\Support\DatabaseConnectionDataMigrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:database:synchronize-sequences
    {--database= : Database connection to synchronize}')]
#[Description('Synchronize PostgreSQL identity sequences with the current table data')]
class SynchronizeDatabaseSequencesCommand extends Command
{
    public function __construct(
        private readonly DatabaseConnectionDataMigrator $migrator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $connection = $this->option('database') ?: config('database.default');

        if (! is_string($connection) || $connection === '') {
            $this->error('A valid database connection is required.');

            return self::FAILURE;
        }

        try {
            $synchronizedSequences = $this->migrator->synchronizeSequences($connection);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Synchronized {$synchronizedSequences} sequence(s) on [{$connection}].");

        return self::SUCCESS;
    }
}
