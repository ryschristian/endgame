# Endgame — Software Design Document (SDD)

> **Version:** 2.2  
> **Status:** Proposed for review  
> **Architecture:** Laravel + Vue/Inertia modular monolith  
> **Purpose:** A Jira-inspired development ticketing and workflow system, designed for future agentic coding integration.

## 1. System overview

**Endgame** manages development projects, tickets, people, and workflows in one application. Teams create tickets, assign work, track progress on a Kanban board, and review the history of every change.

The primary product is a practical, **non-AI ticketing system**. Later phases may connect **GitHub and GitLab** repositories and launch coding agents through approved pay-as-you-go providers. The existing ticket and workflow engine will remain the source of truth.

**Core principle:** Tickets represent work, workflows control allowed changes, and boards visualize ticket status. All actions follow the same server-side permission and workflow rules.

## 2. Core modules and business processes

### P01 — Accounts and authentication

**Flow:** Bootstrap System Admin → invite user → accept invitation → set password → sign in → access authorized projects.

- First-time setup creates a protected **System Admin**.
- Administrators invite users through expiring, single-use links.
- Users can sign in/out, reset passwords, and manage basic account details.
- Administrators can activate or suspend accounts without deleting ticket history.
- Suspended users lose active sessions and cannot be newly assigned work.

**Rules:** Invitation tokens are single-use; duplicate identities are prevented; the final active System Admin cannot be removed or suspended.

### P02 — Projects, members, roles, and permissions

**Flow:** Create project → add members → define roles → grant permissions → assign roles → authorize actions.

- Create and maintain projects with a permanent project key, name, description, and timezone.
- Invite/add/remove project members; one user may join multiple projects.
- Create custom project roles such as Developer, Reviewer, or Coordinator.
- Assign granular permissions to roles; a user may hold multiple roles.
- Manage project settings; archive or restore a project.
- The **System Admin** has unrestricted application administration; project roles do not acquire privileges from their names.

**Rules:** Membership alone does not grant every permission. Laravel policies enforce access on reads and writes. Project managers cannot grant permissions they lack. Archived projects reject business changes until restored, but authorized history remains readable.

### P03 — Ticket creation and management

**Flow:** Select project → create ticket → generate ticket key → assign owner → update work details → track activity.

- Create Task, Bug, and Story tickets using configurable work-type records.
- Record summary, description, priority, reporter, optional assignee, acceptance criteria, estimate, and due date.
- Generate a stable project-scoped key, e.g. `END-00024`.
- Edit permitted fields, assign/reassign work, and view ticket details/history.
- Search, filter, sort, and paginate authorized tickets.

**Rules:** Issued ticket keys are never reused. Assignees must be eligible members of the same project. Concurrent updates cannot silently overwrite newer changes. Ordinary ticket edits cannot directly change workflow status or resolution.

### P04 — Ticket lifecycle and custom workflows

**Flow:** Create ticket → Open → In Progress → Resolved → reopen when authorized.

- Start with a seeded workflow containing **Open**, **In Progress**, and **Resolved**.
- Execute transitions through one backend workflow operation, with permission and input validation.
- Record resolution outcome and completion time; reopen with a reason while retaining prior history.
- Allow project managers, in the workflow phase, to define statuses, allowed transitions, and review/QA checkpoints.
- Publish workflow changes as versions and handle existing tickets without silently changing their historical meaning.

**Rules:** Status, priority, and resolution are separate concepts. Invalid transitions change nothing. Approvals apply only where configured. Workflow versions used by tickets remain traceable.

### P05 — Kanban board and backlog

**Flow:** Open project board → view tickets by status → filter/prioritize → move ticket → validate workflow transition → refresh board.

- Display project tickets as cards grouped into workflow-mapped columns.
- Drag/drop cards to request permitted transitions; show validation errors when a move is blocked.
- Provide a backlog with manual ordering and basic priority management.
- Filter by assignee, work type, priority, and status; support text/key search.
- Show simple counts for open, in-progress, resolved, and overdue tickets.

**Rules:** The ticket record is the source of truth; boards do not duplicate ticket state. All board moves use the same backend transition rules as ticket details. Unauthorized tickets are excluded from cards and counts.

### P06 — Structured tickets and relationships

**Flow:** Create a larger work item → break into subtasks → connect dependent tickets → track each item independently.

- Support parent/subtask relationships and optional epics.
- Link tickets with relationships such as **blocks**, **relates to**, and **duplicates**.
- Organize work with project components and labels where useful.
- Keep related tickets independently assignable and trackable.

**Rules:** Prevent invalid parent cycles and cross-project links unless a later specification authorizes them. Relationships do not automatically override ticket workflow rules.

### P07 — Collaboration and ticket activity

**Flow:** Open ticket → comment or attach evidence → notify relevant members → view discussion and activity.

- Add comments, private attachments, member mentions, and ticket watchers.
- Show ticket changes, assignments, transitions, and discussion in chronological order.
- Send basic in-app or email notifications for relevant changes.
- Maintain a separate restricted audit trail for administrative and security events.

**Rules:** Comments and mentions do not grant access. Downloads require current authorization. Notification failure cannot undo a successful ticket update. Retain historical authorship when users are suspended.

### P08 — Basic dashboards and project tracking

**Flow:** Choose project → apply authorized filters → view ticket counts, assignments, and progress.

