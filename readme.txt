=== Relationship Sync for ACF ===
Contributors: devdmp
Tags: acf, relationship, bidirectional, two-way, sync
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Link any two ACF relationship fields from a settings page and keep them in sync both ways — no code required.

== Description ==

Relationship Sync for ACF lets you connect two Advanced Custom Fields relationship fields so that editing either side automatically updates the other. Unlike approaches that require the same field name on both post types, you map fields explicitly from an admin screen, so you can link **different** fields across **different** post types.

Pick the same field on both sides to create a mutual (symmetric) relationship, where A pointing to B implies B points back to A.

**Features**

* Settings page to link any two ACF relationship fields (Settings → Relationship Sync).
* Automatic bidirectional sync on save — add or remove a relationship on one side and the other side updates itself.
* Cross-field and cross-post-type mappings (fields do not need the same name).
* Symmetric / mutual relationships (link a field to itself).
* One-click backfill per mapping to align existing data.
* Enable/disable mappings without deleting them.
* Writes the ACF field-key reference so synced values render correctly in the editor.

**Requirements**

* Advanced Custom Fields (free or Pro).
* Top-level relationship fields (fields nested inside Repeater or Flexible Content are not supported, because their meta keys are dynamic).

== Installation ==

1. Upload the plugin to `/wp-content/plugins/relationship-sync-for-acf/`, or install it via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Settings → Relationship Sync, add a mapping by choosing two relationship fields, and (optionally) run Backfill once to align existing content.

== Frequently Asked Questions ==

= Do I have to run Backfill regularly? =

No. Backfill is a one-time action per mapping that aligns data created before the mapping existed. Ongoing edits sync automatically.

= How does it know which posts to update? =

It reads the post types each field is assigned to from the field's ACF field group location rules.

= Does ACF already do this? =

ACF 6.2+ has a built-in bidirectional option for a single field. This plugin focuses on mapping two *different* fields together from a UI, which the built-in option does not do.

== Changelog ==

= 2.1.0 =
* Rebranded to Relationship Sync for ACF; generic, configurable engine for public release.

= 2.0.0 =
* Configurable field-pair mappings with a settings page and field auto-discovery.

= 1.2.0 =
* Write the ACF field-key reference on sync; add recursion-safe try/finally.

= 1.1.0 =
* Initial bidirectional sync implementation.
