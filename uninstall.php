<?php
/**
 * Uninstall handler for Relationship Sync for ACF.
 *
 * Runs when the plugin is deleted from the WordPress admin. Removes the
 * plugin's options. Note: synced relationship values themselves are real
 * post data and are intentionally left untouched.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'rsfa_mappings' );
delete_option( 'rsfa_migrated' );

// Clean up the legacy option key from pre-rename builds, if present.
delete_option( 'nec_rs_mappings' );
delete_option( 'nec_rs_migrated' );
