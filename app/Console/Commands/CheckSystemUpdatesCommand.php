<?php

namespace App\Console\Commands;

use App\Actions\SystemUpdates\CheckSystemVersions;
use App\Support\SystemUpdates\SystemUpdateDatabaseReadiness;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm369:updates:check')]
#[Description('Check CRM369, framework, and server component versions')]
class CheckSystemUpdatesCommand extends Command
{
    public function handle(
        CheckSystemVersions $checkSystemVersions,
        SystemUpdateDatabaseReadiness $databaseReadiness,
    ): int {
        if (! $databaseReadiness->isReady()) {
            $this->warn(__('ui.system_updates.errors.database_not_ready'));

            return self::FAILURE;
        }

        $snapshot = $checkSystemVersions->execute();

        $this->info("System update snapshot {$snapshot->id} saved.");

        return self::SUCCESS;
    }
}
