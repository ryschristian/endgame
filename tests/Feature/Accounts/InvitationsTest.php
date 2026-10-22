<?php

use App\Models\AccountInvitation;
use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use App\Services\InvitationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Notification::fake();
});

function issueAccountInvitation(User $admin, string $email = 'invitee@example.com'): array
{
    $invitation = app(InvitationService::class)->issue($admin, $email);
    $token = null;
    Notification::assertSentOnDemand(AccountInvitationNotification::class, function ($notification) use (&$token) {
        $token = $notification->token;
        return true;
    });
    return [$invitation, $token];
}

it('stores only a hash and delivers a 48-hour invitation to a normalized identity', function () {
    [$invitation, $token] = issueAccountInvitation(User::factory()->admin()->create(), ' INVITEE@Example.com ');
    expect($invitation->email)->toBe('invitee@example.com')
        ->and($invitation->token_hash)->toBe(hash('sha256', $token))
        ->and($invitation->expires_at->equalTo(now()->addHours(48)))->toBeTrue()
        ->and($invitation->toArray())->not->toHaveKey('token_hash');
    expect(User::where('email', $invitation->email)->exists())->toBeFalse();
});

it('accepts a valid invitation once and creates an ordinary verified account', function () {
    [$invitation, $token] = issueAccountInvitation(User::factory()->admin()->create());
    $user = app(InvitationService::class)->accept($token, ['name' => 'Invited User', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123']);
    expect($user->email)->toBe($invitation->email)
        ->and($user->is_active)->toBeTrue()->and($user->is_system_admin)->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
    expect(fn () => app(InvitationService::class)->accept($token, ['name' => 'Replay', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123']))->toThrow(ValidationException::class);
});

it('rejects expired and revoked invitations without creating accounts', function (string $state) {
    [$invitation, $token] = issueAccountInvitation(User::factory()->admin()->create());
    $invitation->update($state === 'expired' ? ['expires_at' => now()->subSecond()] : ['revoked_at' => now()]);
    try {
        app(InvitationService::class)->accept($token, ['name' => 'Invited', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123']);
    } finally {
        expect(User::where('email', $invitation->email)->exists())->toBeFalse();
    }
})->with(['expired', 'revoked'])->throws(ValidationException::class);

it('rotates the token on resend and rejects the old link', function () {
    $admin = User::factory()->admin()->create();
    [$invitation, $oldToken] = issueAccountInvitation($admin);
    Notification::fake();
    app(InvitationService::class)->resend($admin, $invitation);
    expect($invitation->fresh()->token_hash)->not->toBe(hash('sha256', $oldToken));
    app(InvitationService::class)->accept($oldToken, ['name' => 'Invited', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123']);
})->throws(ValidationException::class);

it('revokes invitations immediately and audits the action', function () {
    $admin = User::factory()->admin()->create();
    [$invitation] = issueAccountInvitation($admin);
    app(InvitationService::class)->revoke($admin, $invitation);
    expect($invitation->fresh()->canAccept())->toBeFalse();
    $this->assertDatabaseHas('audit_events', ['action' => 'invitation.revoked', 'account_invitation_id' => $invitation->id]);
});

it('rejects duplicate user identities and duplicate invitations', function (bool $existingUser) {
    $admin = User::factory()->admin()->create();
    if ($existingUser) {
        User::factory()->create(['email' => 'invitee@example.com']);
    } else {
        issueAccountInvitation($admin);
    }
    app(InvitationService::class)->issue($admin, 'invitee@example.com');
})->with([true, false])->throws(ValidationException::class);

it('rechecks administrator authority on every invitation operation', function (string $operation) {
    $admin = User::factory()->admin()->create();
    [$invitation] = issueAccountInvitation($admin);
    User::whereKey($admin->id)->update(['is_active' => false]);
    if ($operation === 'issue') {
        app(InvitationService::class)->issue($admin, 'other@example.com');
    } else {
        app(InvitationService::class)->{$operation}($admin, $invitation);
    }
})->with(['issue', 'resend', 'revoke'])->throws(AuthorizationException::class);

it('retains the invitation when mail delivery fails without logging its token', function () {
    config(['mail.default' => 'log']);
    try {
        app(InvitationService::class)->issue(User::factory()->admin()->create(), 'invitee@example.com');
        $this->fail('Expected an invitation delivery error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('delivery');
    }
    expect(AccountInvitation::where('email', 'invitee@example.com')->exists())->toBeTrue();
    Notification::assertNothingSent();
});

it('rolls back acceptance when its audit write fails', function () {
    [$invitation, $token] = issueAccountInvitation(User::factory()->admin()->create());
    AuditEvent::creating(function (AuditEvent $event) {
        if ($event->action === 'invitation.accepted') {
            throw new RuntimeException('Simulated audit failure');
        }
    });
    try {
        expect(fn () => app(InvitationService::class)->accept($token, ['name' => 'Invited', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123']))->toThrow(RuntimeException::class);
        expect(User::where('email', $invitation->email)->exists())->toBeFalse()
            ->and($invitation->fresh()->accepted_at)->toBeNull();
    } finally {
        AuditEvent::flushEventListeners();
    }
});
