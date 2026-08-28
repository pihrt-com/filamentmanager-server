# Changelog

All notable changes to FilamentManager Server are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.5] - 2026-08-28

### Added

- Scrollable commit overview for available updates, with release notes as a fallback.
- Administrator controls for deleting stored database backups.
- Audit logging for backup deletion.

### Changed

- The stored-backup list now shows all backups in a scrollable panel.

### Security

- Backup deletion requires administrator access, CSRF validation, and a strictly validated backup filename.

## [0.1.4] - 2026-08-28

### Added

- Storage-location selection when creating or editing a spool.
- Current storage location in the spool overview.
- Inventory transfer history when a spool changes its storage location.

### Fixed

- Creating a root storage location no longer triggers the self-parent validation and a subsequent 404 page.

## [0.1.3] - 2026-08-27

### Added

- Editing for storage locations and users, including optional password changes.
- Signed-in user name and localized role in the application header.

### Changed

- Standardized action-button dimensions across links and form buttons.
- Render cancel, logout, spool, and storage-location actions as buttons.
- Localized user roles and spool statuses in Czech and English.

### Fixed

- Show storage-location and loaded-spool deletion conflicts as translated flash notices.

## [0.1.2] - 2026-08-27

### Added

- Material editing and administrator user deletion with device revocation and audit history preservation.

### Changed

- Render printer, material, user, and top-navigation actions as clear buttons with active-section highlighting.
- Return expected form conflicts as translated flash notices instead of standalone HTTP error pages.

### Fixed

- Recalculate cached update availability on every dashboard request so the installed release is never advertised as newer.
- Generate subdirectory-aware asset URLs for error layouts.

## [0.1.1] - 2026-08-27

### Fixed

- Recalculate cached update availability against the installed version and clear the cache after a successful update.
- Keep internal application-file rollback archives out of the database-backup restore list.

## [0.1.0] - 2026-08-27

### Added

- Initial standalone PHP project structure.
- Architecture for the installer, web administration, REST API, synchronization, backups, restore, and GitHub Release updates.
- Responsive Czech and English web interface with printer cards and multi-slot assignments.
- Manufacturer, material, OpenPrintTag-ready spool, warehouse location, movement, and audit schema.
- Secure session authentication, CSRF protection, account lockout, role checks, and device-scoped API tokens.
- Versioned REST API with initial snapshots, change cursors, idempotent mutations, and optimistic conflict handling.
- Portable ZIP database backups, password-confirmed restore, and automatic pre-operation backups.
- GitHub Release update checks, administrator notifications, checksum verification, maintenance mode, migration execution, and protected local data.
- Shared-hosting and isolated-public-webroot deployment modes with filesystem security diagnostics.
- One-time shared-hosting permission preparation script.

### Fixed

- Redirect unauthenticated browser requests to the sign-in page instead of rendering HTTP 401.
- Load installer assets consistently through the public asset route in subdirectory deployments.
- Handle an empty GitHub Releases list and show update-check failures inside Settings instead of returning a CDN-facing HTTP 502.
- Build release archives correctly when the repository path contains Windows path separators.
