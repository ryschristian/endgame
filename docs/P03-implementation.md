# P03 — Ticket creation and management

Implemented on 2026-10-23 from the [approved design](superpowers/specs/2026-10-23-p03-ticket-management-design.md) and [implementation plan](superpowers/plans/2026-10-23-p03-ticket-management.md). Work is local on `feat/p03-ticket-management`; no agent commit, push, merge, or deployment was performed.

## Behavior

Project-scoped tickets have immutable padded keys, summary, plain-text description/acceptance criteria, configurable type/priority, immutable reporter, optional eligible member assignment, decimal-hour estimates, date-only due dates, and revisions. Atomic creation/edit events retain actor and before/after snapshots. Concurrent edits reject stale revisions; key allocation serializes in MySQL. Ticket events and counters roll back with failed ticket writes.

The index supports server search, type/priority/assignee/unassigned/due-date filters, allowlisted sorting, and pagination. All queries are scoped and authorized before counts and results. Archived projects stay readable but reject ticket/catalog writes. Suspended or removed owners remain historical, can be unassigned, and cannot receive new assignments; a current member System Admin remains eligible through the explicit project visibility override.

New permissions are `ticket.view`, `ticket.create`, `ticket.update`, and `ticket.configure`. All ticket actions also require project/ticket visibility. Existing custom-role grants remain unchanged: a System Admin explicitly assigns ticket permissions through project Roles. New projects' initial manager roles receive all current grants. The last-manager rule remains based on the original six project permissions.

Project-local work types start with Task/Bug/Story and priorities with Low/Medium/High/Urgent. Ticket settings allow adding, renaming, ordering, deactivating and selecting defaults. Defaults remain active. References are stable; previously selected inactive entries can be retained during unrelated edits. Catalog revisions are independent of project access revisions. Default-change audits include both previous and new defaults.

## Interface and structure

`resources/js/pages/tickets/index.vue` and `show.vue` compose typed components under `pages/tickets/_components`. The table uses TanStack's manual server modes. The screenshot references informed compact rows, fine borders, key/owner metadata, and the right-side details panel. Details also have a direct URL. Create/edit forms use a desktop dialog and mobile bottom-sheet layout; the details sheet fills mobile width. History replaces the screenshot's future comment/attachment/subtask sections.

Official shadcn reka-nova dialog, sheet, combobox, calendar/range-calendar, popover, dropdown, table and required input primitives were installed from the public registry. Direct IPv4 downloads were used after the generator encountered registry/network errors. Files/imports were converted to the repository's kebab-case convention; the existing Button was preserved. The sheet overlay omits background blur to retain readable table context.

Dependencies added: `@tanstack/vue-table` 8.21.3, `@internationalized/date`, `@lucide/vue`, and `@vueuse/core`, required by the table and current official shadcn components. No API framework, state store, or drag/drop package was added.

## Code map and routes

- `TicketService`: current-account authorization, locking, validation, key allocation, revisions and events.
- `TicketCatalogService`: project catalogs, active defaults, independent revisions and audit.
- `TicketPolicy`, `TicketPageData`, and ticket controllers: capability checks, scoped queries and explicit Inertia contracts.
- `2026_10_23_000004_create_ticket_management.php`: additive ticket/catalog/counter/event schema, permission records, and existing-project defaults. Old role grants and migrations are unchanged.
- `GET /projects/{project}/tickets` and `GET /projects/{project}/tickets/{ticket}`: list and direct details. `?ticket={id}` selects the list's details panel while retaining pagination/filter context.
- `POST /projects/{project}/tickets`, `PATCH /projects/{project}/tickets/{ticket}`: CSRF-protected ticket writes.
- `GET /projects/{project}/ticket-assignees`: capped, authorized member search without emails/global directory.
- `POST /projects/{project}/ticket-catalogs/{catalog}`: authorized explicit catalog changes.

## Verification

- Actual **MySQL 8.4.13**: **143 Pest tests / 788 assertions**, plus all **12 SQL concurrency checks** (eight original checks plus ticket creation, conflicting edit, assignment versus suspension, and assignment versus removal).
- Final retained MySQL test schema: `endgame_p01_test_20261023033623_21307895`. Fixtures never use the application database.
- **15 Vitest tests**, TypeScript check, and production build passed.
- **10 Chromium journeys** passed, including ticket creation/editing/assignment, details/activity, catalog changes, filtering and mobile layout. Targeted follow-up additionally checks pagination preservation and computed mobile width.
- Scoped PHP formatting and frontend formatting were run. Existing user changes in `FRONTEND.md` and the authentication invitation page remain intact.
- Independent backend/UI review found assignee sentinel/eligibility, default audit, stale editor revision, list context and responsive width issues. They were corrected with focused tests/browser verification.

Screenshots are retained locally under `.p01-work/p03-table-desktop.png`, `p03-details-desktop.png`, and `p03-details-mobile.png`.

## Database operation and next phase

The user's subsequent MariaDB-removal request was completed separately within this session. All six user databases were copied/verified, the stopped installation was archived, and MariaDB was removed. Endgame now uses MySQL on port 3306 and schema `endgame_import_20261023033240`; all five migrations are applied in that preserved copy. See [MariaDB retirement](MariaDB-retirement.md) and [MySQL setup](MySQL-setup.md).

The user explicitly chose **P03 now, draggable Kanban next phase**. Tickets remain in server-owned Open state. Ordinary edits reject status/resolution/workflow fields. Workflow transitions and persistent draggable boards require the next approved process; comments, attachments, structured relationships, notifications and integrations remain future scope. Live SMTP was not exercised.
