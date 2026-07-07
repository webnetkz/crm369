<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiAccessTokenRequest;
use App\Http\Resources\ApiAccessTokenResource;
use App\Models\ApiAccessToken;
use App\Support\ApiCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Api', [
            'can' => [
                'manage_tokens' => $user?->can('manage-api-tokens') ?? false,
            ],
            'baseUrl' => $this->baseUrl($request),
            'permissions' => collect(ApiAccessToken::permissionDefinitions())
                ->map(fn (array $definition, string $key): array => [
                    'key' => $key,
                    'label' => __($definition['label_key']),
                    'description' => __($definition['description_key']),
                ])
                ->values(),
            'tokens' => $user?->can('manage-api-tokens')
                ? ApiAccessTokenResource::collection(
                    $user->apiAccessTokens()->get()
                )->resolve()
                : [],
        ]);
    }

    public function documentation(Request $request, ApiCatalog $apiCatalog): Response
    {
        return Inertia::render('settings/ApiDocumentation', [
            'baseUrl' => $this->baseUrl($request),
            'documentation' => $apiCatalog->sections(),
        ]);
    }

    public function store(StoreApiAccessTokenRequest $request): RedirectResponse
    {
        [
            'api_access_token' => $apiAccessToken,
            'plain_text_token' => $plainTextToken,
        ] = ApiAccessToken::issueToUser(
            user: $request->user(),
            name: $request->validated('name'),
            permissions: $request->permissions(),
            expiresAt: $request->expiresAt(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.api.created_success'),
        ]);
        Inertia::flash('apiToken', [
            'name' => $apiAccessToken->name,
            'token' => $plainTextToken,
            'expires_at' => $apiAccessToken->expires_at?->toISOString(),
        ]);

        return back();
    }

    public function destroy(Request $request, ApiAccessToken $apiAccessToken): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-api-tokens') ?? false, 403);
        abort_unless($apiAccessToken->user_id === $request->user()?->id, 403);

        $apiAccessToken->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.api.revoked_success'),
        ]);

        return back();
    }

    private function baseUrl(Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/').'/api/v1';
    }
}
