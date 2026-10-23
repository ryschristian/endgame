# P03 Ticket Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans for native execution, or superpowers:subagent-driven-development if the user selects that method. Steps use checkbox syntax for tracking.

**Goal:** Deliver authorized project ticket creation, editing, assignment, catalogs, search, and immutable activity history with the revised frontend structure and reference-inspired table/details panel.

**Architecture:** Extend the Laravel/Inertia monolith with ticket/catalog models, policies, controllers, and focused transactional services. Reuse the existing account-control/project lock order; preserve P02 manager rules and explicit permission grants. Vue pages compose page-local components and receive authorized backend-owned choices.

**Tech Stack:** PHP 8.5, Laravel 13, actual MySQL 8.4, Vue 3/TypeScript, Inertia 3, Tailwind 4, shadcn-vue/Reka, TanStack Vue Table, Pest, Vitest, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-23-p03-ticket-management-design.md`.

## Global constraints

- Deliver P03 only unless the user explicitly settles the pending workflow/board scope expansion. No cross-column drag implementation under the existing P03-only contract.
- Use `resources/js/pages/tickets/_components` for ticket UI and local types. Preserve the user's revised `FRONTEND.md` and existing authentication-page edits.
- Existing roles keep their permissions; new project manager roles receive the expanded catalog. The last-manager invariant still requires the original six project permissions only.
- Use additive migrations, actual MySQL, InnoDB, `utf8mb4`, UTC timestamps, and Manila date presentation. No application database reset, optional seeders, Docker, Nginx, or storage integration.
- Type/priority/assignee/defaults/labels come from Laravel; ordinary edits cannot change lifecycle fields. Creation assigns server-owned Open state.
- All tables use server-side authorization, filtering, sorting, and pagination. Use TanStack's manual modes rather than re-filtering one loaded page in the browser.
- Install only the requested table package and dependencies required by the selected official shadcn primitives. Inspect current official documentation before generation/integration; retain semantic tokens and current Reka dependency.
- Use existing test/build/format commands. Application migration is a final operational step after isolated tests and backup/target verification, not part of fixture preparation.
- Do not automatically commit, merge, push, or deploy user work. Keep reviewable local changes and report verification.

## Review focus

1. Suspended/removed assignee remains readable and can be retained during unrelated edits, but cannot be newly selected: Task 2 tests both branches.
2. A user with all six P02 grants spread across roles remains a usable manager after ticket permissions are introduced: Task 1 tests union grants and suspension protection.
3. Deactivating a selected catalog entry must not make unrelated ticket edits impossible; changing its default requires another active entry: Tasks 2–3 cover retention/default changes.
4. Failed event creation must roll back the ticket, revision, and counter together: Task 2 injects an event-write failure and checks all three.
5. Switching a ticket panel between rows must not retain another ticket's edit form or revision: Task 6 tests keyed form state, navigation, and browser history.

## Task 1: Schema, required catalogs, and permission rollout

**Files:** Create `database/migrations/2026_10_23_000004_create_ticket_management.php`, `app/Models/Ticket.php`, `app/Models/TicketEvent.php`, `app/Models/WorkType.php`, `app/Models/Priority.php`, `app/Support/TicketDefaults.php`, `database/factories/TicketFactory.php`, `tests/Feature/Tickets/TicketSetupTest.php`. Modify `app/Support/ProjectPermissions.php`, `app/Models/Project.php`, `app/Services/ProjectService.php`, `tests/Pest.php`.

**Interfaces:** `TicketDefaults::initialize(Project $project): void` installs Task/Bug/Story, Low/Medium/High/Urgent and a counter in the owning project transaction. `ProjectPermissions::managerKeys(): array` returns exactly the six existing project permissions; `keys(): array` includes the four new ticket permissions. `Project::tickets(): HasMany` exposes scoped tickets.

- [ ] Write setup tests: existing custom role grants unchanged; new project initial manager gets all ten permissions; six-key union still satisfies `ensureManager`; existing projects receive catalogs/counter; defaults Task/Medium; cross-project type/priority references fail at database level.
- [ ] Run `php artisan test --compact --filter=TicketSetup` and confirm missing-schema/implementation failures.
- [ ] Add forward tables/constraints from spec, including `ticket_catalog_revision` and stable catalog IDs. Ticket uniqueness is `(project_id, number)` plus key; ticket/event foreign keys preserve history. Store estimate as decimal(6,2), due date as date, long texts within the specified limits, and initial state Open. Add required permissions through migration, without editing old migrations or widening old roles.
- [ ] Initialize required catalogs for old projects through migration and new projects through `ProjectService::create`. Keep initialization explicit and idempotent; do not use model observers or demo seeds. Make last-manager checks use `managerKeys()`.
- [ ] Extend the isolated test setup guard to `Feature/Tickets`; run focused tests and relevant P02 setup/access tests. Update exact catalog-count expectations only where the expanded contract requires it.

## Task 2: Authorized ticket writes and event history

**Files:** Create `app/Policies/TicketPolicy.php`, `app/Services/TicketService.php`, `app/Http/Requests/SaveTicketRequest.php`, `tests/Feature/Tickets/TicketWriteTest.php`, `tests/Support/tickets.php`.

**Interfaces:** `TicketService::create(User $actor, Project $project, array $values): Ticket`; `TicketService::update(User $actor, Ticket $ticket, int $revision, array $values): Ticket`. Ticket policy exposes `view`, `create`, and `update`; every action requires project visibility and the relevant ticket grants unless active System Admin.

- [ ] Write failing tests for permitted fields, summary trimming/limits, plain text, decimal-hour estimate bounds, nullable date validation, protected project/key/reporter/state/resolution/workflow fields, project isolation, viewer/create/update distinctions, zero-grant membership, archived writes, and stale authentication versions.
- [ ] Add tests for eligible same-project assignees; reject nonmember admins, removed/suspended/invisible members, and cross-project assignments. Retain an unchanged unavailable assignee and unchanged inactive type/priority during unrelated edits; reject newly selecting either.
- [ ] Add tests for sequential padded keys, growth past 99999, stale revision conflicts, no-op rejection, immutable before/after events, retained authorship, and rollback when event creation fails. A rollback leaves no ticket/event/counter advance.
- [ ] Run focused tests to establish failures, then implement rules inside transactions with account-control → project → counter/ticket locking. Use fresh actor state; reject lifecycle fields and select only permitted values. Assign reporter from actor. Compare revisions before mutations; increment only ticket revision.
- [ ] Run `php artisan test --compact --filter=TicketWrite` until passing. Verify history metadata contains field snapshots, not unrelated account data.

## Task 3: Project catalog configuration

**Files:** Create `app/Services/TicketCatalogService.php`, `app/Http/Controllers/TicketCatalogController.php`, `tests/Feature/Tickets/TicketCatalogTest.php`.

**Interfaces:** `TicketCatalogService::save(User $actor, Project $project, int $revision, string $catalog, ?int $id, array $values): void`. Allowlist catalog names to work-types/priorities; values are name, position, active state, and default selection. All operations compare/increment the shared `ticket_catalog_revision`.

- [ ] Write failing tests for add/rename/order/deactivate/default changes, case-insensitive project-local name uniqueness and 80-character limit, stable referenced records, forbidden/cross-project IDs, stale catalog revision, grant ceiling through existing role editor, archived writes, and audit-write rollback.
- [ ] Assert deactivating the default without a replacement fails and switching defaults is atomic; referenced inactive entries remain readable. No catalog delete endpoint.
- [ ] Implement catalog authorization with `ticket.configure` plus `ticket.view`, existing lock order, explicit record validation, one active default per catalog, and before/after administrative audits. Use separate catalog revisions, preserving project access revisions.
- [ ] Run `php artisan test --compact --filter=TicketCatalog` and affected role tests.

## Task 4: HTTP queries, page contracts, and navigation

**Files:** Create `app/Http/Controllers/TicketController.php`, `app/Support/TicketPageData.php`, `tests/Feature/Tickets/TicketHttpTest.php`. Modify `routes/web.php`, `app/Support/ProjectPageData.php`, `resources/js/types/projects.ts`, and `resources/js/features/projects/project-header.vue`.

**Interfaces:** Ticket controller `index`, `show`, `store`, `update`, and `assignees`; routes are those in spec. `TicketPageData` prepares small ticket rows/details, authorized choices, historical selected values, defaults, field labels, `can` flags, and catalog revision. Index supports `ticket` query parameter for a selected authorized detail panel; direct `show` remains linkable.

- [ ] Write failing HTTP tests for unavailable project/ticket reads, forbidden writes, selected-panel cross-project IDs, hidden controls/choice metadata, omitted account emails, archived read/write behavior, and scoped assignee search capped at 25 results.
- [ ] Test search by summary/key; combined type/priority/assignee/unassigned/due-range filters; invalid filter/sort/page-size rejection; all six allowlisted sorts with deterministic tie-breaker; 20 default/max100 rows. Authorization precedes counts and pagination.
- [ ] Implement scoped/eager-loaded queries, form validation, page props, named routes and authenticated/throttled writes. Add a query for at most 25 ticket events per history page, independent of index pagination. Provide current historical selections without offering them as newly eligible options.
- [ ] Index selection uses `/projects/{project}/tickets?ticket={id}` with current filters preserved. Clearing selection closes the sheet; direct detail route supplies the same authorized content. Writes redirect to the proper authorized ticket view, and unavailable access never produces new forbidden props.
- [ ] Add project Tickets navigation guarded by a backend-provided capability. Do not migrate unrelated project/account components to the new structure.
- [ ] Run `php artisan test --compact --filter=TicketHttp` and affected project navigation/authorization tests.

## Task 5: shadcn controls, types, and explicit forms

**Files:** Modify `package.json` and `pnpm-lock.yaml`; create needed official primitives under `resources/js/components/ui`; create `resources/js/pages/tickets/_components/types.ts`, `ticket-form.vue`, `ticket-form-dialog.vue`, `ticket-catalog-dialog.vue`, `assignee-combobox.vue`, `ticket-form.test.ts`, and `ticket-catalog-dialog.test.ts`.

**Interfaces:** Shared local types describe Task 4's ticket/choice/page contracts. `TicketForm` consumes project ID, optional ticket, defaults/options/labels, archive state and revision; emits `saved`. Catalog dialog consumes typed catalog records, revision and allowed actions. Form dialogs use accessible shadcn/Reka focus behavior; mobile layout fills available width.

- [ ] Install requested TanStack Vue Table and selected shadcn dependencies only. Add dialog/sheet, combobox, calendar/range-calendar with popover, table, and dropdown-menu primitives from current official definitions, using existing tokens and kebab-case filenames. Explain any dependency required by those primitives in implementation notes.
- [ ] Write failing Vitest tests for server-provided labels/defaults, selected IDs, decimal/date serialization, null clearing, historical unavailable selections, submission/loading/errors, no status/reporter controls, archive disabling and permission hiding.
- [ ] Implement explicit typed `useForm` submissions. Calendar values become `YYYY-MM-DD` without UTC date shifts. Assignee search calls only Task 4's scoped endpoint, handles empty/error/loading states, and retains current selection. No redundant global store or generic form engine.
- [ ] Implement catalog dialog with revision and server errors; adding/updating choices refreshes authorized page props rather than duplicating permanent client catalogs.
- [ ] Run `pnpm.cmd test` and `pnpm.cmd typecheck`; verify keyboard navigation, focus/error associations and dialog close behavior.

## Task 6: Reference-inspired table and ticket details panel

**Files:** Create `resources/js/pages/tickets/index.vue`, `show.vue`, `_components/ticket-table.vue`, `ticket-filters.vue`, `ticket-details.vue`, `ticket-details-sheet.vue`, `ticket-history.vue`, `ticket-table.test.ts`, `ticket-details-sheet.test.ts`, and `tests/e2e/tickets.spec.ts`. Extend existing isolated Playwright fixtures in `tests/e2e/serve.php`.

**Interfaces:** Table consumes paginated server rows and allowed sort/filter values, emits navigation/selection. Details and history consume the same contract on index sheet and standalone show. Sheet open state derives from selected authorized ticket props and its forms are keyed by ticket ID/revision.

- [ ] Write failing interactive tests for manual server sorting/filtering/pagination, permission visibility, preserved list filters when opening/closing details, selection changes without stale form state, and due-date range submission.
- [ ] Build dense reference-style table with fine borders and compact metadata in the existing shell. Use real ticket keys/names/owners/dates and backend-owned catalogs. Keep priorities as labeled indicators rather than color-only signals. Use the range calendar for due filtering and dropdown menu for supported table actions.
- [ ] Build the right-side details sheet with compact key/project header, expand/close actions, summary, supported field rows, description, acceptance criteria and activity. Keep readable desktop widths and full-width mobile presentation, keyboard focus and Escape/close handling. Exclude green annotation border, copied demo navigation, comments/attachments/subtasks/completion controls.
- [ ] Keep index/show pages focused on layout and typed props; place forms, table, filters and history in `_components`. Use Inertia navigation and server-confirmed state after saves.
- [ ] Add Playwright flows for authorized manager creation/editing/assignment through dialogs, selected panel versus direct detail URL, history, catalog change, filters and denied project access. Verify archive behavior and mobile close/focus/overflow. Use isolated fixture data, not application accounts or screenshots containing real account information.
- [ ] Run `pnpm.cmd test`, `pnpm.cmd typecheck`, `pnpm.cmd build`, and `pnpm.cmd test:e2e`; inspect refreshed desktop/mobile/table/details screenshots. Correct interaction/layout failures before final checks.

## Task 7: Actual MySQL races, final review, and handoff

**Files:** Extend `tests/Integration/accounts.php`, `tests/Integration/account-worker.php`; create `docs/P03-implementation.md`; update `docs/HANDOFF.md`.

**Interfaces:** Extend the existing isolated `race` worker protocol for ticket creation, conflicting edit, assignment-versus-suspension, and assignment-versus-removal. Ticket creation expects two successes with distinct keys; conflicting edits expect one success and one stale rejection. Assignment races may serialize to either legitimate final state, but never newly assign an already unavailable member or corrupt history.

- [ ] Add worker fixtures/assertions for these races, then run the MySQL-only runner using `php tests/Integration/accounts.php`. Check unique committed numbers, revisions, actor history, FK integrity, and eligibility ordering. Preserve all eight original race checks.
- [ ] Run all backend tests plus affected Pint checks; run frontend tests, typecheck, build, format checks, and Chromium journeys. Check diffs for unrelated changes and secret leakage. If sandboxed PHP `realpath` prevents Pest initialization, use the approved unsandboxed test command rather than modifying vendor/Pest code.
- [ ] Perform an independent code review through the requesting-code-review skill; address substantiated defects and rerun affected checks. Report checks that cannot run and retain exact test schema/results.
- [ ] Document implemented routes, permissions/rollout, editable catalogs, history, key/revision rules, frontend structure and screenshot alignment, dependencies, and actual verification results. Record that P04/P05 remain separate unless the user has approved an expanded design.
- [ ] As the final operational step, verify the application MySQL target and take a consistent backup, then apply only the tested forward migrations. Compare pre-existing account/project records with the backup and smoke-check the migrated schema. Never seed/reset application data. Record the migration output; if it cannot safely run, document that specific blocker instead of claiming deployment readiness.

## Execution record — 2026-10-23

Executed natively on `feat/p03-ticket-management`, with independent backend and UI reviews. The checklist above records the original task breakdown; delivered behavior, actual regression coverage, dependencies, and verification results are recorded in `docs/P03-implementation.md` and `docs/HANDOFF.md`. No agent commit, push, or merge was performed. Existing user edits remain preserved.

The user settled the scope explicitly: **P03 now; draggable Kanban next phase**. Workflow transitions, ordering and board tests belong to that next reviewed design. Ordinary ticket updates reject lifecycle fields.

The user also explicitly requested removal of MariaDB during execution. All six user databases were copied into MySQL and verified under a final source read lock, the stopped installation was fully archived and verified, and `C:\xampp\mysql` was removed. Endgame now uses MySQL 8.4.13 on port 3306 and the preserved newer application schema. The source already contained the P03 migration; the copied target reports all five migrations applied and `Nothing to migrate`. See `docs/MariaDB-retirement.md` for exact mappings and backups.
