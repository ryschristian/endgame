<?php

// This fixture runner never opens the application's configured database.
$root = dirname(__DIR__, 2);
$database = getenv('DB_DATABASE');
$fixtureFile = getenv('P01_E2E_FIXTURES');
$workspace = $root.DIRECTORY_SEPARATOR.'.p01-work'.DIRECTORY_SEPARATOR;
if (getenv('APP_ENV') !== 'e2e' || getenv('DB_CONNECTION') !== 'sqlite'
    || ! is_string($database) || ! str_starts_with($database, $workspace.'e2e-')
    || ! str_ends_with($database, '.sqlite') || file_exists($database)
    || ! is_string($fixtureFile) || ! str_starts_with($fixtureFile, $workspace.'e2e-')
    || ! str_ends_with($fixtureFile, '.json') || str_contains(substr($database, strlen($workspace)), DIRECTORY_SEPARATOR)
    || str_contains(substr($fixtureFile, strlen($workspace)), DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Browser fixtures require new, isolated files inside .p01-work.');
}
if (! is_dir($workspace)) {
    mkdir($workspace, 0700, true);
}
touch($database);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

$password = 'BrowserPassword123';
$admin = app(App\Services\AccountService::class)->bootstrap('Browser Admin', 'admin@example.test', $password);
$member = App\Models\User::factory()->create(['name' => 'Browser Member', 'email' => 'member@example.test', 'password' => $password]);
$resetUser = App\Models\User::factory()->create(['name' => 'Reset User', 'email' => 'reset@example.test', 'password' => $password]);
$invitationToken = bin2hex(random_bytes(32));
App\Models\AccountInvitation::create(['email' => 'invited@example.test', 'token_hash' => hash('sha256', $invitationToken), 'invited_by' => $admin->id, 'expires_at' => now()->addHours(48)]);
$resetToken = Illuminate\Support\Facades\Password::createToken($resetUser);
file_put_contents($fixtureFile, json_encode(['password' => $password, 'invitationToken' => $invitationToken, 'resetToken' => $resetToken], JSON_THROW_ON_ERROR));

exit(Illuminate\Support\Facades\Artisan::call('serve', ['--host' => '127.0.0.1', '--port' => 8001, '--no-reload' => true], new Symfony\Component\Console\Output\ConsoleOutput));
