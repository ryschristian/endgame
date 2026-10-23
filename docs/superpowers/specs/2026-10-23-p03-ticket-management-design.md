# P03 — Ticket creation and management

Status: Approved and implemented on 2026-10-23. The user explicitly confirmed P03 only; the draggable Kanban board is deferred to the next phase.

Sources: `docs/SDD.md` P03; revised `AGENTS.md`, `FRONTEND.md`, and `BACKEND.md`; current P01/P02 implementation; `docs/design-system.md`; `docs/HANDOFF.md`.

## 1. Outcome and scope

Authorized people create, find, assign, and edit tickets within a project and read their change history. Preserve existing accounts, project access, application data, and the actual MySQL 8.4 connection. Use PHP 8.5.

Deliver P03 only. No workflow transitions, resolutions/reopening, boards, comments, attachments, relationships, notifications, or integrations. Those belong to later processes. Tickets initially display a server-owned `Open` state; P03 exposes no status editor or transition endpoint. P04 will introduce versioned workflows through forward migrations rather than treating this initial state as a configurable workflow.

Two broader alternatives were considered: delivering seeded P04 transitions together with P03, or introducing the full configurable workflow schema immediately. Both expand the requested process. The recommended scope keeps lifecycle operations for a separately approved P04 specification.

## 2. Frontend structure and interaction

Follow the revised page-local structure rather than adding ticket UI to the old `features/projects` pattern:

```text
resources/js/pages/tickets/
  index.vue
  show.vue
  _components/
    ticket-table.vue
    ticket-filters.vue
    ticket-form.vue
    ticket-form-dialog.vue
    ticket-history.vue
    ticket-catalog-dialog.vue
    types.ts
```

The index and detail views are real, linkable Inertia pages. Creation and editing use a shared explicit form in a dialog on desktop and an accessible drawer/sheet on mobile. No extra create/edit pages are needed. Tickets appear in project navigation only when the backend grants ticket visibility. Reuse the existing authenticated shell and warm/rose design tokens without redesigning unrelated pages.

The user's three screenshots supplied with approval refine the presentation: use a dense table with fine row/column borders and a right-side ticket details panel on desktop. Opening a row should retain list context in an accessible sheet; the dedicated detail URL remains available for direct links and an expand action. On mobile, the sheet fills the available width. Display supported ticket fields, description, acceptance criteria, and activity. The bright green screenshot outline is an annotation, not an application border. Do not copy unrelated navigation, demo data, completion controls, labels, comments, attachments, or subtasks into P03. The board reference specifies a future compact column/card layout; cross-column dragging requires the separately clarified workflow/board scope.

Install `@tanstack/vue-table` as requested by `FRONTEND.md`. Use a small, explicit column definition with manual server pagination, filtering, and sorting; avoid a generic table framework. The visible table columns are key, summary, type, priority, assignee, due date, and updated date. Summary/key link to the ticket detail page.

Use current official shadcn-vue primitives for dialogs/sheets, comboboxes, calendar/popover, tables, and dropdown menus. Add only dependencies required by those selected primitives. Do not replace the existing UI library. Type and priority use backend-provided choices; assignee uses an authorized searchable combobox; due date uses a calendar with a clear action. Use a range calendar for the index's due-date range filter. Use Reka accessibility behavior through the shadcn components, including focus return, keyboard navigation, labels, and error associations.

Use typed props/emits and Inertia `useForm`. Show field errors, conflicts, busy state, and success feedback. Hide controls without permission; disable otherwise permitted writes when archived. Do not put all forms and dialogs inside one large page. Keep static layout/copy in Vue and business options/defaults/labels in Laravel.

## 3. Ticket fields and validation

