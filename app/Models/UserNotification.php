<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'userID',
        'title',
        'body',
        'type',
        'data',
        'readAt',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'readAt' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userID');
    }
}
