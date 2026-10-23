# MariaDB retirement — 2026-10-23

The user explicitly requested removal of MariaDB from this system. The discovered installation was XAMPP's MariaDB 10.4.32 at `C:\xampp\mysql`; it has been shut down and removed. MySQL Community Server 8.4.13 now occupies `127.0.0.1:3306`. Endgame's `.env`, `.env.example`, local start script, and setup instructions use MySQL. Laravel's unused MariaDB connection definition was removed.

## Preserved databases

All six MariaDB user databases were copied into MySQL. Every base-table row was hashed and compared, with both connections in UTC. The final copy held a global source read lock through clean shutdown so new writes could not invalidate verification. Existing MySQL databases were never overwritten.

| Former MariaDB database | Final verified MySQL copy |
|---|---|
| accounting | accounting_import_20261023033229 |
| astreia | astreia_import_20261023033238 |
| endgame | endgame_import_20261023033240 |
| endgame_p01_test_20261022091818_c156becb | endgame_p01_test_20261022091818_c156becb_import_20261023033242 |
| phpmyadmin | phpmyadmin_import_20261023033243 |
| test | test_import_20261023033244 |

Endgame now uses `endgame_import_20261023033240`. This preserves the newer source accounts, project memberships, audit records, and already-applied P03 migration. The older MySQL `endgame` and preliminary verified copies remain available. Other applications must use their chosen MySQL schema and MySQL credentials; their source files were not edited.

## Private backups

The final backup directory is `.setup/mariadb-retired-20261023-033227/`, ignored by Git. It contains individual SQL exports, row-verification snapshots, original environment settings, and `manifest.json`. `installation.zip` is a complete archive of the stopped MariaDB installation: **1,393 files**, each checked against the source's path and uncompressed length before removal. It preserves binaries, configuration, old data directories, and system tables.

The attempted full logical system dump encountered an existing Aria checksum error in `mysql.db`. That failure is recorded in `system-dump-error.txt`; it was not treated as a successful backup. User databases were exported and verified separately. The physical archive preserves the affected system files without modifying or repairing them. MariaDB system tables/authentication metadata were not imported into MySQL.

Do not restore old `.env` files directly: their server/credentials target the retired installation. Current MySQL credentials remain in ignored local configuration; never commit these backups or credentials.

## Operation

Use the commands in [MySQL setup](MySQL-setup.md). MySQL remains a local ZIP installation; run its start command after Windows restarts. No Windows service, Docker, Nginx, or storage integration was installed. The original XAMPP PHP/Apache installations were not removed.
