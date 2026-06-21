# v1.2.0 — fixes

Still site-specific ("NEC Relationship Sync"), but corrects two issues found in review:

- **ACF field-key reference**: `save_relationship()` now writes the `_field_name` reference alongside the value, so synced relationships render in the editor instead of appearing empty.
- **Recursion guard**: sync routines wrapped in `try/finally` so `is_syncing` always resets.
- The bulk backfill routes through the same safe write path.
