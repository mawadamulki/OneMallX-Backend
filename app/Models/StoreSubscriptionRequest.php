<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSubscriptionRequest extends Model
{
    protected $hidden = [
        'password',
    ];

    protected $fillable = [
        'applicantName',
        'email',
        'password',
        'phoneNumber',
        'storeName',
        'description',
        'storeStatus',
        'paymentAccount',
        'storeSubscriptionPlanID',
        'planPriceID',
        'status',
        'reviewedByUserID',
        'reviewedAt',
        'rejectionReason',
        'createdUserID',
        'createdStoreID',
        'createdSubscriptionID',
    ];

    protected function casts(): array
    {
        return [
            'reviewedAt' => 'datetime',
        ];
    }

    public function storeSubscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(StoreSubscriptionPlan::class, 'storeSubscriptionPlanID');
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(StoreSubscriptionPlan::class, 'storeSubscriptionPlanID');
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(StorePlanPrice::class, 'planPriceID');
    }

    public function requestedPlanPrice(): BelongsTo
    {
        return $this->belongsTo(StorePlanPrice::class, 'planPriceID');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewedByUserID');
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdUserID');
    }

    public function createdStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'createdStoreID');
    }

    public function createdSubscription(): BelongsTo
    {
        return $this->belongsTo(StoreSubscription::class, 'createdSubscriptionID');
    }
}
