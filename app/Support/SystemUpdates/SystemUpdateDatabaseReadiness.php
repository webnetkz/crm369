<?php

namespace App\Support\SystemUpdates;

use App\Models\SystemUpdateRun;
use App\Models\SystemUpdateSnapshot;
use Illuminate\Support\Facades\Schema;

class SystemUpdateDatabaseReadiness
{
    public function isReady(): bool
    {
        return Schema::hasTable((new SystemUpdateRun)->getTable())
            && Schema::hasTable((new SystemUpdateSnapshot)->getTable());
    }
}
