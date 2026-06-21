<?php
/**
 * Plugin Name: Relationship Sync for ACF
 * Plugin URI:  https://github.com/daymichaelpraise/relationship-sync-for-acf
 * Description: Link any two ACF relationship fields from a settings page and keep them in sync bidirectionally. Editing either side updates the other automatically. Supports cross-field and symmetric (mutual) relationships.
 * Version:     2.1.0
 * Author:      Praise Day-Michael
 * Author URI:  https://profiles.wordpress.org/devdmp
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: relationship-sync-for-acf
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RSFA_VERSION',     '2.1.0' );
define( 'RSFA_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'RSFA_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'RSFA_PLUGIN_FILE', __FILE__ );

require_once RSFA_PLUGIN_DIR . 'includes/class-rsfa-sync.php';
require_once RSFA_PLUGIN_DIR . 'includes/class-rsfa-admin.php';

/**
 * Boot on init (priority 5) so ACF is loaded by then.
 */
add_action( 'init', 'rsfa_boot', 5 );

function rsfa_boot() {
    if ( ! function_exists( 'get_field' ) ) {
        add_action( 'admin_notices', 'rsfa_acf_missing_notice' );
        return;
    }

    RSFA_Sync::get_instance();
    RSFA_Admin::get_instance();

    rsfa_maybe_migrate();
}

function rsfa_acf_missing_notice() {
    echo '<div class="notice notice-error"><p><strong>'
        . esc_html__( 'Relationship Sync for ACF:', 'relationship-sync-for-acf' )
        . '</strong> '
        . esc_html__( 'Advanced Custom Fields (ACF) must be installed and active.', 'relationship-sync-for-acf' )
        . '</p></div>';
}

/**
 * One-time migration from the plugin's earlier (pre-rename) option key, so
 * sites that ran an interim build keep their configured mappings.
 */
function rsfa_maybe_migrate() {
    if ( get_option( 'rsfa_migrated' ) ) {
        return;
    }

    $engine = RSFA_Sync::get_instance();

    // Nothing to do if mappings already exist under the new key.
    if ( ! empty( $engine->get_mappings() ) ) {
        update_option( 'rsfa_migrated', RSFA_VERSION );
        return;
    }

    // Import from the legacy option key if present.
    $legacy = get_option( 'nec_rs_mappings', null );
    if ( is_array( $legacy ) && ! empty( $legacy ) ) {
        $engine->save_mappings( $legacy );
    }

    update_option( 'rsfa_migrated', RSFA_VERSION );
}
