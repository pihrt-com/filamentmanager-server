# Changelog

All notable changes to FilamentManager Server are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.12] - 2026-08-28

### Added

- Individual deletion of revoked devices and their tokens.
- One-click deletion of every revoked device in the current workspace.
- Stable mobile installation IDs, so signing in again updates one device record instead of creating an unlimited history of rows.
- A floating, localized back-to-top button on every signed-in page with smooth scrolling and no JavaScript dependency.

### Fixed

- Versioned stylesheet URLs now invalidate the browser cache after a server update.
- The connected-device card reliably spans the full Settings width and keeps wide tables inside a horizontal scroll container on small screens.

## [0.1.11] - 2026-08-28

### Added

- Responsive connected-device management in Settings with user, platform, mobile app version, last activity, state, and administrator-controlled access revocation.
- Audit records for device access revocation.
- Current English interface screenshots in the project README.

### Changed

- Device refresh tokens remain stable until expiration, logout, or administrator revocation while short-lived access tokens continue to be renewed. This prevents a lost or overlapping refresh response from disconnecting the mobile application.
- Replaced the obsolete planned-release section in README with the implemented feature set.

## [0.1.10] - 2026-08-28

### Fixed

- Rotating a mobile refresh token now preserves the authenticated user ID, so long-running mobile synchronization can renew access tokens after the initial 15-minute access token expires.

### Changed

- Documented the mobile application's first-connection, offline-queue, and bidirectional synchronization workflow.

## [0.1.9] - 2026-08-28

### Added

- Editable printer operational status: operational, maintenance, downtime, fault, or out of service.
- Localized operational-status labels in Czech and English.
- Status badges and crossed-out, muted printer cards for every non-operational state on the dashboard.
- Database migration and synchronization validation for the expanded printer states.

## [0.1.8] - 2026-08-28

### Changed

- Administrators now trigger a forced GitHub update check immediately after every successful sign-in.
- Routine dashboard update checks use a 15-minute cache instead of a 6-hour cache.
- Available updates appear in a prominent responsive warning banner with a direct link to update details.

### Fixed

- Newly published releases no longer remain hidden on the dashboard for up to six hours after an earlier up-to-date check.

## [0.1.7] - 2026-08-28

### Added

- Official mobile application icon in the shared navigation, login page, installer, and browser favicon.
- Footer links to the FilamentManager mobile application source repository and Google Play listing.

### Changed

- The application icon and FilamentManager name now form a single home-page link.
- Footer content is arranged into separate server and mobile-application rows.

## [0.1.6] - 2026-08-28

### Added

- Material overview filters populated from stored material types, manufacturers, and colors.
- Documented role-permission matrix and regression checks for write authorization.

### Changed

- Viewer pages now hide all create, edit, and delete controls.
- Web write routes enforce permissions in middleware as well as controllers.
- Operators may update spool and printer-slot data through synchronization but cannot delete records.
- Sensitive responses are marked `no-store` and receive same-origin cross-origin isolation headers.

### Security

- Backup restore now validates every imported column against the live database schema, preventing SQL identifier injection from modified backup archives.
- Backup restore limits manifest, per-entry, and total uncompressed archive sizes.

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
