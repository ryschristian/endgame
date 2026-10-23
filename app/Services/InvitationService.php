<?php

namespace App\Services;

use App\Models\AccountInvitation;
use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use App\Support\AccountMail;
use App\Support\AccountRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationService
{
    public function __construct(private AccountService $accounts) {}

    public function issue(User $actor, string $email): AccountInvitation
    {
        $token = bin2hex(random_bytes(32));
        $invitation = DB::transaction(function () use ($actor, $email, $token) {
            $this->accounts->lockAdministration();
            $actor = $this->accounts->requireAdministrator($actor);
            $email = AccountRules::normalizeEmail($email);
            Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:254', Rule::unique('users'), Rule::unique('account_invitations')]])->validate();
            $invitation = AccountInvitation::create(['email' => $email, 'token_hash' => hash('sha256', $token), 'invited_by' => $actor->id, 'expires_at' => now()->addHours(48)]);
            $this->audit('invitation.created', $actor, $invitation);
            return $invitation;
        }, 3);
        $this->deliver($invitation, $token);
        return $invitation;
    }

    public function resend(User $actor, AccountInvitation $invitation): AccountInvitation
    {
        $token = bin2hex(random_bytes(32));
        $invitation = DB::transaction(function () use ($actor, $invitation, $token) {
            $this->accounts->lockAdministration();
            $actor = $this->accounts->requireAdministrator($actor);
            $invitation = AccountInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if ($invitation->accepted_at || User::where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation has already been accepted.']);
            }
            $invitation->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48), 'revoked_at' => null, 'invited_by' => $actor->id]);
            $this->audit('invitation.resent', $actor, $invitation);
            return $invitation;
        }, 3);
        $this->deliver($invitation, $token);
        return $invitation;
    }

    public function revoke(User $actor, AccountInvitation $invitation): void
    {
        DB::transaction(function () use ($actor, $invitation) {
            $this->accounts->lockAdministration();
            $actor = $this->accounts->requireAdministrator($actor);
            $invitation = AccountInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if ($invitation->accepted_at || $invitation->revoked_at) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer pending.']);
            }
            $invitation->update(['revoked_at' => now()]);
            $this->audit('invitation.revoked', $actor, $invitation);
        }, 3);
    }

    public function findValid(#[\SensitiveParameter] string $token): AccountInvitation
    {
        $invitation = AccountInvitation::where('token_hash', hash('sha256', $token))->first();
        if (! $invitation || ! $invitation->canAccept()) {
            throw ValidationException::withMessages(['token' => 'This invitation is invalid, expired, or already used. Ask an administrator for a new invitation.']);
        }
        return $invitation;
    }

    public function accept(#[\SensitiveParameter] string $token, #[\SensitiveParameter] array $values): User
    {
        Validator::make($values, ['name' => ['required', 'string', 'max:255'], 'password' => AccountRules::password()])->validate();
        try {
            return DB::transaction(function () use ($token, $values) {
                $this->accounts->lockAdministration();
                $invitation = $this->findValid($token);
                $invitation = AccountInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
                if (User::where('email', $invitation->email)->exists()) {
                    throw ValidationException::withMessages(['token' => 'An account already exists for this invitation. Sign in or reset your password.']);
                }
                $user = new User(['name' => $values['name'], 'email' => $invitation->email, 'password' => $values['password']]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $invitation->update(['accepted_at' => now()]);
                AuditEvent::create(['action' => 'invitation.accepted', 'actor_id' => $user->id, 'target_user_id' => $user->id, 'account_invitation_id' => $invitation->id]);
                return $user->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['token' => 'An account already exists for this invitation. Sign in or reset your password.']);
        }
    }

    private function deliver(AccountInvitation $invitation, #[\SensitiveParameter] string $token): void
    {
        try {
            AccountMail::ensureConfigured();
            Notification::route('mail', $invitation->email)->notify(new AccountInvitationNotification($token));
        } catch (Throwable) {
            // Do not report the transport exception: it may contain a credential or link.
            throw ValidationException::withMessages(['delivery' => 'The invitation was saved, but email could not be delivered. Check SMTP settings, then use Resend.']);
        }
    }

    private function audit(string $action, User $actor, AccountInvitation $invitation): void
    {
        AuditEvent::create(['action' => $action, 'actor_id' => $actor->id, 'account_invitation_id' => $invitation->id]);
    }
}
