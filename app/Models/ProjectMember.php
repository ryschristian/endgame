<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectMember extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'member_roles')->withPivot('project_id');
    }

    public function assignedPermissionKeys(): array
    {
        // Suspension disables use of permissions, but does not erase retained grants.
        return $this->roles()->with('permissions')->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('key'))->unique()->values()->all();
    }
}
