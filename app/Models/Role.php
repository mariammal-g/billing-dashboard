<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the users for the role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_role_mapping', 'role_id', 'user_id');
    }

    /**
     * Get the user role mappings.
     */
    public function userRoleMappings()
    {
        return $this->hasMany(UserRoleMapping::class);
    }
}