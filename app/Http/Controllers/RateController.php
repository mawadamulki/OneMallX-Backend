<?php

namespace App\Http\Controllers;

use App\Services\RateService;
use App\Support\RateableType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RateController extends Controller
{
    public function __construct(private RateService $rateService) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rateable_type' => ['required', 'string', Rule::in(RateableType::allowedAliases())],
            'rateable_id' => 'required|integer|min:1',
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        return $this->respond($this->rateService->submit((int) Auth::id(), $validated));
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        return $this->respond($this->rateService->updateForCustomer((int) Auth::id(), $id, $validated));
    }

    public function destroy(int $id)
    {
        return $this->respond($this->rateService->deleteForCustomer((int) Auth::id(), $id));
    }

    public function myRates(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->respond($this->rateService->listMine((int) Auth::id(), $perPage));
    }

    public function index(Request $request, string $type, int $id)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->respond($this->rateService->listForRateable($type, $id, $perPage));
    }

    public function report(int $id)
    {
        return $this->respond($this->rateService->report((int) Auth::id(), $id), 201);
    }

    public function unreport(int $id)
    {
        return $this->respond($this->rateService->unreport((int) Auth::id(), $id));
    }

    public function storeRates(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->respond($this->rateService->listStoreRatesForOwner((int) Auth::id(), $perPage));
    }

    public function storeProductRates(Request $request, ?int $productId = null)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->respond($this->rateService->listStoreProductRatesForOwner((int) Auth::id(), $perPage, $productId));
    }

    public function serviceRates(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->respond($this->rateService->listServiceRatesForProvider((int) Auth::id(), $perPage));
    }

    public function serviceItemRates(Request $request, ?int $itemId = null)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->respond($this->rateService->listServiceItemRatesForProvider((int) Auth::id(), $perPage, $itemId));
    }

    public function adminIndex(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->respond($this->rateService->adminListRates(
            $perPage,
            $request->query('rateable_type'),
            $request->filled('rateable_id') ? $request->integer('rateable_id') : null,
            $request->filled('user_id') ? $request->integer('user_id') : null,
        ));
    }

    public function adminShow(int $id)
    {
        return $this->respond($this->rateService->adminShowRate($id));
    }

    public function adminDestroy(int $id)
    {
        return $this->respond($this->rateService->adminDeleteRate($id));
    }

    public function adminReports(Request $request, string $status)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return $this->respond($this->rateService->adminListReports($perPage, $status));
    }

    public function adminShowReport(int $id)
    {
        return $this->respond($this->rateService->adminShowReport($id));
    }

    public function adminDismissReport(int $id)
    {
        return $this->respond($this->rateService->adminDismissReport($id));
    }

    public function adminTakeActionOnReport(int $id)
    {
        return $this->respond($this->rateService->adminTakeActionOnReport($id));
    }

    public function adminReportedUsers()
    {
        return $this->respond($this->rateService->adminReportedUsers());
    }

    public function adminDeleteUser(int $userId)
    {
        return $this->respond($this->rateService->adminDeleteUser($userId));
    }

    private function respond(array $result, int $successStatus = 200)
    {
        if (! ($result['success'] ?? false)) {
            return response()->json(
                ['message' => $result['message'] ?? 'Request failed.'],
                $result['http_status'] ?? 400
            );
        }

        return response()->json($result, $successStatus);
    }
}
