<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use App\Support\AccountMail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ProjectInvitationService
{
    public function __construct(private ProjectService $projects, private AccountService $accounts) {}

    public function issue(User $actor, Project $project, int $revision, string $email, array $roleIds): ProjectInvitation
    {
        $token = bin2hex(random_bytes(32));
        $invitation = $this->projects->change($actor, $project, $revision, 'project.members.manage', function ($actor, $project) use ($email, $roleIds, $token) {
            $user = $this->projects->eligibleUser($email);
            $this->ensureNotMember($project, $user);
            $roles = $this->projects->selectedRoles($actor, $project, $roleIds);
            $invitation = $project->invitations()->where('email', $user->email)->first();
            if ($invitation) {
                $this->projects->invalid('email', 'An invitation already exists. Use Resend to issue a new link.');
            }
            $invitation = $project->invitations()->create(['email' => $user->email, 'user_id' => $user->id, 'invited_by' => $actor->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48)]);
            $invitation->roles()->sync(array_fill_keys($roles, ['project_id' => $project->id]));
            $this->projects->audit($actor, $project, 'project.invitation_created', ['invitation_id' => $invitation->id, 'role_ids' => $roles, 'user_id' => $user->id]);

            return $invitation;
        });
        $this->deliver($invitation, $token);

        return $invitation;
    }

    public function resend(User $actor, Project $project, int $revision, ProjectInvitation $invitation, array $roleIds): ProjectInvitation
    {
        $token = bin2hex(random_bytes(32));
        $invitation = $this->projects->change($actor, $project, $revision, 'project.members.manage', function ($actor, $project) use ($invitation, $roleIds, $token) {
            $invitation = $project->invitations()->findOrFail($invitation->id);
            $user = $this->projects->eligibleUser($invitation->email);
            $this->ensureNotMember($project, $user);
            $roles = $this->projects->selectedRoles($actor, $project, $roleIds);
            // Reissuing an accepted invitation is allowed only after membership removal.
            $invitation->update(['token_hash' => hash('sha256', $token), 'invited_by' => $actor->id, 'expires_at' => now()->addHours(48), 'revoked_at' => null, 'accepted_at' => null]);
            $invitation->roles()->sync(array_fill_keys($roles, ['project_id' => $project->id]));
            $this->projects->audit($actor, $project, 'project.invitation_resent', ['invitation_id' => $invitation->id, 'role_ids' => $roles]);

            return $invitation;
        });
        $this->deliver($invitation, $token);

        return $invitation;
    }

    public function revoke(User $actor, Project $project, int $revision, ProjectInvitation $invitation): void
    {
        $this->projects->change($actor, $project, $revision, 'project.members.manage', function ($actor, $project) use ($invitation) {
            $invitation = $project->invitations()->findOrFail($invitation->id);
            if ($invitation->accepted_at || $invitation->revoked_at) {
                $this->unavailable();
            }
            $invitation->update(['revoked_at' => now()]);
            $this->projects->audit($actor, $project, 'project.invitation_revoked', ['invitation_id' => $invitation->id]);
        });
    }

    public function available(User $actor, #[\SensitiveParameter] string $token): ProjectInvitation
    {
        $actor = $this->projects->currentActor($actor);
        $invitation = ProjectInvitation::where('token_hash', hash('sha256', $token))->with(['project', 'roles'])->first();
        if (! $invitation || $invitation->user_id !== $actor->id || $invitation->email !== $actor->email
            || $invitation->accepted_at || $invitation->revoked_at || $invitation->expires_at->isPast() || $invitation->project->archived_at) {
            $this->unavailable();
        }
        $authorizer = User::find($invitation->invited_by);
        if (! $authorizer || ! $invitation->project->allows($authorizer, 'project.members.manage')) {
            $this->unavailable();
        }
        try {
            $this->projects->selectedRoles($authorizer, $invitation->project, $invitation->roles->modelKeys());
        } catch (AuthorizationException) {
            $this->unavailable();
        }
        $this->ensureNotMember($invitation->project, $actor);

        return $invitation;
    }

    public function accept(User $actor, #[\SensitiveParameter] string $token): Project
    {
        return DB::transaction(function () use ($actor, $token) {
            $this->accounts->lockAdministration();
            $invitation = $this->available($actor, $token);
            $project = Project::whereKey($invitation->project_id)->lockForUpdate()->firstOrFail();
            $invitation = ProjectInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $actor = $this->projects->currentActor($actor);
            $this->projects->join($project, $actor, $invitation->roles()->pluck('roles.id')->all());
            $invitation->update(['accepted_at' => now()]);
            $this->projects->ensureManager($project);
            $project->increment('revision');
            $this->projects->audit($actor, $project, 'project.invitation_accepted', ['invitation_id' => $invitation->id, 'user_id' => $actor->id, 'after' => ['role_ids' => $invitation->roles()->pluck('roles.id')->all()]]);

            return $project;
        }, 3);
    }

    private function ensureNotMember(Project $project, User $user): void
    {
        if ($project->members()->where('user_id', $user->id)->whereNull('removed_at')->exists()) {
            $this->projects->invalid('email', 'This account is already a project member.');
        }
    }

    private function unavailable(): never
    {
        $this->projects->invalid('invitation', 'This invitation is unavailable. Ask a project manager for a new invitation.');
    }

    private function deliver(ProjectInvitation $invitation, #[\SensitiveParameter] string $token): void
    {
        try {
            AccountMail::ensureConfigured();
            Notification::route('mail', $invitation->email)->notify(new ProjectInvitationNotification($token, $invitation->project->name));
        } catch (Throwable) {
            $this->projects->invalid('delivery', 'The invitation was saved, but email could not be delivered. Check SMTP settings, then use Resend.');
        }
    }
}
