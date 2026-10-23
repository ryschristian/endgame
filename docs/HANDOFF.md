# Endgame handoff — P01, P02 and P03

## Current work

Latest operator request (2026-10-23): the user asked to stop the standalone MySQL background process because it occupied XAMPP's port 3306. It was shut down cleanly and the port was verified free. Database files and backups remain intact. Do not automatically restart this server during unrelated work; the user must explicitly request database startup. Endgame database access is unavailable while it is stopped. XAMPP's former MariaDB installation remains removed as previously requested.

P03 ticket management is implemented locally on `feat/p03-ticket-management`. Read [P03 implementation](P03-implementation.md), its [approved design](superpowers/specs/2026-10-23-p03-ticket-management-design.md), and [plan](superpowers/plans/2026-10-23-p03-ticket-management.md). The user confirmed P03 only; the draggable Kanban reference is for the next phase. Preserve the revised page-local frontend structure in `pages/tickets/_components`.

Current database: **MySQL Community Server 8.4.13 on port 3306**, schema **`endgame_import_20261023033240`**. All five migrations are applied in that preserved copy. The user explicitly requested complete MariaDB removal: its six user databases were copied/verified, its entire stopped installation archived, then `C:\xampp\mysql` was removed. The older MySQL `endgame` copy remains untouched. See [MariaDB retirement](MariaDB-retirement.md) for mappings/backups; earlier port-3307 records below are historical.

Final P03 checks: **143 Pest tests / 788 assertions on actual MySQL**, **12 SQL race checks**, **15 Vitest tests**, **10 Chromium journeys**, TypeScript and production build passed. Final retained SQL schema: `endgame_p01_test_20261023033623_21307895`. Scoped formatting was run; unrelated user edits remain preserved. No commit, push, merge or live SMTP delivery was performed by the agent.

Existing custom roles intentionally did not acquire ticket permissions automatically. Use a System Admin to explicitly grant `ticket.view`, `ticket.create`, `ticket.update`, and/or `ticket.configure` through project Roles. New projects' initial manager roles receive the full catalog. Keep last-manager checks tied to the six original project keys.

P01 and P02 are merged into `main`; P02's merge is `43351c0`. Read [P02-implementation.md](P02-implementation.md), the [approved P02 design](superpowers/specs/2026-10-22-p02-projects-access-design.md), and its [implementation plan](superpowers/plans/2026-10-22-p02-projects-access.md) before changing this work. P01 behavior is documented in [P01-implementation.md](P01-implementation.md).

The user explicitly requested P02 implementation on 2026-10-22. Do not restart its approval or recreate existing code. On 2026-10-23, the user requested switching all active database configuration to actual MySQL. See [MySQL setup](MySQL-setup.md) for local operation and preserved backups. The MySQL switch has not been committed or deployed by the agent.

## Verification record

P01's original verification record:

- Pest: **73 passing tests, 354 assertions**, on in-memory SQLite and on the isolated SQL schema.
- Vitest: **5 passing tests**.
- Chromium: **5 passing journeys**, including real CSRF rejection and suspension of a separate member session.
- TypeScript and production build passed.
- Concurrent SQL operations: bootstrap, administrator demotion, administrator suspension, and invitation acceptance all passed with exactly one success per pair.
- SQL run: MariaDB 10.4.32, isolated schema `endgame_p01_test_20261022091818_c156becb`, retained for inspection. This is not proof of an actual MySQL run.

P02 verification after independent review and regression fixes:

- Pest: **115 passing tests, 613 assertions**, on in-memory SQLite and isolated MariaDB.
- Vitest: **11 passing tests**.
- TypeScript, production build, frontend formatting, and scoped Pint checks for P02/touched PHP files passed.
- Chromium: **8 passing journeys**, including guest invitation sign-in/acceptance, admin creation, manager role/member operations, archive/restore, cross-project denial, P01 sessions, and real CSRF rejection.
- SQL concurrency: **8 passing paired races** (four P01 races plus manager removal versus suspension, role downgrade versus removal, project invitation acceptance, and direct addition versus acceptance). Each pair had exactly one success and preserved its invariant.
- Final SQL run: **MariaDB 10.4.32**, retained isolated schema `endgame_p01_test_20261022135646_2f68fdc4`. The earlier P02 schema `endgame_p01_test_20261022134806_eb7951ef` is also retained. Actual MySQL and live SMTP remain unverified.
- Repository-wide Pint still fails on 14 pre-existing P01/foundation/seeder files. These unrelated files were not reformatted; this includes the user's existing seeder changes.
- Independent review found three Important issues and no Critical/Minor issues. Regression tests reproduced all three; fixes preserve suspended-member grant ceilings, permit reinvitation after removal, and safely return guests to invitation URLs after sign-in.

## Workspace interface update

Latest workspace interface update (2026-10-22):

- Geist Sans is self-hosted and bundled by Vite. Authenticated pages use a compact warm sidebar, project grid/list, working top-bar search, and project creation dialog inspired by the user's references.
- Projects use fixed `Asia/Manila` time; creation/settings have no timezone selector. UTC storage is preserved. Apply the additive `2026_10_22_000003_standardize_project_timezone.php` migration to normalize older project timezone values with audit records and revision increments. This migration was run only in isolated tests, not the application database.
- Backend verification: **118 passing tests, 647 assertions** on SQLite and MariaDB 10.4.32. All **8 SQL race checks** passed; latest retained schema `endgame_p01_test_20261022142333_65acc619`.
- Frontend verification: **11 passing Vitest tests**, **9 passing Chromium journeys**, TypeScript, production build, frontend formatting, and scoped PHP formatting passed. Browser verification includes project modal creation, Geist loading, grid/list switching, keyboard search, mobile navigation, and drawer cleanup when resizing to desktop.
- Desktop, creation-dialog, and mobile screenshots are saved in ignored `.p01-work/workspace-desktop.png`, `workspace-create-dialog.png`, and `workspace-mobile.png`; they were visually inspected. Fixture projects exist only in the isolated browser database.
- Independent review found one responsive drawer issue, reproduced in the browser and fixed by closing the modal when entering the desktop breakpoint. No backend permission/timezone/migration defects were found in that review.

