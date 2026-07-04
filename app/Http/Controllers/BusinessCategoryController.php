<?php

namespace App\Http\Controllers;

use App\Services\BusinessCategoryService;
use Illuminate\Http\Request;

class BusinessCategoryController extends Controller
{
    public function __construct(
        protected BusinessCategoryService $businessCategoryService,
    ) {}

    public function index(Request $request)
    {
        return response()->json(
            $this->businessCategoryService->listPublic($request->query('type'))
        );
    }

    public function storesInCategory(Request $request, int $categoryId)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $result = $this->businessCategoryService->listStoresForCustomer($categoryId, $perPage);

        if ($result === null) {
            abort(404);
        }

        return response()->json($result);
    }

    public function servicesInCategory(Request $request, int $categoryId)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $result = $this->businessCategoryService->listServicesForCustomer($categoryId, $perPage);

        if ($result === null) {
            abort(404);
        }

        return response()->json($result);
    }
}
