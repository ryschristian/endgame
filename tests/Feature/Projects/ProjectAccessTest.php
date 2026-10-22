<?php

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ProjectService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('creates a permanent project and a fully authorized manager atomically', function () {
    [$admin, $manager, $project] = projectFixture();
    expect($project->key)->toBe('END')->and($project->revision)->toBe(1)
        ->and($project->allows($manager, 'project.roles.manage'))->toBeTrue()
        ->and($project->allows($admin, 'project.view'))->toBeTrue();
    expect($project->members()->where('user_id', $admin->id)->exists())->toBeFalse();
    $this->assertDatabaseHas('audit_events', ['project_id' => $project->id, 'action' => 'project.created']);
});

it('rejects non-admin creation and invalid project identity', function () {
    $user = User::factory()->create();
    expect(fn () => app(ProjectService::class)->create($user, []))->toThrow(AuthorizationException::class);
    $admin = User::factory()->admin()->create();
    foreach ([['key' => '1bad'], ['timezone' => 'Bad/Zone'], ['manager_email' => 'unknown@example.com']] as $invalid) {
        expect(fn () => app(ProjectService::class)->create($admin, array_merge(['key' => 'GOOD', 'name' => 'Good', 'timezone' => 'Asia/Manila', 'manager_email' => $user->email], $invalid)))->toThrow(ValidationException::class);
    }
    expect(Project::count())->toBe(0);
});

it('combines role permissions without deriving authority from names or membership', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $reader = $service->saveRole($manager, $project, $project->fresh()->revision, null, 'Project Manager 2', ['project.view']);
    $editor = $service->saveRole($manager, $project, $project->fresh()->revision, null, 'Editor', ['project.view', 'project.update']);
    $user = User::factory()->create();
    $member = $service->addMember($manager, $project, $project->fresh()->revision, $user->email, []);
    expect($project->allows($user, 'project.view'))->toBeFalse();
    $service->assignRoles($manager, $project, $project->fresh()->revision, $member, [$reader->id, $editor->id]);
    expect($project->allows($user, 'project.update'))->toBeTrue()->and($project->allows($user, 'project.roles.manage'))->toBeFalse();
});

it('rejects stale revisions immutable keys and archived business writes', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $service->update($manager, $project, 1, ['name' => 'New name', 'description' => '', 'timezone' => 'Asia/Manila']);
    expect(fn () => $service->update($manager, $project, 1, ['name' => 'Stale', 'timezone' => 'Asia/Manila']))->toThrow(ValidationException::class);
    expect(fn () => $service->update($manager, $project, 2, ['key' => 'OTHER', 'name' => 'Rename', 'timezone' => 'Asia/Manila']))->toThrow(ValidationException::class);
    $service->setArchived($manager, $project, 2, true);
    expect(fn () => $service->saveRole($admin, $project, 3, null, 'Reader', ['project.view']))->toThrow(ValidationException::class);
    expect($project->fresh()->allows($manager, 'project.view'))->toBeTrue();
    $service->setArchived($manager, $project, 3, false);
    expect($project->fresh()->archived_at)->toBeNull();
});

it('preserves the final manager during role changes removal and suspension', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $member = $project->members()->where('user_id', $manager->id)->firstOrFail();
    $role = $member->roles()->firstOrFail();
    expect(fn () => $service->assignRoles($admin, $project, 1, $member, []))->toThrow(ValidationException::class);
    expect(fn () => $service->removeMember($admin, $project, 1, $member))->toThrow(ValidationException::class);
    expect(fn () => $service->saveRole($admin, $project, 1, $role, 'Manager', ['project.view']))->toThrow(ValidationException::class);
    expect(fn () => app(AccountService::class)->setActive($admin, $manager, false, 'password'))->toThrow(ValidationException::class);
    expect($manager->fresh()->is_active)->toBeTrue()->and($project->fresh()->revision)->toBe(1);
    $other = User::factory()->create();
    $service->addMember($admin, $project, 1, $other->email, [$role->id]);
    app(AccountService::class)->setActive($admin, $manager, false, 'password');
    expect($manager->fresh()->is_active)->toBeFalse();
});

it('prevents grants above the actors authority and cross-project role selection', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $limited = User::factory()->create();
    $role = $service->saveRole($manager, $project, 1, null, 'Coordinator', ['project.view', 'project.members.manage', 'project.roles.manage']);
    $service->addMember($manager, $project, 2, $limited->email, [$role->id]);
    expect(fn () => $service->saveRole($limited, $project, 3, null, 'Escalate', ['project.view', 'project.archive']))->toThrow(AuthorizationException::class);
    $managerRole = $project->roles()->where('name', 'Project Manager')->firstOrFail();
    expect(fn () => $service->addMember($limited, $project, 3, User::factory()->create()->email, [$managerRole->id]))->toThrow(AuthorizationException::class);
    $other = $service->create($admin, ['key' => 'OTHER', 'name' => 'Other', 'timezone' => 'Asia/Manila', 'manager_email' => $manager->email]);
    expect(fn () => $service->addMember($manager, $project, 3, User::factory()->create()->email, [$other->roles()->first()->id]))->toThrow(ValidationException::class);
});

