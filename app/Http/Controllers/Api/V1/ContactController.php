<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\EnsuresContactsTableIsReady;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterContactsIndexRequest;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ApiContactResource;
use App\Models\Contact;
use App\Models\User;
use App\Support\ContactAvatarManager;
use App\Support\PerPageOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    use EnsuresContactsTableIsReady;

    public function __construct(private ContactAvatarManager $contactAvatarManager) {}

    public function index(FilterContactsIndexRequest $request): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

        $user = $request->user();
        abort_unless($user !== null, 403);

        $filters = $request->filters();
        $activeType = $this->resolveActiveType($user, $filters['type']);

        $contacts = Contact::query()
            ->with([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
            ])
            ->visibleTo($user)
            ->when($filters['search'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['search']))
            ->when($activeType !== 'all', fn (Builder $query) => $query->where('type', $activeType))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return response()->json([
            'can' => [
                'create_person' => $user->canAccessPersonContacts(),
                'create_company' => $user->canAccessCompanyContacts(),
            ],
            'available_types' => $this->availableTypesFor($user),
            'filters' => [
                ...$filters,
                'type' => $activeType,
            ],
            'data' => collect($contacts->items())
                ->map(fn (Contact $contact): array => (new ApiContactResource($contact))->resolve())
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
                'from' => $contacts->firstItem(),
                'to' => $contacts->lastItem(),
            ],
            'per_page_options' => PerPageOptions::allowed(),
        ]);
    }

    public function show(FilterContactsIndexRequest $request, string $contact): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

        $user = $request->user();
        abort_unless($user !== null, 403);

        $visibleContact = Contact::query()
            ->with([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
            ])
            ->visibleTo($user)
            ->findOrFail($contact);

        return response()->json([
            'data' => (new ApiContactResource($visibleContact))->resolve(),
        ]);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

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

        return response()->json([
            'message' => __('ui.contacts.created_success'),
            'data' => (new ApiContactResource($contact->load([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
            ])))->resolve(),
        ], 201);
    }

    public function update(UpdateContactRequest $request, string $contact): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

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

        return response()->json([
            'message' => __('ui.contacts.updated_success'),
            'data' => (new ApiContactResource($visibleContact->fresh()->load([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
            ])))->resolve(),
        ]);
    }

    public function destroy(FilterContactsIndexRequest $request, string $contact): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

        $user = $request->user();
        abort_unless($user !== null, 403);

        $visibleContact = Contact::query()
            ->visibleTo($user)
            ->findOrFail($contact);

        $deletedId = $visibleContact->id;
        $visibleContact->delete();

        return response()->json([
            'message' => __('ui.contacts.deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
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

    /**
     * @param  Builder<Contact>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('position', 'like', "%{$search}%");
        });
    }
}
