<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Inertia\Testing\AssertableInertia as Assert;

it('lists only authorized projects and protects every management page', function () {
    [$admin, $manager, $project] = projectFixture();
    $outsider = User::factory()->create();
    $this->get('/projects')->assertRedirect('/login');
    $this->signIn($outsider)->get('/projects')->assertOk()->assertInertia(fn (Assert $p) => $p->component('projects/index', false)->has('projects.data', 0)->where('canCreate', false));
    foreach (['', '/members', '/roles'] as $suffix) {
        $this->get('/projects/'.$project->id.$suffix)->assertNotFound();
    }
    $this->get('/projects/create')->assertForbidden();
    $this->signIn($manager)->get('/projects')->assertInertia(fn (Assert $p) => $p->has('projects.data', 1)->where('projects.data.0.key', 'END'));
});

it('creates projects through the web and rejects protected lifecycle fields', function () {
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->create();
    $this->signIn($admin)->get('/projects/create')->assertOk()->assertInertia(fn (Assert $p) => $p->missing('timezones')->where('teamTimezone', 'Asia/Manila')->missing('users'));
    $this->post('/projects', ['key' => 'web', 'name' => 'Web project', 'manager_email' => $manager->email])->assertRedirect('/projects/'.Project::first()->id);
    $project = Project::first();
    $this->patch('/projects/'.$project->id, ['revision' => 1, 'name' => 'Hacked', 'timezone' => 'Asia/Manila', 'key' => 'NEW', 'archived_at' => now()->toISOString()])->assertSessionHasErrors(['key', 'archived_at']);
    expect($project->fresh()->key)->toBe('WEB');
});

it('fixes project timezone to Manila and rejects alternatives on creation and update', function () {
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->create();
    $this->signIn($admin)->post('/projects', ['key' => 'TEAM', 'name' => 'Team', 'manager_email' => $manager->email])->assertSessionHasNoErrors();
    $project = Project::firstOrFail();
    expect($project->timezone)->toBe('Asia/Manila');
    $this->post('/projects', ['key' => 'OTHER', 'name' => 'Other', 'manager_email' => $manager->email, 'timezone' => 'UTC'])->assertSessionHasErrors('timezone');
    $this->patch('/projects/'.$project->id, ['revision' => 1, 'name' => 'Changed', 'timezone' => 'Asia/Tokyo'])->assertSessionHasErrors('timezone');
    $this->patch('/projects/'.$project->id, ['revision' => 1, 'name' => 'Changed'])->assertSessionHasNoErrors();
    expect($project->fresh()->timezone)->toBe('Asia/Manila');
});

it('shares only authorized projects with the workspace sidebar and card metadata', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $project->update(['description' => 'Build our team workspace.']);
    $service->create($admin, ['key' => 'PRIVATE', 'name' => 'Private', 'manager_email' => $admin->email]);
    $this->signIn($manager)->get('/projects')->assertInertia(fn (Assert $p) => $p
        ->has('workspace.projects', 1)->where('workspace.projects.0.id', $project->id)
        ->where('projects.data.0.description', 'Build our team workspace.')
        ->where('projects.data.0.members_count', 1)->where('projects.data.0.memberPreviews.0.name', $manager->name)
        ->missing('projects.data.0.memberPreviews.0.email')
    );
});

it('limits member and role props to authorized choices and omits credentials', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $viewer = User::factory()->create();
    $role = $service->saveRole($manager, $project, 1, null, 'Viewer', ['project.view']);
    $service->addMember($manager, $project, 2, $viewer->email, [$role->id]);
    $this->signIn($viewer)->get('/projects/'.$project->id)->assertInertia(fn (Assert $p) => $p->component('projects/show', false)->where('can.members', false)->missing('members.data.0.email')->missing('roleOptions')->missing('timezones'));
    $this->get('/projects/'.$project->id.'/members')->assertForbidden();
    $this->signIn($manager)->get('/projects/'.$project->id.'/members')->assertInertia(fn (Assert $p) => $p->has('roleOptions', 2)->has('members.data', 2)->missing('members.data.0.password')->missing('invitations.data.0.token_hash'));
});

