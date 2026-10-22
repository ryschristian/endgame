<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/login');
    }

    public function store(LoginRequest $request, AccountService $accounts): RedirectResponse
    {
        $key = 'login:'.hash('sha256', $request->validated('email').'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Try again in one minute.']);
        }
        $authenticated = DB::transaction(function () use ($request, $accounts) {
            $accounts->lockAdministration();
            if (! Auth::attempt([...$request->safe()->only(['email', 'password']), 'is_active' => true], $request->boolean('remember'))) {
                return false;
            }
            $request->session()->regenerate();
            $request->session()->put('auth_version', $request->user()->auth_version);
            return true;
        }, 3);
        if (! $authenticated) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        RateLimiter::clear($key);
        $intended = $request->session()->pull('url.intended');
        // Only known local, authorized landing pages can be reused after authentication.
        if ($intended === route('account.edit') || ($request->user()->can('manage-accounts') && $intended === route('accounts.index'))) {
            return redirect($intended);
        }
        return to_route('account.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return to_route('login');
    }
}
