# Project workspace views implementation plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task by task.

**Goal:** Six interactive project views sharing configurable, authorized workflows and confirmed activity feedback.

**Architecture:** Laravel owns workflow versions, transitions, scheduling and order. Vue/Inertia pages render typed project-scoped data and submit through focused service operations.

**Tech Stack:** Laravel 13 / PHP 8.5 / MySQL; Vue 3 / TypeScript / Inertia 3 / Tailwind 4 / existing shadcn-vue and Reka.

**Spec:** `docs/superpowers/specs/2026-10-23-project-workspace-views-design.md`

## Global constraints

- Keep local MySQL stopped; never reset existing databases.
- Use page-owned `_components`, no `resources/js/features`.
- Reuse installed dependencies. Preserve existing permission and project isolation rules.
- Status changes require an authorized transition; saved order never determines status.

## Review focus

- Cross-project status mappings, transitions, ranks and date edits must be rejected.
- Concurrent publication and stale edits must not overwrite changes.
- Archived projects and removed/inactive members cannot mutate tickets.
- Completing and reopening must preserve resolution and reason history.
- Empty, unscheduled and paginated projects must remain usable in every view.

### Task 1: Page structure and activity feedback

- [x] Relocate feature components/tests and update imports and frontend conventions.
- [x] Run existing frontend tests and typecheck after relocation.
- [x] Add a failing repeated-success/error toast event test.
- [x] Implement `pages/_components/activity-toasts.vue`, mount once in `app.ts`, run test and typecheck.

### Task 2: Versioned workflow operations

Files: additive migration; `WorkflowService`, `WorkflowDefaults`, `WorkflowController`; existing Ticket model/service/page data; `tests/Feature/Tickets/WorkflowTest.php`.

- [x] Add failing tests for configurable publication, scope, stale edits, allowed transitions, outcomes, reopening and archive guards; run Pest to establish failure.
- [x] Implement immutable versions and atomic mappings. Extend ticket creation to the published initial status. Add transition/order/schedule operations with audit events and revision checks.
- [x] Verify affected Pest tests with PHP 8.5 on isolated SQLite.

### Task 3: Six workspace views and workflow editor

Files: project header/show, `ProjectWorkspaceController`, `pages/projects/workspace.vue`, `workflow.vue`, page-owned view components and tests; existing ticket forms/details/types.

- [x] Add focused interactive tests for board transition selection, grouped list and date handling.
- [x] Implement backend workspace props, overview aggregates and authorized workflow props/routes.
- [x] Build six tabs, dynamic board/list/table, month calendar, timeline, workflow editor, transition dialog and start-date field. Reuse ticket creation/details.
- [x] Verify Vue tests, typecheck, build and critical browser journeys using the existing isolated test harness.

### Task 4: Completion

- [x] Review authorization, mappings and empty/unscheduled states; fix findings.
- [x] Update implementation notes and handoff with setup, usage, checks and limitations.
- [x] Run formatting, affected tests and final checks. Leave changes available for user review without starting MySQL.

## Execution record

Implemented in the existing checkout on `feat/project-workspace-views`; no commits or remote writes. The user explicitly requested dynamic workflows for admins and project managers, so the existing project.update capability controls setup. Workflow definitions use immutable per-project JSON version snapshots to keep this configuration small and readable; no new dependency was needed.

The code review found missing project association for publication audit records and an unavailable unscheduled Timeline drop target. Failing regression tests reproduced both; both fixes pass. Local MySQL was kept stopped, so the application migration and actual MySQL concurrency verification remain operator follow-up. Browser fixtures are disposable SQLite databases. See the implementation document and handoff for final verification results.
