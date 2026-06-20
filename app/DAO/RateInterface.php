<?php

namespace App\DAO;

use App\Models\Rate;
use App\Models\RateReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RateInterface
{
    public function findByUserAndRateable(int $userId, string $rateableType, int $rateableId): ?Rate;

    public function upsertRate(int $userId, string $rateableType, int $rateableId, int $score, ?string $comment): Rate;

    public function findRateForUser(int $rateId, int $userId): ?Rate;

    public function findById(int $rateId): ?Rate;

    public function deleteRate(Rate $rate): bool;

    public function paginateForRateable(string $rateableType, int $rateableId, int $perPage): LengthAwarePaginator;

    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator;

    public function paginateAdmin(
        int $perPage,
        ?string $rateableType = null,
        ?int $rateableId = null,
        ?int $userId = null,
    ): LengthAwarePaginator;

    public function paginateForStore(int $storeId, int $perPage, ?int $reporterUserId = null): LengthAwarePaginator;

    public function paginateForStoreProducts(int $storeId, int $perPage, ?int $productId = null, ?int $reporterUserId = null): LengthAwarePaginator;

    public function paginateForService(int $serviceId, int $perPage, ?int $reporterUserId = null): LengthAwarePaginator;

    public function paginateForServiceItems(int $serviceId, int $perPage, ?int $serviceItemId = null, ?int $reporterUserId = null): LengthAwarePaginator;

    public function countReportsByReporterToday(int $reporterUserId): int;

    public function reportExists(int $rateId, int $reporterUserId): bool;

    public function createReport(int $rateId, int $reporterUserId): RateReport;

    public function findReportByRateAndReporter(int $rateId, int $reporterUserId): ?RateReport;

    public function deleteReport(RateReport $report): bool;

    public function paginateReportsAdmin(int $perPage, ?string $status = null): LengthAwarePaginator;

    public function findReport(int $reportId): ?RateReport;

    public function updateReportStatus(RateReport $report, string $status): RateReport;

    public function markPendingReportsActionTakenForRate(int $rateId): void;

    /** @return Collection<int, object> */
    public function getReportedUsersSummary(): Collection;
}
