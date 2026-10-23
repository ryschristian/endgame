<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Project extends Model
{
    use HasFactory;

    public const TEAM_TIMEZONE = 'Asia/Manila';

    protected $attributes = ['timezone' => self::TEAM_TIMEZONE];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'revision' => 'integer'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    public function permissionKeys(User $user): array
    {
        if (! $user->is_active) {
            return [];
        }

        return DB::table('project_members as m')
            ->join('member_roles as mr', 'mr.project_member_id', '=', 'm.id')
            ->join('role_permissions as rp', 'rp.role_id', '=', 'mr.role_id')
            ->where('m.project_id', $this->id)->where('m.user_id', $user->id)->whereNull('m.removed_at')
            ->distinct()->pluck('rp.permission_key')->all();
    }

    public function allows(User $user, string $permission): bool
    {
        return $user->is_active && ($user->is_system_admin || in_array($permission, $this->permissionKeys($user), true));
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_active) {
            $query->whereRaw('1 = 0');
        } elseif (! $user->is_system_admin) {
            $query->whereHas('members', fn (Builder $members) => $members->where('user_id', $user->id)->whereNull('removed_at')
                ->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('permissions.key', 'project.view')));
        }
    }
}
