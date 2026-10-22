<?php

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

it('bootstraps one verified administrator without replacing existing users', function () {
    $admin = app(AccountService::class)->bootstrap('First Admin', ' ADMIN@Example.com ', 'SecurePassword123');

    expect($admin->email)->toBe('admin@example.com')
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->is_system_admin)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('SecurePassword123', $admin->password))->toBeTrue();
    expect(AuditEvent::where('action', 'account.bootstrapped')->count())->toBe(1);

    expect(fn () => app(AccountService::class)->bootstrap('Another Admin', 'other@example.com', 'SecurePassword123'))->toThrow(ValidationException::class);
});

it('never overwrites an existing identity during bootstrap', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    try {
        app(AccountService::class)->bootstrap('Admin', 'existing@example.com', 'SecurePassword123');
    } finally {
        expect($user->fresh()->name)->toBe($user->name)
            ->and($user->fresh()->is_system_admin)->toBeFalse();
    }
})->throws(ValidationException::class);

it('refuses bootstrap even if the existing admin is suspended', function () {
    User::factory()->admin()->suspended()->create();
    app(AccountService::class)->bootstrap('Admin', 'admin@example.com', 'SecurePassword123');
})->throws(ValidationException::class);

it('revokes sessions and reset tokens when suspending an account', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'old-hash', 'created_at' => now()]);

    app(AccountService::class)->setActive($admin, $user, false, 'password');

    expect($user->fresh()->is_active)->toBeFalse()
        ->and($user->fresh()->auth_version)->toBe($user->auth_version + 1)
        ->and($user->fresh()->remember_token)->not->toBe($user->remember_token);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseHas('audit_events', ['action' => 'account.suspended', 'target_user_id' => $user->id]);
});

it('protects the final active admin from demotion', function () {
    $admin = User::factory()->admin()->create();
    app(AccountService::class)->setAdministrator($admin, $admin, false, 'password');
})->throws(ValidationException::class);

it('allows self-demotion only when another active admin exists', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();
    app(AccountService::class)->setAdministrator($admin, $admin, false, 'password');
    expect($admin->fresh()->is_system_admin)->toBeFalse();
});

it('does not count suspended administrators as usable administrators', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->suspended()->create();
    app(AccountService::class)->setAdministrator($admin, $admin, false, 'password');
})->throws(ValidationException::class);

it('rejects self-suspension', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();
    app(AccountService::class)->setActive($admin, $admin, false, 'password');
})->throws(ValidationException::class);

it('rechecks an actors current authority inside the operation', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    User::whereKey($admin->id)->update(['is_system_admin' => false]);
    app(AccountService::class)->setActive($admin, $user, false, 'password');
})->throws(AuthorizationException::class);

it('requires the actors current password for access changes', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    app(AccountService::class)->setActive($admin, $user, false, 'incorrect');
})->throws(ValidationException::class);

it('does not audit repeated no-op account changes', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    try {
        app(AccountService::class)->setActive($admin, $user, true, 'password');
    } finally {
        expect(AuditEvent::count())->toBe(0);
    }
})->throws(ValidationException::class);

it('keeps account flags outside ordinary mass assignment', function () {
    $user = User::factory()->create();
    $user->fill(['name' => 'New Name', 'is_active' => false, 'is_system_admin' => true, 'auth_version' => 100])->save();
    expect($user->fresh()->is_active)->toBeTrue()
        ->and($user->fresh()->is_system_admin)->toBeFalse()
        ->and($user->fresh()->auth_version)->toBe(1);
});
