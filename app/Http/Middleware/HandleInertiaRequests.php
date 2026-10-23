<?php

namespace App\Http\Middleware;

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
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email']),
                'canManageAccounts' => $request->user()?->can('manage-accounts') ?? false,
            ],
            'flash' => fn () => ['status' => $request->session()->get('status')],
        ];
    }
}
