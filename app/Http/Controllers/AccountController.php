<?php

namespace App\Http\Controllers;

use App\Services\AccountService;
use App\Support\AccountRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('account/edit');
    }

    public function update(Request $request, AccountService $accounts): RedirectResponse
    {
        $values = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['prohibited'], 'password' => ['prohibited'], 'is_active' => ['prohibited'], 'is_system_admin' => ['prohibited'], 'auth_version' => ['prohibited']]);
        $accounts->updateProfile($request->user(), $values['name']);
        return to_route('account.edit')->with('status', 'Your profile has been updated.');
    }

    public function password(Request $request, AccountService $accounts): RedirectResponse
    {
        $values = $request->validate(['current_password' => ['required', 'string'], 'password' => AccountRules::password()]);
        $user = $accounts->changePassword($request->user(), $values['current_password'], $values['password']);
        Auth::setUser($user);
        $request->session()->regenerate(true);
        $request->session()->put('auth_version', $user->auth_version);
        return to_route('account.edit')->with('status', 'Your password has been changed. Other sessions have been signed out.');
    }
}
