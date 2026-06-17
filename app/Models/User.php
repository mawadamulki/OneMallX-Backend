<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phoneNumber',
        'status',
        'otp_code',
        'otp_expires_at',
        'password_reset_otp',
        'password_reset_otp_expires_at',
        'is_verified',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->media->first()?->url;
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable', 'mediableType', 'mediableID');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
        'otp_expires_at',
        'password_reset_otp',
        'password_reset_otp_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_reset_otp_expires_at' => 'datetime',
        ];
    }

    public function rates()
    {
        return $this->hasMany(Rate::class, 'userID');
    }

    public function rateReports()
    {
        return $this->hasMany(RateReport::class, 'reporterUserID');
    }
}
