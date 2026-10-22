# P01 — Accounts and authentication

**Status:** Proposed for user review; product implementation has not started.

**Source:** `docs/SDD.md`, P01, together with `AGENTS.md`, `BACKEND.md`, and `FRONTEND.md`.

**Confirmed decision:** The first System Admin is created by a one-time Artisan command with a hidden password prompt. The user selected this approach on 2026-10-22.

## 1. Outcome and scope

An operator creates the initial System Admin. Active System Admins invite users, manage account activation, and maintain administrative access. Invitees accept a single-use link, choose a password, and sign in. Active users can sign out, reset their password, and maintain their account details. Suspension removes access while retaining identity and history.

This process implements application accounts only. Projects, memberships, project roles, ticket assignment, workflows, and project access screens belong to P02 and later processes. P01 exposes an active-account rule for those processes; it does not invent project permissions or assignment records.

Keep PHP 8.5, Laravel 13, Vue 3/TypeScript, Inertia 3, Tailwind 4, shadcn-vue, Pest, Vitest, and Playwright. Docker, Nginx, storage integrations, queue workers, and external identity providers remain excluded.

## 2. Approach

Use Laravel's existing session guard, hashing, validation, password broker, notifications, policies/gates, and rate limiter. Add conventional controllers, explicit Inertia forms, and focused services for invitation acceptance and account administration, where transactions coordinate multiple records. No new authentication package is required.

The alternative is Laravel Fortify, which supplies authentication endpoints but adds a dependency and still requires Endgame's invitation, suspension, and administrator rules. The native Laravel approach fits the installed foundation and keeps the P01 behavior visible in a small number of conventional files.

Use database-backed sessions for P01 so account suspension and password changes can remove sessions explicitly. This uses the existing Laravel session table and the user's existing MySQL connection; it adds no infrastructure service.

## 3. Account identity and authority

- Emails are trimmed and normalized to lowercase before validation and persistence. A unique database constraint prevents duplicate user identities.
- Users have explicit `is_active` and `is_system_admin` flags, protected from ordinary profile updates and client mass assignment.
- An active System Admin can manage application accounts. Ordinary users can manage only their own profile and password. Project roles will not grant System Admin authority.
- New invitations create ordinary accounts. Additional System Admins are promoted explicitly after acceptance; the change is audited.
- Account states exposed to forms and filters come from small backend-provided options. Vue does not maintain a separate business-state catalog.
- There is no public registration, account deletion, account impersonation, or self-service role assignment.

## 4. Bootstrap System Admin

- Provide `php artisan app:bootstrap-admin` with interactive name, email, password, and password-confirmation prompts. Password input is hidden and is never a command-line argument, log entry, or output value.
- Validate the same email and password rules used by the web flows. Reject an email that already belongs to a user; never overwrite an existing identity or credential.
- Permit bootstrap only before any System Admin has been created. Refuse subsequent execution, including when an existing administrator is suspended; later account recovery is not an implicit bootstrap bypass.
- Create an active, verified System Admin and the bootstrap audit event atomically. Concurrent command executions must create at most one bootstrap administrator.
- Bootstrap protection means preserving administrative access. Once another active System Admin exists, the initial administrator can be suspended or demoted under the same last-admin rule as other administrators.
- Do not put permanent administrator credentials or accounts in seeders.

## 5. Invitations

