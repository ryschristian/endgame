# Endgame

Laravel 13 modular monolith with PHP 8.5, Vue 3, TypeScript, Inertia 3, Vite, Tailwind CSS 4, and shadcn-vue. Accounts, project access, and P03 ticket management are implemented. See [P03 implementation](docs/P03-implementation.md) and [handoff notes](docs/HANDOFF.md) for behavior, setup, and verification.

Read [AGENTS.md](AGENTS.md), [SDD.md](docs/SDD.md), [FRONTEND.md](FRONTEND.md), and [BACKEND.md](BACKEND.md) before making changes. The document review and setup scope are recorded in [the setup plan](docs/setup-plan.md).

## Local setup on Windows

PHP 8.5 is installed at `C:\php85\php.exe`. XAMPP's older PHP appears first on this machine's PATH. In each PowerShell terminal used for this project:

```powershell
$env:Path = "C:\php85;$env:Path"
$env:COMPOSER_IPRESOLVE = "4"
php --version
```

These settings affect only the current terminal. Composer's IPv4 setting avoids the connection timeout encountered on this machine. Use `npm.cmd`/`npx.cmd` because the current PowerShell policy blocks `npm.ps1`.

For a fresh checkout:

```powershell
composer install
npm.cmd ci
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
```

Generate the application key once for a new environment. Preserve an existing environment's key.

This machine uses **MySQL Community Server 8.4.13** at `127.0.0.1:3306`. MariaDB has been removed after its user databases were copied and verified. Start MySQL before starting Laravel:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/mysql.ps1 start
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/mysql.ps1 status
```

The script uses the private installation under `.setup/mysql`; it does not install a Windows service. Start MySQL again after restarting Windows. See [MySQL setup and migration](docs/MySQL-setup.md) for backup locations, verification, and stop instructions.

The existing `.env` points to MySQL database `endgame_import_20261023033240`, preserving the newest application data without overwriting the older MySQL `endgame` copy. Preserve that file and its generated password. For a fresh checkout or another machine, install MySQL and set that machine's connection details in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=endgame
DB_USERNAME=root
DB_PASSWORD=
```

Use your server's actual port and password; never commit credentials. MySQL tables use InnoDB and `utf8mb4`. All six former MariaDB user databases have verified MySQL copies, and a complete private installation archive is retained; see [MariaDB retirement](docs/MariaDB-retirement.md). Changing `DB_CONNECTION=mysql` alone does not change the server product. Verify `SELECT VERSION(), @@version_comment` before running migrations or SQL tests on another machine.

P01 uses database-backed sessions and SMTP account notifications. Set `SESSION_DRIVER=database` and `MAIL_MAILER=smtp` in an existing `.env`; the old foundation settings are not overwritten automatically. Cache remains file-backed and jobs run synchronously. After verifying the database target, run `php artisan migrate` and `php artisan app:bootstrap-admin` once. Project permissions belong to P02.

Start the application and Vite together:

```powershell
composer run dev
```

Open `http://127.0.0.1:8000`. The welcome page includes a link to Laravel's `/up` health endpoint. For a production asset build, run `npm.cmd run build`.

## Checks

Use the PHP 8.5 PATH setting above before backend or browser checks.

```powershell
composer validate --strict
composer check-platform-reqs
composer test
composer run format:check
npm.cmd run typecheck
npm.cmd run format:check
npm.cmd test
npm.cmd run build
npx.cmd playwright install chromium
npm.cmd run test:e2e
```

Playwright starts a separate Laravel server on port 8001 with a new isolated SQLite fixture database and uses production assets. Run the build first and stop any Vite process that has left `public/hot` active. Journeys cover invitation acceptance, authentication, profile changes, password reset, suspension, CSRF protection, and health. `composer run test:sql` creates a new isolated test schema on the configured SQL server and additionally checks concurrent account operations; see the P01 notes before running it. No application database is reset or reused for fixtures.

Vitest uses Vue Test Utils 2.4 to support the installed Node 24.14 release. TypeScript is constrained to 5.9 because the installed Vue type checker still requires the pre-TypeScript-7 compiler entry point.

## UI components

The generated shadcn-vue Button and its styles live in `resources/js/components/ui/button` and `resources/css/shadcn.css`. `components.json` records the generator configuration. Shared shadcn CSS is kept with the generated source so the application does not need the generator CLI and its dependency tree as a runtime package. Keep component filenames in kebab case when adding UI primitives.

## Deferred infrastructure

Docker, Nginx, storage integrations, Redis/Horizon, queue workers, external authentication providers, GitHub/GitLab integrations, and coding-agent execution are outside this setup. Laravel's standard filesystem and queue configuration files do not install those services.
