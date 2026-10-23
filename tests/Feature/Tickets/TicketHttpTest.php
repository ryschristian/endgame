<?php

use App\Models\User;
use App\Services\TicketService;
use Inertia\Testing\AssertableInertia as Assert;

it('scopes ticket reads search selection and choices before exposing page data', function () {
    [$admin, $manager, $project, $projects] = projectFixture();
    $ticket = app(TicketService::class)->create($manager, $project, ticketValues($project));
    $outsider = User::factory()->create();
    $this->signIn($outsider)->get('/projects/'.$project->id.'/tickets')->assertNotFound();
    $this->signIn($manager)->get('/projects/'.$project->id.'/tickets?q=homepage&ticket='.$ticket->id)->assertOk()->assertInertia(fn (Assert $p) => $p->component('tickets/index', false)->has('tickets.data', 1)->where('selectedTicket.key', $ticket->key)->has('history.data', 1)->missing('selectedTicket.reporter.email'));
    $foreign = $projects->create($admin, ['key' => 'OTHER', 'name' => 'Other', 'manager_email' => $manager->email]);
    $this->get('/projects/'.$foreign->id.'/tickets?ticket='.$ticket->id)->assertNotFound();
    $this->get('/projects/'.$foreign->id.'/tickets/'.$ticket->id)->assertNotFound();
    $this->get('/projects/'.$project->id.'/tickets?sort=password')->assertSessionHasErrors('sort');
    $this->get('/projects/'.$project->id.'/tickets?per_page=101')->assertSessionHasErrors('per_page');
});

it('creates and edits through HTTP without exposing lifecycle fields', function () {
    [$admin, $manager, $project] = projectFixture();
    $this->signIn($manager)->post('/projects/'.$project->id.'/tickets', ticketValues($project))->assertSessionHasNoErrors();
    $ticket = $project->tickets()->firstOrFail();
    $this->patch('/projects/'.$project->id.'/tickets/'.$ticket->id, [...ticketValues($project), 'revision' => 1, 'state' => 'resolved'])->assertSessionHasErrors('state');
    $this->patch('/projects/'.$project->id.'/tickets/'.$ticket->id, [...ticketValues($project), 'revision' => 1, 'summary' => 'Changed through web'])->assertSessionHasNoErrors();
    $this->get('/projects/'.$project->id.'/tickets/'.$ticket->id)->assertInertia(fn (Assert $p) => $p->component('tickets/show', false)->where('selectedTicket.summary', 'Changed through web'));
});
