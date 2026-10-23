# P02 — Projects, memberships, roles, and permissions

**Status:** Approved for implementation by the user on 2026-10-22. The user explicitly requested implementation of this document after reviewing the proposal.

**Sources:** `docs/SDD.md` section 2, P02; `AGENTS.md`; `FRONTEND.md`; `BACKEND.md`; the approved P01 design and its implementation/handoff records.

## 1. Outcome and boundaries

An active System Admin creates a project and selects an active account as its initial manager. Authorized members maintain project settings, invite or add members, define custom roles, assign multiple roles, and archive or restore the project. People see only projects and records they are authorized to read.

This delivers P02 only. Tickets, ticket permissions, workflows, boards, dashboards, repository integrations, and agents remain outside this process. Existing P01 authentication and account administration remain in place. No additional packages or infrastructure are needed.

Use the installed PHP 8.5, Laravel 13, MySQL, Vue 3/TypeScript, Inertia 3, Tailwind 4, shadcn-vue, Pest, Vitest, and Playwright stack. Keep conventional controllers, explicit forms, policies, and a focused project service for transactional access changes. Do not introduce a generic permissions engine, repositories, DTOs, or a separate API.

## 2. Project identity and lifecycle

**Confirmed:** Only active System Admins can create projects. Creation selects one active existing account as the initial project manager; the creator need not join the project.

**Proposed defaults:**

- A project has an immutable, globally unique key, name, optional description, timezone, archive timestamp, and integer revision. Normalize keys to uppercase and accept 2–10 ASCII letters/digits, beginning with a letter. Do not provide project deletion or key changes.
- Names are required, at most 255 characters. Description is plain text, at most 10,000 characters. Updated by the user's 2026-10-22 workspace-interface request: projects use the team's fixed `Asia/Manila` timezone. There is no timezone selector; Laravel defaults omitted values and rejects submitted alternatives. Storage timestamps remain UTC.
- Creation atomically creates the project, initial membership, a custom role named `Project Manager` containing every P02 permission, its assignment, and an audit event. The name itself never grants authority.
- Archive rejects project settings, member, invitation, and role writes. Restore is a dedicated operation available to authorized users on archived projects. Account security operations remain available globally.
- Archived projects remain visible to currently authorized readers. Outstanding invitations cannot be accepted while archived; restore permits acceptance if still valid. Archive does not extend their expiry.
- Settings and access forms submit the project revision. Reject stale writes with a validation error asking the user to reload; never overwrite a newer change. Successful project writes increment the revision.

## 3. Permission model

Use these stable P02 permission keys and backend-owned labels:

| Key | Capability |
|---|---|
| `project.view` | Read project overview, settings, and member names/role names |
| `project.update` | Change project name, description, and timezone |
| `project.members.manage` | Read membership administration, add/invite/remove members, assign roles |
| `project.roles.manage` | Read role administration and create/change/delete roles |
| `project.archive` | Archive an active project |
| `project.restore` | Restore an archived project |

- Active membership is required for ordinary users. Effective permission keys are the union of permissions on their assigned roles in this project. Membership alone grants no permission.
- Every role must include `project.view`. An unassigned member can see no project details; they may receive a role later from an authorized manager.
- Active System Admins can read and administer every project without membership. This is explicit policy behavior; it does not allow writes on archived projects other than restore. Suspended users have no authority.
- A project manager means an active member whose effective permissions include all six P02 keys. It is a capability definition, not a role name or a separate flag.
- A member needs `project.members.manage` to change memberships and assignments, and `project.roles.manage` to edit roles. Neither permission grants the other.
- A non-admin may only grant permissions they currently hold. They cannot assign a role containing permissions outside their current effective set, or create/edit/delete a role whose current or proposed permissions exceed that set. They also cannot remove a member or replace assignments if that member currently has permissions outside their set. Check all permissions again inside the write transaction.
- Role names are required, at most 80 characters, unique case-insensitively within the project. Roles may be renamed. Reject deletion while a role is assigned; require reassignment first. Deleting an unassigned role is audited, and identifiers are not reused.
- Do not seed future ticket/workflow permissions. Future approved processes extend the permission catalog and define how existing projects receive them.

## 4. Membership and invitations

**Proposed boundary:** P01 remains the only way to create application identities. P02 membership invitations target existing active accounts. For someone without an account, a System Admin first sends the P01 invitation; after acceptance, the project manager can add or invite that account. Project invitations never grant System Admin authority or create a user.

