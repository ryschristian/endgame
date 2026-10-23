# P01 — Accounts and authentication implementation

Implemented on 2026-10-22 from [the P01 design](superpowers/specs/2026-10-22-p01-accounts-authentication-design.md). This delivers application accounts; P02 project membership/permissions and later ticket processes remain separate.

## Delivered behavior

- A one-time `app:bootstrap-admin` command creates the first verified, active System Admin through hidden password prompts. Existing identities and subsequent bootstrap attempts are rejected.
- Active System Admins can invite, resend, and revoke invitations; search/filter/paginate accounts; activate/suspend users; and grant/revoke administrative authority after confirming their current password.
- Invitations last 48 hours. Only token hashes are persisted. Acceptance creates one ordinary verified account and consumes the invitation in the same transaction as its audit event. Resend replaces the previous token; revoke invalidates it. Resending a revoked or expired invitation deliberately issues a fresh link.
- Accounts use native Laravel session authentication, credential/IP throttling, explicit active-account checks, and an optional remember-me cookie. No public registration or account deletion route exists.
- Password reset uses Laravel's broker, 60-minute tokens, a 60-second resend throttle, and a generic response that does not reveal whether an account exists or is suspended.
- Users can update their own display name and password. Email is read-only. Password changes require the old password, preserve the regenerated current session, and revoke other sessions.
- Suspension and password reset revoke database sessions, remember-me access, and outstanding reset tokens. Reactivation does not restore revoked sessions.
- At least one active System Admin must remain. Self-suspension is prohibited. Self-demotion is allowed only with another active administrator and redirects to the ordinary account page.
- Account and invitation changes create restricted audit records. Passwords, hashes, authentication tokens, and session payloads are excluded from audit metadata and shared page props.

## Code map

| Location | Responsibility |
|---|---|
| `app/Console/Commands/BootstrapAdmin.php` | Interactive initial administrator setup |
| `app/Services/AccountService.php` | Bootstrap, account changes, session revocation, profile/password transactions |
| `app/Services/InvitationService.php` | Invitation creation/resend/revocation/acceptance and post-transaction delivery |
| `app/Services/PasswordResetService.php` | Broker operations, reset serialization, revocation and audit writes |
| `app/Http/Controllers/*Account*`, `*Session*`, `*Invitation*`, `*PasswordReset*` | Validation, Inertia responses and redirects |
| `app/Http/Middleware/EnsureActiveAccount.php` | Current account state and session-version enforcement |
| `app/Support/AccountRules.php`, `app/Rules/PasswordByteLimit.php` | Shared email/password validation |
| `app/Support/AccountMail.php` | Prevent authentication links from reaching a log transport |
| `resources/js/pages/auth`, `pages/account`, `pages/admin` | Explicit typed account forms and administration |
| `resources/js/features/accounts` | Accessible fields, pagination, and confirmation dialog |

Global account administration uses the explicit `manage-accounts` gate. The gate controls route access and UI availability; the services re-read the actor inside the transaction before account or invitation changes. This is not a project-role implementation.

## Database changes and concurrency

The original create-users migration is preserved. The additive migration `2026_10_22_000001_add_account_security.php` adds:

- `users.is_active`, `users.is_system_admin`, and `users.auth_version`.
- `account_administration`, containing one control row and a durable bootstrap timestamp.
- `account_invitations` and `audit_events` with foreign keys and indexes.

The control row is locked within transactions before operations that change account identity/access or invitation acceptance. A stable row is necessary because an empty users table provides no existing user row to serialize initial bootstrap. Administrator operations re-read the current actor, target, and usable administrator count while holding this lock. Deadlocks may retry up to three times.

Deleting sessions alone cannot prevent an in-flight response from writing an old session back after suspension. Each authenticated session therefore carries the user's `auth_version`. Suspension and password changes increment it; middleware rejects old versions even after the account becomes active again. Valid remember-me authentication initializes the snapshot only after Laravel verifies the rotated remember token.

Audit writes share their business transaction. Notification delivery occurs after successful invitation/token creation. Invitation delivery failure retains the saved invitation, returns a delivery error, and allows an explicit resend. Password-reset delivery failure preserves the generic guest response and logs only a fixed diagnostic message without a link, token, identity, or transport exception.

## Routes

| Access | Routes |
|---|---|
| Public | `/`, `/up` |
| Guest | `GET/POST /login`, `GET/POST /forgot-password`, `GET /reset-password/{token}`, `POST /reset-password`, `GET/POST /invitations/{token}` |
| Active account | `GET/PATCH /account`, `PUT /account/password`, `POST /logout` |
| Active System Admin | `GET /admin/accounts`, `POST /admin/invitations`, `POST /admin/invitations/{invitation}/resend`, `POST /admin/invitations/{invitation}/revoke`, `PATCH /admin/accounts/{user}/status`, `PATCH /admin/accounts/{user}/administrator` |

All state-changing web requests use CSRF protection. Guest endpoints have IP limits; credential failures additionally use the normalized email/IP key. Passwords require at least 12 characters, letters and numbers, confirmation, no null bytes, and no more than 72 UTF-8 bytes for bcrypt.

## Running P01

Use PHP 8.5 in the terminal:

```powershell
$env:Path = "C:\php85;$env:Path"
```

Configure your intended existing MySQL server and database in `.env`. Verify the connection before applying migrations. Set:

```dotenv
SESSION_DRIVER=database
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=your-sender@example.com
```

Use the SMTP scheme and port required by the provider. Credentials belong only in ignored environment configuration. The old `log` mailer is intentionally rejected for account notifications. No live SMTP delivery was attempted during implementation.

After verifying the database target:

```powershell
php artisan migrate
php artisan app:bootstrap-admin
npm.cmd run build
composer run dev
```

No default account or permanent credential is seeded. An already initialized installation refuses bootstrap; do not bypass this by changing the control row or a user flag.

## Verification

Commands used for the normal suite:

```powershell
composer test
composer run format:check
npm.cmd test
npm.cmd run typecheck
npm.cmd run format:check
npm.cmd run build
npm.cmd run test:e2e
```

The browser runner creates a new SQLite database and fixture file inside ignored `.p01-work`. It refuses to reuse an existing file, uses its own application key, and runs with `APP_ENV=e2e` so Laravel's unit-test CSRF bypass is not active. It never opens the application database and sends no live mail.

The SQL verification command is:

```powershell
composer run test:sql
```

It requires permission to create a test schema on the configured SQL server. It creates a unique `endgame_p01_test_...` database, applies forward migrations, runs the complete Pest suite using per-test transactions, then starts paired PHP workers to race bootstrap, self-demotion, cross-suspension, and invitation acceptance. Each pair must produce exactly one success and preserve its invariant. The generated database is printed and retained for inspection; the command never drops a database. Use dedicated test-server credentials when available.

### Verified environment and limits

- The original SQL verification used **MariaDB 10.4.32**. On 2026-10-23, the application switched to actual MySQL 8.4.13 and the combined P01/P02 suite passed there; see [MySQL setup](MySQL-setup.md). The normal suite also runs on isolated in-memory SQLite.
- The configured application database `endgame` did not exist. No application database was created or migrated. `.env` was not changed; explicitly update its session/mail settings before running P01.
- Live SMTP delivery was not verified. Notifications are faked in feature tests; no external messages were sent.
- Docker, Nginx, storage integrations, queue infrastructure, external identity providers, and future product modules were not installed.

See [HANDOFF.md](HANDOFF.md) for the final verification counts, review outcome, and next operator/agent steps.
