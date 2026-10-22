# Technical architecture installation plan

**Scope:** Install the foundation described in `docs/SDD.md` section 3, using PHP 8.5 as requested. Preserve the four existing project documents. This does not implement a product phase.

## Review findings

- The workspace initially contains documentation only, with no application, package manifests, tests, or Git repository.
- `AGENTS.md`, `FRONTEND.md`, and `BACKEND.md` agree on a conventional Laravel/Inertia monolith with server-owned authorization and feature-specific Vue components.
- The SDD remains marked proposed; no approved phase/process specification exists. Product modules and their database tables must wait for those specifications.
- The SDD explicitly defers storage integrations, queue infrastructure, Docker, and Nginx. The user reiterated the Docker, Nginx, and storage exclusions.
- The active `php` command resolves to XAMPP PHP 8.0.30. PHP 8.5.11 is separately installed at `C:\php85\php.exe`; use that executable and a process-local PATH, without changing machine settings.
- Node 24 and Composer 2 are available. Use `npm.cmd` because PowerShell blocks `npm.ps1` under its current execution policy.
- The discovered XAMPP database client is MariaDB 10.4, not MySQL. Configure Laravel for MySQL without silently substituting MariaDB, creating databases, or running migrations against an unverified connection.
- `BACKEND.md` permits editing original migrations only for disposable history. There is no existing application history to preserve, but future changes must verify this condition again.

## Installation steps

- [x] Download the official Laravel 13 base scaffold into a staging directory and copy it into the workspace without overwriting the existing documentation.
- [x] Require PHP 8.5, install Inertia 3 and Pest, and configure the normal Laravel web middleware, MySQL connection, and a minimal Inertia welcome route. Leave product authentication and authorization behavior for P01/P02; Laravel's session and policy foundations remain available.
- [x] Install Vue 3, TypeScript, Vite, Tailwind CSS 4, and shadcn-vue primitives using npm; keep filenames in kebab case.
- [x] Configure Vitest, Playwright, type checks, and formatting scripts. Add focused foundation smoke coverage without inventing business rules.
- [x] Run package validation, platform checks, Pest, frontend checks, a production build, and a Chromium smoke journey. Document the exact commands and any external dependency that prevents verification.

## Verification results — 2026-10-22

- Laravel 13.38.0 runs on PHP 8.5.11; Inertia Laravel 3.5.2 and Vue adapter 3.9.1 are installed. PHP and JavaScript dependencies have lockfiles.
- `composer validate --strict` and `composer check-platform-reqs` passed.
- `composer test` passed: four tests, 20 assertions, covering initial rendering, client page data, stale asset version handling, and health.
- `composer run format:check`, `npm.cmd run format:check`, and `npm.cmd run typecheck` passed.
- `npm.cmd test` passed: one Vue component smoke test. `npm.cmd run build` passed.
- `npm.cmd run test:e2e` passed: one Chromium journey, including browser startup and health navigation.
- `composer run dev` started Laravel and Vite successfully; both HTTP probes returned 200. The temporary processes were stopped and the staging directory was removed.
- A read-only code review found no remaining actionable issues after removing the scaffold's demo account seed and the optional setup script that rotated existing application keys.
- The user selected an existing MySQL server. `.env` uses the MySQL defaults from `.env.example`; actual credentials and live connectivity remain unverified. No database was created or migrated.
- `composer audit` could not complete because DNS resolution timed out for `packagist.org/api/security-advisories/`. This is an unavailable check, not a clean audit result.
- The final standalone `npm.cmd audit` also failed DNS resolution for `registry.npmjs.org`. npm reported zero vulnerabilities during the final dependency installation, but the standalone advisory recheck remains unavailable.

## Safety and completion

Do not install Docker/Sail, Nginx, S3 integrations, Redis, Horizon, external authentication providers, agent tooling, or future-phase integrations. Do not run migrations automatically. Keep secrets in ignored `.env` files and generated assets/dependencies out of version control. The installation is complete when the specified packages are installed and the local application shell builds and passes its smoke checks; a live MySQL connection must be reported separately if unavailable.
