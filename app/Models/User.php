<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Enquiries submitted by this user.
     * The enquiries table doesn't yet have a customer_id FK (Phase 3); for now
     * we match on email — which is unique and immutable per user.
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class, 'email', 'email');
    }

    /**
     * Reviews written by this user (matched on customer_email).
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_email', 'email');
    }
}
