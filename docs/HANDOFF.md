# Endgame handoff — P01

## Current work

P01 account/authentication code is on local branch `feat/p01-accounts-authentication`. Read [P01-implementation.md](P01-implementation.md) and the [approved design](superpowers/specs/2026-10-22-p01-accounts-authentication-design.md) before changing it. The user authorized implementation and requested documentation afterward; do not restart design approval or recreate the existing code.

## Verification record

Before the final independent review:

- Pest: **73 passing tests, 354 assertions**, on in-memory SQLite and on the isolated SQL schema.
- Vitest: **5 passing tests**.
- Chromium: **5 passing journeys**, including real CSRF rejection and suspension of a separate member session.
- TypeScript and production build passed.
- Concurrent SQL operations: bootstrap, administrator demotion, administrator suspension, and invitation acceptance all passed with exactly one success per pair.
- SQL run: MariaDB 10.4.32, isolated schema `endgame_p01_test_20261022091818_c156becb`, retained for inspection. This is not proof of an actual MySQL run.

Final review and formatting/check results will be recorded here before handoff is complete.

## Operator setup still required

1. Point `.env` at the intended existing **MySQL** server/database. The currently configured local server is MariaDB, and its `endgame` application database did not exist during implementation.
2. Set `SESSION_DRIVER=database` and configure a real SMTP mailer (`MAIL_MAILER=smtp`). The foundation's old file-session/log-mail environment values were not silently rewritten.
3. Verify the database target, then run `php artisan migrate` using PHP 8.5. Do not use reset/truncate/fresh commands.
4. Run `php artisan app:bootstrap-admin` once. It prompts for the password without echoing it. Keep credentials out of command arguments, seeders, and documentation.
5. Start the app with `composer run dev`, sign in, and send an invitation using the configured SMTP server. Verify delivery and password reset against that environment.

The code rejects log-based account notification delivery to prevent token leakage. A saved invitation whose mail failed can be resent from Accounts after SMTP is fixed.

## Database and test safety

- The application database was not created, reset, or migrated by the agent.
- Backend account tests refuse database targets other than in-memory SQLite or an `endgame_p01_test_...` schema.
- `composer run test:sql` creates a new isolated schema on the configured server and leaves it behind. It does not reuse or delete application data. The test credentials require schema-creation permission.
- Browser tests create unique files under ignored `.p01-work`, use a separate key, and keep CSRF checks active. No browser fixture route or provisioning endpoint exists in the application.
- Generated fixture files contain only test identities and test tokens; they are ignored by Git. Do not copy them into application configuration.

## Design decisions to preserve

- The singleton account-administration row serializes initial bootstrap and administrator changes. Do not replace it with an unlocked count or a process-local lock.
- `auth_version` prevents revoked in-flight sessions from becoming usable again after account reactivation. Keep the middleware check as well as explicit session/remember-token revocation.
- Services revalidate the current actor and state inside their transactions. Frontend permission props are presentation only.
- Invitation tokens and reset tokens are not audit metadata. Never report raw SMTP exceptions that may contain account links or credentials.
- Profile email stays read-only; account deletion and public registration remain absent.

## Next product work

P02 projects, memberships, roles, and permissions need their own reviewed process specification and explicit implementation request. P01's `is_active` flag must be checked when later processes choose eligible assignees. No project dashboard, ticket workflow, repository integration, or coding-agent execution is implemented here.
