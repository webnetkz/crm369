<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiRequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiSubjectUser
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED_ROUTE_NAME_PREFIXES = [
        'api.v1.profile.',
        'api.v1.notifications.',
        'api.v1.chats.',
        'api.v1.knowledge-bases.index',
        'api.v1.knowledge-bases.show',
        'api.v1.knowledge-bases.articles.show',
        'api.v1.projects.',
        'api.v1.tasks.',
        'api.v1.menu.',
        'api.v1.equipment.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $owner = $request->user();

        if (! $owner instanceof User) {
            return $next($request);
        }

        $request->attributes->set(ApiRequestContext::OWNER_ATTRIBUTE, $owner);

        $subject = $owner;
        $subjectUserId = $request->query(ApiRequestContext::QUERY_PARAMETER);

        if (
            $subjectUserId !== null
            && $subjectUserId !== ''
            && $this->routeSupportsSubjectUser($request)
        ) {
            Validator::make($request->query(), [
                ApiRequestContext::QUERY_PARAMETER => ['bail', 'required', 'integer', 'min:1', 'exists:users,id'],
            ])->validate();

            $subject = User::query()->findOrFail((int) $subjectUserId);

            if (! $subject->is($owner)) {
                abort_unless($owner->canImpersonate($subject), 403);
            }
        }

        $request->attributes->set(ApiRequestContext::SUBJECT_ATTRIBUTE, $subject);

        return $next($request);
    }

    private function routeSupportsSubjectUser(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (! is_string($routeName) || $routeName === '') {
            return false;
        }

        foreach (self::SUPPORTED_ROUTE_NAME_PREFIXES as $supportedRouteNamePrefix) {
            if (str_starts_with($routeName, $supportedRouteNamePrefix)) {
                return true;
            }
        }

        return false;
    }
}
