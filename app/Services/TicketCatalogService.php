<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TicketCatalogService
{
    public function __construct(private TicketService $tickets, private ProjectService $projects) {}

    public function save(User $actor, Project $project, int $revision, string $catalog, ?int $id, array $values): void
    {
        DB::transaction(function () use ($actor, $project, $revision, $catalog, $id, $values) {
            [$actor, $project] = $this->tickets->lock($actor, $project, 'configure');
            if ($project->ticket_catalog_revision !== $revision) {
                $this->invalid('revision', 'Ticket catalogs changed. Reload before saving again.');
            }
            $table = ['work-types' => 'work_types', 'priorities' => 'priorities'][$catalog] ?? null;
            if (! $table) {
                $this->invalid('catalog', 'Choose a valid ticket catalog.');
            }
            $record = $id ? DB::table($table)->where('project_id', $project->id)->where('id', $id)->first() : null;
            if ($id && ! $record) {
                $this->invalid('catalog', 'Choose a record from this project.');
            }
            $values['name'] = is_string($values['name'] ?? null) ? trim($values['name']) : null;
            $data = Validator::make($values, ['name' => ['required', 'string', 'max:80'], 'position' => ['required', 'integer', 'min:0', 'max:100000'], 'is_active' => ['required', 'boolean'], 'is_default' => ['required', 'boolean']])->validate();
            if (DB::table($table)->where('project_id', $project->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                $this->invalid('name', 'This name is already used in this project.');
            }
            if ($data['is_default'] && ! $data['is_active']) {
                $this->invalid('is_default', 'The default must be active.');
            }
            if ($record?->is_default && ! $data['is_default']) {
                $this->invalid('is_default', 'Choose another default before changing this record.');
            }
            $before = $record ? (array) $record : null;
            $defaultsBefore = DB::table($table)->where('project_id', $project->id)->where('is_default', true)->get()->map(fn ($row) => (array) $row)->all();
            if ($record && $record->name === $data['name'] && (int) $record->position === (int) $data['position'] && (bool) $record->is_active === (bool) $data['is_active'] && (bool) $record->is_default === (bool) $data['is_default']) {
                $this->invalid('catalog', 'No catalog fields have changed.');
            }
            if ($data['is_default']) {
                DB::table($table)->where('project_id', $project->id)->update(['is_default' => false]);
            }
            if ($record) {
                DB::table($table)->where('id', $id)->update($data);
            } else {
                $id = DB::table($table)->insertGetId(['project_id' => $project->id, ...$data]);
            }
            $project->increment('ticket_catalog_revision');
            $defaultsAfter = DB::table($table)->where('project_id', $project->id)->where('is_default', true)->get()->map(fn ($row) => (array) $row)->all();
            $this->projects->audit($actor, $project, 'ticket.catalog_updated', ['catalog' => $catalog, 'record_id' => $id, 'before' => $before, 'after' => $data, 'defaults_before' => $defaultsBefore, 'defaults_after' => $defaultsAfter]);
        }, 3);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
