# FilamentManager Server

FilamentManager Server is a self-hosted PHP web application and REST API for managing 3D printers, loaded filament spools, users, and future filament inventory. It is the server companion for [FilamentManager Mobile](https://github.com/pihrt-com/filamentmanager-mobile-app).

The responsive web dashboard mirrors the mobile workflow: each printer shows all filament slots with material, color, and remaining weight. Authorized users can manage printers, spools, materials, manufacturers, locations, and users. Changes are recorded in an audit trail and exposed through the versioned REST API for bidirectional mobile synchronization.

## Planned first release

- Guided browser installation on standard PHP hosting
- Czech and English user interface
- Secure web login with roles and CSRF protection
- Printer and multi-slot filament management
- Manufacturer, material, spool, and storage-location records
- REST API v1 with device tokens, versioned records, and conflict detection
- Database and configuration backup and restore
- Signed GitHub Release update checks with automatic pre-update backup
- OpenPrintTag-ready spool metadata

## Requirements

- PHP 8.4 or newer
- MySQL 8.0+ or MariaDB 10.6+
- HTTPS for production use
- PHP extensions: PDO MySQL, JSON, OpenSSL, mbstring, fileinfo, ZIP

## Installation

Upload a release package to the web server, point the web root to `public/`, and open `/install/`. The installer checks the environment, tests the database connection, creates all tables, creates the first administrator, writes the local configuration, and permanently locks itself.

On shared hosting, set the project directory to `0755` and `.htaccess` plus `prepare-install.php` to `0644`, then open `/prepare-install.php` once. It normalizes all remaining release permissions, verifies writable installer paths, and provides a link to the installer. Delete this preparation script immediately after use.

Two deployment layouts are supported. The recommended layout points the domain or subdirectory document root to `public/`, keeping all private code outside the web root. On Apache shared hosting where this cannot be configured, upload the complete project directory to a subdirectory such as `public_html/filamentmanager` and open `https://pihrt.com/filamentmanager/install/`; the root `.htaccess` blocks all private directories and routes public assets. See [Deployment](docs/DEPLOYMENT.md) for the complete procedure.

The installer requires an existing empty database on most shared hosting services. It validates PHP extensions and writable paths, creates the complete schema, generates a random application key, creates the first administrator, hardens supported filesystem permissions, and writes an installer lock. Delete both installer entry directories after installation when the hosting account permits it; the lock prevents reuse even when deletion is unavailable.

## Administration and updates

Administrators can create and download portable ZIP backups and restore them after a clean installation. A backup contains application data, users, printer assignments, inventory, movements, settings, and audit history. It intentionally excludes MySQL credentials, application secrets, sessions, and mobile device tokens.

The dashboard checks GitHub Releases at a limited interval and notifies administrators when a newer semantic version is available. A one-click update requires the administrator password, creates a database backup, downloads the dedicated release package, verifies its SHA-256 checksum, enables maintenance mode, preserves local configuration and storage, copies the release atomically per file, and applies pending database migrations. See [Updates](docs/UPDATES.md) before publishing the first release.

## REST API and synchronization

The API is versioned under `/api/v1`. Mobile clients receive short-lived access tokens and rotating device refresh tokens. Initial synchronization uses a snapshot; subsequent synchronization uses a monotonic change cursor, client-generated UUIDs, idempotent mutation IDs, optimistic record versions, tombstones, and explicit conflict results. See [API](docs/API.md).

## Security

Never commit `.env`, backups, logs, API tokens, or production credentials. Production installations must use HTTPS. Web requests use secure sessions and CSRF tokens; mobile clients use short-lived access tokens and rotating device refresh tokens.

## License

Copyright (c) 2026 Martin Pihrt. FilamentManager Server is distributed under the GNU General Public License v3.0, matching FilamentManager Mobile. See [LICENSE](LICENSE).
