<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresConferenceTablesAreReady;
use App\Models\Conference;
use App\Support\ConferencePageData;
use Inertia\Inertia;
use Inertia\Response;

class PublicConferenceController extends Controller
{
    use EnsuresConferenceTablesAreReady;

    public function show(string $conference, ConferencePageData $pageData): Response
    {
        $this->ensureConferencesTablesExist();

        $publicConference = Conference::query()
            ->where('public_token', $conference)
            ->firstOrFail();

        abort_unless($publicConference->allowsPublicJoin(), 404);

        $publicConference->load('creator:id,name,last_name,email');

        return Inertia::render('public/conferences/Show', $pageData->buildPublicShow($publicConference));
    }
}
