<?php

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('creates permanent sequential ticket keys with reporter and atomic change history', function () {
    [$admin, $manager, $project] = projectFixture();
    $service = app(TicketService::class);
    $first = $service->create($manager, $project, [...ticketValues($project), 'summary' => '  Homepage  ', 'estimate' => '2.50', 'due_date' => '2026-11-01']);
    $second = $service->create($manager, $project, ticketValues($project));
    expect($first->key)->toBe('END-00001')->and($second->key)->toBe('END-00002')->and($first->reporter_id)->toBe($manager->id)->and($first->state)->toBe('open');
    expect($first->events()->first()->changes['after']['summary'])->toBe('Homepage');
    $service->update($manager, $first, 1, [...ticketValues($project), 'summary' => 'Changed']);
    expect($first->fresh()->revision)->toBe(2)->and($first->events()->count())->toBe(2)->and($project->fresh()->revision)->toBe(1);
    expect(fn () => $service->update($manager, $first, 1, [...ticketValues($project), 'summary' => 'Stale']))->toThrow(ValidationException::class);
});

it('validates editable fields and rejects protected lifecycle and identity fields', function (array $invalid) {
    [$admin, $manager, $project] = projectFixture();
    expect(fn () => app(TicketService::class)->create($manager, $project, [...ticketValues($project), ...$invalid]))->toThrow(ValidationException::class);
    expect(Ticket::count())->toBe(0)->and(TicketEvent::count())->toBe(0);
})->with([
    [['summary' => ' ']], [['summary' => str_repeat('a', 256)]], [['estimate' => -1]], [['estimate' => '10000']], [['estimate' => '1.111']], [['due_date' => '2026-02-30']], [['state' => 'resolved']], [['status' => 'done']], [['reporter_id' => 4]], [['key' => 'OTHER-1']], [['resolution' => 'fixed']], [['workflow_version_id' => 1]], [['assignee_id' => 0]], [['assignee_id' => -1]],
]);

it('requires current permission and project access and rejects archived writes', function () {
    [$admin, $manager, $project] = projectFixture();
    $outsider = User::factory()->create();
    expect(fn () => app(TicketService::class)->create($outsider, $project, ticketValues($project)))->toThrow(AuthorizationException::class);
    $project->update(['archived_at' => now()]);
    expect(fn () => app(TicketService::class)->create($admin, $project, ticketValues($project)))->toThrow(ValidationException::class);
});

it('requires eligible assignees but preserves an unchanged unavailable assignment', function () {
    [$admin, $manager, $project, $projects] = projectFixture();
    $other = User::factory()->create();
    $service = app(TicketService::class);
    expect(fn () => $service->create($manager, $project, [...ticketValues($project), 'assignee_id' => $other->id]))->toThrow(ValidationException::class);
    $member = $projects->addMember($manager, $project, 1, $other->email, [$project->roles()->first()->id]);
    $ticket = $service->create($manager, $project, [...ticketValues($project), 'assignee_id' => $other->id]);
    $other->forceFill(['is_active' => false])->save();
    $service->update($manager, $ticket, 1, [...ticketValues($project), 'summary' => 'Retain historical owner', 'assignee_id' => $other->id]);
    expect($ticket->fresh()->assignee_id)->toBe($other->id);
    expect(fn () => $service->create($manager, $project, [...ticketValues($project), 'assignee_id' => $other->id]))->toThrow(ValidationException::class);
});

it('retains inactive catalogs on edits but rejects new selections and cross-project catalogs', function () {
    [$admin, $manager, $project, $projects] = projectFixture();
    $service = app(TicketService::class);
    $values = ticketValues($project);
    $ticket = $service->create($manager, $project, $values);
    DB::table('work_types')->where('id', $values['work_type_id'])->update(['is_active' => false]);
    $service->update($manager, $ticket, 1, [...$values, 'summary' => 'Keep inactive type']);
    expect(fn () => $service->create($manager, $project, $values))->toThrow(ValidationException::class);
    $foreign = $projects->create($admin, ['key' => 'OTHER', 'name' => 'Other', 'manager_email' => $manager->email]);
    expect(fn () => $service->create($manager, $project, ticketValues($foreign)))->toThrow(ValidationException::class);
});

it('rolls back tickets and counters when the event cannot be saved', function () {
    [$admin, $manager, $project] = projectFixture();
    TicketEvent::creating(fn () => throw new RuntimeException('Event failure'));
    try {
        expect(fn () => app(TicketService::class)->create($manager, $project, ticketValues($project)))->toThrow(RuntimeException::class);
        expect(Ticket::count())->toBe(0)->and(DB::table('project_counters')->where('project_id', $project->id)->value('next_number'))->toBe(1);
    } finally {
        TicketEvent::flushEventListeners();
    }
});

it('allows assignment to a system admin who is a current member without role grants', function () {
    [$admin, $manager, $project, $projects] = projectFixture();
    $projects->addMember($manager, $project, 1, $admin->email, []);
    $ticket = app(TicketService::class)->create($manager, $project, [...ticketValues($project), 'assignee_id' => $admin->id]);
    expect($ticket->assignee_id)->toBe($admin->id);
    $this->signIn($manager)->getJson('/projects/'.$project->id.'/ticket-assignees?q='.$admin->name)->assertJsonFragment(['value' => $admin->id]);
});