- Show ticket totals by status, priority, type, and assignee.
- Show assigned work, overdue work, and recently updated tickets.
- Allow users to open the underlying tickets only when authorized.

**Rules:** Filter by project and permissions **before** counts, pagination, and report queries. Use consistent project timezones when interpreting due dates.

## 3. Technical architecture

**Pattern:** One **Laravel modular monolith**, with focused services for ticket operations, permissions, and workflow transitions. Vue/Inertia handles the application interface. Background workers run separately when required; the main application does not need microservices.

| Layer | Technology |
|---|---|
| Backend | **Laravel 13**, or approved compatible stable release |
| Frontend | **Vue 3** + TypeScript + Composition API |
| Application navigation | **Inertia.js 3** + Vite |
| Styling | **Tailwind CSS 4** + shadcn-vue |
| Database | **MySQL**, InnoDB, `utf8mb4`, Laravel migrations and foreign keys |
| Authentication | Laravel session authentication, password setup/reset |
| Authorization | Laravel policies + simple project-scoped permission checks |
| Files | Private Laravel filesystem; S3-compatible storage if needed |  -- dont install yet
| Background work | Laravel queues; Redis/Horizon when operationally justified |  -- dont install yet
| Testing | **Pest**, **Vitest**, **Playwright** |
| Deployment | **Docker + Nginx** |  -- dont install yet

Keep domain logic in focused Laravel services; avoid generic engines and extra packages when framework features are enough.

## 4. Core database model

| Area | Main tables |
|---|---|
| Accounts | `users`, `account_invitations`, `sessions`, `password_reset_tokens` |
| Projects and access | `projects`, `project_members`, `roles`, `permissions`, `member_roles`, `role_permissions` |
| Ticket configuration | `work_types`, `priorities`, `resolutions`, `components` |
| Ticket records | `project_counters`, `tickets`, `ticket_events`, `audit_events` |
| Workflows | `workflows`, `workflow_versions`, `workflow_statuses`, `workflow_transitions`, `project_workflow_bindings` |
| Boards | `boards`, `board_columns`, `board_column_statuses`, `backlog_ranks` |
| Structured work | Parent ticket reference, `ticket_links`, `labels`, `ticket_labels` |
| Collaboration | `comments`, `attachments`, `ticket_watchers`, `notifications` |

**Data rules:**

- Use stable IDs and a unique `(project_id, number)` for ticket keys.
- Scope ticket, board, role, and workflow references to their owning project.
- Write ticket state changes and related activity events in one transaction.
- Use optimistic locking or equivalent checks for concurrent updates.
- Store published workflow versions and ticket history without rewriting old events.
- Store attachments privately and authorize every download.
- Add tables only when the corresponding approved process is implemented.

## 5. Development phases

| Phase | Processes | Completion milestone |
|---|---|---|
| **01 — Secure core** | P01–P03 plus seeded P04 workflow, ticket activity, and basic search | Users can create projects and manage tickets through resolution/reopen. |
| **02 — Custom workflows** | P04 configuration, review/QA rules, version publishing | Project managers can customize development stages safely. |
| **03 — Kanban and backlog** | P05 | Teams can manage and move work through a board. |
| **04 — Structured tickets** | P06 | Teams can organize subtasks, epics, and dependencies. |
| **05 — Collaboration and dashboard** | P07–P08 | The non-AI ticketing platform is ready for everyday team use. |
| **06 — GitHub and GitLab integration** | Repository linking and ticket-to-code traceability | Tickets link to branches, commits, GitHub PRs, GitLab MRs, and CI results. |
| **07 — Manual coding agents** | Authorized manual execution using approved coding-agent providers | A user can launch an isolated agent run and review code/test output. |
| **08 — Workflow automation** | Opt-in workflow-triggered agent runs with approvals and budgets | Authorized ticket transitions can initiate controlled agent work. |

Each phase requires a separate reviewed process specification and explicit implementation approval. **Phase 01 is the starting scope.**

## 6. Future agentic readiness

Endgame will eventually support **GitHub and GitLab** and approved pay-as-you-go coding agents such as Codex and Claude. Agents may inspect linked repositories, implement ticket requirements, execute tests in isolated environments, and prepare **GitHub pull requests** or **GitLab merge requests** for human review.

The core system prepares for this by preserving **durable ticket transition events**, stable ticket IDs, workflow version information, and an authorization boundary. Future agent runs will have their own execution states; they will not automatically mark tickets completed or bypass review. Provider integrations, code sandboxes, and spending controls are introduced only in their approved phases.

## 7. Quality and Codex implementation rules

- Enforce authorization on the server for every project, ticket, file, and workflow action.
- Test project isolation, invalid transitions, stale updates, and ticket-key uniqueness.
- Ensure the board and ticket detail screen share the same business rules.
- Keep interfaces and modules simple; prefer feature-focused organization and focused services.
- Read `AGENTS.md`, `docs/SDD.md`, and the **approved active phase/process specification** before changing code.
- Implement **one approved process at a time**, add tests, and report results before moving to the next process.
- Do not infer permission or workflow behavior from UI components; unresolved rules require an explicit decision.

---

**Endgame:** Practical project ticketing, customizable workflows, and Kanban first—built on a foundation that can later support GitHub/GitLab coding agents.
