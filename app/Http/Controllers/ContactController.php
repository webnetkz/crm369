<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresContactsTableIsReady;
use App\Http\Requests\FilterContactsIndexRequest;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ApiContactResource;
use App\Models\Contact;
use App\Models\User;
use App\Support\ContactAvatarManager;
use App\Support\PaginationData;
use App\Support\PerPageOptions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    use EnsuresContactsTableIsReady;

    public function __construct(private ContactAvatarManager $contactAvatarManager) {}

    public function index(FilterContactsIndexRequest $request): Response
    {
        $this->ensureContactsTableExists();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $filters = $request->filters();
        $activeType = $this->resolveActiveType($user, $filters['type']);

        $contacts = Contact::query()
            ->with([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
                'comments.author:id,name,last_name',
            ])
            ->visibleTo($user)
            ->when($filters['search'] !== '', fn ($query) => $query->search($filters['search']))
            ->withBlacklistFilter($filters['blacklist'])
            ->when($activeType !== 'all', fn ($query) => $query->where('type', $activeType))
            ->orderBy('type')
            ->orderBy('is_blacklisted', 'desc')
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Contact $contact): array => (new ApiContactResource($contact))->resolve());

        return Inertia::render('contacts/Index', [
            'contacts' => PaginationData::from($contacts),
            'filters' => [
                ...$filters,
                'type' => $activeType,
            ],
            'availableTypes' => $this->availableTypesFor($user),
            'perPageOptions' => PerPageOptions::allowed(),
            'can' => [
                'create_person' => $user->canAccessPersonContacts(),
                'create_company' => $user->canAccessCompanyContacts(),
            ],
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $this->ensureContactsTableExists();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $contact = Contact::query()->create([
            ...$request->payload(),
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        if ($this->contactAvatarManager->sync($contact, $request->file('avatar'))) {
            $contact->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.contacts.created_success')]);

        return back();
    }

    public function update(UpdateContactRequest $request, string $contact): RedirectResponse
    {
        $this->ensureContactsTableExists();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $visibleContact = Contact::query()
            ->visibleTo($user)
            ->findOrFail($contact);

        abort_unless($user->canAccessContactType($request->contactType()), 403);

        $visibleContact->update([
            ...$request->payload(),
            'updated_by_user_id' => $user->id,
        ]);

        if ($this->contactAvatarManager->sync($visibleContact, $request->file('avatar'))) {
            $visibleContact->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.contacts.updated_success')]);

        return back();
    }

    public function destroy(FilterContactsIndexRequest $request, string $contact): RedirectResponse
    {
        $this->ensureContactsTableExists();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $visibleContact = Contact::query()
            ->visibleTo($user)
            ->findOrFail($contact);

        $visibleContact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.contacts.deleted_success')]);

        return back();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function availableTypesFor(User $user): array
    {
        return collect($user->accessibleContactTypes())
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => __(Contact::typeDefinitions()[$type]['label_key']),
            ])
            ->values()
            ->all();
    }

    private function resolveActiveType(User $user, string $type): string
    {
        $availableTypes = $user->accessibleContactTypes();

        if ($type === 'all' || in_array($type, $availableTypes, true)) {
            return $type;
        }

        return count($availableTypes) === 1 ? $availableTypes[0] : 'all';
    }
}
