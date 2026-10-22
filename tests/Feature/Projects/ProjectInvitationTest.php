<?php

use App\Models\AuditEvent;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use App\Services\ProjectInvitationService;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

it('accepts a hashed single-use invitation only for its authenticated recipient', function () {
    [$admin, $manager, $project, $user, $reader, $invitation, $token, $service] = inviteProjectMember();
    expect($invitation->token_hash)->toBe(hash('sha256', $token))->and($invitation->toArray())->not->toHaveKey('token_hash');
    expect(fn () => $service->accept($manager, $token))->toThrow(ValidationException::class);
    $service->accept($user, $token);
    expect($project->allows($user, 'project.view'))->toBeTrue()->and($project->fresh()->revision)->toBe(4);
    expect(fn () => $service->accept($user, $token))->toThrow(ValidationException::class);
    expect(AuditEvent::where('action', 'project.invitation_accepted')->count())->toBe(1);
});

it('rejects expired revoked and archived invitation acceptance', function (string $state) {
    [$admin, $manager, $project, $user, $reader, $invitation, $token, $service] = inviteProjectMember();
    if ($state === 'expired') {
        $invitation->update(['expires_at' => now()->subSecond()]);
    }
    if ($state === 'revoked') {
        $invitation->update(['revoked_at' => now()]);
    }
    if ($state === 'archived') {
        $project->update(['archived_at' => now()]);
    }
    expect(fn () => $service->accept($user, $token))->toThrow(ValidationException::class);
    expect($project->members()->where('user_id', $user->id)->exists())->toBeFalse();
})->with(['expired', 'revoked', 'archived']);

it('rotates invitations and validates the current authorizer and role grants', function () {
    [$admin, $manager, $project, $user, $reader, $invitation, $token, $service] = inviteProjectMember();
    $service->resend($admin, $project, 3, $invitation, [$reader->id]);
    expect($invitation->fresh()->invited_by)->toBe($admin->id);
    expect(fn () => $service->accept($user, $token))->toThrow(ValidationException::class);
    $newToken = null;
    Notification::assertSentOnDemand(ProjectInvitationNotification::class, function ($notification) use (&$newToken, $token) {
        if ($notification->token !== $token) {
            $newToken = $notification->token;
        }

        return true;
    });
    $admin->forceFill(['is_system_admin' => false])->save();
    expect(fn () => $service->accept($user, $newToken))->toThrow(ValidationException::class);
    expect($invitation->fresh()->accepted_at)->toBeNull();
});

it('revokes a pending invitation when adding directly and preserves the selected roles', function () {
    [$admin, $manager, $project, $user, $reader, $invitation, $token, $service] = inviteProjectMember();
    app(ProjectService::class)->addMember($manager, $project, 3, $user->email, []);
    expect($invitation->fresh()->revoked_at)->not->toBeNull();
    expect(fn () => $service->accept($user, $token))->toThrow(ValidationException::class);
    expect($project->members()->where('user_id', $user->id)->first()->roles()->count())->toBe(0);
});

it('retains a saved invitation on delivery failure and allows a deliberate resend', function () {
    Notification::fake();
    [$admin, $manager, $project] = projectFixture();
    $user = User::factory()->create();
    config(['mail.default' => 'log']);
    expect(fn () => app(ProjectInvitationService::class)->issue($manager, $project, 1, $user->email, []))->toThrow(ValidationException::class);
    expect(ProjectInvitation::count())->toBe(1)->and($project->fresh()->revision)->toBe(2);
    config(['mail.default' => 'array']);
    app(ProjectInvitationService::class)->resend($manager, $project, 2, ProjectInvitation::first(), []);
    expect(AuditEvent::where('action', 'project.invitation_resent')->count())->toBe(1);
});

it('rolls back membership and invitation consumption when auditing acceptance fails', function () {
    [$admin, $manager, $project, $user, $reader, $invitation, $token, $service] = inviteProjectMember();
    AuditEvent::creating(fn () => throw new RuntimeException('audit unavailable'));
    try {
        expect(fn () => $service->accept($user, $token))->toThrow(RuntimeException::class);
        expect($invitation->fresh()->accepted_at)->toBeNull()->and($project->members()->where('user_id', $user->id)->exists())->toBeFalse();
    } finally {
        AuditEvent::flushEventListeners();
    }
});

it('revalidates expanded invitation roles against the current authorizers grant ceiling', function () {
    [$admin, $manager, $project, $user, $reader, $invitation, $token, $service] = inviteProjectMember();
    $projects = app(ProjectService::class);
    $fullRole = $project->roles()->where('name', 'Project Manager')->firstOrFail();
    $projects->addMember($admin, $project, 3, $admin->email, [$fullRole->id]);
    $limitedRole = $projects->saveRole($admin, $project, 4, null, 'Inviter', ['project.view', 'project.members.manage']);
    $managerMember = $project->members()->where('user_id', $manager->id)->firstOrFail();
    $projects->assignRoles($admin, $project, 5, $managerMember, [$limitedRole->id]);
    $projects->saveRole($admin, $project, 6, $reader, 'Reader', ['project.view', 'project.archive']);
    expect(fn () => $service->accept($user, $token))->toThrow(ValidationException::class);
    expect($invitation->fresh()->accepted_at)->toBeNull();
});

it('blocks deleting a role referenced by a pending invitation until revocation', function () {
    [$admin, $manager, $project, $user, $reader, $invitation, $token, $service] = inviteProjectMember();
    $projects = app(ProjectService::class);
    expect(fn () => $projects->deleteRole($manager, $project, 3, $reader))->toThrow(ValidationException::class);
    $service->revoke($manager, $project, 3, $invitation);
    $projects->deleteRole($manager, $project, 4, $reader);
    $this->assertDatabaseMissing('roles', ['id' => $reader->id]);
    expect(fn () => $service->accept($user, $token))->toThrow(ValidationException::class);
});
