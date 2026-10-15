<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'status',
        'attempts',
        'last_attempt_at',
        'email_verified_at',
        'created_by',
        'modified_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'last_attempt_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get the roles for the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role_mapping', 'user_id', 'role_id');
    }

    /**
     * Get the user role mappings.
     */
    public function userRoleMappings()
    {
        return $this->hasMany(UserRoleMapping::class);
    }

    /**
     * Get the full name attribute.
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Check if user can attempt login (not blocked)
     */
    public function canAttemptLogin(): bool
    {
        // If no attempts recorded, allow login
        if (!$this->last_attempt_at) {
            return true;
        }

        // If attempts < 5, allow login
        if ($this->attempts < 5) {
            return true;
        }

        // If 24 hours have passed since last attempt, reset and allow login
        if ($this->last_attempt_at->copy()->addDay()->isPast()) {
            $this->resetLoginAttempts();
            return true;
        }

        // User is blocked
        return false;
    }

    /**
     * Record a failed login attempt
     */
    public function recordFailedAttempt(): void
    {
        $this->increment('attempts');
        $this->update(['last_attempt_at' => now()]);
    }

    /**
     * Reset login attempts (on successful login or password reset)
     */
    public function resetLoginAttempts(): void
    {
        $this->update([
            'attempts' => 0,
            'last_attempt_at' => null
        ]);
    }

    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts(): int
    {
        return max(0, 5 - $this->attempts);
    }

    /**
     * Check if user is currently blocked
     */
    public function isBlocked(): bool
    {
        return $this->attempts >= 5 && $this->last_attempt_at && $this->last_attempt_at->copy()->addDay()->isFuture();
    }

    /**
     * Clean up expired password reset tokens
     */
    public static function cleanupExpiredTokens(): void
    {
        \DB::table('password_reset_tokens')
            ->where('expires_at', '<', now())
            ->delete();
    }
}
