<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'storeID',
        'parentID',
        'name',
        'slug',
        'sortOrder',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'storeID');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parentID');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parentID');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_category', 'categoryID', 'productID')
            ->withTimestamps();
    }
}
