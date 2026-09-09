---
name: filamentmanager-server-maintenance
description: Maintain FilamentManager Server across its PHP web UI, inventory rules, localization, synchronization, documentation, tests, and release packaging. Use for changes inside this repository; do not treat source inspection as proof of a deployed installation.
---

# FilamentManager Server maintenance

Keep each change consistent across persistence, controllers or services, Czech and English UI text, synchronization-visible versions, documentation, and `tests/smoke.php` when those surfaces are affected.

Preserve these application invariants:

- A physical spool is identified by `spools.id` and can be loaded in at most one active printer slot. The database enforces this with `printer_loaded_spool_unique`.
- Do not silently move a spool between printers from an ambiguous selector. Show identifying data such as the spool note, and require unloading from the previous printer before loading elsewhere.
- Distinguish total physical inventory from availability. Loaded spools count toward the physical total but are not available for another printer and do not occupy storage-location capacity.
- `spools.notes` already exists in the initial schema and synchronization map; displaying it does not require a migration.
- Web mutations that alter synchronized entities must update entity versions and add `sync_changes` records.

Before handing off a change, run `php tests/smoke.php` when PHP is available, inspect `git diff --check`, and report separately what was verified locally and what still requires deployment/runtime verification.

For a GitHub patch release, follow the repository's established format: update `VERSION` and move changelog entries under a dated version heading, commit the complete change, create the matching annotated `vX.Y.Z` tag, build from a clean release commit with `tools/build-release.ps1`, and publish both the versioned ZIP and its `.zip.sha256` file. Verify the published tag, commit, asset names, and displayed ZIP digest. Keep `.codex` project guidance in Git but out of distributable archives through `export-ignore`.
