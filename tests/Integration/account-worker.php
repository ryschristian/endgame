<?php

use App\Models\User;
use App\Services\AccountService;
use App\Services\InvitationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || ! str_starts_with(config('database.connections.mysql.database'), 'endgame_p01_test_')) {
    throw new RuntimeException('Concurrency workers require a dedicated P01 SQL test database.');
}
$fixture = json_decode(file_get_contents(getenv('P01_CONCURRENCY_FIXTURE')), true, flags: JSON_THROW_ON_ERROR);
$worker = (int) ($argv[1] ?? 0);
$actor = isset($fixture['actors']) ? User::findOrFail($fixture['actors'][$worker]) : null;
file_put_contents($fixture['ready_prefix'].$worker, 'ready');
try {
    match ($fixture['operation']) {
        'bootstrap' => app(AccountService::class)->bootstrap('Concurrent Admin', "concurrent-$worker@example.test", 'ConcurrentPassword123'),
        'demote' => app(AccountService::class)->setAdministrator($actor, $actor, false, 'ConcurrentPassword123'),
        'suspend' => app(AccountService::class)->setActive($actor, User::findOrFail($fixture['actors'][1 - $worker]), false, 'ConcurrentPassword123'),
        'accept' => app(InvitationService::class)->accept($fixture['token'], ['name' => 'Concurrent Invitee', 'password' => 'ConcurrentPassword123', 'password_confirmation' => 'ConcurrentPassword123']),
    };
    echo 'success';
} catch (Illuminate\Validation\ValidationException|Illuminate\Auth\Access\AuthorizationException) {
    echo 'rejected';
}
