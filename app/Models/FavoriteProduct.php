<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoriteProduct extends Model
{
    protected $fillable = [
        'userID',
        'productID',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userID');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productID');
    }
}
