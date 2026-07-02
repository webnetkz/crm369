<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresContactsTableIsReady;
use App\Http\Requests\StoreContactCommentRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ContactCommentController extends Controller
{
    use EnsuresContactsTableIsReady;

    public function store(StoreContactCommentRequest $request, string $contact): RedirectResponse
    {
        $this->ensureContactsTableExists();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $visibleContact = Contact::query()
            ->visibleTo($user)
            ->findOrFail($contact);

        $visibleContact->comments()->create([
            ...$request->payload(),
            'created_by_user_id' => $user->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.contacts.comment_created_success')]);

        return back();
    }
}
