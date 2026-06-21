# Relationship Sync for ACF

Link any two [Advanced Custom Fields](https://www.advancedcustomfields.com/) relationship fields from a settings page and keep them in sync **bidirectionally**. Editing either side updates the other automatically — no code required.

Unlike same-field-name approaches (and ACF's own built-in bidirectional option, which works on a single field), this plugin lets you map **two different fields** across **different post types** from an admin UI, and also supports symmetric (mutual) relationships.

## Features

- **Settings page** (Settings → Relationship Sync) to link any two ACF relationship fields.
- **Automatic two-way sync** on save — add/remove a relationship on one side, the other updates itself.
- **Cross-field / cross-post-type** mappings; fields don't need the same name.
- **Symmetric mappings** — pick the same field on both sides for a mutual relationship (A→B implies B→A).
- **Per-mapping backfill** to align pre-existing data in one click.
- **Enable/disable** mappings without deleting them.
- Writes the ACF **field-key reference** so synced values render correctly in the editor.

## Requirements

- WordPress 5.8+, PHP 7.4+
- Advanced Custom Fields (free or Pro)
- Works on **top-level** relationship fields (not fields nested inside Repeater / Flexible Content — their meta keys are dynamic).

## Installation

1. Copy this repository's plugin files into `wp-content/plugins/relationship-sync-for-acf/` (the main file, `relationship-sync-for-acf.php`, plus `includes/`), or build a zip of those files and upload via **Plugins → Add New → Upload Plugin**.
2. Activate it.
3. Go to **Settings → Relationship Sync**, add a mapping, and run **Backfill** once if you have existing data to align.

## How it works

On `acf/save_post`, the engine runs every enabled mapping the saved post is part of. For each mapping it:

1. Reads the post types each field applies to (from the field's ACF field group location rules).
2. Reconciles the opposite field across those posts — adding the link where the source now points, removing it where it no longer does.

A recursion guard plus direct `update_post_meta` writes (which don't re-trigger `acf/save_post`) keep it from looping. Self-mapped fields use a symmetric reconcile.

## Repository layout

```
relationship-sync-for-acf.php   # main plugin file (v2.1.0)
includes/
  class-rsfa-sync.php           # sync engine
  class-rsfa-admin.php          # settings page
uninstall.php                   # option cleanup on delete
languages/                      # translation template (.pot)
readme.txt                      # wordpress.org readme
history/                        # prior versions, preserved for reference
  1.1.0/  1.2.0/  2.0.0/
```

The `history/` folder archives the plugin's evolution from the original site-specific build (NEC Relationship Sync) to the generic, configurable release.

## wordpress.org submission readiness

- Text domains are passed as **string literals** in every translation call (i18n-scanner compatible).
- Translation template included at [`languages/relationship-sync-for-acf.pot`](languages/relationship-sync-for-acf.pot); `load_plugin_textdomain()` is wired up on boot.
- [`uninstall.php`](uninstall.php) removes the `rsfa_mappings` / `rsfa_migrated` options (and legacy keys) on delete; synced relationship values are left intact as real post data.

Remaining nice-to-haves: add screenshots to the readme, and run the official Plugin Check (PCP) plugin before submitting.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
