<?php

use App\Models\AccountInvitation;
use App\Models\AuditEvent;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ProjectService;
use App\Services\TicketService;
use App\Support\ProjectPermissions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
$server = new PDO("mysql:host={$config['host']};port={$config['port']}", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$serverVersion = $server->query('SELECT VERSION()')->fetchColumn();
$serverProduct = $server->query('SELECT @@version_comment')->fetchColumn();
if (stripos($serverVersion, 'MariaDB') !== false || stripos($serverProduct, 'MySQL') === false) {
    throw new RuntimeException('SQL verification requires an actual MySQL server. Check DB_HOST and DB_PORT before continuing.');
}
$database = 'endgame_p01_test_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
// Only create a new schema with a generated identifier. Never reuse or drop a schema.
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo 'Server: '.$serverVersion.' ('.$serverProduct.')'.PHP_EOL;
echo "Isolated test database: $database (retained; application database untouched)".PHP_EOL;
$environment = [
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_URL' => '',
    'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
    'MAIL_MAILER' => 'array', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'BCRYPT_ROUNDS' => '4',
];
foreach ($environment as $name => $value) {
    putenv("$name=$value");
    $_ENV[$name] = $_SERVER[$name] = $value;
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null, 'hashing.bcrypt.rounds' => 4]);
DB::purge('mysql');
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Test migrations failed.');
}
$suite = new Process([PHP_BINARY, 'artisan', 'test', '--compact'], $root, $environment, timeout: 90);
$suite->run();
echo $suite->getOutput();
if (! $suite->isSuccessful()) {
    fwrite(STDERR, $suite->getErrorOutput());
    exit(1);
}
$workspace = $root.'/.p01-work';
if (! is_dir($workspace)) {
    mkdir($workspace, 0700, true);
}

function race(string $operation, array $values = [], array $allowedResults = [['rejected', 'success']]): void
{
    global $root, $workspace, $environment;
    $prefix = $workspace.'/race-'.bin2hex(random_bytes(6));
    $fixtureFile = $prefix.'.json';
    file_put_contents($fixtureFile, json_encode(['operation' => $operation, 'ready_prefix' => $prefix.'-ready-', ...$values], JSON_THROW_ON_ERROR));
    $workers = [];
    DB::beginTransaction();
    app(AccountService::class)->lockAdministration();
    try {
        foreach ([0, 1] as $worker) {
            $process = new Process([PHP_BINARY, 'tests/Integration/account-worker.php', (string) $worker], $root, [...$environment, 'P01_CONCURRENCY_FIXTURE' => $fixtureFile], timeout: 30);
            $process->start();
            $workers[] = $process;
        }
        $deadline = microtime(true) + 15;
        while (! file_exists($prefix.'-ready-0') || ! file_exists($prefix.'-ready-1')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Concurrency workers did not become ready.');
            }
            usleep(10000);
        }
        DB::commit();
        $results = [];
        foreach ($workers as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('A concurrency worker failed: '.$process->getErrorOutput());
            }
            $results[] = trim($process->getOutput());
        }
        sort($results);
        if (! in_array($results, $allowedResults, true)) {
            throw new RuntimeException("Expected exactly one successful $operation, got ".json_encode($results));
        }
        echo "PASS concurrent $operation: ".implode(', ', $results).PHP_EOL;
    } finally {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
        foreach ($workers as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }
}

race('bootstrap');
if (User::where('is_system_admin', true)->count() !== 1 || AuditEvent::where('action', 'account.bootstrapped')->count() !== 1) {
    throw new RuntimeException('Bootstrap did not preserve a single administrator and audit record.');
}
$first = User::where('is_system_admin', true)->firstOrFail();
$second = User::factory()->admin()->create(['password' => 'ConcurrentPassword123']);
race('demote', ['actors' => [$first->id, $second->id]]);
if (User::where('is_active', true)->where('is_system_admin', true)->count() !== 1) {
    throw new RuntimeException('Concurrent demotion removed the final administrator.');
}
$remaining = User::where('is_active', true)->where('is_system_admin', true)->firstOrFail();
$other = User::factory()->admin()->create(['password' => 'ConcurrentPassword123']);
race('suspend', ['actors' => [$remaining->id, $other->id]]);
if (User::where('is_active', true)->where('is_system_admin', true)->count() !== 1) {
    throw new RuntimeException('Concurrent suspension removed the final administrator.');
}
$remaining = User::where('is_active', true)->where('is_system_admin', true)->firstOrFail();
$token = bin2hex(random_bytes(32));
$invitation = AccountInvitation::create(['email' => 'race-invitee@example.test', 'token_hash' => hash('sha256', $token), 'invited_by' => $remaining->id, 'expires_at' => now()->addHours(48)]);
race('accept', ['token' => $token]);
if (User::where('email', $invitation->email)->count() !== 1 || AuditEvent::where('action', 'invitation.accepted')->count() !== 1) {
    throw new RuntimeException('Concurrent acceptance did not preserve a single identity and audit event.');
}
$projects = app(ProjectService::class);
$firstManager = User::factory()->create();
$secondManager = User::factory()->create();
$project = $projects->create($remaining, ['key' => 'RACEONE', 'name' => 'Manager removal versus suspension', 'timezone' => 'Asia/Manila', 'manager_email' => $firstManager->email]);
$managerRole = $project->roles()->firstOrFail();
$projects->addMember($remaining, $project, 1, $secondManager->email, [$managerRole->id]);
race('project-remove-suspend', ['actors' => [$remaining->id, $remaining->id], 'project_id' => $project->id, 'revision' => 2,
    'member_id' => $project->members()->where('user_id', $firstManager->id)->firstOrFail()->id, 'suspend_user_id' => $secondManager->id]);
