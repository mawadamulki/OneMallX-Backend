<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_ACTION_TAKEN = 'action_taken';

    protected $fillable = [
        'rateID',
        'reporterUserID',
        'status',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class, 'rateID');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporterUserID');
    }
}
