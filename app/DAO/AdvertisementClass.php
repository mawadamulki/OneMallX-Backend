<?php

namespace App\DAO;

use App\Models\Advertisement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdvertisementClass implements AdvertisementInterface
{
    public function paginateAllForAdmin(
        int $perPage,
        ?string $ownerType = null,
        ?string $status = null,
        ?string $placement = null,
    ): LengthAwarePaginator {
        $query = Advertisement::query()
            ->with([
                'store:id,name,accountStatus',
                'service:id,name,accountStatus',
            ])
            ->orderByDesc('startDate')
            ->orderByDesc('id');

        if ($ownerType === 'store') {
            $query->whereNotNull('storeID');
        } elseif ($ownerType === 'service') {
            $query->whereNotNull('serviceID');
        }

        if ($placement !== null && in_array($placement, ['home', 'deals'], true)) {
            $query->where('placement', $placement);
        }

        if ($status !== null && $status !== 'all') {
            $today = Carbon::today()->toDateString();

            if ($status === 'active') {
                $query->whereDate('startDate', '<=', $today)
                    ->whereDate('endDate', '>=', $today);
            } elseif ($status === 'scheduled') {
                $query->whereDate('startDate', '>', $today);
            } elseif ($status === 'expired') {
                $query->whereDate('endDate', '<', $today);
            }
        }

        return $query->paginate($perPage);
    }

    public function listForStore(int $storeId): Collection
    {
        return Advertisement::query()
            ->where('storeID', $storeId)
            ->orderByDesc('startDate')
            ->orderByDesc('id')
            ->get();
    }

    public function listForService(int $serviceId): Collection
    {
        return Advertisement::query()
            ->where('serviceID', $serviceId)
            ->orderByDesc('startDate')
            ->orderByDesc('id')
            ->get();
    }

    public function findForStore(int $adId, int $storeId): ?Advertisement
    {
        return Advertisement::query()
            ->whereKey($adId)
            ->where('storeID', $storeId)
            ->first();
    }

    public function findForService(int $adId, int $serviceId): ?Advertisement
    {
        return Advertisement::query()
            ->whereKey($adId)
            ->where('serviceID', $serviceId)
            ->first();
    }

    public function countActiveForStore(int $storeId): int
    {
        return $this->activeQuery()
            ->where('storeID', $storeId)
            ->count();
    }

    public function countActiveForService(int $serviceId): int
    {
        return $this->activeQuery()
            ->where('serviceID', $serviceId)
            ->count();
    }

    public function listActivePublic(string $placement): Collection
    {
        return $this->activeQuery()
            ->where('placement', $placement)
            ->where(function ($query) {
                $query->whereHas('store', fn ($q) => $q->where('accountStatus', 'active'))
                    ->orWhereHas('service', fn ($q) => $q->where('accountStatus', 'active'));
            })
            ->with([
                'store:id,name,accountStatus',
                'service:id,name,accountStatus',
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(array $data): Advertisement
    {
        return Advertisement::query()->create($data);
    }

    public function update(Advertisement $ad, array $data): Advertisement
    {
        $ad->update($data);

        return $ad->fresh();
    }

    public function delete(Advertisement $ad): bool
    {
        return (bool) $ad->delete();
    }

    private function activeQuery()
    {
        $today = Carbon::today()->toDateString();

        return Advertisement::query()
            ->whereDate('startDate', '<=', $today)
            ->whereDate('endDate', '>=', $today);
    }
}
