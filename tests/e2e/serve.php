<?php

use App\Models\AccountInvitation;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ProjectService;
use App\Services\TicketService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Console\Output\ConsoleOutput;

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
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('e2e') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database || config('database.connections.sqlite.url')) {
    throw new RuntimeException('Resolved browser configuration must use the isolated SQLite fixture.');
}
Artisan::call('migrate', ['--force' => true]);

$password = 'BrowserPassword123';
$admin = app(AccountService::class)->bootstrap('Browser Admin', 'admin@example.test', $password);
$member = User::factory()->create(['name' => 'Browser Member', 'email' => 'member@example.test', 'password' => $password]);
$resetUser = User::factory()->create(['name' => 'Reset User', 'email' => 'reset@example.test', 'password' => $password]);
$invitationToken = bin2hex(random_bytes(32));
AccountInvitation::create(['email' => 'invited@example.test', 'token_hash' => hash('sha256', $invitationToken), 'invited_by' => $admin->id, 'expires_at' => now()->addHours(48)]);
$resetToken = Password::createToken($resetUser);
$manager = User::factory()->create(['name' => 'Project Manager', 'email' => 'manager@example.test', 'password' => $password]);
User::factory()->create(['name' => 'Project Member', 'email' => 'project-member@example.test', 'password' => $password]);
$projectInvitee = User::factory()->create(['name' => 'Project Invitee', 'email' => 'project-invitee@example.test', 'password' => $password]);
$projects = app(ProjectService::class);
$project = $projects->create($admin, ['key' => 'TEAM', 'name' => 'Browser team', 'description' => 'A shared workspace for our product, design, and engineering work.', 'timezone' => 'Asia/Manila', 'manager_email' => $manager->email]);
app(TicketService::class)->create($manager, $project, ['summary' => 'Review project scope', 'work_type_id' => DB::table('work_types')->where('project_id', $project->id)->where('is_default', true)->value('id'), 'priority_id' => DB::table('priorities')->where('project_id', $project->id)->where('is_default', true)->value('id')]);
$reader = $projects->saveRole($manager, $project, 1, null, 'Fixture Reader', ['project.view']);
$hidden = $projects->create($admin, ['key' => 'HIDDEN', 'name' => 'Private project', 'timezone' => 'Asia/Manila', 'manager_email' => $manager->email]);
foreach ([
    ['WEB', 'Website redesign', 'Create a clearer, more focused home for our product and the people who use it.'],
    ['APP', 'Mobile app', 'Build a thoughtful mobile experience that keeps the team connected on the move.'],
    ['LAUNCH', 'Product launch', 'Coordinate launch planning, documentation, and everything needed for release.'],
] as [$key, $name, $description]) {
    $projects->create($admin, ['key' => $key, 'name' => $name, 'description' => $description, 'manager_email' => $manager->email]);
}
$projectInvitationToken = bin2hex(random_bytes(32));
$projectInvitation = ProjectInvitation::create(['project_id' => $project->id, 'user_id' => $projectInvitee->id, 'email' => $projectInvitee->email, 'invited_by' => $manager->id, 'token_hash' => hash('sha256', $projectInvitationToken), 'expires_at' => now()->addHours(48)]);
$projectInvitation->roles()->sync([$reader->id => ['project_id' => $project->id]]);
file_put_contents($fixtureFile, json_encode(['password' => $password, 'invitationToken' => $invitationToken, 'resetToken' => $resetToken, 'projectId' => $project->id, 'hiddenProjectId' => $hidden->id, 'projectInvitationToken' => $projectInvitationToken], JSON_THROW_ON_ERROR));

exit(Artisan::call('serve', ['--host' => '127.0.0.1', '--port' => 8001, '--no-reload' => true], new ConsoleOutput));
