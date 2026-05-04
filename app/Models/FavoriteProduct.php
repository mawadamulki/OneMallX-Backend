<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FavoriteProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'favoriteID',
        'productID',
    ];

    public function favorite()
    {
        return $this->belongsTo(Favorite::class, 'favoriteID');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'productID');
    }
}

