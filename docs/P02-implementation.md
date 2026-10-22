# P02 — Projects and access implementation

Implemented on 2026-10-22 from the [approved P02 design](superpowers/specs/2026-10-22-p02-projects-access-design.md), following `AGENTS.md`, `FRONTEND.md`, and `BACKEND.md`. The work is on local branch `feat/p02-projects-access`.

## Delivered behavior

- Active System Admins create projects with a permanent uppercase key, name, description, the team's fixed `Asia/Manila` timezone, and a selected active initial manager. The user's later interface request removed timezone selection.
- Project permissions combine across custom roles. Membership and role names do not grant implicit authority. System Admin access is explicit and does not require membership.
- Authorized users search and paginate projects, maintain settings, add/remove members, assign roles, maintain role permissions, and archive/restore projects. Archived projects remain readable and reject business writes until restored.
- Member selection uses exact email, without exposing the global user directory. Laravel supplies role, permission and filter choices, labels, defaults, allowed actions, and the fixed team timezone through typed Inertia props.
- Ordinary members cannot grant or alter permissions above their own authority. Retained grants on suspended target accounts still count toward that restriction.
- Membership removal retains its identity and audit history, clears role assignments, and revokes outstanding invitations. Rejoining uses only explicitly selected new roles.
- Project invitations target existing active application accounts, expire in 48 hours, store only token hashes, and require sign-in as the intended recipient. New identities continue through P01 account invitations first.
- Invitation acceptance rechecks the authorizer, role grants, recipient, archive state, and existing membership under locks. Direct addition revokes pending invitations; concurrent acceptance cannot replace the winner's role selection.
- Resend rotates tokens, revalidates selected roles, and transfers authorization to the resending actor. Previously accepted invitations can be reissued after member removal. Delivery failures preserve the saved invitation and show a deliberate resend path.
- Guest invitation URLs survive sign-in through an exact local URL allowlist; external destinations, query strings, and fragments do not become redirects.
- A project always retains an active member with all six P02 permissions. Role/member changes and P01 suspension preserve that invariant, including for archived projects. A System Admin without membership does not count as the project's manager.
- Revision checks reject stale project forms. Successful multi-row changes, their revision increments, and audit writes are atomic. Self-removal/demotion redirects to a page the actor can still read.

## Code map

| Location | Responsibility |
|---|---|
| `app/Models/Project.php`, `ProjectMember.php`, `Role.php`, `Permission.php` | Project relationships, visibility and permission queries |
| `app/Models/ProjectInvitation.php` | Hashed-token invitation data and relationships |
| `app/Policies/ProjectPolicy.php` | Explicit server action authorization |
| `app/Services/ProjectService.php` | Project, role, membership, revision, grant-ceiling and manager operations |
| `app/Services/ProjectInvitationService.php` | Invitation lifecycle and safe post-transaction mail delivery |
| `app/Services/AccountService.php` | P01 suspension now checks affected project manager availability |
| `app/Support/ProjectPageData.php`, `ProjectPermissions.php` | Small module-owned page/permission contracts |
| `app/Http/Controllers/Project*Controller.php` | Authorized page queries, validation and redirects |
| `resources/js/pages/projects`, `features/projects`, `types/projects.ts` | Six Inertia pages and explicit typed forms |
| `tests/Support/projects.php` | Shared fixtures usable when running individual test files |
| `tests/Feature/Projects`, `tests/e2e/projects.spec.ts` | Project rules, HTTP access and browser journeys |
| `tests/Integration/accounts.php`, `account-worker.php` | Isolated SQL suite and paired P01/P02 concurrency workers |

## Migration and transaction safety

The forward migration `2026_10_22_000002_create_projects_and_access.php` adds projects, memberships, roles, the six required permission records, role/member pivots, project invitations, invitation-role pivots, and a nullable project reference on `audit_events`. Same-project composite foreign keys protect member-role and invitation-role selections. Deleted role identifiers and relevant before/after values remain in audit metadata.

Existing P01 migrations are unchanged. Required permission records are installed by the forward migration, independently of optional user seeders. No new dependencies were added, and existing user seeder changes were not modified or run.

Every P02 write takes the existing `account_administration` row lock before project locks. P01 suspension uses that same lock before checking affected projects in ID order. Fresh account state, authentication version, permission grants, revision, and last-manager availability are checked while holding the locks. A global lock is a deliberate simple choice for the current application size.

## Routes and operation

The authenticated Projects navigation opens `/projects`. Project creation is `/projects/create`; overview/settings, members, and roles are `/projects/{project}`, `/projects/{project}/members`, and `/projects/{project}/roles`. Invitation recipients use `/project-invitations/{token}`. State-changing operations remain CSRF-protected Laravel web routes with account-management throttling.

Before using P02 in an application environment, verify the intended MySQL connection and back up any data as appropriate, then apply forward migrations with `php artisan migrate`. This implementation did not migrate or create the application database. P01 database-backed sessions and safe SMTP configuration still apply. No live SMTP message was sent during this work.

The UI uses existing shadcn-vue buttons/tokens and Reka alert dialogs. The [current official shadcn-vue alert-dialog documentation](https://www.shadcn-vue.com/docs/components/alert-dialog) informed the confirmation behavior; no component-generator or dependency update was needed.

## Verification and review

Final verification results are recorded in [HANDOFF.md](HANDOFF.md). All runtime tests use isolated in-memory SQLite, generated browser SQLite files, or uniquely created `endgame_p01_test_...` SQL schemas. The existing P01 isolation prefix is intentionally retained for the combined P01/P02 suite. SQL schemas are retained and never dropped by the runner.

An independent whole-feature review found three Important issues, with no Critical or Minor issues: suspended-target retained grants, reissuing an invitation after removal, and the guest invitation return URL. Each was reproduced with a failing regression test and fixed. The reviewer did not independently perform live SMTP, actual MySQL, or rendered browser checks; those limits are distinct from the implementer's recorded checks.

The repository-wide PHP formatting check still reports pre-existing issues in unrelated P01/foundation files and the user's seeders. P02 PHP files and touched PHP files pass the scoped Pint check. Those unrelated files remain untouched.

## Remaining scope

### Workspace interface update — 2026-10-22

The user's visual references now guide the authenticated layout: warm neutral sidebar, compact top bar and navigation, rose actions, project grid/list views, authorized project shortcuts, and a compact New project dialog. All links and actions correspond to implemented P01/P02 behavior. Cards display actual project descriptions, member counts/previews, archive state, and update dates; ticket progress is not fabricated.

The official Geist Sans variable font is self-hosted, bundled by Vite, and licensed under the included SIL Open Font License. The exact font source revision is in `public/fonts/README.md`. No package was added.

Projects use `Asia/Manila`. The service defaults omitted timezone input and rejects alternatives; forms do not submit or offer a timezone choice. `2026_10_22_000003_standardize_project_timezone.php` normalizes older project values transactionally, records each previous value in audit history, and increments revisions. Application timestamps remain UTC and card dates use Manila time. The migration is applied only to isolated tests during agent work.

Actual MySQL and live SMTP verification remain operator checks; the available local SQL server is MariaDB 10.4.32. P03 tickets and the seeded P04 workflow need their own approved process design and implementation request. This work introduces no ticket, board, dashboard, repository, or agent functionality.
