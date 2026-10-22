<?php

use App\Models\AccountInvitation;
use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('protects every administrator endpoint from ordinary users', function (string $verb, string $path) {
    $user = User::factory()->create();
    $target = User::factory()->create();
    $invitation = AccountInvitation::create(['email' => 'pending@example.com', 'token_hash' => hash('sha256', 'test-token'), 'invited_by' => $user->id, 'expires_at' => now()->addHours(48)]);
    $path = str_replace('999', (string) (str_contains($path, '/invitations/') ? $invitation->id : $target->id), $path);
    $this->signIn($user)->{$verb}($path, ['email' => 'invitee@example.com', 'is_active' => false, 'is_system_admin' => true, 'current_password' => 'password'])->assertForbidden();
})->with([
    ['get', '/admin/accounts'], ['post', '/admin/invitations'], ['post', '/admin/invitations/999/resend'], ['post', '/admin/invitations/999/revoke'], ['patch', '/admin/accounts/999/status'], ['patch', '/admin/accounts/999/administrator'],
]);

it('excludes sensitive account fields and provides authorized filter options', function () {
    $admin = User::factory()->admin()->create();
    $this->signIn($admin)->get('/admin/accounts')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/accounts', false)
        ->has('statusOptions', 3)
        ->has('users.data', 1)
        ->missing('users.data.0.password')
        ->missing('users.data.0.remember_token')
        ->missing('users.data.0.auth_version')
    );
});

it('filters administrator account lists on the server', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Visible Person', 'email' => 'visible@example.com']);
    User::factory()->suspended()->create(['name' => 'Suspended Person']);
    $this->signIn($admin)->get('/admin/accounts?q=Visible&status=active')->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Visible Person'));
    $this->get('/admin/accounts?status=not-allowed')->assertSessionHasErrors('status');
});

it('creates and resends invitations without exposing the token in administrator props', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $this->signIn($admin)->post('/admin/invitations', ['email' => 'new@example.com'])->assertSessionHasNoErrors();
    $invitation = AccountInvitation::firstOrFail();
    $this->post("/admin/invitations/{$invitation->id}/resend")->assertSessionHasNoErrors();
    $this->get('/admin/accounts')->assertInertia(fn (Assert $page) => $page->has('invitations.data', 1)->missing('invitations.data.0.token_hash')->missing('invitations.data.0.token'));
});

it('accepts an invitation via the web without silently authenticating the invitee', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    app(InvitationService::class)->issue($admin, 'new@example.com');
    $token = null;
    Notification::assertSentOnDemand(AccountInvitationNotification::class, function ($notification) use (&$token) { $token = $notification->token; return true; });
    $this->get('/invitations/'.$token)->assertInertia(fn (Assert $page) => $page->component('auth/accept-invitation', false)->where('valid', true)->where('email', 'new@example.com'));
    $this->post('/invitations/'.$token, ['name' => 'New User', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123'])->assertRedirect('/login');
    $this->assertGuest();
    $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'is_system_admin' => false]);
    $this->get('/invitations/'.$token)->assertInertia(fn (Assert $page) => $page->where('valid', false)->missing('token'));
});

it('does not let signed-in users accept another identity', function () {
    $this->signIn(User::factory()->create())->post('/invitations/'.str_repeat('a', 64), ['name' => 'Other'])->assertRedirect('/account');
});

it('requires password confirmation for administrative status changes', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $this->signIn($admin)->patch("/admin/accounts/{$user->id}/status", ['is_active' => false, 'current_password' => 'incorrect'])->assertSessionHasErrors('current_password');
    expect($user->fresh()->is_active)->toBeTrue();
});

it('redirects a self-demoted administrator to their account page', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();
    $this->signIn($admin)->patch("/admin/accounts/{$admin->id}/administrator", ['is_system_admin' => false, 'current_password' => 'password'])->assertRedirect('/account');
    $this->get('/admin/accounts')->assertForbidden();
});

it('supports interactive bootstrap without password arguments or default accounts', function () {
    $this->artisan('app:bootstrap-admin')
        ->expectsQuestion('Name', 'Operator')
        ->expectsQuestion('Email', ' OPERATOR@Example.com ')
        ->expectsQuestion('Password', 'SecurePassword123')
        ->expectsQuestion('Confirm password', 'SecurePassword123')
        ->expectsOutput('System Admin created. You can now sign in.')
        ->assertSuccessful();
    $this->assertDatabaseHas('users', ['email' => 'operator@example.com', 'is_system_admin' => true]);
    expect(AuditEvent::where('action', 'account.bootstrapped')->count())->toBe(1);
});

it('rejects mismatched bootstrap confirmation without creating a user', function () {
    $this->artisan('app:bootstrap-admin')->expectsQuestion('Name', 'Operator')->expectsQuestion('Email', 'operator@example.com')->expectsQuestion('Password', 'SecurePassword123')->expectsQuestion('Confirm password', 'DifferentPassword123')->expectsOutput('The password confirmation does not match.')->assertFailed();
    expect(User::count())->toBe(0);
});
