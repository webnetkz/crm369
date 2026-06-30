<?php

namespace App\Support;

use Illuminate\Http\Request;

class PerPageOptions
{
    public const int DEFAULT = 50;

    /**
     * @var array<int, int>
     */
    public const array ALLOWED = [50, 100, 500];

    public static function resolve(Request $request, string $key = 'per_page'): int
    {
        $value = (int) $request->integer($key, self::DEFAULT);

        return in_array($value, self::ALLOWED, true)
            ? $value
            : self::DEFAULT;
    }

    /**
     * @return array<int, int>
     */
    public static function allowed(): array
    {
        return self::ALLOWED;
    }
}
