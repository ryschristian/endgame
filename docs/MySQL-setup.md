# Local MySQL setup — 2026-10-23

Endgame now uses **MySQL Community Server 8.4.13 LTS** at `127.0.0.1:3306`, database `endgame_import_20261023033240`. Laravel uses the `mysql` connection and PHP 8.5's PDO MySQL driver. On the user's explicit request, XAMPP's MariaDB installation was removed after copying and verifying all its user databases. See [MariaDB retirement](MariaDB-retirement.md) for the final database mapping and complete installation archive. The original MySQL `endgame` database remains intact as an older copy.

## Local operation

Run these from the repository root:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/mysql.ps1 start
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/mysql.ps1 status
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/mysql.ps1 stop
```

The start command is safe to repeat. The server runs in the background with its window hidden, binds only to `127.0.0.1`, and uses UTC for database sessions by default. This is a local ZIP installation, not a Windows service: run `start` after restarting Windows, before starting Laravel. The execution-policy flag applies only to that PowerShell process.

The official [MySQL Windows archive](https://dev.mysql.com/downloads/mysql/8.4.html?os=3) is installed under `.setup/mysql/mysql-8.4.13-winx64`. The downloaded archive matched the publisher's MD5 checksum `7b94489d5524dea9b27097068bea25a8`.

Private files under the Git-ignored `.setup/mysql` directory:

- `my.ini`: server paths, port, bind address, UTF-8 defaults, and UTC timezone.
- `client.ini`: local client credentials; the generated root password also appears in the ignored `.env`.
- `data/`: MySQL data files. Do not delete or copy these while the server is running.
- `server.err`: server log.
- `backups/20261023-004734/`: source SQL dump, original environment configuration, and row-verification metadata.

The installation directory's Windows permissions are restricted to the current user, Administrators, and SYSTEM. Keep its credentials and backups private. `.env.example` contains no password; a fresh machine needs its own MySQL installation and credentials.

## Initial data preservation (before the later retirement)

The existing MariaDB application database was exported with a consistent transactional dump while Laravel was in maintenance mode, then imported into a new MySQL `endgame` database. Every row in all **15 tables** was compared using hashes, with timestamp sessions normalized to UTC on both servers. Both accounts, the existing project, permission data, audit history, and all four migration records were preserved. The original MariaDB database was not changed or removed.

The connection was switched only after the comparison passed. Laravel's configuration cache was cleared and maintenance mode was removed. No account seeder, migration reset, or application data reset was run. All four existing migrations are already applied on MySQL.

The original database is a snapshot fallback: writes made after the switch exist only in MySQL. Do not restore the old `.env` or switch back to MariaDB without accounting for those newer writes.

## Verification

- The initial switch reported `8.4.13`, `MySQL Community Server - GPL`, port `3307`; after retiring MariaDB, the same MySQL server now uses port `3306`.
- Stop, start, repeated start, and status commands passed; Laravel reconnected after restart.
- The existing SQL runner passed **118 Pest tests / 647 assertions** and **all 8 account/project concurrency checks** against actual MySQL.
- The SQL runner checks the server product before creating a test schema and rejects MariaDB.
- The rejection path was checked against the original MariaDB server; its schema count did not change.
- Test schemas are isolated under `endgame_p01_test_...` and retained; the application database is never used for test fixtures.
- Live SMTP was not exercised.

Use PHP 8.5 on PATH, then `composer run test:sql` to repeat the SQL checks. That runner needs schema-creation privileges and creates a new isolated schema each time. Record retained schema names and fresh results in [HANDOFF.md](HANDOFF.md).