- Only active System Admins can list, create, resend, or revoke account invitations.
- An invitation is bound to one normalized email. A pending invitation does not create a user until acceptance succeeds.
- Invitation links expire after **48 hours**. Store only a cryptographic token hash in the database. Never expose token hashes in Inertia props or audits.
- Reject invitations for existing users. A repeated pending invitation uses the resend operation; resending rotates the token and expiry, invalidating the previous link. Revoke invalidates the link immediately.
- On acceptance, require a display name, password, and password confirmation. The email is read-only and comes from the invitation, not submitted account-ownership data.
- Acceptance validates the token and expiry again inside the write transaction, locks the invitation, creates the active ordinary user, marks the invitation accepted, and writes its audit event. A used, expired, revoked, or replaced token creates no account and changes no history.
- The unique user email constraint remains the final protection against simultaneous invitations/acceptance or another account taking the same identity.
- Acceptance verifies control of the invited email. Redirect to sign-in with a success message; do not silently sign the invitee into an existing browser session.
- Invitation acceptance is a guest flow. An already signed-in user must sign out before accepting an invitation for another identity.
- Deliver links using Laravel mail notifications after the invitation transaction commits. No queue worker is required. Mail failure leaves the invitation available for a deliberate resend and reports delivery failure to the administrator without showing the token.
- Configure a real SMTP mailer before delivering invitations or password resets. Tests fake mail delivery. Reset and invitation links must not be written to application logs; the foundation's `log` mailer is unsuitable for these messages.

## 6. Sign-in, sign-out, and sessions

- Provide email/password sign-in and an optional remember-me checkbox. Authenticate only active users.
- Invalid credentials, unknown email, and suspended accounts receive the same sign-in error. Do not disclose account status through guest authentication responses.
- Limit failed attempts to **5 per minute per normalized email and IP address**; apply a separate IP-based endpoint limit. A successful sign-in clears the credential-attempt counter.
- Regenerate the session ID after sign-in. Redirect to an authorized intended local route or the account page by default.
- All protected web routes check authentication and current active-account state. If an account becomes suspended, invalidate its session, clear authentication, regenerate the CSRF token, and require sign-in.
- Sign-out uses POST with CSRF protection, invalidates the session, and regenerates the CSRF token.
- Suspension removes all database sessions, rotates the remember token, and removes outstanding password-reset tokens. Reactivation does not restore old sessions or remember-me access.
- In-flight requests must revalidate account-management authority and target state inside the relevant transaction before writing.

## 7. Password setup, reset, and account details

- Passwords require **at least 12 characters and at most 72 UTF-8 bytes**, letters, numbers, and confirmation. Validate the byte limit explicitly for Laravel's default bcrypt hasher instead of allowing hashing errors or truncation. Do not introduce external password-checking services.
- Use Laravel's password broker for single-use, hashed reset tokens, a **60-minute** expiry, and a **60-second** resend throttle. Apply endpoint rate limiting as well.
- Forgot-password submission always returns the same generic confirmation for active, suspended, and nonexistent identities. Only active accounts receive a reset notification.
- A suspended account cannot use a previously issued reset link. Recheck active status when completing the reset.
- A successful reset changes the password, rotates the remember token, revokes existing sessions, consumes the token, and records the event. It redirects to sign-in rather than authenticating automatically.
- The account page allows display-name changes and password changes. Password changes require the current password, preserve the newly regenerated current session, and revoke other sessions and remember-me access.
- **Proposed identity boundary:** email is displayed read-only in P01. Changing a verified identity requires its own verification flow and is not folded into a basic profile update.
- Password values and tokens are never returned as shared page props, flashed as old input, or recorded in audit metadata.

## 8. Account administration and last-admin protection

- Provide a paginated System Admin user list with text search and active/suspended filters, plus pending invitation management. Return only necessary account fields and backend-provided allowed actions.
- Allow active System Admins to activate/suspend users and grant/revoke System Admin authority. Sensitive changes require confirmation of the acting administrator's current password.
- Do not allow self-suspension. An administrator may revoke their own administrative authority only when another active System Admin remains; the subsequent response must no longer expose administrator-only data.
- Reject suspension or demotion if it would leave no active System Admin. Serialize administrator changes and recheck this invariant under database locks, including concurrent requests.
- Repeated activation/suspension or role updates must not create misleading duplicate change events. Report when a target has already changed.
- Changes to status, administrative authority, session revocation, reset-token revocation, and audit records are atomic where they share the database transaction.
- Keep user rows and authorship intact. Do not implement account deletion or modify future ticket/history records.

## 9. Data and audit changes

