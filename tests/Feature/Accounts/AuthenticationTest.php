<?php

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('signs in active accounts using normalized email and rotates the session', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $this->withSession(['marker' => 'before']);
    $before = session()->getId();
    $this->post('/login', ['email' => ' USER@Example.com ', 'password' => 'password', 'remember' => true])
        ->assertRedirect('/account')->assertSessionHas('auth_version', $user->auth_version);
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($before);
});

it('does not disclose unknown or suspended account status', function (string $state) {
    if ($state === 'suspended') {
        User::factory()->suspended()->create(['email' => 'user@example.com']);
    } elseif ($state === 'wrong_password') {
        User::factory()->create(['email' => 'user@example.com']);
    }
    $this->post('/login', ['email' => 'user@example.com', 'password' => 'incorrect'])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    $this->assertGuest();
})->with(['unknown', 'suspended', 'wrong_password']);

it('throttles after five failed attempts', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', ['email' => 'unknown@example.com', 'password' => 'incorrect'])->assertSessionHasErrors('email');
    }
    $this->post('/login', ['email' => 'unknown@example.com', 'password' => 'incorrect'])->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Try again in one minute.']);
});

it('invalidates an authenticated session after suspension or a version change', function (bool $suspended) {
    $user = User::factory()->create();
    $this->signIn($user);
    User::whereKey($user->id)->update($suspended ? ['is_active' => false] : ['auth_version' => 2]);
    Auth::forgetGuards();
    $this->get('/account')->assertRedirect('/login');
    $this->assertGuest();
})->with([true, false]);

it('signs out using a post and clears authentication', function () {
    $this->signIn(User::factory()->create())->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
    $this->get('/logout')->assertStatus(405);
});

it('requires authentication for the account page', function () {
    $this->get('/account')->assertRedirect('/login');
});

it('updates only the signed-in users display name and audits it', function () {
    $user = User::factory()->create();
    $this->signIn($user)->patch('/account', ['name' => 'Updated Name'])->assertSessionHasNoErrors();
    expect($user->fresh()->name)->toBe('Updated Name');
    $this->assertDatabaseHas('audit_events', ['action' => 'account.profile_updated', 'actor_id' => $user->id]);
});

it('rejects protected profile fields', function (string $field) {
    $user = User::factory()->create();
    $this->signIn($user)->patch('/account', ['name' => 'Name', $field => $field === 'email' ? 'changed@example.com' : true])->assertSessionHasErrors($field);
    expect($user->fresh()->is_system_admin)->toBeFalse()->and($user->fresh()->email)->toBe($user->email);
})->with(['email', 'is_active', 'is_system_admin', 'auth_version', 'password']);

it('changes the password and revokes other sessions while preserving the current session', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $this->signIn($user)->put('/account/password', ['current_password' => 'password', 'password' => 'NewSecurePassword123', 'password_confirmation' => 'NewSecurePassword123'])->assertSessionHasNoErrors();
    expect(Hash::check('NewSecurePassword123', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    $this->get('/account')->assertOk();
    expect(AuditEvent::where('action', 'account.password_changed')->count())->toBe(1);
});

it('requires the old password and enforces safe password input', function (string $password, string $current) {
    $user = User::factory()->create();
    $this->signIn($user)->put('/account/password', ['current_password' => $current, 'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors();
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    'old password required' => ['NewSecurePassword123', 'wrong'],
    'short password' => ['Abc123', 'password'],
    'letters required' => ['1234567890123', 'password'],
    'numbers required' => ['LongPasswordWithoutNumbers', 'password'],
    'byte limit' => [str_repeat('é', 36).'A1', 'password'],
    'null bytes' => ["SecurePassword123\0suffix", 'password'],
]);

it('returns the same forgot-password confirmation for all identities', function (string $state) {
    Notification::fake();
    $user = $state === 'missing' ? null : User::factory()->create(['is_active' => $state === 'active']);
    $email = $user?->email ?? 'missing@example.com';
    $this->post('/forgot-password', ['email' => $email])->assertSessionHas('status', 'If an eligible account exists, a password reset link has been sent.');
    if ($state === 'active') {
        Notification::assertSentTo($user, ResetPassword::class);
    } else {
        Notification::assertNothingSent();
    }
})->with(['active', 'suspended', 'missing']);

it('resets an active accounts password once and revokes all sessions', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);
    DB::table('sessions')->insert(['id' => 'reset-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $values = ['email' => $user->email, 'token' => $token, 'password' => 'ResetPassword123', 'password_confirmation' => 'ResetPassword123'];
    $this->post('/reset-password', $values)->assertRedirect('/login')->assertSessionHasNoErrors();
    expect(Hash::check('ResetPassword123', $user->fresh()->password))->toBeTrue();
    $this->assertGuest();
    $this->assertDatabaseMissing('sessions', ['id' => 'reset-session']);
    $this->post('/reset-password', $values)->assertSessionHasErrors('email');
});

it('rejects expired and suspended-account reset tokens', function (bool $suspended) {
    $user = User::factory()->create();
    $token = Password::createToken($user);
    if ($suspended) {
        $user->forceFill(['is_active' => false])->save();
    } else {
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(61)]);
    }
    $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'ResetPassword123', 'password_confirmation' => 'ResetPassword123'])->assertSessionHasErrors('email');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([true, false]);

it('does not flash passwords or reset tokens as old input', function () {
    $this->post('/reset-password', ['email' => 'user@example.com', 'token' => 'secret-token', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation')->assertSessionMissing('_old_input.token');
});
