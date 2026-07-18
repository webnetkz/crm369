<?php

namespace App\Concerns;

use App\Models\Conference;
use App\Models\ConferenceInvitation;
use App\Models\ConferenceMessage;
use App\Models\ConferenceParticipant;
use App\Models\ConferenceSignal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

trait EnsuresConferenceTablesAreReady
{
    protected function conferencesTablesExist(): bool
    {
        return Schema::hasTable((new Conference)->getTable())
            && Schema::hasTable((new ConferenceInvitation)->getTable())
            && Schema::hasTable((new ConferenceParticipant)->getTable())
            && Schema::hasTable((new ConferenceSignal)->getTable())
            && Schema::hasTable((new ConferenceMessage)->getTable());
    }

    protected function ensureConferencesTablesExist(): void
    {
        abort_unless($this->conferencesTablesExist(), 503, __('ui.conferences.module_not_ready'));
    }

    protected function conferencesUnavailableResponse(): JsonResponse
    {
        return response()->json([
            'message' => __('ui.conferences.module_not_ready'),
        ], 503);
    }
}
