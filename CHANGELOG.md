# Changelog

All notable changes to FilamentManager Server are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
