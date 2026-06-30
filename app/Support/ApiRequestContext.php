<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use LogicException;

class ApiRequestContext
{
    public const string OWNER_ATTRIBUTE = 'api_owner_user';
    public const string SUBJECT_ATTRIBUTE = 'api_subject_user';
    public const string QUERY_PARAMETER = 'user_id';

    public static function owner(Request $request): User
    {
        $owner = $request->attributes->get(self::OWNER_ATTRIBUTE, $request->user());

        if (! $owner instanceof User) {
            throw new LogicException('The API request does not have an authenticated owner.');
        }

        return $owner;
    }

    public static function subject(Request $request): User
    {
        $subject = $request->attributes->get(self::SUBJECT_ATTRIBUTE);

        if ($subject instanceof User) {
            return $subject;
        }

        return self::owner($request);
    }

    public static function isImpersonating(Request $request): bool
    {
        return ! self::owner($request)->is(self::subject($request));
    }
}