See [workspace interface design](superpowers/specs/2026-10-22-workspace-interface-design.md) for the user's revised requirements. Existing pre-P02 global Pint failures and live SMTP limitations remain. The MariaDB results above are historical; actual MySQL verification follows.

### MySQL switch — 2026-10-23

- Active application connection: **MySQL Community Server 8.4.13**, `127.0.0.1:3307`, database `endgame`.
- All 15 application tables were copied and verified against the original MariaDB data, including two accounts and one project. Four migration records were preserved; no application migration or seed was run.
- Actual MySQL verification passed **118 Pest tests / 647 assertions** and **all 8 SQL concurrency checks**. Final retained schema after adding the MySQL-only runner guard: `endgame_p01_test_20261023005042_c350e959`; the earlier `endgame_p01_test_20261023004747_cdf92e22` is also retained. The MariaDB rejection check passed without creating a source-server test schema.
- Local MySQL stop/start, repeated start, status, and Laravel reconnection passed. Use `scripts/mysql.ps1 start` after reboot; this local installation is not a Windows service.
- The original MariaDB database and private SQL/environment backups remain available. Current configuration and setup instructions point to MySQL; historical MariaDB test records above remain accurate.
- The SQL runner now rejects non-MySQL server products before creating a test schema. Live SMTP remains unverified.

## Operator setup still required

The subsequent design-system foundation pass aligns root light tokens, the 252px sidebar, 46px top bar, compact shared controls, and existing account/project forms with [design-system.md](design-system.md). The New project dialog becomes a bottom sheet on small screens. Geist Sans and the fixed Manila timezone are preserved. Verification: 11 frontend tests and 9 Chromium journeys passed, with desktop/dialog/mobile screenshots inspected. No backend changes were made in this pass.

1. Start local MySQL using the command in [MySQL setup](MySQL-setup.md). The existing `.env` points to the verified MySQL server on port 3306 and schema `endgame_import_20261023033240`. Preserve its generated password. On another environment, configure and verify that environment's actual MySQL server first.
2. Set `SESSION_DRIVER=database` and configure a real SMTP mailer (`MAIL_MAILER=smtp`). The foundation's old file-session/log-mail environment values were not silently rewritten.
3. Verify the database target, then run `php artisan migrate` using PHP 8.5. Do not use reset/truncate/fresh commands.
4. On a new installation, run `php artisan app:bootstrap-admin` once. It prompts for the password without echoing it. Existing initialized installations must use their existing administrator.
5. Start the app with `composer run dev`, sign in, and send an invitation using the configured SMTP server. Verify delivery and password reset against that environment.

The code rejects log-based account notification delivery to prevent token leakage. A saved invitation whose mail failed can be resent from Accounts after SMTP is fixed.

After migrating, System Admins can open `/projects`, create a project, and select an existing active account as its initial manager. P02 project invitations also require an existing active account; use P01 first for new identities. Assign another full-permission project manager before suspending or removing the final one.

## Database and test safety

- The application database was not created, reset, or migrated during P02 implementation.
- Backend account tests refuse database targets other than in-memory SQLite or an `endgame_p01_test_...` schema.
- `composer run test:sql` creates a new isolated schema on the configured server and leaves it behind. It does not reuse or delete application data. The test credentials require schema-creation permission.
- Browser tests create unique files under ignored `.p01-work`, use a separate key, and keep CSRF checks active. No browser fixture route or provisioning endpoint exists in the application.
- Generated fixture files contain only test identities and test tokens; they are ignored by Git. Do not copy them into application configuration.

## Design decisions to preserve

- The singleton account-administration row serializes initial bootstrap and administrator changes. Do not replace it with an unlocked count or a process-local lock.
- `auth_version` prevents revoked in-flight sessions from becoming usable again after account reactivation. Keep the middleware check as well as explicit session/remember-token revocation.
- Services revalidate the current actor and state inside their transactions. Frontend permission props are presentation only.
- Invitation tokens and reset tokens are not audit metadata. Never report raw SMTP exceptions that may contain account links or credentials.
- Profile email stays read-only; account deletion and public registration remain absent.
- P02 mutations use the same account control-row lock before project locks. Do not introduce an inverted lock order or replace database locks with process-local state.
- Full project-manager authority comes from all six P02 permission keys, not role names or implicit System Admin access. Suspension and access changes must preserve an active full-permission member.
- Retained grants on suspended members still restrict which actors may edit/remove their assignments. Direct addition and invitation acceptance cannot overwrite each other's role choices.
- Existing user edits in `FRONTEND.md`, `database/seeders/DatabaseSeeder.php`, `database/seeders/UserSeeder.php`, and `pnpm-lock.yaml` were preserved. The reviewed seeder concerns remain outside P02 scope; no seeder was run.

## Next product work

P03 ticket management is complete. Seeded P04 lifecycle operations and P05 persistent draggable boards need the next reviewed process specification. Use the supplied Kanban screenshot as the next phase's layout reference, with backend-authorized transitions and saved ordering. Current ticket edits cannot change workflow state. No dashboard, repository integration, coding-agent execution, comments, or attachments are implemented here.
