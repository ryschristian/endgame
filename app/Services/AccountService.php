<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\AccountRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function lockAdministration(): object
    {
        return DB::table('account_administration')->where('id', 1)->lockForUpdate()->firstOrFail();
    }

    public function requireAdministrator(User $actor): User
    {
        $freshActor = User::findOrFail($actor->id);
        if (! $freshActor->is_active || ! $freshActor->is_system_admin || $freshActor->auth_version !== $actor->auth_version) {
            throw new AuthorizationException;
        }
        return $freshActor;
    }

    public function bootstrap(string $name, string $email, #[\SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password) {
            $administration = $this->lockAdministration();
            if ($administration->bootstrapped_at || User::where('is_system_admin', true)->exists()) {
                throw ValidationException::withMessages(['email' => 'The System Admin has already been initialized.']);
            }
            $values = ['name' => $name, 'email' => AccountRules::normalizeEmail($email), 'password' => $password, 'password_confirmation' => $password];
            Validator::make($values, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:254', Rule::unique('users')],
                'password' => AccountRules::password(),
            ])->validate();

            $user = new User(collect($values)->only(['name', 'email', 'password'])->all());
            $user->forceFill(['is_active' => true, 'is_system_admin' => true, 'email_verified_at' => now()])->save();
            DB::table('account_administration')->where('id', 1)->update(['bootstrapped_at' => now()]);
            AuditEvent::create(['action' => 'account.bootstrapped', 'target_user_id' => $user->id]);
            return $user->refresh();
        }, 3);
    }

    public function setActive(User $actor, User $target, bool $active, #[\SensitiveParameter] string $password): User
    {
        return $this->changeAccess($actor, $target, 'is_active', $active, $password);
    }

    public function setAdministrator(User $actor, User $target, bool $administrator, #[\SensitiveParameter] string $password): User
    {
        return $this->changeAccess($actor, $target, 'is_system_admin', $administrator, $password);
    }

    private function changeAccess(User $actor, User $target, string $field, bool $value, #[\SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($actor, $target, $field, $value, $password) {
            $this->lockAdministration();
            $actor = $this->requireAdministrator($actor);
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->verifyPassword($actor, $password);
            if ($field === 'is_active' && ! $value && $actor->is($target)) {
                throw ValidationException::withMessages(['account' => 'You cannot suspend your own account.']);
            }
            if ($target->{$field} === $value) {
                throw ValidationException::withMessages(['account' => 'This account has already changed. Refresh and try again.']);
            }
            if (! $value && $target->is_active && $target->is_system_admin
                && ! User::where('is_active', true)->where('is_system_admin', true)->whereKeyNot($target->id)->exists()) {
                throw ValidationException::withMessages(['account' => 'At least one active System Admin must remain.']);
            }
            $before = $target->{$field};
            $target->{$field} = $value;
            if ($field === 'is_active' && ! $value) {
                $this->revokeSessions($target);
            }
            $target->save();
            $action = $field === 'is_active' ? ($value ? 'account.activated' : 'account.suspended') : ($value ? 'account.admin_granted' : 'account.admin_revoked');
            AuditEvent::create(['actor_id' => $actor->id, 'target_user_id' => $target->id, 'action' => $action, 'metadata' => ['before' => [$field => $before], 'after' => [$field => $value]]]);
            return $target;
        }, 3);
    }

    public function verifyPassword(User $user, #[\SensitiveParameter] string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
    }

    public function updateProfile(User $actor, string $name): User
    {
        return DB::transaction(function () use ($actor, $name) {
            $this->lockAdministration();
            $user = $this->activeUser($actor);
            if ($user->name !== $name) {
                $before = $user->name;
                $user->update(['name' => $name]);
                AuditEvent::create(['actor_id' => $user->id, 'target_user_id' => $user->id, 'action' => 'account.profile_updated', 'metadata' => ['before' => ['name' => $before], 'after' => ['name' => $name]]]);
            }
            return $user;
        }, 3);
    }

    public function changePassword(User $actor, #[\SensitiveParameter] string $currentPassword, #[\SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($actor, $currentPassword, $password) {
            $this->lockAdministration();
            $user = $this->activeUser($actor);
            $this->verifyPassword($user, $currentPassword);
            $this->revokeSessions($user);
            $user->password = $password;
            $user->save();
            AuditEvent::create(['actor_id' => $user->id, 'target_user_id' => $user->id, 'action' => 'account.password_changed']);
            return $user;
        }, 3);
    }

    private function activeUser(User $actor): User
    {
        $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        if (! $user->is_active || $user->auth_version !== $actor->auth_version) {
            throw new \Illuminate\Auth\AuthenticationException;
        }
        return $user;
    }

    public function revokeSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $user->auth_version++;
        $user->remember_token = Str::random(60);
    }
}