$projects->ensureManager($project);

$firstManager = User::factory()->create();
$secondManager = User::factory()->create();
$project = $projects->create($remaining, ['key' => 'RACETWO', 'name' => 'Role downgrade versus removal', 'timezone' => 'Asia/Manila', 'manager_email' => $firstManager->email]);
$firstRole = $project->roles()->firstOrFail();
$secondRole = $projects->saveRole($remaining, $project, 1, null, 'Second manager', ProjectPermissions::keys());
$member = $projects->addMember($remaining, $project, 2, $secondManager->email, [$secondRole->id]);
race('project-role-remove', ['actors' => [$remaining->id, $remaining->id], 'project_id' => $project->id, 'revision' => 3, 'role_id' => $firstRole->id, 'member_id' => $member->id]);
$projects->ensureManager($project);

$invitee = User::factory()->create();
$token = bin2hex(random_bytes(32));
$invitation = ProjectInvitation::create(['project_id' => $project->id, 'user_id' => $invitee->id, 'email' => $invitee->email, 'token_hash' => hash('sha256', $token), 'invited_by' => $remaining->id, 'expires_at' => now()->addHours(48)]);
race('project-accept', ['actors' => [$invitee->id, $invitee->id], 'token' => $token]);
if ($project->members()->where('user_id', $invitee->id)->count() !== 1 || AuditEvent::where('action', 'project.invitation_accepted')->count() !== 1) {
    throw new RuntimeException('Project invitation race did not preserve one membership and audit event.');
}
$invitee = User::factory()->create();
$token = bin2hex(random_bytes(32));
$invitation = ProjectInvitation::create(['project_id' => $project->id, 'user_id' => $invitee->id, 'email' => $invitee->email, 'token_hash' => hash('sha256', $token), 'invited_by' => $remaining->id, 'expires_at' => now()->addHours(48)]);
$reader = $projects->saveRole($remaining, $project, $project->fresh()->revision, null, 'Race reader', ['project.view']);
$invitation->roles()->sync([$reader->id => ['project_id' => $project->id]]);
race('project-add-accept', ['actors' => [$remaining->id, $invitee->id], 'project_id' => $project->id, 'revision' => $project->fresh()->revision, 'token' => $token, 'email' => $invitee->email]);
$member = $project->members()->where('user_id', $invitee->id)->firstOrFail();
$expectedRoles = $invitation->fresh()->accepted_at ? [$reader->id] : [];
if ($member->roles()->pluck('roles.id')->all() !== $expectedRoles) {
    throw new RuntimeException('Direct addition and invitation acceptance overwrote the winning role selection.');
}
echo 'PASS all P01/P02 SQL concurrency checks'.PHP_EOL;

$project = $projects->create($remaining, ['key' => 'TICKETS', 'name' => 'Ticket races', 'manager_email' => $remaining->email]);
$values = ['summary' => 'Concurrent ticket', 'work_type_id' => DB::table('work_types')->where('project_id', $project->id)->where('is_default', true)->value('id'), 'priority_id' => DB::table('priorities')->where('project_id', $project->id)->where('is_default', true)->value('id')];
$actors = [$remaining->id, $remaining->id];
race('ticket-create', ['actors' => $actors, 'project_id' => $project->id, 'values' => $values], [['success', 'success']]);
if ($project->tickets()->count() !== 2 || $project->tickets()->distinct()->count('number') !== 2 || DB::table('project_counters')->where('project_id', $project->id)->value('next_number') !== 3) {
    throw new RuntimeException('Concurrent ticket creation lost or reused a number.');
}
$ticket = $project->tickets()->firstOrFail();
race('ticket-edit', ['actors' => $actors, 'project_id' => $project->id, 'ticket_id' => $ticket->id, 'revision' => 1, 'values' => $values]);
if ($ticket->fresh()->revision !== 2 || $ticket->events()->count() !== 2) {
    throw new RuntimeException('Concurrent edits overwrote data or lost history.');
}
$reader = $projects->saveRole($remaining, $project, $project->fresh()->revision, null, 'Assignable reader', ['project.view']);
foreach (['suspend', 'remove'] as $operation) {
    $target = User::factory()->create();
    $member = $projects->addMember($remaining, $project, $project->fresh()->revision, $target->email, [$reader->id]);
    $revision = $ticket->fresh()->revision;
    race('ticket-assign-'.$operation, ['actors' => $actors, 'project_id' => $project->id, 'ticket_id' => $ticket->id, 'revision' => $revision, 'project_revision' => $project->fresh()->revision, 'target_id' => $target->id, 'member_id' => $member->id, 'values' => [...$values, 'summary' => 'Assignment versus '.$operation, 'assignee_id' => $target->id]], [['rejected', 'success'], ['success', 'success']]);
    if (app(TicketService::class)->eligibleAssignee($project, $target->id)) {
        throw new RuntimeException('Unavailable member remains eligible for new assignment.');
    }
    if ($ticket->events()->count() !== $ticket->fresh()->revision) {
        throw new RuntimeException('Assignment race lost event/revision integrity.');
    }
}
echo 'PASS all P03 SQL concurrency checks'.PHP_EOL;