- Membership administration supports adding an active existing account by exact normalized email and assigning zero or more project roles. Do not send the global account directory to project managers. Invalid, unknown, and suspended targets receive the same eligibility error.
- Add grants membership immediately and atomically revokes any unconsumed invitation for that project/account. Invite sends a 48-hour single-use link to an active existing account and records the proposed role IDs. The UI distinguishes these actions plainly.
- Only members with `project.members.manage`, or active System Admins, can create/resend/revoke project invitations. Inviting an existing member is rejected. One pending invitation per project/email is allowed; resend rotates the token and expiry, invalidating the old link. Expired/revoked invitations may be deliberately reissued.
- Persist only a cryptographic token hash. Deliver notifications after commit through P01's safe mail checks. Delivery failure retains the invitation and returns a resendable delivery error without raw transport exceptions, links, or tokens in logs/props/audit metadata.
- Invitation acceptance requires authentication as the invited active account. A guest is sent through sign-in to the intended invitation URL. Another signed-in identity receives a generic unavailable response; do not disclose the invited email.
- Acceptance revalidates the token, expiry, archive state, target account, role ownership, inviter authority, and grant ceiling under locks. If the inviter is no longer authorized, acceptance fails without consuming the link; an authorized manager can resend it. A resend uses the resending actor as the current authorizer and revalidates all proposed roles.
- A role referenced by a pending invitation cannot be deleted until that invitation is revoked or changed by resend. Role permission edits take effect at acceptance only if the current authorizer still has authority to grant them; acceptance never trusts the original UI choices.
- Acceptance creates/reinstates one membership with its approved roles, marks the invitation accepted, increments the revision, and writes its audit event atomically. Reject acceptance if an active membership already exists; never replace its roles with invitation choices. Concurrent/repeated acceptance produces one success, including an acceptance racing a direct addition.
- Removing a member revokes project access immediately, detaches assigned roles, and records `removed_at` on the retained membership row. Do not delete user identities or rewrite history. A removed member can be added/invited again, receiving only the newly selected roles. Their pending project invitations are revoked on removal.
- Suspended accounts retain memberships and role assignments but cannot access projects or accept invitations. Reactivation restores existing membership authority, consistent with P01 account reactivation; session revocation remains enforced by P01.

## 5. Last-manager protection and concurrency

- Every project, including archived projects, retains at least one usable project manager as defined above. System Admin recovery access without membership does not count as that manager.
- Reject member removal, role reassignment, role permission changes, or account suspension if the change would remove the final active full-permission manager. This includes self-removal and self-demotion. A second active manager permits those changes.
- P01 account suspension must check this invariant for every affected project in the same transaction. The error identifies the projects to the authorized System Admin, who can assign replacement managers first. Administrator demotion alone does not remove project permissions.
- Serialize P02 writes using the existing account-administration control-row lock followed by affected project locks in ascending ID order. Re-read the actor's account/authentication version, memberships, roles, target state, project revision, and manager availability after locking. This deliberately favors correctness and a simple shared lock order at the current scale; no new lock infrastructure is needed.
- P01 suspension already takes the account-administration lock. Extend that operation within the same lock order so suspension cannot race a project access change. Keep session revocation, account changes, and audit writes atomic.
- Policies enforce read visibility and action permissions. Transactional write checks are also mandatory so a stale or in-flight request cannot use revoked authority.
- After self-removal or a permission change that removes access to the current page, redirect to the authorized project overview or project list. Do not return newly unauthorized administration props in the response.
- Cross-project member/role/invitation IDs are rejected even when the actor has access to both projects. Database constraints back same-project integrity where practical; server validation remains mandatory.

## 6. Data and audit

Add forward migrations; P01 migrations are already shared in Git and must remain unchanged. No application migration or destructive database operation is authorized by this document.

| Record | Purpose |
|---|---|
| `projects` | Key, name, description, timezone, archive state, revision |
| `project_members` | Unique project/user identity, joining/removal timestamps |
| `roles` | Project-owned named roles, unique project/name |
| `permissions` | Fixed P02 key/label catalog, installed repeatably as required system data |
| `member_roles` | Same-project member/role assignments with unique pairs |
| `role_permissions` | Unique role/permission grants |
| `project_invitations` | Project, normalized email, invited user, token hash, authorizer, expiry and consumption/revocation timestamps |
| `project_invitation_roles` | Same-project invitation/role selections |

