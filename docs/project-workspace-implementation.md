# Project workspace views and workflow setup

Implemented on 23 October 2026 on `feat/project-workspace-views`. This implements the user's later request for working views and dynamic workflow setup, superseding the earlier P03-only board deferral. See the [design](superpowers/specs/2026-10-23-project-workspace-views-design.md) and [plan](superpowers/plans/2026-10-23-project-workspace-views.md).

## Using the workspace

Every project has Overview, Board, List, Table, Calendar and Timeline navigation. Ticket views require `project.view` and `ticket.view`; create/edit/catalog controls follow the existing ticket capabilities. System Admins retain their explicit override. Archived projects stay readable with writes disabled and rejected on the backend.

- Overview shows real completion, overdue work, upcoming tickets, membership and recent ticket events. **Edit project** opens existing settings in a dialog; archive/restore remain available.
- Board renders the project's published stages. Drag cards between permitted stages or use their status selector. Manual sorting supports persistent reordering; other sorts disable dragging. Completing asks for a resolution; reopening asks for a reason. Failed or stale writes keep the server state and offer Reload.
- List groups tickets by status with collapsible sections and status controls. Table uses the existing TanStack table and server sorting. Clicking a ticket opens the same details drawer in every ticket view.
- Calendar provides month navigation, due-date placement and drag rescheduling. Timeline renders start/due ranges; dragging preserves duration. Unscheduled tickets have their own Timeline lanes and can be dragged onto a date. Date dialogs provide keyboard/touch alternatives. Clearing the due date returns a ticket to Unscheduled.
- Search, type, priority, status, assignee and sorting run on the server. Views paginate at 100 tickets and clearly show when more pages exist. Calendar/Timeline load their displayed date window plus unscheduled tickets. Counts are scoped to the project and active filters.
- Successful server mutations produce a shared toast, including repeated identical messages. Validation failures produce an error toast and retain detailed inline errors. Read navigation does not invent success notifications.

## Configuring workflows

Open **Workflow setup** as a System Admin or a user with `project.view` and `project.update`. Existing Project Manager roles already carry this capability; role names confer no authority. No permissions were silently granted to old roles.

1. Add, rename, color and reorder stages; mark completed stages and choose the initial active stage.
2. Check allowed transitions in the From/To matrix. All stages must be reachable from the initial stage.
3. Edit resolution labels and map every existing stage to a stage with the same completion category.
4. Publish. This creates an immutable version and atomically migrates all existing tickets with history and revision increments. Unpublished edits remain local to the form.

Projects begin with the SDD's editable Open / In Progress / Resolved workflow. No screenshot stage names are hardcoded into Vue. Managers can configure Backlog / To Do / In Progress / Review / Done or their own stages. Creating tickets uses the published initial stage.

Workflow publication cannot silently resolve or reopen tickets through mapping. Lifecycle operations require an explicit allowed edge, a current ticket revision and current edit authority. Resolution and completion time are separate from status. Reopening clears current resolution/completion while retaining the prior transition and reason history. Review/QA stages are configurable; approval automation is not introduced here.

## Code and data

- `WorkflowService` coordinates publication, transitions, order and schedule changes using the established administration → project → ticket lock order. `WorkflowController` validates request shape; services recheck permissions and relationships under locks.
- `workflow_versions` stores small, immutable JSON definitions with project/version uniqueness. Tickets reference versions through a composite project/version foreign key; existing `state` is the stable stage key. `board_rank`, `start_date`, `resolution` and `completed_at` extend tickets. A project board revision rejects stale ordering.
- `ProjectWorkspaceController` supplies scoped view data; existing ticket forms/details/catalogs are reused. Date-only values stay date-only, with UTC date arithmetic in Vue and Asia/Manila project dates.
- `resources/js/features` is removed. Project page controls, forms, views, types and tests live in `pages/projects/_components`; ticket controls remain in `pages/tickets/_components`; account administration's dialog lives in `pages/admin/_components`; shared page controls/toasts live in `pages/_components`. shadcn primitives remain in `components/ui` and shell components in `layouts`.
- Project workspace surfaces use white content and indigo actions from the latest references. Existing shared shell and self-hosted Geist remain. Vite resolves route pages while excluding `_components` from its page glob.

## Applying the database change

The additive migration is `2026_10_23_000005_create_project_workflows.php`. It seeds a workflow for existing projects and assigns existing Open tickets to that version without deleting data or rewriting old events. The agent did not run a reset or application migration during this work.

**The standalone MySQL instance was not started.** A final read-only check found an existing XAMPP server running **MariaDB 10.4.32** on port 3306, with `.env` selecting **`endgame`**. That database already records the new migration. Neither its data, its process, nor `.env` was changed by the agent. The `mysql` driver name does not identify the underlying product.

Actual MySQL deployment remains pending. After the operator deliberately starts/selects the intended MySQL instance and verifies `.env` points to it, check migration status and apply pending migrations:

```powershell
C:/php85/php.exe artisan migrate
```

No seeding or migration reset is required. Actual MySQL migration/locking checks remain pending; SQLite verification does not prove MySQL concurrency behavior. Docker, Nginx and storage integrations are unchanged.

## Verification

- Full PHP 8.5 Pest suite: **158 tests, 977 assertions**, isolated in-memory SQLite.
- Vitest: **20 tests**, including repeated success/error toasts, dynamic board controls, date arithmetic and unscheduled Timeline dragging.
- TypeScript, production build, scoped Pint and frontend formatting passed. The final affected PHP recheck passed 85 tests / 623 assertions after the last page-data/defaults changes.
- **All 11 Chromium journeys passed**, including existing account/project/ticket behavior and custom five-stage workflow publication, board drag persistence, completion/reopening, date editing, scheduled and unscheduled Timeline dragging, reload persistence, all six views and mobile width. Desktop Overview, Board and mobile Board screenshots were visually inspected.
- Read-only independent review found two issues: missing project association on publication audit events and missing unscheduled Timeline drop targets. Both were reproduced by failing tests and fixed.

Browser screenshots and disposable fixtures live under ignored `.p01-work`. No commit, push, merge or application database startup was performed.
