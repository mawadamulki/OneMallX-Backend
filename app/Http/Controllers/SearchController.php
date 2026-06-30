<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        protected SearchService $searchService,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'type' => 'nullable|in:all,stores,products,services',
            'storeId' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $perPage = min(max((int) ($validated['per_page'] ?? 10), 1), 50);

        return response()->json(
            $this->searchService->search(
                trim($validated['q']),
                $validated['type'] ?? 'all',
                $perPage,
                isset($validated['storeId']) ? (int) $validated['storeId'] : null,
            )
        );
    }
}