| Field | Proposed behavior |
|---|---|
| Project, number, key | Server-generated and immutable |
| Summary | Required, trimmed, 1–255 characters |
| Description | Optional plain text, at most 50,000 characters |
| Work type | Required active work-type record from this project; defaults to Task |
| Priority | Required active priority record from this project; defaults to Medium |
| Reporter | Creating account, immutable in P03; retain historical authorship |
| Assignee | Optional eligible project member; no implicit assignment |
| Acceptance criteria | Optional plain text, at most 20,000 characters |
| Estimate | Optional decimal hours, 0–9,999.99; no negative values |
| Due date | Optional valid `YYYY-MM-DD`, interpreted as a Manila calendar date; past dates allowed |
| Initial state | Server-owned Open; ordinary submissions cannot set state/status, resolution, completion time, or workflow references |
| Revision | Integer starting at 1; required for edits |

Render text safely without raw HTML. No rich-text or Markdown dependency is needed. Timestamps remain UTC; displayed dates use `Asia/Manila`. Due dates are stored as dates rather than UTC timestamps.

Assignee eligibility requires an active account, a current membership in this project, and project visibility. A System Admin without membership is not assignable. An assignment remains historical when its owner is suspended, removed, or loses access. Show that owner as unavailable; allow unrelated edits to retain the unchanged assignment, but reject newly assigning an unavailable owner. Unassigning or choosing another eligible member remains possible.

## 4. Permissions and rollout

All ticket access first requires `project.view`. Add stable permission records and backend labels:

| Key | Capability |
|---|---|
| `ticket.view` | Read all tickets and their history within the project |
| `ticket.create` | Create a ticket, including its initial optional assignment |
| `ticket.update` | Edit permitted details and assign/reassign a ticket |
| `ticket.configure` | Maintain this project's work types and priorities |

Each action also requires `ticket.view`. There is no reporter-only or assignee-only visibility exception. Reporter/assignee identity confers no additional authority. Active System Admins retain explicit policy override; suspended accounts have no authority. Archived projects remain readable but reject ticket and catalog writes.

Continue P02's grant ceiling when editing roles or assigning them. Preserve the existing last-manager definition as the original six project permissions; adding ticket permissions must not silently make existing projects managerless. Separate the project-manager key set from the expanded assignable permission catalog.

Existing custom roles keep their grants unchanged. A System Admin explicitly grants the new ticket permissions through the existing role editor. This avoids silently broadening established access. Newly created projects give their initial Project Manager role all current project/ticket permissions. Role names never grant authority. This rollout choice needs user approval with this design.

## 5. Configurable records

Each project owns work-type and priority records. Initially create Task, Bug, Story and Low, Medium, High, Urgent, with Task/Medium as the defaults. Defaults, ordering, active state, IDs, and display labels come from Laravel records; never duplicate catalogs in TypeScript.

People with `ticket.configure` use a compact catalog dialog to add, rename, reorder, and deactivate entries and change the default. Names are required, at most 80 characters, and unique case-insensitively within each project/catalog. Keep stable IDs and historical references. Do not delete referenced records. The default must remain active; require a replacement default before deactivating it. Deactivated choices cannot be newly selected but may remain unchanged on existing tickets during unrelated edits.

Changes to labels affect their current display; ticket events retain the labels/IDs seen at the time of each event. Catalog writes use revisions, current authorization, project locks, and atomic audit records. Initial required catalogs are installed for existing projects through an additive migration and for new projects in the project-creation transaction. Do not run optional user/demo seeders.

## 6. Persistence and transaction boundaries

Use additive migrations for `work_types`, `priorities`, `project_counters`, `tickets`, and `ticket_events`, plus permission records and a `ticket_catalog_revision` integer on projects. That separate catalog revision starts at 1 and protects catalog/default/reordering changes without modifying the project's existing access revision. Do not rewrite P01/P02 migrations.

Tickets store project ID, integer number, immutable key, permitted business fields, user references, initial state, revision, and timestamps. Add unique `(project_id, number)` and globally unique key constraints. Enforce same-project catalog references with composite foreign keys where practical. User/member eligibility remains a server-side check. Foreign keys retain reporters, assignees, tickets, and historical events.

Use a focused `TicketService` for transactional writes, a focused catalog service where needed, ordinary controllers, form requests when justified, and Laravel policies. No repositories, DTOs, generic engines, or a separate API.

