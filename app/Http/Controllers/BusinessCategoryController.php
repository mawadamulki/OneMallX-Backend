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

        return $this->respond(
            $this->businessCategoryService->listStoresForCustomer($categoryId, $perPage)
        );
    }

    public function servicesInCategory(Request $request, int $categoryId)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        return $this->respond(
            $this->businessCategoryService->listServicesForCustomer($categoryId, $perPage)
        );
    }

    private function respond(array $result)
    {
        if (isset($result['success']) && $result['success'] === false) {
            return response()->json(
                ['success' => false, 'message' => $result['message']],
                $result['http_status'] ?? 404
            );
        }

        return response()->json($result);
    }
}
