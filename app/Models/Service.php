<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'serviceOwnerID',
        'price',
        'areaID',
        'description',
        'logo',
        'paymentAccount',
        'openTime',
        'closeTime',
        'duration',
        'locationID',
        'status',
        'accountStatus',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'serviceOwnerID');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'areaID');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'locationID');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'serviceID');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'serviceID');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable', 'mediableType', 'mediableID');
    }

    public function rates()
    {
        return $this->morphMany(Rate::class, 'rateable', 'rateableType', 'rateableID');
    }

    public function subscriptions()
    {
        return $this->hasMany(ServiceSubscription::class, 'serviceID');
    }

    public function serviceItems()
    {
        return $this->hasMany(ServiceItem::class, 'serviceID');
    }

    public function workingDays(): HasMany
    {
        return $this->hasMany(ServiceWorkingDay::class, 'service_id');
    }

    public function worksOnIsoWeekday(int $iso): bool
    {
        if ($this->relationLoaded('workingDays')) {
            return $this->workingDays->contains('weekday', $iso);
        }

        return $this->workingDays()->where('weekday', $iso)->exists();
    }

    public function hasWorkingDaySchedule(): bool
    {
        return $this->relationLoaded('workingDays')
            ? $this->workingDays->isNotEmpty()
            : $this->workingDays()->exists();
    }

    /**
     * Calendar / availability listing: no configured days means the service shows no open days.
     */
    public function isOpenOnWeekdayForDisplay(int $isoWeekday): bool
    {
        if (! $this->hasWorkingDaySchedule()) {
            return false;
        }

        return $this->worksOnIsoWeekday($isoWeekday);
    }

    /**
     * Booking validation: no configured days matches legacy behaviour (do not block by weekday).
     */
    public function allowsBookingOnWeekday(int $isoWeekday): bool
    {
        if (! $this->hasWorkingDaySchedule()) {
            return true;
        }

        return $this->worksOnIsoWeekday($isoWeekday);
    }
}
