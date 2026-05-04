<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSubscriptionExtensionRequest extends Model
{
    protected $table = 'service_subscription_extension_requests';

    protected $fillable = [
        'serviceSubscriptionID',
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

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requestedByUserID');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewedByUserID');
    }
}
