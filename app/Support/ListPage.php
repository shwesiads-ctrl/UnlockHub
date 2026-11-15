<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListPage
{
    public static function from(Collection $items, int $perPage = 20, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = max(1, (int) request()->query($pageName, 1));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->except($pageName), 'pageName' => $pageName],
        );
    }
}
