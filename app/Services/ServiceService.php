<?php

namespace App\Services;

use App\DAO\RateInterface;
use App\DAO\ServiceDAO;
use App\Models\Rate;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Support\BusinessCategoryFormatter;
use App\Support\WorkingWeekday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceService
{
    public function __construct(
        private ServiceDAO $serviceDAO,
        private RateInterface $rateDAO
    ) {}

    public function getServicesByArea($areaId)
    {
        $services = $this->serviceDAO->getByArea($areaId);

        return $services->map(function ($service) {
            return [
                'id' => $service->id,
                'name' => $service->name,
                'image' => $service->media->first()?->url,
                'rating' => round($service->rates->avg('score'), 1),
                'rating_count' => $service->rates->count(),
                'is_favorite' => false, // لاحقاً
            ];
        });
    }

    public function getServiceDetails($id)
    {
        $service = $this->serviceDAO->findWithDetails($id);

        if (! $service) {
            return ['error' => 'Service not found'];
        }

        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'location' => $service->locationID,
            'openTime' => $service->openTime,
            'closeTime' => $service->closeTime,
            'days' => $service->workingDays
                ->sortBy('weekday')
                ->map(fn ($d) => WorkingWeekday::isoToAbbrev($d->weekday))
                ->values()
                ->all(),
            'weekdays' => $service->workingDays->sortBy('weekday')->pluck('weekday')->values()->all(),
            'image' => $service->media->first()?->url,
            'rating' => round($service->rates->avg('score'), 1),
            'rating_count' => $service->rates->count(),
        ];
    }

    public function adminServicesSummaryList(int $perPage): LengthAwarePaginator
    {
        return $this->serviceDAO->paginateAdminServicesSummary($perPage)
            ->through(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'media' => $service->media->first()?->url,
            ]);
    }

    public function adminServiceDetails($serviceId): array
    {
        $service = $this->serviceDAO->findAdminServiceById($serviceId);

        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'location' => $service->locationID,
            'media' => $service->media->first()?->url,
            'openTime' => $service->openTime,
            'closeTime' => $service->closeTime,
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
            'days' => $service->workingDays
                ->sortBy('weekday')
                ->map(fn ($d) => WorkingWeekday::isoToAbbrev($d->weekday))
                ->values()
                ->all(),
            'owner' => $service->relationLoaded('owner') && $service->owner
                ? [
                    'id' => $service->owner->id,
                    'name' => $service->owner->name,
                    'email' => $service->owner->email,
                    'phoneNumber' => $service->owner->phoneNumber,
                    'status' => $service->owner->status,
                ]
                : null,
            'area' => $service->relationLoaded('area') && $service->area
                ? [
                    'id' => $service->area->id,
                    'name' => $service->area->name,
                    'number' => $service->area->number,
                    'usageType' => $service->area->usageType,
                    'category' => BusinessCategoryFormatter::toArray($service->area->category),
                    'floorID' => $service->area->floorID,
                    'floor' => $service->area->relationLoaded('floor') && $service->area->floor
                        ? [
                            'id' => $service->area->floor->id,
                            'name' => $service->area->floor->name,
                            'number' => $service->area->floor->number,
                            'mallID' => $service->area->floor->mallID,
                        ]
                        : null,
                ]
                : null,
        ];
    }

    public function adminServiceItems($serviceId)
    {
        $serviceItems = $this->serviceDAO->getAdminServiceItems($serviceId);

        return $serviceItems->map(function ($serviceItem) {
            return [
                'id' => $serviceItem->id,
                'name' => $serviceItem->name,
                'media' => $serviceItem->media->first()?->url,
            ];
        });
    }

    public function getServiceRate(int $serviceId, int $perPage = 10): array
    {
        $summary = $this->serviceDAO->getServiceRateSummary($serviceId);

        if ($summary === null) {
            return ['success' => false, 'message' => 'Service not found.', 'http_status' => 404];
        }

        $rates = $this->rateDAO->paginateForRateable(Service::class, $serviceId, $perPage);

        return [
            'success' => true,
            'summary' => $summary,
            'rates' => $rates->through(fn (Rate $rate) => $this->formatRateForAdmin($rate)),
        ];
    }

    public function getServiceItemRate(int $serviceItemId, int $perPage = 10): array
    {
        $summary = $this->serviceDAO->getServiceItemRateSummary($serviceItemId);

        if ($summary === null) {
            return ['success' => false, 'message' => 'Service item not found.', 'http_status' => 404];
        }

        $rates = $this->rateDAO->paginateForRateable(ServiceItem::class, $serviceItemId, $perPage);

        return [
            'success' => true,
            'summary' => $summary,
            'rates' => $rates->through(fn (Rate $rate) => $this->formatRateForAdmin($rate)),
        ];
    }

    private function formatRateForAdmin(Rate $rate): array
    {
        return [
            'id' => $rate->id,
            'user_id' => $rate->userID,
            'score' => (int) $rate->score,
            'comment' => $rate->comment,
            'created_at' => $rate->created_at,
            'user' => $rate->relationLoaded('user') && $rate->user
                ? [
                    'id' => $rate->user->id,
                    'name' => $rate->user->name,
                    'image' => (new \App\Models\Media(['url' => $rate->user->image_url]))->url,
                ]
                : null,
        ];
    }

    public function adminServiceItemDetails($serviceItemId)
    {
        $serviceItem = $this->serviceDAO->findAdminServiceItemById($serviceItemId);
        if (! $serviceItem) {
            return ['error' => 'Service item not found'];
        }

        $service = $serviceItem->service;

        return [
            'id' => $serviceItem->id,
            'name' => $serviceItem->name,
            'price' => $serviceItem->price,
            'duration' => $serviceItem->duration,
            'status' => $serviceItem->status,
            'created_at' => $serviceItem->created_at,
            'updated_at' => $serviceItem->updated_at,
            'media' => $serviceItem->media->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->url,
                'fileType' => $m->fileType,
            ])->values()->all(),
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'owner' => $service->relationLoaded('owner') && $service->owner
                    ? [
                        'id' => $service->owner->id,
                        'name' => $service->owner->name,
                        'email' => $service->owner->email,
                        'phoneNumber' => $service->owner->phoneNumber,
                    ]
                    : null,
                'area' => $service->relationLoaded('area') && $service->area
                    ? [
                        'id' => $service->area->id,
                        'name' => $service->area->name,
                        'number' => $service->area->number,
                        'floor' => $service->area->relationLoaded('floor') && $service->area->floor
                            ? [
                                'id' => $service->area->floor->id,
                                'name' => $service->area->floor->name,
                                'number' => $service->area->floor->number,
                            ]
                            : null,
                    ]
                    : null,
            ],
            'employees' => $serviceItem->employees->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'price' => $employee->pivot->price ?? null,
                ];
            }),
        ];
    }
}
