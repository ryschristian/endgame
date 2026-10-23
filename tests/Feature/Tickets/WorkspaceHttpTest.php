<?php

use App\Models\User;
use App\Models\WorkflowVersion;
use App\Services\TicketService;
use App\Services\WorkflowService;
use Inertia\Testing\AssertableInertia as Assert;

it('renders every project view with authorized dynamic workflow and ticket data', function (string $view) {
    [$admin, $manager, $project] = projectFixture();
    $ticket = app(TicketService::class)->create($manager, $project, [...ticketValues($project), 'start_date' => '2026-10-25', 'due_date' => '2026-10-28']);
    $this->signIn($manager)->get('/projects/'.$project->id.'/'.$view.'?month=2026-10&ticket='.$ticket->id)
        ->assertOk()->assertInertia(fn (Assert $p) => $p->component('projects/workspace', false)->where('view', $view)->has('workflow.definition.statuses', 3)->where('selectedTicket.id', $ticket->id)->where('tickets.data.0.start_date', '2026-10-25')->where('ticketCan.update', true));
    $this->signIn(User::factory()->create())->get('/projects/'.$project->id.'/'.$view)->assertNotFound();
})->with(['board', 'list', 'table', 'calendar', 'timeline']);

it('protects workflow setup and mutations by capability instead of role names', function () {
    [$admin, $manager, $project, $projects] = projectFixture();
    $ticket = app(TicketService::class)->create($manager, $project, ticketValues($project));
    $reader = User::factory()->create();
    $role = $projects->saveRole($manager, $project, 1, null, 'Project Manager impostor', ['project.view', 'ticket.view']);
    $projects->addMember($manager, $project, 2, $reader->email, [$role->id]);
    $this->signIn($reader)->get('/projects/'.$project->id.'/workflow')->assertForbidden();
    $this->post('/projects/'.$project->id.'/workflow', [])->assertForbidden();
    $this->post('/projects/'.$project->id.'/tickets/'.$ticket->id.'/transition', ['revision' => 1, 'state' => 'in_progress'])->assertForbidden();
    $this->patch('/projects/'.$project->id.'/tickets/'.$ticket->id.'/schedule', ['revision' => 1, 'start_date' => null, 'due_date' => '2026-10-29'])->assertForbidden();
    $this->signIn($manager)->get('/projects/'.$project->id.'/workflow')->assertOk();
    $this->signIn($admin)->get('/projects/'.$project->id.'/workflow')->assertOk();
});

it('rejects cross-project nested ticket writes and selection and foreign workflow versions', function () {
    [$admin, $manager, $project, $projects] = projectFixture();
    $other = $projects->create($admin, ['key' => 'OTH', 'name' => 'Other', 'manager_email' => $manager->email]);
    $ticket = app(TicketService::class)->create($manager, $other, ticketValues($other));
    $this->signIn($manager)->get('/projects/'.$project->id.'/board?ticket='.$ticket->id)->assertNotFound();
    $this->post('/projects/'.$project->id.'/tickets/'.$ticket->id.'/transition', ['revision' => 1, 'state' => 'in_progress'])->assertNotFound();
    $this->post('/projects/'.$project->id.'/tickets/'.$ticket->id.'/move', ['revision' => 1, 'state' => 'open', 'board_revision' => 1])->assertNotFound();
    $this->patch('/projects/'.$project->id.'/tickets/'.$ticket->id.'/schedule', ['revision' => 1, 'start_date' => null, 'due_date' => null])->assertNotFound();
    $workflow = app(WorkflowService::class)->current($other);
    $this->post('/projects/'.$project->id.'/workflow', ['version_id' => $workflow->id, 'definition' => $workflow->definition, 'mapping' => ['open' => 'open', 'in_progress' => 'in_progress', 'resolved' => 'resolved']])->assertSessionHasErrors('workflow');
    expect(WorkflowVersion::where('project_id', $project->id)->count())->toBe(1);
});

it('paginates workspace queries and scopes overview and date windows', function () {
    [$admin, $manager, $project] = projectFixture();
    $service = app(TicketService::class);
    $service->create($manager, $project, [...ticketValues($project), 'summary' => 'October task', 'due_date' => '2026-10-28']);
    $service->create($manager, $project, [...ticketValues($project), 'summary' => 'December task', 'due_date' => '2026-12-28']);
    $service->create($manager, $project, [...ticketValues($project), 'summary' => 'Unscheduled task']);
    $this->signIn($manager)->get('/projects/'.$project->id.'/calendar?month=2026-10')->assertInertia(fn (Assert $p) => $p->has('tickets.data', 2));
    $this->get('/projects/'.$project->id.'/board?q=October')->assertInertia(fn (Assert $p) => $p->has('tickets.data', 1)->where('tickets.data.0.summary', 'October task'));
    $this->get('/projects/'.$project->id)->assertInertia(fn (Assert $p) => $p->where('overview.total', 3)->where('overview.completed', 0)->has('overview.activity', 3));
});
