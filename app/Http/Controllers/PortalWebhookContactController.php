<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresContactsTableIsReady;
use App\Http\Requests\FilterContactsIndexRequest;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ApiContactResource;
use App\Models\Contact;
use App\Models\PortalWebhook;
use App\Support\ContactAvatarManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class PortalWebhookContactController extends Controller
{
    use EnsuresContactsTableIsReady;

    public function __construct(private ContactAvatarManager $contactAvatarManager) {}

    public function index(FilterContactsIndexRequest $request, PortalWebhook $portalWebhook): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

        $filters = $request->filters();

        $contacts = Contact::query()
            ->with([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
            ])
            ->when($filters['search'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['search']))
            ->when($filters['type'] !== 'all', fn (Builder $query) => $query->where('type', $filters['type']))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'filters' => $filters,
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
        ]);
    }

    public function show(FilterContactsIndexRequest $request, PortalWebhook $portalWebhook, string $contact): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

        $resolvedContact = Contact::query()
            ->with([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
            ])
            ->findOrFail($contact);

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'data' => (new ApiContactResource($resolvedContact))->resolve(),
        ]);
    }

    public function store(StoreContactRequest $request, PortalWebhook $portalWebhook): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

        $contact = Contact::query()->create($request->payload());

        if ($this->contactAvatarManager->sync($contact, $request->file('avatar'))) {
            $contact->save();
        }

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.contacts.created_success'),
            'data' => (new ApiContactResource($contact))->resolve(),
        ], 201);
    }

    public function update(UpdateContactRequest $request, PortalWebhook $portalWebhook, string $contact): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

        $resolvedContact = Contact::query()->findOrFail($contact);
        $resolvedContact->update($request->payload());

        if ($this->contactAvatarManager->sync($resolvedContact, $request->file('avatar'))) {
            $resolvedContact->save();
        }

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.contacts.updated_success'),
            'data' => (new ApiContactResource($resolvedContact->fresh()->load([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
            ])))->resolve(),
        ]);
    }

    public function destroy(FilterContactsIndexRequest $request, PortalWebhook $portalWebhook, string $contact): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

        $resolvedContact = Contact::query()->findOrFail($contact);
        $deletedId = $resolvedContact->id;
        $resolvedContact->delete();

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.contacts.deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
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
