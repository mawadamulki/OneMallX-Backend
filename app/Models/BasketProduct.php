<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BasketProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'basketID',
        'productID',
        'quantity',
        'unitPrice',
    ];

    protected $table = 'basket_products';

    public function basket()
    {
        return $this->belongsTo(Basket::class, 'basketID');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'productID');
    }
}

