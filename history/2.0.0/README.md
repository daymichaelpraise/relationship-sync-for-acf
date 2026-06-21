# v2.0.0 — configurable engine (NEC-branded)

The turning point: the hardcoded Ministry/Service ↔ Team logic was replaced with a generic, configuration-driven engine plus a settings page to map any two ACF relationship fields. It still shipped under the `NEC_` namespace and auto-seeded the original NEC pair as a default mapping.

This version is functionally equivalent to the current release at the repository root. The only differences from root (v2.1.0) are mechanical: the rename to `Relationship Sync for ACF`, the `RSFA_` prefix / `RSFA_Sync` / `RSFA_Admin` classes, the `rsfa_*` option and action keys, the dropped NEC seed, and i18n wrapping. Refer to the root `includes/class-rsfa-sync.php` and `includes/class-rsfa-admin.php` for the equivalent engine and admin source.
