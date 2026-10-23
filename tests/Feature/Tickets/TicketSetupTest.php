<?php

use App\Models\Ticket;
use App\Support\ProjectPermissions;
use Illuminate\Support\Facades\DB;

it('initializes project catalogs and preserves the six-permission manager definition', function () {
    [$admin, $manager, $project, $service] = projectFixture();
    expect(DB::table('work_types')->where('project_id', $project->id)->orderBy('position')->pluck('name')->all())->toBe(['Task', 'Bug', 'Story']);
    expect(DB::table('priorities')->where('project_id', $project->id)->where('is_default', true)->value('name'))->toBe('Medium');
    expect(ProjectPermissions::managerKeys())->toHaveCount(6);
    expect($project->permissionKeys($manager))->toContain('ticket.view', 'ticket.create', 'ticket.update', 'ticket.configure');
    $role = $project->roles()->first();
    $role->permissions()->sync(ProjectPermissions::managerKeys());
    $service->ensureManager($project);
    expect(Ticket::count())->toBe(0);
});