Use the existing `users`, `sessions`, and `password_reset_tokens` tables. Add only the P01 data needed now:

| Record | Purpose |
|---|---|
| User account flags | Active/suspended state and explicit System Admin authority |
| `account_invitations` | Normalized email, hashed token, inviting administrator, expiry, acceptance/revocation state, timestamps |
| `audit_events` | Actor, action, affected user/invitation identifiers, restricted before/after metadata, timestamp |

Audit bootstrap, invitation creation/resend/revocation/acceptance, account activation/suspension, administrator grants/revocations, profile changes, and password changes/resets. Do not store credentials, session payloads, or invitation/reset tokens in audit metadata. Audit records are append-only through application operations and are never shared with ordinary users. A full audit-browsing UI is outside P01.

Inspect the existing MySQL migration history before choosing whether to extend the original user migration or add a forward migration. Preserve any applied/shared history. No reset, truncation, `migrate:fresh`, or destructive database command is authorized by this design. Verify database targets before migrations or database tests.

## 10. Interface and data flow

Use shallow feature-specific organization and typed Inertia props. The expected pages are:

- Sign-in, forgot password, reset password, and invitation acceptance.
- The authenticated account page with profile/password forms and sign-out.
- The System Admin accounts page with invitation and account-management forms.

Use existing shadcn-vue primitives and add only the controls these forms require. Provide accessible labels, field errors, submission/loading states, empty states, and clear success/error feedback. Keep layout and static helper copy in Vue. Laravel supplies user data, configurable options, and action availability; Laravel revalidates all writes.

The root welcome page and `/up` remain available. Add appropriate sign-in/account links without inventing a project dashboard. P02 will supply the authorized project experience.

## 11. Verification and acceptance

Backend coverage must include:

- Bootstrap success, duplicate email, repeat execution, password validation, and concurrent bootstrap protection.
- Administrator authorization for every invitation/account action; rejected attempts by guests, ordinary users, and suspended users.
- Valid, expired, consumed, revoked, and replaced invitation tokens; duplicate identities; acceptance rollback; resend delivery failure; and concurrent acceptance.
- Sign-in success/failure/throttling, session regeneration, CSRF-protected sign-out, suspended-account rejection, and remember-token invalidation.
- Password-reset enumeration-safe responses, expiry, single use, inactive accounts, rate limits, and session revocation.
- Profile ownership, current-password verification, protected-field rejection, and preservation of the current session on a password change.
- Final-active-admin protection during suspension and demotion, including simultaneous changes, plus atomic audit writes and absence of credential/token leakage.

Use Pest factories and mail/notification fakes. Keep fast isolated tests where appropriate, but verify MySQL uniqueness, transaction/locking behavior, and database-session revocation against a dedicated test database. Never point destructive test setup at the user's application database.

Use Vitest for meaningful form behavior and permission visibility. Playwright covers invitation acceptance followed by sign-in/sign-out, password reset, and suspension denying an existing session. Browser fixtures use a dedicated test database and fake mail delivery; they do not create users in an unverified application database.

Run the repository's PHP tests/format checks, Vue tests/type checks/format checks, production build, and relevant Chromium journeys. Report unavailable MySQL or mail checks explicitly. Completion requires the P01 flows and their access rules to work, not just successful page rendering.

## 12. Items awaiting this review

The bootstrap method is confirmed. The remaining proposed defaults to approve are: native Laravel authentication, 48-hour invitations, database-backed sessions, read-only account email, explicit audited System Admin grants/revocations, and the password/rate-limit rules above.

After written-spec approval, prepare the implementation plan and obtain its review before product implementation, as required by the brainstorming workflow. No product code, dependency installation, database migration, or external notification delivery is part of this design-only change.

## Framework references

- [Laravel 13 authentication](https://laravel.com/docs/13.x/authentication)
- [Laravel 13 password resets](https://laravel.com/docs/13.x/passwords)
- [Laravel 13 sessions](https://laravel.com/docs/13.x/session)
