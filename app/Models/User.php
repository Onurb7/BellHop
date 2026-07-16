<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\SetPasswordMail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
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
        'first_name',
        'last_name',
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

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
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

    /**
     * Overrides the default `Illuminate\Auth\Notifications\ResetPassword`
     * notification — every other email in this app is a hand-styled
     * Mailable (gold theme, Bellhop header), not a framework Notification,
     * so this keeps password-reset/set-up email visually consistent with
     * the rest. Used both for a genuine "forgot password" request and,
     * proactively, right after a public self-service booking creates a
     * brand-new account.
     */
    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email)->send(new SetPasswordMail($this, $token));
    }
}
