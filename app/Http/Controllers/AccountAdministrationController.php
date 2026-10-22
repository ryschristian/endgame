<?php

namespace App\Http\Controllers;

use App\Models\AccountInvitation;
use App\Models\User;
use App\Rules\PasswordByteLimit;
use App\Services\AccountService;
use App\Services\InvitationService;
use App\Support\AccountRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountAdministrationController extends Controller
{
    public function index(Request $request): Response
    {
        $statusOptions = [['value' => 'all', 'label' => 'All accounts'], ['value' => 'active', 'label' => 'Active'], ['value' => 'suspended', 'label' => 'Suspended']];
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(array_column($statusOptions, 'value'))]]);
        $status = $filters['status'] ?? 'all';
        $search = $filters['q'] ?? '';
        $activeAdmins = User::where('is_active', true)->where('is_system_admin', true)->count();
        $users = User::query()->select(['id', 'name', 'email', 'is_active', 'is_system_admin'])
            ->when($status !== 'all', fn ($query) => $query->where('is_active', $status === 'active'))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString()
            ->through(function (User $user) use ($request, $activeAdmins) {
                $lastAdmin = $user->is_active && $user->is_system_admin && $activeAdmins === 1;
                return [
                    'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                    'is_active' => $user->is_active, 'is_system_admin' => $user->is_system_admin,
                    'statusLabel' => $user->is_active ? 'Active' : 'Suspended',
                    'authorityLabel' => $user->is_system_admin ? 'System Admin' : 'Member',
                    'canChangeStatus' => ! $user->is($request->user()) && ! $lastAdmin,
                    'canChangeAdministrator' => ! $lastAdmin,
                    'restriction' => $lastAdmin ? 'At least one active System Admin must remain.' : ($user->is($request->user()) ? 'You cannot suspend your own account.' : null),
                ];
            });
        $invitations = AccountInvitation::whereNull('accepted_at')->orderByDesc('id')->paginate(10, ['id', 'email', 'expires_at', 'revoked_at', 'created_at'], 'invitations_page')->withQueryString()
            ->through(fn (AccountInvitation $invitation) => [
                'id' => $invitation->id, 'email' => $invitation->email,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'statusLabel' => $invitation->revoked_at ? 'Revoked' : ($invitation->expires_at->isPast() ? 'Expired' : 'Pending'),
                'canRevoke' => ! $invitation->revoked_at,
            ]);
        return Inertia::render('admin/accounts', ['users' => $users, 'invitations' => $invitations, 'statusOptions' => $statusOptions, 'filters' => ['q' => $search, 'status' => $status]]);
    }

    public function invite(Request $request, InvitationService $invitations): RedirectResponse
    {
        $request->merge(['email' => AccountRules::normalizeEmail($request->input('email'))]);
        $values = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $invitations->issue($request->user(), $values['email']);
        return to_route('accounts.index')->with('status', 'Invitation sent.');
    }

    public function resend(Request $request, AccountInvitation $invitation, InvitationService $invitations): RedirectResponse
    {
        $invitations->resend($request->user(), $invitation);
        return to_route('accounts.index')->with('status', 'A new invitation link has been sent. Previous links no longer work.');
    }

    public function revoke(Request $request, AccountInvitation $invitation, InvitationService $invitations): RedirectResponse
    {
        $invitations->revoke($request->user(), $invitation);
        return to_route('accounts.index')->with('status', 'Invitation revoked.');
    }

    public function status(Request $request, User $user, AccountService $accounts): RedirectResponse
    {
        $values = $request->validate(['is_active' => ['required', 'boolean'], 'current_password' => ['required', 'string', new PasswordByteLimit]]);
        $accounts->setActive($request->user(), $user, $values['is_active'], $values['current_password']);
        return to_route('accounts.index')->with('status', $values['is_active'] ? 'Account activated.' : 'Account suspended. Existing sessions have been signed out.');
    }

    public function administrator(Request $request, User $user, AccountService $accounts): RedirectResponse
    {
        $values = $request->validate(['is_system_admin' => ['required', 'boolean'], 'current_password' => ['required', 'string', new PasswordByteLimit]]);
        $updated = $accounts->setAdministrator($request->user(), $user, $values['is_system_admin'], $values['current_password']);
        $route = $request->user()->is($updated) && ! $updated->is_system_admin ? 'account.edit' : 'accounts.index';
        return to_route($route)->with('status', 'Administrator access updated.');
    }
}