it('retains removed membership identity and reinstates only explicitly chosen roles', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $reader = $service->saveRole($manager, $project, 1, null, 'Reader', ['project.view']);
    $user = User::factory()->create();
    $member = $service->addMember($manager, $project, 2, $user->email, [$reader->id]);
    $service->removeMember($manager, $project, 3, $member);
    expect($project->allows($user, 'project.view'))->toBeFalse();
    $again = $service->addMember($manager, $project, 4, $user->email, []);
    expect($again->id)->toBe($member->id)->and($again->roles()->count())->toBe(0);
});

it('rolls back project creation if the audit cannot be written', function () {
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->create();
    AuditEvent::creating(fn () => throw new RuntimeException('audit unavailable'));
    try {
        expect(fn () => app(ProjectService::class)->create($admin, ['key' => 'FAIL', 'name' => 'Fail', 'timezone' => 'Asia/Manila', 'manager_email' => $manager->email]))->toThrow(RuntimeException::class);
        expect(Project::count())->toBe(0);
    } finally {
        AuditEvent::flushEventListeners();
    }
});

it('does not let limited managers change privileged assignments on suspended members', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $fullRole = $project->roles()->firstOrFail();
    $service->addMember($admin, $project, 1, $admin->email, [$fullRole->id]);
    $limited = User::factory()->create();
    $limitedRole = $service->saveRole($admin, $project, 2, null, 'Limited', ['project.view', 'project.members.manage']);
    $service->addMember($admin, $project, 3, $limited->email, [$limitedRole->id]);
    app(AccountService::class)->setActive($admin, $manager, false, 'password');
    $member = $project->members()->where('user_id', $manager->id)->firstOrFail();
    expect(fn () => $service->assignRoles($limited, $project, 4, $member, []))->toThrow(AuthorizationException::class);
    expect(fn () => $service->removeMember($limited, $project, 4, $member))->toThrow(AuthorizationException::class);
});

it('rejects stale actor sessions even when their account is still active', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    DB::table('users')->where('id', $manager->id)->increment('auth_version');
    expect(fn () => $service->saveRole($manager, $project, 1, null, 'Reader', ['project.view']))->toThrow(AuthorizationException::class);
});

it('enforces case-insensitive role names and blocks deletion while assigned', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $role = $service->saveRole($manager, $project, 1, null, 'Reader', ['project.view']);
    expect(fn () => $service->saveRole($manager, $project, 2, null, 'READER', ['project.view']))->toThrow(ValidationException::class);
    $service->addMember($manager, $project, 2, User::factory()->create()->email, [$role->id]);
    expect(fn () => $service->deleteRole($manager, $project, 3, $role))->toThrow(ValidationException::class);
    $unused = $service->saveRole($manager, $project, 3, null, 'Unused', ['project.view']);
    $service->deleteRole($manager, $project, 4, $unused);
    $this->assertDatabaseMissing('roles', ['id' => $unused->id]);
    $this->assertDatabaseHas('audit_events', ['project_id' => $project->id, 'action' => 'project.role_deleted']);
});

it('enforces same-project role references at the database boundary', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $other = $service->create($admin, ['key' => 'OTHER', 'name' => 'Other', 'timezone' => 'Asia/Manila', 'manager_email' => $manager->email]);
    $member = $project->members()->firstOrFail();
    expect(fn () => DB::table('member_roles')->insert(['project_id' => $project->id, 'project_member_id' => $member->id, 'role_id' => $other->roles()->firstOrFail()->id]))->toThrow(QueryException::class);
});

it('normalizes existing project timezones once while auditing the previous value', function () {
    [$admin, $manager, $project] = projectFixture();
    DB::table('projects')->where('id', $project->id)->update(['timezone' => 'Asia/Tokyo']);
    $migration = require database_path('migrations/2026_10_22_000003_standardize_project_timezone.php');
    $migration->up();
    $migration->up();
    expect($project->fresh()->timezone)->toBe('Asia/Manila')->and($project->fresh()->revision)->toBe(2);
    $event = AuditEvent::where('project_id', $project->id)->where('action', 'project.timezone_standardized')->sole();
    expect($event->metadata['before']['timezone'])->toBe('Asia/Tokyo')->and($event->metadata['after']['timezone'])->toBe('Asia/Manila');
});
