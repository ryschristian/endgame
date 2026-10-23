<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    public function show(string $token, InvitationService $invitations): Response
    {
        try {
            $invitation = $invitations->findValid($token);
        } catch (ValidationException) {
            return Inertia::render('auth/accept-invitation', ['valid' => false]);
        }
        return Inertia::render('auth/accept-invitation', ['valid' => true, 'email' => $invitation->email, 'token' => $token]);
    }

    public function store(Request $request, string $token, InvitationService $invitations): RedirectResponse
    {
        $request->validate(['email' => ['prohibited'], 'is_active' => ['prohibited'], 'is_system_admin' => ['prohibited']]);
        $invitations->accept($token, $request->only(['name', 'password', 'password_confirmation']));
        return to_route('login')->with('status', 'Your account is ready. You can now sign in.');
    }
}