Keep the existing lock order: account-administration control row, project row, then counter/ticket/catalog rows. Re-read actor activity and authentication version, project archive state, permissions, catalog selections, and assignee eligibility after locking. This serializes with suspension, project archive, role changes, and membership removal.

Creation atomically allocates the next project number, inserts the ticket, and records its creation event. Format keys with at least five digits, growing naturally after 99999. Persisted keys/numbers never change or get reused. No ticket deletion endpoint is included. A rolled-back transaction has not issued a ticket key.

Updates lock and compare the ticket revision before saving. Reject stale submissions with a reload message and preserve user input; never overwrite a newer save. Successful writes increment the ticket revision, not the unrelated project access revision. Catalog edits compare and increment `ticket_catalog_revision` under the project lock. Reject no-op changes without misleading success events.

Ticket events record the human actor, action, timestamp, and changed fields with before/after values, including assignment changes. The creation snapshot includes all ticket fields. Events are immutable and ordered by timestamp/ID, with server pagination. Event failure rolls back the ticket write. Ticket history is distinct from restricted administrative audit history; catalog and permission changes remain administrative audits. No tokens, account credentials, or unrelated account data enter ticket props/history.

## 7. Routes and page data

- `GET /projects/{project}/tickets`: authorized paginated index and filter/form choices.
- `GET /projects/{project}/tickets/{ticket}`: authorized detail and paginated history.
- `POST /projects/{project}/tickets`: create.
- `PATCH /projects/{project}/tickets/{ticket}`: update permitted fields with revision.
- Project-scoped catalog write routes: explicit create/update/default/deactivation operations, protected by `ticket.configure`.
- A project-scoped assignee search endpoint only supplies small, authorized `{value, label}` results and the current selection; it does not expose the global account directory.

Reject cross-project ticket/catalog IDs even when the actor can access both projects. Unauthorized project/ticket reads return the established unavailable response. Authorized readers without a requested write capability receive a forbidden response.

Search summary and immutable key. Filter by work type, priority, assignee (including Unassigned), and due-date range. Allowlist sorting by key/number, summary, priority order, due date, and updated date, with ID tie-breakers. Default to most recently updated, 20 records per page, maximum 100. Apply authorization and project scope before search, counts, sorting, and pagination. Status filtering is unnecessary while Open is the only state.

Backend page data supplies only authorized choices, selected historical values, defaults, field labels, and `can` flags. Assignee search must support an unavailable current assignee without making that account newly selectable. Submitted values are always revalidated in the transaction.

## 8. Verification and delivery

Pest tests cover field validation, policy boundaries, project isolation, grant ceilings, archived projects, assignee suspension/removal/access changes, retained historical selections, key uniqueness, revision conflicts, protected lifecycle fields, catalog defaults, event atomicity, and no-op writes. Existing P01/P02 tests must continue passing.

Run actual MySQL tests against newly generated isolated schemas, including concurrent ticket creation and conflicting edits, plus assignment versus suspension/removal. Preserve the MySQL product guard and existing account/project race checks. Never use the application database as test fixtures.

Vitest covers forms using server-provided catalogs, combobox selections, calendar date values, validation/conflict feedback, and permission/archive states. Playwright covers a project manager creating and editing a ticket through dialogs, assignment, filters/sorting/pagination, history, denied cross-project access, and mobile keyboard/focus behavior.

Run relevant repository formatter, PHP, TypeScript, build, unit, and browser commands; report actual results and limitations. Inspect desktop/mobile rendering. Update `docs/P03-implementation.md` and `docs/HANDOFF.md` after implementation. No Docker, Nginx, storage integration, live SMTP, destructive database operations, or optional demo seeding is included.

Application migrations are a separate final operational step: prepare and verify the migrations against isolated MySQL first, then identify and back up the application target before applying them. Preserve existing user edits, including `FRONTEND.md` and `pages/auth/accept-invitation.vue`.

## 9. Review decisions

Approval of this document confirms the proposed P03 boundary, the initial read-only Open state, permission rollout, project-owned editable catalogs, decimal-hour estimates, and date-only due dates. After review, prepare the implementation plan and proceed through the repository's implementation workflow.
