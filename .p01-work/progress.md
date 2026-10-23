# P01 execution ledger

User approved implementation of the design on 2026-10-22, requested documentation afterward and handoff notes. Execute in place on feat/p01-accounts-authentication; no further design/plan approval required.

1. Account data, bootstrap, administration, invitations: implemented, HTTP/service tests passing.
2. Authentication, reset, profile, middleware: implemented; last full backend suite 73 passed/353 assertions.
3. Typed Vue forms, permissions, browser journeys: Vue pages and administrator dialog implemented; Vitest 5 pass, TS/build pass. Isolated e2e harness and 4 account journeys added, ready to run.
4. Review, verification, implementation documentation and handoff: pending.

Baseline: Pest 4/4; Vitest 1/1. Configured database endgame does not exist. Preserve original migrations with additive forward migrations.

Ruling: add one account_administration control row with bootstrapped_at, used as the transaction lock for bootstrap/admin/account mutations — protects first bootstrap and the final admin without depending on an existing user — cost: one small P01 table.
Ruling: use auth_version on users and session snapshots in addition to deleting session rows — in-flight responses must not recreate sessions that become valid after reactivation — cost: one explicit account field and middleware check.
Server probe: configured server is MariaDB 10.4.32, application endgame database absent. Do not claim actual MySQL verification. Existing .env not modified. Additive migrations supplied, no application database created/migrated.
UI skill local Python search unavailable; follow built-in accessibility rules and existing shadcn neutral tokens. No UI packages added.
