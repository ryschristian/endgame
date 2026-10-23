<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\User;
use App\Support\AccountRules;
use App\Support\ProjectPermissions;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(private AccountService $accounts) {}

    public function currentActor(User $actor): User
    {
        $fresh = User::findOrFail($actor->id);
        if (! $fresh->is_active || $fresh->auth_version !== $actor->auth_version) {
            throw new AuthorizationException;
        }

        return $fresh;
    }

    public function create(User $actor, array $values): Project
    {
        return DB::transaction(function () use ($actor, $values) {
            $this->accounts->lockAdministration();
            $actor = $this->accounts->requireAdministrator($actor);
            $values['key'] = strtoupper(trim($values['key'] ?? ''));
            $values['manager_email'] = AccountRules::normalizeEmail($values['manager_email'] ?? '');
            $values = Validator::make($values, [
                ...$this->settingsRules(), 'key' => ['required', 'regex:/^[A-Z][A-Z0-9]{1,9}$/D', Rule::unique('projects', 'key')],
                'manager_email' => ['required', 'email'],
            ])->validate();
            $manager = $this->eligibleUser($values['manager_email']);
            $values['timezone'] = Project::TEAM_TIMEZONE;
            $project = Project::create(collect($values)->only(['key', 'name', 'description', 'timezone'])->all());
            $this->initializeManager($project, $manager);
            $this->audit($actor, $project, 'project.created', ['after' => $project->only(['key', 'name', 'description', 'timezone']), 'manager_id' => $manager->id]);

            return $project->refresh();
        }, 3);
    }

    public function initializeManager(Project $project, User $manager): void
    {
        $role = $project->roles()->create(['name' => 'Project Manager']);
        $role->permissions()->sync(ProjectPermissions::keys());
        $member = $project->members()->create(['user_id' => $manager->id, 'joined_at' => now()]);
        $member->roles()->sync([$role->id => ['project_id' => $project->id]]);
    }

    public function settingsRules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'], 'timezone' => ['sometimes', Rule::in([Project::TEAM_TIMEZONE])]];
    }

    public function change(User $actor, Project $project, int $revision, string $permission, Closure $operation, bool $allowArchived = false): mixed
    {
        return DB::transaction(function () use ($actor, $project, $revision, $permission, $operation, $allowArchived) {
            $this->accounts->lockAdministration();
            $actor = $this->currentActor($actor);
            $project = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            if (! $project->allows($actor, $permission)) {
                throw new AuthorizationException;
            }
            if ($project->revision !== $revision) {
                $this->invalid('revision', 'This project changed. Reload the page and try again.');
            }
            if ($project->archived_at && ! $allowArchived) {
                $this->invalid('project', 'Restore this project before making changes.');
            }
            $result = $operation($actor, $project);
            $this->ensureManager($project);
            $project->increment('revision');

            return $result;
        }, 3);
    }

    public function update(User $actor, Project $project, int $revision, array $values): void
    {
        $this->change($actor, $project, $revision, 'project.update', function ($actor, $project) use ($values) {
            $values = Validator::make($values, [...$this->settingsRules(), 'key' => ['prohibited']])->validate();
            $values['timezone'] = Project::TEAM_TIMEZONE;
            $before = $project->only(['name', 'description', 'timezone']);
            $project->fill($values);
            if (! $project->isDirty()) {
                $this->invalid('project', 'No settings have changed.');
            }
            $project->save();
            $this->audit($actor, $project, 'project.updated', ['before' => $before, 'after' => $project->only(array_keys($before))]);
        });
    }

    public function setArchived(User $actor, Project $project, int $revision, bool $archived): void
    {
        $this->change($actor, $project, $revision, $archived ? 'project.archive' : 'project.restore', function ($actor, $project) use ($archived) {
            if ((bool) $project->archived_at === $archived) {
                $this->invalid('project', 'This project has already changed.');
            }
            $project->update(['archived_at' => $archived ? now() : null]);
            $this->audit($actor, $project, $archived ? 'project.archived' : 'project.restored');
        }, ! $archived);
    }

    public function saveRole(User $actor, Project $project, int $revision, ?Role $role, string $name, array $keys): Role
    {
        return $this->change($actor, $project, $revision, 'project.roles.manage', function ($actor, $project) use ($role, $name, $keys) {
            $role = $role ? $project->roles()->findOrFail($role->id) : null;
            $values = Validator::make(['name' => trim($name), 'permission_keys' => $keys], [
                'name' => ['required', 'string', 'max:80'], 'permission_keys' => ['required', 'array', 'contains:project.view'],
                'permission_keys.*' => ['string', 'distinct', Rule::in(ProjectPermissions::keys())],
            ])->validate();
            if ($project->roles()->whereRaw('LOWER(name) = ?', [mb_strtolower($values['name'])])->when($role, fn ($q) => $q->whereKeyNot($role->id))->exists()) {
                $this->invalid('name', 'A role with this name already exists.');
            }
            $before = $role ? ['name' => $role->name, 'permission_keys' => $role->permissions()->pluck('key')->all()] : null;
            $this->checkGrant($actor, $project, array_merge($keys, $before['permission_keys'] ?? []));
            if ($before && $before['name'] === $values['name'] && ! array_diff($keys, $before['permission_keys']) && ! array_diff($before['permission_keys'], $keys)) {
                $this->invalid('role', 'No role settings have changed.');
            }
            $role = $role ?? $project->roles()->make();
            $role->name = $values['name'];
            $role->save();
            $role->permissions()->sync($keys);
            $this->audit($actor, $project, $before ? 'project.role_updated' : 'project.role_created', ['role_id' => $role->id, 'before' => $before, 'after' => ['name' => $role->name, 'permission_keys' => $keys]]);

            return $role;
        });
    }

    public function deleteRole(User $actor, Project $project, int $revision, Role $role): void
    {
        $this->change($actor, $project, $revision, 'project.roles.manage', function ($actor, $project) use ($role) {
            $role = $project->roles()->findOrFail($role->id);
            $keys = $role->permissions()->pluck('key')->all();
            $this->checkGrant($actor, $project, $keys);
            $pending = $project->invitations()->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->whereHas('roles', fn ($q) => $q->where('roles.id', $role->id))->exists();
            if ($role->members()->exists() || $pending) {
                $this->invalid('role', 'Reassign members and revoke pending invitations before deleting this role.');
            }
            $this->audit($actor, $project, 'project.role_deleted', ['role_id' => $role->id, 'before' => ['name' => $role->name, 'permission_keys' => $keys]]);
            DB::table('project_invitation_roles')->where('role_id', $role->id)->delete();
            $role->permissions()->detach();
            $role->delete();
        });
    }

    public function eligibleUser(string $email): User
    {
        $user = User::where('email', AccountRules::normalizeEmail($email))->where('is_active', true)->first();
        if (! $user) {
            $this->invalid('email', 'Choose an eligible active account.');
        }

        return $user;
    }

    public function checkGrant(User $actor, Project $project, array $keys): void
    {
        if (! $actor->is_system_admin && array_diff($keys, $project->permissionKeys($actor))) {
            throw new AuthorizationException;
        }
    }

    public function selectedRoles(User $actor, Project $project, array $ids): array
    {
        Validator::make(['role_ids' => $ids], ['role_ids' => ['array'], 'role_ids.*' => ['integer', 'distinct']])->validate();
        $roles = $project->roles()->with('permissions')->whereIn('id', $ids)->get();
        if ($roles->count() !== count($ids)) {
            $this->invalid('role_ids', 'Choose roles from this project.');
        }
        $this->checkGrant($actor, $project, $roles->flatMap(fn ($r) => $r->permissions->pluck('key'))->unique()->all());

        return $roles->modelKeys();
    }

    public function addMember(User $actor, Project $project, int $revision, string $email, array $roleIds): ProjectMember
    {
        return $this->change($actor, $project, $revision, 'project.members.manage', function ($actor, $project) use ($email, $roleIds) {
            $user = $this->eligibleUser($email);
            $ids = $this->selectedRoles($actor, $project, $roleIds);
            $member = $this->join($project, $user, $ids);
            $invitations = $project->invitations()->where('user_id', $user->id)->whereNull('accepted_at')->whereNull('revoked_at')->get();
            foreach ($invitations as $invitation) {
                $invitation->update(['revoked_at' => now()]);
                $this->audit($actor, $project, 'project.invitation_revoked', ['invitation_id' => $invitation->id]);
            }
            $this->audit($actor, $project, 'project.member_added', ['member_id' => $member->id, 'user_id' => $user->id, 'after' => ['role_ids' => $ids]]);

            return $member;
        });
    }

    public function join(Project $project, User $user, array $roleIds): ProjectMember
    {
        $member = $project->members()->where('user_id', $user->id)->first();
        if ($member && ! $member->removed_at) {
            $this->invalid('email', 'This account is already a project member.');
        }
        $member = $member ?? $project->members()->make(['user_id' => $user->id]);
        $member->fill(['joined_at' => now(), 'removed_at' => null])->save();
        $member->roles()->sync(array_fill_keys($roleIds, ['project_id' => $project->id]));

        return $member;
    }

    public function assignRoles(User $actor, Project $project, int $revision, ProjectMember $member, array $ids): void
    {
        $this->change($actor, $project, $revision, 'project.members.manage', function ($actor, $project) use ($member, $ids) {
            $member = $project->members()->whereNull('removed_at')->findOrFail($member->id);
            $this->checkGrant($actor, $project, $member->assignedPermissionKeys());
            $ids = $this->selectedRoles($actor, $project, $ids);
            $before = $member->roles()->pluck('roles.id')->all();
            if (! array_diff($before, $ids) && ! array_diff($ids, $before)) {
                $this->invalid('role_ids', 'These roles are already assigned.');
            }
            $member->roles()->sync(array_fill_keys($ids, ['project_id' => $project->id]));
            $this->audit($actor, $project, 'project.member_roles_changed', ['member_id' => $member->id, 'before' => ['role_ids' => $before], 'after' => ['role_ids' => $ids]]);
        });
    }

    public function removeMember(User $actor, Project $project, int $revision, ProjectMember $member): void
    {
        $this->change($actor, $project, $revision, 'project.members.manage', function ($actor, $project) use ($member) {
            $member = $project->members()->whereNull('removed_at')->findOrFail($member->id);
            $this->checkGrant($actor, $project, $member->assignedPermissionKeys());
            $before = $member->roles()->pluck('roles.id')->all();
            $member->roles()->detach();
            $member->update(['removed_at' => now()]);
            foreach ($project->invitations()->where('user_id', $member->user_id)->whereNull('accepted_at')->whereNull('revoked_at')->get() as $invitation) {
                $invitation->update(['revoked_at' => now()]);
                $this->audit($actor, $project, 'project.invitation_revoked', ['invitation_id' => $invitation->id]);
            }
            $this->audit($actor, $project, 'project.member_removed', ['member_id' => $member->id, 'user_id' => $member->user_id, 'before' => ['role_ids' => $before]]);
        });
    }

    public function ensureManager(Project $project): void
    {
        $members = $project->members()->whereNull('removed_at')->whereHas('user', fn ($q) => $q->where('is_active', true))->with('user')->get();
        foreach ($members as $member) {
            if (! array_diff(ProjectPermissions::keys(), $project->permissionKeys($member->user))) {
                return;
            }
        }
        $this->invalid('manager', 'Keep at least one active project manager for '.$project->key.'.');
    }

    public function ensureSuspensionAllowed(User $user): void
    {
        $projects = Project::whereHas('members', fn ($q) => $q->where('user_id', $user->id)->whereNull('removed_at'))->orderBy('id')->lockForUpdate()->get();
        foreach ($projects as $project) {
            $this->ensureManager($project);
        }
    }

    public function audit(User $actor, Project $project, string $action, array $metadata = []): void
    {
        AuditEvent::create(['actor_id' => $actor->id, 'project_id' => $project->id, 'action' => $action, 'metadata' => $metadata ?: null]);
    }

    public function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
