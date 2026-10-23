# Project workspace views and workflow setup

Requested on 23 October 2026: six working project views matching the supplied workspace references, dynamic workflows configured by System Admins and project managers, activity feedback, and page-owned frontend components.

## Behavior

- Project navigation exposes Overview, Board, List, Table, Calendar, Timeline. Existing members, roles and project settings remain accessible. All views use the same project-scoped tickets and existing details drawer and creation dialog.
- Overview shows real progress, upcoming work, members and recent ticket events. No invented milestones or attachment/comment functionality.
- Board columns follow the project's published workflow, with persisted manual ordering. Moving between columns runs the same transition operation as ticket details; invalid moves retain server state. A status selector provides a keyboard and touch alternative to dragging.
- List groups tickets by status with collapsible groups. Table retains TanStack sorting and pagination. Search and filters run on the server. Board/List expose pagination rather than silently omitting matches.
- Calendar renders a navigable month using due dates and an unscheduled list. Timeline uses optional start and due dates, with date editing and drag scheduling. Date-only values are treated as calendar dates in the project's Asia/Manila timezone.
- Shared toast feedback reports confirmed successful mutations, including repeated identical messages. Validation errors also show a toast and retain inline details.

## Workflow configuration

System Admins and users with the existing `project.update` capability can configure the project workflow. Project Manager roles already include this capability; role labels do not grant authority. Ticket transitions require `ticket.update` and ticket visibility. All operations recheck active accounts, membership and archive state under locks.

Each project has immutable published workflow versions containing ordered statuses and an explicit transition graph. Statuses have stable keys, configurable labels/colors, and an active/completed category. The original Open, In Progress and Resolved stages are supplied as an editable starter, preserving existing ticket meaning. Admins/managers can add and reorder stages, choose the initial stage and allowed transitions, preview their changes locally, then publish atomically.

Publishing requires an explicit mapping of every old stage to a new stage, including renamed/removed stages. Every existing ticket is migrated in the same transaction, with version/status history and a revision increment. Publishing cannot silently resolve or reopen work: mappings preserve completion category. New lifecycle changes use transitions instead. Historical versions and ticket events remain unchanged.

Transitions require the current ticket revision and a valid edge in its saved workflow. Completing requires a resolution outcome; reopening requires a reason and clears current resolution/completion while retaining history. Ordinary ticket edits cannot modify protected lifecycle fields. Approval automation and external integrations remain later work.

## Structure and data

Page-owned controls and tests live under `pages/<module>/_components`; shared page controls live in `pages/_components`. The `features` directory is removed. Existing shadcn/Reka controls are reused, with native HTML drag-and-drop plus accessible transition/date controls, without a new dependency.

Additive migrations preserve existing databases. Workflow versions, status keys, rank and optional start date extend tickets. Backend services coordinate publication, transitions and ordering in transactions using the established administration → project → ticket lock order. Optimistic revisions reject stale ticket/workflow submissions. Routes and all selection props remain project scoped.

Local MySQL remains stopped at the user's request. Verification uses isolated in-memory SQLite and the existing frontend scripts; production migration is a separate deployment action.
