<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSubscriptionExtensionRequest extends Model
{
    protected $table = 'store_subscription_extension_requests';

    protected $appends = [
        'requestedPlan',
        'requestedPlanPrice',
    ];

    protected $fillable = [
        'storeSubscriptionID',
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
        return $this->belongsTo(StoreSubscription::class, 'storeSubscriptionID');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requestedByUserID');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewedByUserID');
    }

    public function getRequestedPlanAttribute(): ?StoreSubscriptionPlan
    {
        return $this->subscription?->storeSubscriptionPlan;
    }

    public function getRequestedPlanPriceAttribute(): ?StorePlanPrice
    {
        return $this->subscription?->planPrice;
    }
}
