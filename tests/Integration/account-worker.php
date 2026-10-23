<?php

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AccountService;
use App\Services\InvitationService;
use App\Services\ProjectInvitationService;
use App\Services\ProjectService;
use App\Services\TicketService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || ! str_starts_with(config('database.connections.mysql.database'), 'endgame_p01_test_')) {
    throw new RuntimeException('Concurrency workers require a dedicated P01 SQL test database.');
}
$fixture = json_decode(file_get_contents(getenv('P01_CONCURRENCY_FIXTURE')), true, flags: JSON_THROW_ON_ERROR);
$worker = (int) ($argv[1] ?? 0);
$actor = isset($fixture['actors']) ? User::findOrFail($fixture['actors'][$worker]) : null;
file_put_contents($fixture['ready_prefix'].$worker, 'ready');
try {
    match ($fixture['operation']) {
        'ticket-create' => app(TicketService::class)->create($actor, Project::findOrFail($fixture['project_id']), $fixture['values']),
        'ticket-edit' => app(TicketService::class)->update($actor, Ticket::findOrFail($fixture['ticket_id']), $fixture['revision'], [...$fixture['values'], 'summary' => 'Changed by worker '.$worker]),
        'ticket-assign-suspend' => $worker === 0
            ? app(TicketService::class)->update($actor, Ticket::findOrFail($fixture['ticket_id']), $fixture['revision'], $fixture['values'])
            : app(AccountService::class)->setActive($actor, User::findOrFail($fixture['target_id']), false, 'ConcurrentPassword123'),
        'ticket-assign-remove' => $worker === 0
            ? app(TicketService::class)->update($actor, Ticket::findOrFail($fixture['ticket_id']), $fixture['revision'], $fixture['values'])
            : app(ProjectService::class)->removeMember($actor, Project::findOrFail($fixture['project_id']), $fixture['project_revision'], ProjectMember::findOrFail($fixture['member_id'])),
        'bootstrap' => app(AccountService::class)->bootstrap('Concurrent Admin', "concurrent-$worker@example.test", 'ConcurrentPassword123'),
        'demote' => app(AccountService::class)->setAdministrator($actor, $actor, false, 'ConcurrentPassword123'),
        'suspend' => app(AccountService::class)->setActive($actor, User::findOrFail($fixture['actors'][1 - $worker]), false, 'ConcurrentPassword123'),
        'accept' => app(InvitationService::class)->accept($fixture['token'], ['name' => 'Concurrent Invitee', 'password' => 'ConcurrentPassword123', 'password_confirmation' => 'ConcurrentPassword123']),
        'project-remove-suspend' => $worker === 0
            ? app(ProjectService::class)->removeMember($actor, Project::findOrFail($fixture['project_id']), $fixture['revision'], ProjectMember::findOrFail($fixture['member_id']))
            : app(AccountService::class)->setActive($actor, User::findOrFail($fixture['suspend_user_id']), false, 'ConcurrentPassword123'),
        'project-role-remove' => $worker === 0
            ? app(ProjectService::class)->saveRole($actor, Project::findOrFail($fixture['project_id']), $fixture['revision'], Role::findOrFail($fixture['role_id']), 'Downgraded manager', ['project.view'])
            : app(ProjectService::class)->removeMember($actor, Project::findOrFail($fixture['project_id']), $fixture['revision'], ProjectMember::findOrFail($fixture['member_id'])),
        'project-accept' => app(ProjectInvitationService::class)->accept($actor, $fixture['token']),
        'project-add-accept' => $worker === 0
            ? app(ProjectService::class)->addMember($actor, Project::findOrFail($fixture['project_id']), $fixture['revision'], $fixture['email'], [])
            : app(ProjectInvitationService::class)->accept($actor, $fixture['token']),
    };
    echo 'success';
} catch (ValidationException|AuthorizationException) {
    echo 'rejected';
}
