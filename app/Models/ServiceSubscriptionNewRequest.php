<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSubscriptionNewRequest extends Model
{
    protected $table = 'service_subscription_new_requests';

    protected $fillable = [
        'serviceSubscriptionID',
        'requestedServiceSubscriptionPlanID',
        'requestedPlanPriceID',
        'applicantNote',
        'requestedByUserID',
        'status',
        'reviewedByUserID',
        'reviewedAt',
        'rejectionReason',
    ];

    protected function casts(): array
    {
        return [
            'reviewedAt' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ServiceSubscription::class, 'serviceSubscriptionID');
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(ServiceSubscriptionPlan::class, 'requestedServiceSubscriptionPlanID');
    }

    public function requestedPlanPrice(): BelongsTo
    {
        return $this->belongsTo(ServicePlanPrice::class, 'requestedPlanPriceID');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requestedByUserID');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewedByUserID');
    }
}
