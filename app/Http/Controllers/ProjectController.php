<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectService;
use App\Support\ProjectPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $statusOptions = [['value' => 'active', 'label' => 'Active'], ['value' => 'archived', 'label' => 'Archived'], ['value' => 'all', 'label' => 'All projects']];
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(array_column($statusOptions, 'value'))]]);
        $status = $filters['status'] ?? 'active';
        $projects = Project::visibleTo($request->user())->select(['id', 'key', 'name', 'description', 'timezone', 'archived_at', 'updated_at'])
            ->withCount(['members' => fn ($q) => $q->whereNull('removed_at')])
            ->with(['members' => fn ($q) => $q->whereNull('removed_at')->orderBy('id')->limit(3)->with('user:id,name')])
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$q.'%')->orWhere('key', 'like', '%'.$q.'%')))
            ->when($status === 'active', fn ($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString()
            ->through(fn ($project) => [...$project->only(['id', 'key', 'name', 'description', 'timezone', 'archived_at', 'updated_at', 'members_count']),
                'statusLabel' => $project->archived_at ? 'Archived' : 'Active',
                'memberPreviews' => $project->members->map(fn ($member) => ['id' => $member->user_id, 'name' => $member->user->name]),
            ]);

        return Inertia::render('projects/index', ['projects' => $projects, 'filters' => ['q' => $filters['q'] ?? '', 'status' => $status], 'statusOptions' => $statusOptions, 'canCreate' => $request->user()->can('create', Project::class)]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('projects/create', ['teamTimezone' => Project::TEAM_TIMEZONE]);
    }

    public function store(Request $request, ProjectService $service): RedirectResponse
    {
        Gate::authorize('create', Project::class);
        $values = $request->validate([...$service->settingsRules(), 'key' => ['required', 'string', 'max:10'], 'manager_email' => ['required', 'email', 'max:254'], 'archived_at' => ['prohibited'], 'revision' => ['prohibited']]);
        $project = $service->create($request->user(), $values);

        return redirect()->route('projects.show', $project)->with('status', 'Project created.');
    }

    public function show(Request $request, Project $project): Response
    {
        ProjectPageData::authorize($request->user(), $project);
        $members = $project->members()->whereNull('removed_at')->with(['user:id,name', 'roles:id,name'])->orderBy('id')->paginate(20)->withQueryString()
            ->through(fn ($m) => ['id' => $m->id, 'name' => $m->user->name, 'roles' => $m->roles->pluck('name')]);
        $props = [...ProjectPageData::shared($request->user(), $project), 'members' => $members];

        return Inertia::render('projects/show', $props);
    }

    public function update(Request $request, Project $project, ProjectService $service): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, 'update');
        $values = $request->validate([...$service->settingsRules(), 'revision' => ['required', 'integer', 'min:1'], 'key' => ['prohibited'], 'archived_at' => ['prohibited']]);
        $revision = $values['revision'];
        unset($values['revision']);
        $service->update($request->user(), $project, $revision, $values);

        return ProjectPageData::redirect($request->user(), $project)->with('status', 'Project settings saved.');
    }

    public function archive(Request $request, Project $project, ProjectService $service): RedirectResponse
    {
        return $this->archiveState($request, $project, $service, true);
    }

    public function restore(Request $request, Project $project, ProjectService $service): RedirectResponse
    {
        return $this->archiveState($request, $project, $service, false);
    }

    private function archiveState(Request $request, Project $project, ProjectService $service, bool $archive): RedirectResponse
    {
        ProjectPageData::authorize($request->user(), $project, $archive ? 'archive' : 'restore');
        $values = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $service->setArchived($request->user(), $project, $values['revision'], $archive);

        return ProjectPageData::redirect($request->user(), $project)->with('status', $archive ? 'Project archived.' : 'Project restored.');
    }
}
