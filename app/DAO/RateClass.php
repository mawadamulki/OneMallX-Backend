<?php

namespace App\DAO;

use App\Models\Product;
use App\Models\Rate;
use App\Models\RateReport;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RateClass implements RateInterface
{
    public function findByUserAndRateable(int $userId, string $rateableType, int $rateableId): ?Rate
    {
        return Rate::query()
            ->where('userID', $userId)
            ->where('rateableType', $rateableType)
            ->where('rateableID', $rateableId)
            ->first();
    }

    public function upsertRate(int $userId, string $rateableType, int $rateableId, int $score, ?string $comment): Rate
    {
        return Rate::query()->updateOrCreate(
            [
                'userID' => $userId,
                'rateableType' => $rateableType,
                'rateableID' => $rateableId,
            ],
            [
                'score' => $score,
                'comment' => $comment,
            ]
        );
    }

    public function findRateForUser(int $rateId, int $userId): ?Rate
    {
        return Rate::query()
            ->whereKey($rateId)
            ->where('userID', $userId)
            ->first();
    }

    public function findById(int $rateId): ?Rate
    {
        return Rate::query()
            ->with(['user', 'rateable', 'reports.reporter'])
            ->find($rateId);
    }

    public function deleteRate(Rate $rate): bool
    {
        return (bool) $rate->delete();
    }

    public function paginateForRateable(string $rateableType, int $rateableId, int $perPage): LengthAwarePaginator
    {
        return Rate::query()
            ->where('rateableType', $rateableType)
            ->where('rateableID', $rateableId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator
    {
        return Rate::query()
            ->where('userID', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateAdmin(
        int $perPage,
        ?string $rateableType = null,
        ?int $rateableId = null,
        ?int $userId = null,
    ): LengthAwarePaginator {
        $query = Rate::query()
            ->with(['user:id,name,email', 'rateable'])
            ->orderByDesc('created_at');

        if ($rateableType !== null) {
            $query->where('rateableType', $rateableType);
        }

        if ($rateableId !== null) {
            $query->where('rateableID', $rateableId);
        }

        if ($userId !== null) {
            $query->where('userID', $userId);
        }

        return $query->paginate($perPage);
    }

    public function paginateForStore(int $storeId, int $perPage): LengthAwarePaginator
    {
        return Rate::query()
            ->where('rateableType', Store::class)
            ->where('rateableID', $storeId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateForStoreProducts(int $storeId, int $perPage, ?int $productId = null): LengthAwarePaginator
    {
        $productIds = Product::query()
            ->where('storeID', $storeId)
            ->when($productId !== null, fn ($q) => $q->whereKey($productId))
            ->pluck('id');

        return Rate::query()
            ->where('rateableType', Product::class)
            ->whereIn('rateableID', $productIds)
            ->with(['user:id,name', 'rateable'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateForService(int $serviceId, int $perPage): LengthAwarePaginator
    {
        return Rate::query()
            ->where('rateableType', Service::class)
            ->where('rateableID', $serviceId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateForServiceItems(int $serviceId, int $perPage, ?int $serviceItemId = null): LengthAwarePaginator
    {
        $itemIds = ServiceItem::query()
            ->where('serviceID', $serviceId)
            ->when($serviceItemId !== null, fn ($q) => $q->whereKey($serviceItemId))
            ->pluck('id');

        return Rate::query()
            ->where('rateableType', ServiceItem::class)
            ->whereIn('rateableID', $itemIds)
            ->with(['user:id,name', 'rateable'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function countReportsByReporterToday(int $reporterUserId): int
    {
        return RateReport::query()
            ->where('reporterUserID', $reporterUserId)
            ->whereDate('created_at', today())
            ->count();
    }

    public function reportExists(int $rateId, int $reporterUserId): bool
    {
        return RateReport::query()
            ->where('rateID', $rateId)
            ->where('reporterUserID', $reporterUserId)
            ->exists();
    }

    public function createReport(int $rateId, int $reporterUserId): RateReport
    {
        return RateReport::query()->create([
            'rateID' => $rateId,
            'reporterUserID' => $reporterUserId,
            'status' => RateReport::STATUS_PENDING,
        ]);
    }

    public function paginateReportsAdmin(int $perPage, ?string $status = null): LengthAwarePaginator
    {
        $query = RateReport::query()
            ->with([
                'rate.user:id,name,email',
                'rate.rateable',
                'reporter:id,name,email',
            ])
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function findReport(int $reportId): ?RateReport
    {
        return RateReport::query()
            ->with([
                'rate.user:id,name,email',
                'rate.rateable',
                'reporter:id,name,email',
            ])
            ->find($reportId);
    }

    public function updateReportStatus(RateReport $report, string $status): RateReport
    {
        $report->update(['status' => $status]);

        return $report->fresh();
    }

    public function markPendingReportsActionTakenForRate(int $rateId): void
    {
        RateReport::query()
            ->where('rateID', $rateId)
            ->where('status', RateReport::STATUS_PENDING)
            ->update(['status' => RateReport::STATUS_ACTION_TAKEN]);
    }

    public function getReportedUsersSummary(): Collection
    {
        return DB::table('rates')
            ->join('rate_reports', 'rate_reports.rateID', '=', 'rates.id')
            ->join('users', 'users.id', '=', 'rates.userID')
            ->whereNull('rates.deleted_at')
            ->groupBy('rates.userID', 'users.name', 'users.email')
            ->select([
                'rates.userID as user_id',
                'users.name',
                'users.email',
            ])
            ->selectRaw('COUNT(rate_reports.id) as total_reports')
            ->selectRaw("SUM(CASE WHEN rate_reports.status = 'pending' THEN 1 ELSE 0 END) as pending_reports")
            ->selectRaw("SUM(CASE WHEN rate_reports.status = 'action_taken' THEN 1 ELSE 0 END) as action_taken_reports")
            ->selectRaw('COUNT(DISTINCT rates.id) as reported_rates_count')
            ->orderByDesc('total_reports')
            ->get();
    }
}
