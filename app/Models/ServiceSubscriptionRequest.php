<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSubscriptionRequest extends Model
{
    protected $hidden = [
        'password',
    ];

    protected $appends = [
        'isAccountProvisioned',
        'provisioningState',
    ];

    protected $fillable = [
        'applicantName',
        'email',
        'password',
        'phoneNumber',
        'serviceName',
        'price',
        'description',
        'paymentAccount',
        'openTime',
        'closeTime',
        'duration',
        'locationID',
        'serviceStatus',
        'daysOfWeek',
        'serviceSubscriptionPlanID',
        'planPriceID',
        'status',
        'reviewedByUserID',
        'reviewedAt',
        'rejectionReason',
        'createdUserID',
        'createdServiceID',
        'createdSubscriptionID',
    ];

    protected function casts(): array
    {
        return [
            'reviewedAt' => 'datetime',
        ];
    }

    public function serviceSubscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(ServiceSubscriptionPlan::class, 'serviceSubscriptionPlanID');
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(ServiceSubscriptionPlan::class, 'serviceSubscriptionPlanID');
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(ServicePlanPrice::class, 'planPriceID');
    }

    public function requestedPlanPrice(): BelongsTo
    {
        return $this->belongsTo(ServicePlanPrice::class, 'planPriceID');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewedByUserID');
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdUserID');
    }

    public function createdService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'createdServiceID');
    }

    public function createdSubscription(): BelongsTo
    {
        return $this->belongsTo(ServiceSubscription::class, 'createdSubscriptionID');
    }

    public function getIsAccountProvisionedAttribute(): bool
    {
        return $this->status === 'approved' && $this->createdUserID !== null;
    }

    public function getProvisioningStateAttribute(): string
    {
        if ($this->status === 'approved' && $this->createdUserID === null) {
            return 'approved_incomplete';
        }

        return (string) $this->status;
    }
}
