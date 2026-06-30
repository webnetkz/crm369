<?php

namespace App\Concerns;

use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

trait EnsuresContactsTableIsReady
{
    protected function contactsTableExists(): bool
    {
        return Schema::hasTable((new Contact)->getTable());
    }

    protected function ensureContactsTableExists(): void
    {
        abort_unless($this->contactsTableExists(), 503, __('ui.contacts.module_not_ready'));
    }

    protected function contactsUnavailableResponse(): JsonResponse
    {
        return response()->json([
            'message' => __('ui.contacts.module_not_ready'),
        ], 503);
    }
}
