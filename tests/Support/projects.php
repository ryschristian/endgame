<?php

use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use App\Services\ProjectInvitationService;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Notification;

function projectFixture(): array
{
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->create();
    $project = app(ProjectService::class)->create($admin, ['key' => 'end', 'name' => 'Endgame', 'description' => '', 'timezone' => 'Asia/Manila', 'manager_email' => $manager->email]);

    return [$admin, $manager, $project, app(ProjectService::class)];
}

function inviteProjectMember(): array
{
    Notification::fake();
    [$admin, $manager, $project, $projects] = projectFixture();
    $user = User::factory()->create();
    $reader = $projects->saveRole($manager, $project, 1, null, 'Reader', ['project.view']);
    $service = app(ProjectInvitationService::class);
    $invitation = $service->issue($manager, $project, 2, $user->email, [$reader->id]);
    $token = null;
    Notification::assertSentOnDemand(ProjectInvitationNotification::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    return [$admin, $manager, $project, $user, $reader, $invitation, $token, $service];
}
