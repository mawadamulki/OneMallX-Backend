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
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->respond($this->favoriteProductService->listForUser((int) Auth::id(), $perPage));
    }

    public function store(int $productId)
    {
        return $this->respond(
            $this->favoriteProductService->add((int) Auth::id(), $productId),
            201,
        );
    }

    public function destroy(int $productId)
    {
        return $this->respond($this->favoriteProductService->remove((int) Auth::id(), $productId));
    }

    private function respond(array $result, int $successStatus = 200)
    {
        if (! ($result['success'] ?? false)) {
            return response()->json(
                ['message' => $result['message'] ?? 'Request failed.'],
                $result['http_status'] ?? 400,
            );
        }

        $status = $result['http_status'] ?? $successStatus;
        unset($result['http_status']);

        return response()->json($result, $status);
    }
}
