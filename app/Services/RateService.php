<?php

namespace App\Services;

use App\DAO\ProductInterface;
use App\DAO\RateInterface;
use App\DAO\ServiceProviderInterface;
use App\DAO\StoreInterface;
use App\DAO\UserDAOInterface;
use App\Models\Product;
use App\Models\Rate;
use App\Models\RateReport;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\Store;
use App\Models\User;
use App\Support\RateableType;
use Illuminate\Support\Collection;

class RateService
{
    public const REPORT_DAILY_LIMIT = 5;

    public const ACCOUNT_REVIEW_ACTION_TAKEN_THRESHOLD = 5;

    public const ACCOUNT_REVIEW_TOTAL_REPORTS_THRESHOLD = 10;

    public function __construct(
        private RateInterface $rateDAO,
        private StoreInterface $storeDAO,
        private ProductInterface $productDAO,
        private ServiceProviderInterface $serviceProviderDAO,
        private UserDAOInterface $userDAO,
    ) {}

    public function submit(int $userId, array $payload): array
    {
        $user = User::query()->find($userId);
        if ($user === null || ! $user->hasRole('Customer')) {
            return $this->fail('Only customers can rate.', 403);
        }

        $rateableClass = RateableType::resolveClass($payload['rateable_type']);
        if ($rateableClass === null) {
            return $this->fail('Invalid rateable type.', 422);
        }

        $entityError = $this->validateRateableExists($rateableClass, (int) $payload['rateable_id']);
        if ($entityError !== null) {
            return $entityError;
        }

        $rate = $this->rateDAO->upsertRate(
            $userId,
            $rateableClass,
            (int) $payload['rateable_id'],
            (int) $payload['score'],
            $payload['comment'] ?? null,
        );

        return [
            'success' => true,
            'message' => 'Rating saved.',
            'rate' => $this->formatRate($rate),
        ];
    }

