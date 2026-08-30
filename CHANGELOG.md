# Changelog

All notable changes to FilamentManager Server are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.2.3] - 2026-08-30

### Fixed

- Fixed SQL error 1451 when permanently deleting a revoked mobile device that had processed synchronization mutations.
- Individual and bulk revoked-device deletion now removes the device-specific idempotency cache before tokens and the device record, while preserving inventory, movement, change, and audit history.
- Added a migration that changes the synchronization-mutation device foreign key to `ON DELETE CASCADE` for future-safe cleanup.

## [0.2.2] - 2026-08-30

### Added

- Added exact Czech and English Windows instructions to Help for installing the PrusaSlicer helper, creating one CMD wrapper per printer, configuring each printer profile, manually testing import, and protecting the integration token.
- Added neutral copy-ready command examples that use `example.com` and contain no production server address or real credentials.
- Added permanent deletion of revoked PrusaSlicer integration-token records; active tokens must be revoked first.

## [0.2.1] - 2026-08-30

### Fixed

- Localized SMTP test messages according to the recipient user's configured Czech or English language.
- Reworked the desktop header to use the available width and wrap cleanly on narrower screens instead of hiding navigation behind horizontal scrolling.
- Widened the main content area and improved the Users layout, table wrapping, and action visibility.
- Replaced SMTP and per-user notification checkboxes with accessible on/off switches.

## [0.2.0] - 2026-08-30

### Added

- Added per-user email notification preferences for empty spools, low spool weight, unavailable or low-count materials, and full storage locations.
- Added encrypted SMTP settings, test delivery, a retrying mail queue, delivery history, and a CLI cron worker with state-based duplicate suppression.
- Added print jobs with safe text G-code metadata import, per-extruder spool assignment, actual-usage correction, and filament deduction only after explicit completion.
- Added restricted, revocable PrusaSlicer integration tokens and a fail-open post-processing helper that creates ready print jobs without modifying G-code.
- Expanded the Czech and English Help page with warehouse capacity, print jobs, PrusaSlicer, notifications, synchronization, roles, backups, security, and accuracy guidance.

### Changed

- Backups now include notification preferences and print-job history while excluding SMTP passwords and integration tokens; older backups remain restorable.
- Server discovery now advertises print-job and email-notification capabilities.

## [0.1.22] - 2026-08-30

### Added

- Added a grouped available-spool inventory card to Warehouse with combinable manufacturer, material, storage-location, color, and minimum-quantity filters.
- Added storage-location detail pages showing total, available, loaded, and empty spool counts, grouped material quantities and weights, and every individual spool.
- Loaded spools assigned to a storage location now show their current printer and slot in that location's detail.
- Added optional spool capacity to storage locations with occupied and free-space counts and a clear full-location warning; spools currently loaded in printers do not occupy shelf space.

### Changed

- Storage-location names are now buttons linking to the corresponding detail page for every signed-in role.
- Added complete Czech and English translations and responsive layouts for the expanded warehouse views.

## [0.1.21] - 2026-08-29

### Fixed

- Release ZIP generation now always uses portable forward-slash entry paths, including when built with Windows PowerShell.
- Failed web updates now return to Settings with the specific failure reason instead of showing a generic HTTP 500 page.
- Unsafe release archive diagnostics now identify the rejected entry while retaining path-traversal protection.

## [0.1.20] - 2026-08-29

### Added

- Added a responsive Czech and English Help page available to every signed-in role.
- Documented the recommended material, storage location, spool, printer, loading, weight-update, and unloading workflow directly in the web interface.
- Added concise definitions of the inventory entities and notes about planned G-code/slicer consumption imports and pending physical OpenPrintTag verification.

## [0.1.19] - 2026-08-28

### Added

- Added natural A–Z, natural Z–A, and administrator-controlled custom printer sorting modes.
- Added up and down controls to printer cards when custom sorting is enabled.

### Changed

- Mobile synchronization no longer overwrites the server-specific printer order.
- Updated the README and API/update guides with the application logo, linked Google Play badge, current synchronization behavior, independent server/mobile ordering, and cross-repository documentation links.

## [0.1.18] - 2026-08-28

### Changed

- Removed fractional seconds from device activity, user sign-in, and audit timestamps in the web interface while retaining full database precision.

## [0.1.17] - 2026-08-28

### Fixed

- Synchronization conflicts now include the client mutation ID and originally requested entity ID so mobile clients can retain the exact pending change when a natural key resolves to an existing server record.

## [0.1.16] - 2026-08-28

### Fixed

- Mobile upserts now reuse an existing empty printer slot with the same printer and slot number instead of failing its unique constraint when the phone generated a new slot UUID.
- A genuinely occupied matching slot is returned as a synchronization conflict rather than an internal server error.

## [0.1.15] - 2026-08-28

### Added

- Added an administrator-only diagnostics card showing the latest server exception log entries with request IDs; all output is HTML-escaped and authentication tokens are never logged.

## [0.1.14] - 2026-08-28

### Fixed

- Quoted the synchronization cursor column in all SQL statements for compatibility with MariaDB/MySQL variants where `sequence` conflicts with SQL syntax, preventing HTTP 500 responses from the snapshot endpoint.

## [0.1.13] - 2026-08-28

### Fixed

- Bearer authentication now works on Apache/FastCGI hosting configurations that expose the `Authorization` header as `REDIRECT_HTTP_AUTHORIZATION` or otherwise omit `HTTP_AUTHORIZATION`.
- Both supported web-root layouts explicitly preserve the Authorization header during URL rewriting, preventing freshly issued access tokens from being rejected by protected API endpoints.

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