Extend `audit_events` additively with nullable project, target membership, target role, and project invitation references as needed. Retain rows referenced by audits or store stable deleted-role identifiers in metadata so role deletion does not erase history. Audit project creation/settings/archive/restore, membership additions/removals, role assignment/configuration/deletion, and invitation lifecycle events. Include actor and relevant before/after values; never credentials, token hashes, or raw tokens.

Use transactions for every multi-row change and its audit record. Required permission seed data is separate from optional sample users; do not run or rewrite the existing user seeder as part of P02.

## 7. Interface and data flow

Add these Inertia pages using the existing authenticated layout and design tokens:

- `/projects`: authorized project list, server-side search, active/archived filter, pagination, and a create link visible to System Admins.
- `/projects/create`: explicit creation form, initial manager by exact email, immutable-key explanation, and fixed team timezone. The project list also offers this form in a dialog.
- `/projects/{project}`: project overview and settings form for permitted users, archive/restore actions, and navigation to authorized member/role administration.
- `/projects/{project}/members`: paginated members, add/invite form, role assignment, removal confirmation, and paginated invitations with resend/revoke controls. Only member managers see this page; ordinary project viewers see member names/role names on the overview without emails.
- `/projects/{project}/roles`: role list, explicit role editor with backend-provided grantable permission choices, assigned-member counts, and deletion confirmation. Only role managers see this page.
- `/project-invitations/{token}`: an authenticated invitation review/acceptance page for the intended recipient, including project name and proposed roles.

Create one page per GET route, feature components where forms have meaningful responsibilities, typed props/emits, and Inertia forms. Use backend-provided labels, options, defaults, allowed actions, and revision. Hide unauthorized controls; disable permitted controls temporarily unavailable due to archive state and explain the reason. Keep static copy/layout in Vue. Do not make a generic form renderer.

Use existing shadcn-vue primitives first. Any missing primitives should follow the current official shadcn-vue conventions without a new dependency unless a specific need is demonstrated. Support visible labels, keyboard use, dialog focus, field errors, busy states, concise feedback, and useful empty states. Add Projects to authenticated navigation and retain account administration/sign-out.

## 8. Acceptance and verification

Pest coverage must prove:

- Project creation is System Admin-only, validates the key/timezone/manager, and atomically creates usable initial access and audit history.
- Reads, list results/counts, nested bindings, writes, and option props exclude unauthorized projects and cross-project relationships.
- Multiple roles combine permissions; role names confer nothing; ordinary membership is insufficient; inactive/removed users have no access.
- Grant ceilings apply to role edits, membership assignments/removal, and invitation issue/resend/acceptance, including stale actor authority.
- Project keys are unique and immutable; archive blocks all business writes except restore; stale revisions reject writes without changing data.
- No access change or P01 suspension removes the last usable manager, including concurrent changes and multi-project suspension.
- Invitations enforce recipient identity, expiry, single use, token rotation, revocation, current authorization, account eligibility, archive state, atomic acceptance, and safe delivery failure. A direct addition racing acceptance cannot overwrite the winning membership's roles.
- Membership removal/reinstatement preserves identity/history and never restores removed role assignments automatically.
- Audit failure rolls back the associated change; secrets are absent from audit/props; failed/no-op writes do not create misleading success events.

Vitest covers meaningful create/member/role form behavior, server-provided choices, archive states, and permission visibility. Playwright covers admin project creation, manager member/role administration, invited-user acceptance, denied cross-project access, and archive/restore.

Use the repository's existing test, formatter, typecheck, build, and browser scripts. Extend their isolated database fixtures and target guards to P02. SQL concurrency verification should include manager removal versus suspension, role changes versus member removal, and invitation acceptance races. Never run tests against the application database; verify a dedicated target before SQL execution. Report MySQL separately from MariaDB, and do not send live SMTP messages without a specific operator request.

## 9. Implementation discretion

The implementer may choose exact controller/model/service names, component boundaries, route names for writes, index names, and concise static copy while following repository conventions. All authorization, invitation, archive, and last-manager behavior above is part of the proposed product design and must not be silently weakened during implementation.

After approval, write a task-by-task implementation plan, implement P02 with focused tests, and update the implementation/handoff documents with fresh verification results and limitations. Existing unrelated user changes remain intact.

## 10. Design review

A separate builder review found one planning gap: direct addition could race invitation acceptance and replace newer role choices. The design now requires direct addition to revoke pending invitations and acceptance to reject existing active membership, under the same transaction locks. This was settled as an implementation-safety clarification. The user subsequently approved implementation of this document.
