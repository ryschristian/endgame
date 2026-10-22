# P02 Projects and Access Implementation Plan

> **For agentic workers:** Use executing-plans to implement this plan task-by-task. Track progress below. The user has explicitly requested implementation; proceed through verification without another approval round.

**Goal:** Deliver the approved P02 project, membership, role, and invitation flows.
**Architecture:** Conventional Laravel policies/controllers and focused transactional services, with explicit typed Inertia/Vue forms. All access writes take the P01 account-administration lock before project locks.
**Tech Stack:** Installed PHP 8.5/Laravel 13, MySQL, Vue 3/TypeScript, Inertia 3, Tailwind 4/shadcn-vue, Pest, Vitest, Playwright.
**Spec:** `docs/superpowers/specs/2026-10-22-p02-projects-access-design.md`

## Global constraints

- Preserve existing uncommitted user edits and P01 migrations. Add no dependencies.
- Never migrate the application database or send live notifications.
- Use the repository's existing scripts and isolated test fixtures.
- No P03 or future-phase functionality.

## Review focus

- Concurrent suspension and role/member changes preserve the last manager.
- Self-demotion/removal responses do not disclose administration props.
- Invitation role changes and direct addition never escalate or overwrite access.
- SQL composite foreign keys reject cross-project relationships.
- All writes reject stale actor authority and stale project revisions.

### Task 1: Project/access domain and security

**Files:** New project/access migration, models `Project`, `ProjectMember`, `Role`, `Permission`, policy `ProjectPolicy`, `Support/ProjectPermissions`, `Services/ProjectService`, project factories and `tests/Feature/Projects/ProjectAccessTest.php`; extend `AccountService` and test isolation in `tests/Pest.php`.
**Interfaces:** `Project::allows(User, string): bool`, `Project::permissionKeys(User): array`; `ProjectService::create(User,array): Project`, `change(User,Project,int,string,Closure): mixed`, member/role operations and `ensureManager(Project): void`.
- [x] Write request/service tests for creation, union of roles, grant ceilings, archive, revision, cross-project IDs, last manager and account suspension. Run `php artisan test --compact --filter=ProjectAccessTest`; expect missing-feature failures.
- [x] Implement additive schema, fixed permission catalog, relationships, policy and locked transactional operations; run the same tests to green.

### Task 2: Membership invitation lifecycle

**Files:** `ProjectInvitation` model, `ProjectInvitationService`, notification, tests `ProjectInvitationTest.php`.
**Interfaces:** `issue(User,Project,int,string,array): ProjectInvitation`, `resend(User,Project,int,ProjectInvitation,array): ProjectInvitation`, `revoke(...)`, `accept(User,string): Project`, `available(User,string): ProjectInvitation`.
- [x] Write failing tests for recipient/expiry/replacement/current-authority/archive/delivery/atomic acceptance and direct-add conflict.
- [x] Implement hashed tokens, post-commit safe notification, transaction locks, role revalidation, audit and revision changes. Run `php artisan test --compact --filter=ProjectInvitationTest`; expect green.

### Task 3: HTTP and Vue flows

**Files:** Project, member, role and invitation controllers; routes; `resources/js/types/projects.ts`; project feature forms; six project pages; authenticated navigation; HTTP tests and Vue tests.
**Interfaces:** Pages receive `auth`, `flash`, `project` (id/key/name/description/timezone/archived_at/revision), `can` (view/update/members/roles/archive/restore), and module-provided choices. Writes submit `revision` plus explicit fields. Member writes use `email`, `role_ids`; role writes use `name`, `permission_keys`.
- [x] Write failing HTTP tests for routes, visibility, pagination/props, protected fields, and safe redirects; Vue tests for real form payloads and permission/archive states.
- [x] Implement authorized/paginated queries and explicit Inertia forms using existing controls. Run PHP tests, `npm.cmd test`, `npm.cmd run typecheck`.

### Task 4: Browser/SQL verification and handoff

**Files:** Extend e2e fixture runner, `tests/e2e/projects.spec.ts`, SQL race runner/workers; `docs/P02-implementation.md` and `docs/HANDOFF.md`.
- [x] Write browser journeys before checking the UI. Add SQL races for manager protection and invitation acceptance in generated isolated schemas.
- [x] Run `composer test`, `composer run format:check`, `npm.cmd test`, `npm.cmd run typecheck`, `npm.cmd run format:check`, `npm.cmd run build`, `npm.cmd run test:e2e`, and dedicated SQL checks when available.
- [x] Obtain an independent whole-change review, address significant findings with regression tests, record actual verification results and remaining environment limitations.

## Execution record

- Baseline: 73 Pest tests/354 assertions and 5 Vitest tests passed.
- Workspace: feature branch `feat/p02-projects-access` in the existing checkout. Existing edits are preserved; no extra checkout or dependency copy is needed.
- Execution: native inline implementation because the service/policy/controller interfaces are tightly coupled; one independent final review.
- Preflight: Tasks 1/2 share project locking and audit contracts; Task 3 consumes their service methods and revision rules; Task 4 exercises the same HTTP routes and isolation guards. No interface conflict found.
- Review: independent whole-feature review found three Important issues and no Critical/Minor issues. Each was reproduced with a failing test and fixed: suspended-member retained grants, reissuing an accepted invitation after removal, and safe guest invitation return after login.
- Test maintenance: shared project fixtures live in `tests/Support/projects.php`, loaded by Pest, so individual test files run independently.
- SQL environment: MariaDB 10.4.32, retained schema `endgame_p01_test_20261022134806_eb7951ef`; initial 94-test suite and all eight P01/P02 races passed. Final expanded checks follow after review fixes.
- Final verification: 115 Pest tests/613 assertions passed on SQLite and MariaDB, 11 Vitest tests, 8 Chromium journeys, all 8 SQL race pairs, TypeScript, production build, frontend formatting, scoped Pint and `git diff --check` passed. Final SQL schema: `endgame_p01_test_20261022135646_2f68fdc4`, retained.
- Formatting limit: global Pint reports 14 pre-existing unrelated P01/foundation/seeder files. No P02/touched PHP file fails its scoped check; unrelated files were preserved.
- Environment limits: actual MySQL and live SMTP were not verified. No application database migrations, external messages, commit, push, merge, or deployment occurred.
- Tasks 1–4: complete. Review fixes were verified by regression tests and the full suites. No product-design deviations or deferred review findings remain.
