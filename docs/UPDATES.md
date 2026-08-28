# Server updates

FilamentManager Server checks the latest public GitHub Release at most once every configured interval. Only administrators see update notifications and only administrators can install an update.

## Release assets

Every stable GitHub Release must contain both `filamentmanager-server-X.Y.Z.zip` and `filamentmanager-server-X.Y.Z.zip.sha256`. The archive root must contain a `VERSION` file whose exact value is `X.Y.Z`. A release tag is `vX.Y.Z`.

The PowerShell script `tools/build-release.ps1` creates both assets. Run it from a clean tagged checkout. Never package `config/local.php`, `storage/installed.lock`, logs, caches, database backups, `.git`, IDE configuration, or development test output.

## Installation flow

The administrator confirms the update with the current password. The server creates a logical database backup and an application-file rollback archive, downloads the release archive and checksum from GitHub, verifies SHA-256, rejects path traversal, verifies `VERSION`, enables maintenance mode, preserves local configuration and all storage, copies application files, runs pending migrations, and returns to normal mode. If copying or migration execution fails, the previous application files are restored automatically; the database backup remains available for a manual database rollback when a migration itself changed data.

An update needs temporary free disk space for the archive, extracted package, and backup. If an update fails, keep the generated pre-update backup and inspect `storage/logs/app.log`. Release packages use a separately published SHA-256 checksum; install only releases published by the repository owner.
