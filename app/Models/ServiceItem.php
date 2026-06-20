<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceItem extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'serviceID',
        'name',
        'price',
        'duration',
        'status',
    ];

    public function scopeActive($query)
    {
        return $query->where(function ($query) {
            $query->where('status', self::STATUS_ACTIVE)
                ->orWhereNull('status');
        });
    }

    public function isActive(): bool
    {
        $status = $this->status ?? self::STATUS_ACTIVE;

        return $status === self::STATUS_ACTIVE;
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'serviceID');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_service_item', 'serviceItemID', 'employeeID')
            ->withPivot('price');
    }

    /**
     * Price for this item when performed by the given employee.
     * Uses pivot `price` when set; otherwise `service_items.price`.
     */
    public function priceForEmployee(int $employeeId): int
    {
        if ($this->relationLoaded('employees')) {
            $match = $this->employees->firstWhere('id', $employeeId);
            if ($match !== null && $match->pivot !== null && $match->pivot->price !== null) {
                return (int) $match->pivot->price;
            }
        }

        $attached = $this->employees()->where('employees.id', $employeeId)->first();

        if ($attached !== null && $attached->pivot->price !== null) {
            return (int) $attached->pivot->price;
        }

        return (int) $this->price;
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'serviceItemID');
    }

    public function basketItems()
    {
        return $this->morphMany(BasketItem::class, 'item', 'itemType', 'itemID');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable', 'mediableType', 'mediableID');
    }

    public function rates()
    {
        return $this->morphMany(Rate::class, 'rateable', 'rateableType', 'rateableID');
    }

    
}
