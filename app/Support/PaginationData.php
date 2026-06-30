<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;

class PaginationData
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, mixed>
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_pages' => $paginator->hasPages(),
            ],
            'links' => $paginator->linkCollection()->toArray(),
        ];
    }
}
