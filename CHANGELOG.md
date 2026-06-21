# Changelog

All notable changes to this project are documented here.

## [2.1.0] — Rebrand
- Renamed from **NEC Relationship Sync** to **Relationship Sync for ACF**.
- New prefix `RSFA_`, classes `RSFA_Sync` / `RSFA_Admin`, text domain `relationship-sync-for-acf`.
- Removed the site-specific (NEC) default-mapping seed; added migration from the prior option key.
- Internationalized the admin UI strings; added `readme.txt`, `README.md`, and license.

## [2.0.0] — Configurable engine
- Replaced the hardcoded Ministry/Service ↔ Team logic with a generic, configuration-driven engine.
- Added a settings page to link any two ACF relationship fields, with field auto-discovery and post-type derivation from ACF field groups.
- Added symmetric (self-paired) mappings and per-mapping backfill.
- Auto-migrated the original NEC pair into a default mapping.

## [1.2.0] — Fixes
- Write the ACF field-key reference (`_field_name`) alongside synced values so they render in the editor.
- Wrapped sync routines in `try/finally` so the recursion guard always resets.
- Routed the bulk backfill through the same safe write path.

## [1.1.0] — Initial
- Bidirectional ACF relationship sync between NEC Team posts and Ministry/Service posts.
- Tools → NEC Sync Debug page with status, manual single-post sync, and bulk backfill.
