<?php

namespace App\Http\Controllers;

use App\Services\PasswordResetService;
use App\Support\AccountRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/forgot-password');
    }

    public function store(Request $request, PasswordResetService $passwords): RedirectResponse
    {
        $request->merge(['email' => AccountRules::normalizeEmail($request->input('email'))]);
        $values = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $passwords->sendLink($values['email']);
        return back()->with('status', 'If an eligible account exists, a password reset link has been sent.');
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('auth/reset-password', ['token' => $token, 'email' => is_string($request->query('email')) ? $request->query('email') : '']);
    }

    public function update(Request $request, PasswordResetService $passwords): RedirectResponse
    {
        $request->merge(['email' => AccountRules::normalizeEmail($request->input('email'))]);
        $values = $request->validate(['email' => ['required', 'email', 'max:254'], 'token' => ['required', 'string', 'max:255'], 'password' => AccountRules::password()]);
        $passwords->reset($values);
        return to_route('login')->with('status', 'Your password has been reset. You can now sign in.');
    }
}
