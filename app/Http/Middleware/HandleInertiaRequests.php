<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'passwordHint' => 'Use at least 12 characters, including letters and numbers.',
            'teamTimezone' => Project::TEAM_TIMEZONE,
            'workspace' => fn () => [
                'projects' => $request->user()
                    ? Project::visibleTo($request->user())->whereNull('archived_at')->orderBy('name')->limit(8)->get(['id', 'key', 'name'])
                    : [],
                'canCreateProjects' => $request->user()?->can('create', Project::class) ?? false,
            ],
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email']),
                'canManageAccounts' => $request->user()?->can('manage-accounts') ?? false,
            ],
            'flash' => fn () => ['status' => $request->session()->get('status')],
        ];
    }
}