it('rejects cross-project nested records and redirects safely after self removal', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    $other = $service->create($admin, ['key' => 'OTHER', 'name' => 'Other', 'timezone' => 'Asia/Manila', 'manager_email' => $manager->email]);
    $foreignRole = $other->roles()->first();
    $this->signIn($manager)->patch('/projects/'.$project->id.'/roles/'.$foreignRole->id, ['revision' => 1, 'name' => 'Steal', 'permission_keys' => ['project.view']])->assertNotFound();
    $managerRole = $project->roles()->first();
    $service->addMember($admin, $project, 1, $admin->email, [$managerRole->id]);
    $member = $project->members()->where('user_id', $manager->id)->first();
    $this->delete('/projects/'.$project->id.'/members/'.$member->id, ['revision' => 2])->assertRedirect('/projects');
    $this->get('/projects/'.$project->id.'/members')->assertNotFound();
});

it('handles unavailable project invitations without exposing recipient details', function () {
    [$admin, $manager, $project, $user, $reader, $invitation, $token] = inviteProjectMember();
    $this->get('/project-invitations/'.$token)->assertRedirect('/login');
    $this->signIn($manager)->get('/project-invitations/'.$token)->assertInertia(fn (Assert $p) => $p->where('valid', false)->missing('invitation')->missing('token'));
    $this->signIn($user)->get('/project-invitations/'.$token)->assertInertia(fn (Assert $p) => $p->where('valid', true)->where('invitation.projectName', 'Endgame')->missing('invitation.email'));
    $this->post('/project-invitations/'.$token)->assertRedirect('/projects/'.$project->id);
});

it('returns guests to their project invitation after signing in', function () {
    [$admin, $manager, $project, $user, $reader, $invitation, $token] = inviteProjectMember();
    $destination = route('project-invitations.show', ['token' => $token]);
    $this->get($destination)->assertRedirect('/login')->assertSessionHas('url.intended', $destination);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($destination);
});

it('rejects forged external invitation destinations after login', function (string $destination) {
    $user = User::factory()->create();
    $this->withSession(['url.intended' => $destination])->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/account');
})->with(['https://evil.example/project-invitations/'.str_repeat('a', 64), '//evil.example/project-invitations/'.str_repeat('a', 64), 'http://localhost/project-invitations/'.str_repeat('a', 64).'?redirect=https://evil.example']);

it('offers resend after accepted membership removal while retaining invitation history', function () {
    [$admin, $manager, $project, $user, $reader, $invitation, $token, $service] = inviteProjectMember();
    $service->accept($user, $token);
    $member = $project->members()->where('user_id', $user->id)->firstOrFail();
    app(ProjectService::class)->removeMember($manager, $project, 4, $member);
    $this->signIn($manager)->get('/projects/'.$project->id.'/members')->assertInertia(fn (Assert $p) => $p->where('invitations.data.0.statusLabel', 'Accepted')->where('invitations.data.0.canResend', true));
    $this->post('/projects/'.$project->id.'/invitations/'.$invitation->id.'/resend', ['revision' => 5, 'role_ids' => [$reader->id]])->assertSessionHasNoErrors();
    expect($invitation->fresh()->accepted_at)->toBeNull();
});

it('rejects project management writes by a viewer', function (string $method, string $path) {
    [$admin, $manager, $project, $service] = projectFixture();
    $user = User::factory()->create();
    $reader = $service->saveRole($manager, $project, 1, null, 'Reader', ['project.view']);
    $member = $service->addMember($manager, $project, 2, $user->email, [$reader->id]);
    $path = str_replace(['{project}', '{member}', '{role}'], [$project->id, $member->id, $reader->id], $path);
    $this->signIn($user)->{$method}($path, ['revision' => 3, 'name' => 'Unauthorized', 'timezone' => 'Asia/Manila', 'email' => $admin->email, 'role_ids' => [], 'permission_keys' => ['project.view']])->assertForbidden();
})->with([
    ['patch', '/projects/{project}'], ['post', '/projects/{project}/archive'], ['post', '/projects/{project}/restore'],
    ['post', '/projects/{project}/members'], ['patch', '/projects/{project}/members/{member}'], ['delete', '/projects/{project}/members/{member}'],
    ['post', '/projects/{project}/roles'], ['patch', '/projects/{project}/roles/{role}'], ['delete', '/projects/{project}/roles/{role}'], ['post', '/projects/{project}/invitations'],
]);
