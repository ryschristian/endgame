<?php

use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use App\Services\WorkflowService;
use App\Support\TicketPageData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('initializes a project workflow and records completion and reopening through allowed transitions', function () {
    [, $manager, $project] = projectFixture();
    $ticket = app(TicketService::class)->create($manager, $project, ticketValues($project));
    $service = app(WorkflowService::class);
    expect($ticket->workflow_version_id)->not->toBeNull();
    $service->transition($manager, $ticket, 1, 'in_progress');
    expect(fn () => $service->transition($manager, $ticket, 2, 'resolved'))->toThrow(ValidationException::class);
    $service->transition($manager, $ticket, 2, 'resolved', 'fixed');
    expect($ticket->fresh()->resolution)->toBe('fixed')->and($ticket->fresh()->completed_at)->not->toBeNull();
    expect(fn () => $service->transition($manager, $ticket, 3, 'open'))->toThrow(ValidationException::class);
    $service->transition($manager, $ticket, 3, 'open', null, 'Regression found');
    expect($ticket->fresh()->completed_at)->toBeNull()->and($ticket->fresh()->resolution)->toBeNull();
    expect($ticket->events()->latest('id')->first()->changes['reason'])->toBe('Regression found');
    expect(fn () => $service->transition($manager, $ticket, 1, 'in_progress'))->toThrow(ValidationException::class);
});

it('publishes immutable project workflows with explicit compatible status mappings', function () {
    [, $manager, $project] = projectFixture();
    $ticket = app(TicketService::class)->create($manager, $project, ticketValues($project));
    $service = app(WorkflowService::class);
    $old = $service->current($project);
    $definition = $old->definition;
    $definition['statuses'][0]['label'] = 'Backlog';
    $definition['statuses'][] = ['key' => 'review', 'label' => 'Review', 'color' => '#8b5cf6', 'completed' => false];
    $definition['transitions'][] = ['from' => 'in_progress', 'to' => 'review'];
    $definition['transitions'][] = ['from' => 'review', 'to' => 'resolved'];
    expect(fn () => $service->publish($manager, $project, $old->id, $definition, []))->toThrow(ValidationException::class);
    $new = $service->publish($manager, $project, $old->id, $definition, ['open' => 'open', 'in_progress' => 'in_progress', 'resolved' => 'resolved']);
    expect($new->version)->toBe(2)->and($old->fresh()->definition['statuses'][0]['label'])->toBe('Open');
    expect(DB::table('audit_events')->where('project_id', $project->id)->where('action', 'project.workflow_published')->count())->toBe(1);
    expect($ticket->fresh()->workflow_version_id)->toBe($new->id)->and($ticket->fresh()->revision)->toBe(2);
    expect($ticket->events()->latest('id')->first()->action)->toBe('workflow_migrated');
    $created = app(TicketService::class)->create($manager, $project, ticketValues($project));
    expect($created->workflow_version_id)->toBe($new->id);
    expect(fn () => $service->publish($manager, $project, $old->id, $definition, ['open' => 'open', 'in_progress' => 'in_progress', 'resolved' => 'resolved']))->toThrow(ValidationException::class);
    expect(fn () => $service->publish($manager, $project, $new->id, $definition, ['open' => 'resolved', 'in_progress' => 'in_progress', 'resolved' => 'resolved']))->toThrow(ValidationException::class);
});

it('rejects unauthorized and invalid transitions without changing history or board order', function () {
    [, $manager, $project] = projectFixture();
    $ticket = app(TicketService::class)->create($manager, $project, ticketValues($project));
    $service = app(WorkflowService::class);
    $outsider = User::factory()->create();
    expect(fn () => $service->transition($outsider, $ticket, 1, 'in_progress'))->toThrow(AuthorizationException::class);
    expect(fn () => $service->transition($manager, $ticket, 1, 'resolved', 'fixed'))->toThrow(ValidationException::class);
    expect(fn () => $service->transition($manager, $ticket, 1, 'foreign'))->toThrow(ValidationException::class);
    expect($ticket->events()->count())->toBe(1)->and($ticket->fresh()->revision)->toBe(1);
    $project->update(['archived_at' => now()]);
    expect(fn () => $service->transition($manager, $ticket, 1, 'in_progress'))->toThrow(ValidationException::class);
});

it('persists manual ordering and rejects stale or foreign board anchors', function () {
    [$admin, $manager, $project, $projects] = projectFixture();
    $tickets = app(TicketService::class);
    $first = $tickets->create($manager, $project, ticketValues($project));
    $second = $tickets->create($manager, $project, ticketValues($project));
    $service = app(WorkflowService::class);
    $revision = $project->fresh()->board_revision;
    $service->move($manager, $second, 1, 'open', $first->id, $revision);
    expect($project->tickets()->orderBy('board_rank')->first()->id)->toBe($second->id);
    expect(fn () => $service->move($manager, $first, 1, 'open', null, $revision))->toThrow(ValidationException::class);
    $other = $projects->create($admin, ['key' => 'OTH', 'name' => 'Other', 'manager_email' => $manager->email]);
    $foreign = $tickets->create($manager, $other, ticketValues($other));
    expect(fn () => $service->move($manager, $first, 1, 'open', $foreign->id, $project->fresh()->board_revision))->toThrow(ValidationException::class);
});

it('validates scheduling ranges and stale revisions and records date edits', function () {
    [, $manager, $project] = projectFixture();
    $ticket = app(TicketService::class)->create($manager, $project, ticketValues($project));
    $service = app(WorkflowService::class);
    expect(fn () => $service->schedule($manager, $ticket, 1, '2026-11-03', '2026-11-01'))->toThrow(ValidationException::class);
    $service->schedule($manager, $ticket, 1, '2026-11-01', '2026-11-03');
    expect($ticket->fresh()->start_date->format('Y-m-d'))->toBe('2026-11-01');
    expect($ticket->events()->latest('id')->first()->action)->toBe('scheduled');
    expect(fn () => $service->schedule($manager, $ticket, 1, null, null))->toThrow(ValidationException::class);
});

it('keeps factories valid for workflow-aware ticket data', function () {
    $ticket = Ticket::factory()->create();
    expect(TicketPageData::row($ticket)['stateLabel'])->toBe('Open');
});

it('keeps schedule ranges valid during ordinary edits and allows clearing a start date', function () {
    [, $manager, $project] = projectFixture();
    $service = app(TicketService::class);
    $ticket = $service->create($manager, $project, [...ticketValues($project), 'start_date' => '2026-11-01', 'due_date' => '2026-11-03']);
    expect(fn () => $service->update($manager, $ticket, 1, [...ticketValues($project), 'start_date' => '2026-11-04']))->toThrow(ValidationException::class);
    $service->update($manager, $ticket, 1, [...ticketValues($project), 'start_date' => null, 'due_date' => '2026-10-01']);
    expect($ticket->fresh()->start_date)->toBeNull()->and($ticket->fresh()->due_date->format('Y-m-d'))->toBe('2026-10-01');
});
