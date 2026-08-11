<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PortfolioAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PortfolioController extends ApiController
{
    public function __construct(private readonly PortfolioAnalyticsService $portfolio) {}

    public function summary(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return response()->json(['success' => true, 'data' => $this->portfolio->summary(
            $request->user(), $data['branch_id'] ?? null, $data['from'] ?? null, $data['to'] ?? null
        )]);
    }

    public function branches(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $items = collect($this->portfolio->branches($request->user(), $data['from'] ?? null, $data['to'] ?? null));
        $page = max(1, $request->integer('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
            ],
            'links' => ['first' => $paginator->url(1), 'last' => $paginator->url($paginator->lastPage()), 'prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()],
        ]);
    }
}
