<?php

namespace App\Support;

use Illuminate\Support\Str;

class CsvDelimiter
{
    public static function normalize(mixed $delimiter, string $default = ';'): ?string
    {
        if (! is_string($delimiter)) {
            return $default;
        }

        $normalized = trim($delimiter);

        if ($normalized === '') {
            return $default;
        }

        if (in_array(Str::lower($normalized), ['\\t', 'tab'], true)) {
            return "\t";
        }

        return mb_strlen($normalized) === 1 ? $normalized : null;
    }
}
