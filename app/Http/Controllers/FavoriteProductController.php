<?php

namespace App\Http\Controllers;

use App\Services\FavoriteProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteProductController extends Controller
{
    public function __construct(
        protected FavoriteProductService $favoriteProductService,
    ) {}

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        return $this->respond(
            $this->favoriteProductService->list((int) Auth::id(), $perPage)
        );
    }

    public function store($productId)
    {
        return $this->respond(
            $this->favoriteProductService->add((int) Auth::id(), (int) $productId)
        );
    }

    public function destroy($productId)
    {
        return $this->respond(
            $this->favoriteProductService->remove((int) Auth::id(), (int) $productId)
        );
    }

    private function respond(array $result)
    {
        $status = $result['http_status'] ?? ($result['success'] ? 200 : 422);
        unset($result['http_status'], $result['success']);

        return response()->json($result, $status);
    }
}
