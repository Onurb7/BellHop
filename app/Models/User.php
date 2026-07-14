<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * A `guest` role account's matching row in `guests` (the person a
     * booking is actually for) — nullable because it's only set once
     * this account has been linked to a stay, not on every guest user.
     */
    public function guest(): HasOne
    {
        return $this->hasOne(Guest::class);
    }

    /**
     * Reads $this->settings (not a fresh query) so repeated calls in the
     * same request — e.g. reading date/time/week-start preferences one
     * after another — cost a single query, not one per key.
     */
    public function getSetting(string $key, ?string $default = null): ?string
    {
        return $this->settings->firstWhere('key', $key)?->value ?? $default;
    }
}
