<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSubscriptionNewRequest extends Model
{
    protected $table = 'store_subscription_new_requests';

    protected $fillable = [
        'storeSubscriptionID',
        'requestedStoreSubscriptionPlanID',
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
        return $this->belongsTo(StoreSubscription::class, 'storeSubscriptionID');
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(StoreSubscriptionPlan::class, 'requestedStoreSubscriptionPlanID');
    }

    public function requestedPlanPrice(): BelongsTo
    {
        return $this->belongsTo(StorePlanPrice::class, 'requestedPlanPriceID');
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
