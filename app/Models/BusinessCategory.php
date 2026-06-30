<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessCategory extends Model
{
    protected $table = 'business_categories';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'icon',
        'sortOrder',
        'isActive',
    ];

    protected function casts(): array
    {
        return [
            'sortOrder' => 'integer',
            'isActive' => 'boolean',
        ];
    }

    public function areas()
    {
        return $this->hasMany(Area::class, 'businessCategoryID');
    }

    public function scopeActive($query)
    {
        return $query->where('isActive', true);
    }

    public function scopeForUsageType($query, string $usageType)
    {
        return $query->where('type', $usageType);
    }
}