    public function updateForCustomer(int $userId, int $rateId, array $payload): array
    {
        $user = User::query()->find($userId);
        if ($user === null || ! $user->hasRole('Customer')) {
            return $this->fail('Only customers can rate.', 403);
        }

        $rate = $this->rateDAO->findRateForUser($rateId, $userId);
        if ($rate === null) {
            return $this->fail('Rating not found.', 404);
        }

        $rate->update([
            'score' => (int) $payload['score'],
            'comment' => $payload['comment'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'Rating updated.',
            'rate' => $this->formatRate($rate->fresh()),
        ];
    }

    public function deleteForCustomer(int $userId, int $rateId): array
    {
        $user = User::query()->find($userId);
        if ($user === null || ! $user->hasRole('Customer')) {
            return $this->fail('Only customers can manage ratings.', 403);
        }

        $rate = $this->rateDAO->findRateForUser($rateId, $userId);
        if ($rate === null) {
            return $this->fail('Rating not found.', 404);
        }

        $this->rateDAO->deleteRate($rate);

        return [
            'success' => true,
            'message' => 'Rating deleted.',
        ];
    }

    public function listForRateable(string $typeAlias, int $rateableId, int $perPage): array
    {
        $rateableClass = RateableType::resolveClass($typeAlias);
        if ($rateableClass === null) {
            return $this->fail('Invalid rateable type.', 422);
        }

        $entityError = $this->validateRateableExists($rateableClass, $rateableId, false);
        if ($entityError !== null) {
            return $entityError;
        }

        $paginator = $this->rateDAO->paginateForRateable($rateableClass, $rateableId, $perPage);

        return [
            'success' => true,
            'rateable_type' => $typeAlias,
            'rateable_id' => $rateableId,
            'summary' => $this->summaryForRateable($rateableClass, $rateableId),
            'rates' => $paginator->through(fn (Rate $rate) => $this->formatRate($rate, false)),
        ];
    }

    public function listMine(int $userId, int $perPage): array
    {
        $paginator = $this->rateDAO->paginateForUser($userId, $perPage);

        return [
            'success' => true,
            'rates' => $paginator->through(fn (Rate $rate) => $this->formatRate($rate)),
        ];
    }

    public function listStoreRatesForOwner(int $userId, int $perPage): array
    {
        $store = $this->storeDAO->findStoreByOwnerId($userId);
        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        return [
            'success' => true,
            'store_id' => $store->id,
            'rates' => $this->rateDAO->paginateForStore((int) $store->id, $perPage, $userId)
                ->through(fn (Rate $rate) => $this->formatRate($rate, false, true)),
        ];
    }

    public function listStoreProductRatesForOwner(int $userId, int $perPage, ?int $productId = null): array
    {
        $store = $this->storeDAO->findStoreByOwnerId($userId);
        if ($store === null) {
            return $this->fail('Store not found for this account.', 404);
        }

        if ($productId !== null) {
            $product = $this->productDAO->findProductForStore($productId, (int) $store->id);
            if ($product === null) {
                return $this->fail('Product not found.', 404);
            }
        }

        return [
            'success' => true,
            'store_id' => $store->id,
            'product_id' => $productId,
            'rates' => $this->rateDAO->paginateForStoreProducts((int) $store->id, $perPage, $productId, $userId)
                ->through(fn (Rate $rate) => $this->formatRate($rate, false, true)),
        ];
    }

    public function listServiceRatesForProvider(int $userId, int $perPage): array
    {
        $service = $this->serviceProviderDAO->findServiceByProviderId($userId);
        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        return [
            'success' => true,
            'service_id' => $service->id,
            'rates' => $this->rateDAO->paginateForService((int) $service->id, $perPage, $userId)
                ->through(fn (Rate $rate) => $this->formatRate($rate, false, true)),
        ];
    }

    public function listServiceItemRatesForProvider(int $userId, int $perPage, ?int $itemId = null): array
    {
        $service = $this->serviceProviderDAO->findServiceByProviderId($userId);
        if ($service === null) {
            return $this->fail('Service not found for this account.', 404);
        }

        if ($itemId !== null) {
            $item = ServiceItem::query()
                ->whereKey($itemId)
                ->where('serviceID', $service->id)
                ->first();

            if ($item === null) {
                return $this->fail('Service item not found.', 404);
            }
        }

        return [
            'success' => true,
            'service_id' => $service->id,
            'service_item_id' => $itemId,
            'rates' => $this->rateDAO->paginateForServiceItems((int) $service->id, $perPage, $itemId, $userId)
                ->through(fn (Rate $rate) => $this->formatRate($rate, false, true)),
        ];
    }

    public function report(int $reporterUserId, int $rateId): array
    {
        $rate = $this->rateDAO->findById($rateId);
        if ($rate === null) {
            return $this->fail('Rating not found.', 404);
        }

        if ((int) $rate->userID === $reporterUserId) {
            return $this->fail('You cannot report your own rating.', 422);
        }

        if (! $this->canUserReportRate($reporterUserId, $rate)) {
            return $this->fail('You are not allowed to report this rating.', 403);
        }

        if ($this->rateDAO->reportExists($rateId, $reporterUserId)) {
            return $this->fail('You already reported this rating.', 422);
        }

        if ($this->rateDAO->countReportsByReporterToday($reporterUserId) >= self::REPORT_DAILY_LIMIT) {
            return $this->fail('You can report up to '.self::REPORT_DAILY_LIMIT.' reviews per day.', 429);
        }

        $report = $this->rateDAO->createReport($rateId, $reporterUserId);

        return [
            'success' => true,
            'message' => 'Rating reported.',
            'report' => $this->formatReport($report),
        ];
    }

    public function unreport(int $reporterUserId, int $rateId): array
    {
        $rate = $this->rateDAO->findById($rateId);
        if ($rate === null) {
            return $this->fail('Rating not found.', 404);
        }

        $report = $this->rateDAO->findReportByRateAndReporter($rateId, $reporterUserId);
        if ($report === null) {
            return $this->fail('You have not reported this rating.', 422);
        }

        if ($report->status !== RateReport::STATUS_PENDING) {
            return $this->fail('This report can no longer be withdrawn.', 422);
        }

        $this->rateDAO->deleteReport($report);

        return [
            'success' => true,
            'message' => 'Rating report withdrawn.',
        ];
    }

    public function adminListRates(int $perPage, ?string $typeAlias, ?int $rateableId, ?int $userId): array
    {
        $rateableClass = $typeAlias !== null ? RateableType::resolveClass($typeAlias) : null;
        if ($typeAlias !== null && $rateableClass === null) {
            return $this->fail('Invalid rateable type.', 422);
        }

        return [
            'success' => true,
            'rates' => $this->rateDAO->paginateAdmin($perPage, $rateableClass, $rateableId, $userId)
                ->through(fn (Rate $rate) => $this->formatRate($rate)),
        ];
    }

    public function adminShowRate(int $rateId): array
    {
        $rate = $this->rateDAO->findById($rateId);
        if ($rate === null) {
            return $this->fail('Rating not found.', 404);
        }

        return [
            'success' => true,
            'rate' => $this->formatRate($rate),
            'reports' => $rate->reports->map(fn (RateReport $report) => $this->formatReport($report))->values()->all(),
        ];
    }

    public function adminDeleteRate(int $rateId): array
    {
        $rate = $this->rateDAO->findById($rateId);
        if ($rate === null) {
            return $this->fail('Rating not found.', 404);
        }

        $this->rateDAO->markPendingReportsActionTakenForRate($rateId);
        $this->rateDAO->deleteRate($rate);

        return [
            'success' => true,
            'message' => 'Rating deleted.',
        ];
    }

    public function adminListReports(int $perPage, string $status): array
    {
        if (! in_array($status, [
            'all',
            RateReport::STATUS_PENDING,
            RateReport::STATUS_DISMISSED,
            RateReport::STATUS_ACTION_TAKEN,
        ], true)) {
            return $this->fail('Invalid report status.', 422);
        }

        $filterStatus = $status === 'all' ? null : $status;

        return [
            'success' => true,
            'reports' => $this->rateDAO->paginateReportsAdmin($perPage, $filterStatus)
                ->through(fn (RateReport $report) => $this->formatReport($report)),
        ];
    }

    public function adminShowReport(int $reportId): array
    {
        $report = $this->rateDAO->findReport($reportId);
        if ($report === null) {
            return $this->fail('Report not found.', 404);
        }

        return [
            'success' => true,
            'report' => $this->formatReport($report),
        ];
    }

    public function adminDismissReport(int $reportId): array
    {
        $report = $this->rateDAO->findReport($reportId);
        if ($report === null) {
            return $this->fail('Report not found.', 404);
        }

        if ($report->status !== RateReport::STATUS_PENDING) {
            return $this->fail('Only pending reports can be dismissed.', 422);
        }

        $this->rateDAO->updateReportStatus($report, RateReport::STATUS_DISMISSED);

        return [
            'success' => true,
            'message' => 'Report dismissed.',
        ];
    }

    public function adminTakeActionOnReport(int $reportId): array
    {
        $report = $this->rateDAO->findReport($reportId);
        if ($report === null) {
            return $this->fail('Report not found.', 404);
        }

        if ($report->status !== RateReport::STATUS_PENDING) {
            return $this->fail('Only pending reports can be actioned.', 422);
        }

        $rate = $report->rate;
        if ($rate !== null) {
            $this->rateDAO->markPendingReportsActionTakenForRate((int) $rate->id);
            $this->rateDAO->deleteRate($rate);
        }

        $this->rateDAO->updateReportStatus($report, RateReport::STATUS_ACTION_TAKEN);

        return [
            'success' => true,
            'message' => 'Action taken. Rating removed.',
        ];
    }

    public function adminReportedUsers(): array
    {
        $users = $this->rateDAO->getReportedUsersSummary()
            ->map(function ($row) {
                $actionTaken = (int) $row->action_taken_reports;
                $total = (int) $row->total_reports;

                return [
                    'user_id' => (int) $row->user_id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'total_reports' => $total,
                    'pending_reports' => (int) $row->pending_reports,
                    'action_taken_reports' => $actionTaken,
                    'reported_rates_count' => (int) $row->reported_rates_count,
                    'suggest_account_review' => $actionTaken >= self::ACCOUNT_REVIEW_ACTION_TAKEN_THRESHOLD
                        || $total >= self::ACCOUNT_REVIEW_TOTAL_REPORTS_THRESHOLD,
                ];
            })
            ->values()
            ->all();

        return [
            'success' => true,
            'users' => $users,
        ];
    }

    public function adminDeleteUser(int $userId): array
    {
        $user = User::query()->with('roles')->find($userId);
        if ($user === null) {
            return $this->fail('User not found.', 404);
        }

        if ($user->hasRole('Admin')) {
            return $this->fail('Admin accounts cannot be deleted through this endpoint.', 422);
        }

        $this->userDAO->deleteUser($user);

        return [
            'success' => true,
            'message' => 'User account deleted.',
        ];
    }

    /**
     * @return array{rating: float|null, rating_count: int}
     */
    public function summaryForRateable(string $rateableClass, int $rateableId): array
    {
        $query = Rate::query()
            ->where('rateableType', $rateableClass)
            ->where('rateableID', $rateableId);

        $count = (clone $query)->count();
        $avg = (clone $query)->avg('score');

        return [
            'rating' => $count > 0 ? round((float) $avg, 1) : null,
            'rating_count' => $count,
        ];
    }

    public function myRatingFor(int $userId, string $rateableClass, int $rateableId): ?array
    {
        $rate = $this->rateDAO->findByUserAndRateable($userId, $rateableClass, $rateableId);

        return $rate !== null ? $this->formatRate($rate) : null;
    }

    private function canUserReportRate(int $reporterUserId, Rate $rate): bool
    {
        $user = User::query()->find($reporterUserId);
        if ($user === null) {
            return false;
        }

        if ($user->hasRole('Customer')) {
            return true;
        }

        if ($user->hasRole('Store Owner')) {
            return $this->rateBelongsToStoreOwner($reporterUserId, $rate);
        }

        if ($user->hasRole('Service Provider')) {
            return $this->rateBelongsToServiceProvider($reporterUserId, $rate);
        }

        return false;
    }

    private function rateBelongsToStoreOwner(int $ownerId, Rate $rate): bool
    {
        $store = $this->storeDAO->findStoreByOwnerId($ownerId);
        if ($store === null) {
            return false;
        }

        if ($rate->rateableType === Store::class && (int) $rate->rateableID === (int) $store->id) {
            return true;
        }

        if ($rate->rateableType === Product::class) {
            $product = Product::query()->find($rate->rateableID);

            return $product !== null && (int) $product->storeID === (int) $store->id;
        }

        return false;
    }

    private function rateBelongsToServiceProvider(int $providerId, Rate $rate): bool
    {
        $service = $this->serviceProviderDAO->findServiceByProviderId($providerId);
        if ($service === null) {
            return false;
        }

        if ($rate->rateableType === Service::class && (int) $rate->rateableID === (int) $service->id) {
            return true;
        }

        if ($rate->rateableType === ServiceItem::class) {
            $item = ServiceItem::query()->find($rate->rateableID);

            return $item !== null && (int) $item->serviceID === (int) $service->id;
        }

        return false;
    }

    private function validateRateableExists(string $rateableClass, int $rateableId, bool $strictVisibility = true): ?array
    {
        if ($rateableClass === Store::class) {
            $store = Store::query()->find($rateableId);
            if ($store === null) {
                return $this->fail('Store not found.', 404);
            }
            if ($strictVisibility && ! $store->isVisibleToCustomers()) {
                return $this->fail('Store not found.', 404);
            }

            return null;
        }

        if ($rateableClass === Product::class) {
            $product = Product::query()->with('store')->find($rateableId);
            if ($product === null) {
                return $this->fail('Product not found.', 404);
            }
            if ($strictVisibility && ($product->store === null || ! $product->store->isVisibleToCustomers())) {
                return $this->fail('Product not found.', 404);
            }

            return null;
        }

        if ($rateableClass === Service::class) {
            if (! Service::query()->whereKey($rateableId)->exists()) {
                return $this->fail('Service not found.', 404);
            }

            return null;
        }

        if ($rateableClass === ServiceItem::class) {
            if (! ServiceItem::query()->whereKey($rateableId)->exists()) {
                return $this->fail('Service item not found.', 404);
            }

            return null;
        }

        return $this->fail('Invalid rateable type.', 422);
    }

    private function formatRate(Rate $rate, bool $includeRateable = true, bool $includeReportStatus = false): array
    {
        $data = [
            'id' => $rate->id,
            'user_id' => $rate->userID,
            'score' => (int) $rate->score,
            'comment' => $rate->comment,
            'rateable_type' => RateableType::aliasForClass($rate->rateableType),
            'rateable_id' => (int) $rate->rateableID,
            'created_at' => $rate->created_at,
            'updated_at' => $rate->updated_at,
        ];

        if ($includeReportStatus) {
            $data['is_reported'] = (bool) ($rate->is_reported ?? false);
        }

        if ($rate->relationLoaded('user') && $rate->user) {
            $data['user'] = [
                'id' => $rate->user->id,
                'name' => $rate->user->name,
                'image' => $rate->user->image_url,
            ];
        }

        if ($includeRateable && $rate->relationLoaded('rateable') && $rate->rateable) {
            $data['rateable'] = [
                'id' => $rate->rateable->id,
                'name' => $rate->rateable->name ?? null,
            ];
        }

        return $data;
    }

    private function formatReport(RateReport $report): array
    {
        $data = [
            'id' => $report->id,
            'rate_id' => $report->rateID,
            'reporter_user_id' => $report->reporterUserID,
            'status' => $report->status,
            'created_at' => $report->created_at,
            'updated_at' => $report->updated_at,
        ];

        if ($report->relationLoaded('rate') && $report->rate) {
            $data['rate'] = $this->formatRate($report->rate);
        }

        if ($report->relationLoaded('reporter') && $report->reporter) {
            $data['reporter'] = [
                'id' => $report->reporter->id,
                'name' => $report->reporter->name,
                'email' => $report->reporter->email,
                'image' => $report->reporter->image_url,
            ];
        }

        return $data;
    }

    /** @return array{success: false, message: string, http_status: int} */
    private function fail(string $message, int $httpStatus): array
    {
        return [
            'success' => false,
            'message' => $message,
            'http_status' => $httpStatus,
        ];
    }
}
