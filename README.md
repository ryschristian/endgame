# Endgame

Laravel 13 modular monolith with PHP 8.5, Vue 3, TypeScript, Inertia 3, Vite, Tailwind CSS 4, and shadcn-vue. The installed application is a minimal foundation; product modules require their approved process specifications.

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

Set your existing MySQL server's connection details in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=endgame
DB_USERNAME=root
DB_PASSWORD=
```

The database name and credentials are initial local defaults, not a verified connection. Enter the actual password directly in `.env`; never commit it. MySQL tables use InnoDB and `utf8mb4`. No MySQL server was installed and no migrations were run during foundation setup. Verify the target connection before running `php artisan migrate`. Tests that exercise database behavior must also run against a dedicated MySQL test database once those features exist.

Sessions and cache use Laravel's local file drivers, and jobs run synchronously so the application shell does not depend on database tables or background workers. Laravel's built-in session authentication and policy support are available; account screens, invitations, and project permissions belong to P01/P02.

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

Playwright starts a separate Laravel server on port 8001 and uses the production assets, so run the build first and stop any Vite process that has left `public/hot` active. The smoke checks cover initial Inertia rendering, client page data, the Vue component, browser JavaScript startup, and navigation to the health endpoint. No smoke check claims to verify a live MySQL connection.

Vitest uses Vue Test Utils 2.4 to support the installed Node 24.14 release. TypeScript is constrained to 5.9 because the installed Vue type checker still requires the pre-TypeScript-7 compiler entry point.

## UI components

The generated shadcn-vue Button and its styles live in `resources/js/components/ui/button` and `resources/css/shadcn.css`. `components.json` records the generator configuration. Shared shadcn CSS is kept with the generated source so the application does not need the generator CLI and its dependency tree as a runtime package. Keep component filenames in kebab case when adding UI primitives.

## Deferred infrastructure

Docker, Nginx, storage integrations, Redis/Horizon, queue workers, external authentication providers, GitHub/GitLab integrations, and coding-agent execution are outside this setup. Laravel's standard filesystem and queue configuration files do not install those services.
