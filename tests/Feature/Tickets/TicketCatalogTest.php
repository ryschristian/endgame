<?php

use App\Models\User;
use App\Services\TicketCatalogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('manages project catalogs with revisions defaults and audit history', function () {
    [$admin, $manager, $project] = projectFixture();
    $service = app(TicketCatalogService::class);
    $service->save($manager, $project, 1, 'work-types', null, ['name' => 'Investigation', 'position' => 4, 'is_active' => true, 'is_default' => true]);
    expect(DB::table('work_types')->where('project_id', $project->id)->where('is_default', true)->pluck('name')->all())->toBe(['Investigation']);
    expect($project->fresh()->ticket_catalog_revision)->toBe(2)->and($project->fresh()->revision)->toBe(1);
    expect(DB::table('audit_events')->where('action', 'ticket.catalog_updated')->count())->toBe(1);
    $audit = json_decode(DB::table('audit_events')->where('action', 'ticket.catalog_updated')->value('metadata'), true);
    expect($audit['defaults_before'][0]['name'])->toBe('Task');
    expect(fn () => $service->save($manager, $project, 1, 'work-types', null, ['name' => 'Stale']))->toThrow(ValidationException::class);
    $id = DB::table('work_types')->where('name', 'Investigation')->value('id');
    expect(fn () => $service->save($manager, $project, 2, 'work-types', $id, ['name' => 'Investigation', 'is_active' => false, 'is_default' => false, 'position' => 4]))->toThrow(ValidationException::class);
});

it('rejects cross-project catalogs duplicate names and unauthorized catalog changes', function () {
    [$admin, $manager, $project, $projects] = projectFixture();
    $other = $projects->create($admin, ['key' => 'OTHER', 'name' => 'Other', 'manager_email' => $manager->email]);
    $foreign = DB::table('work_types')->where('project_id', $other->id)->value('id');
    $service = app(TicketCatalogService::class);
    expect(fn () => $service->save($manager, $project, 1, 'work-types', $foreign, ['name' => 'Foreign']))->toThrow(ValidationException::class);
    expect(fn () => $service->save($manager, $project, 1, 'work-types', null, ['name' => 'task', 'position' => 3, 'is_active' => true, 'is_default' => false]))->toThrow(ValidationException::class);
    expect(fn () => $service->save(User::factory()->create(), $project, 1, 'work-types', null, ['name' => 'Denied']))->toThrow(AuthorizationException::class);
});
