<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserRoleMapping extends Model
{
    use HasFactory;

    protected $table = 'user_role_mapping';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'role_id',
    ];

    /**
     * Get the user that owns the role mapping.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the role that owns the role mapping.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
