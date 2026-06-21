<?php
/**
 * Plugin Name: NEC Relationship Sync
 * Plugin URI:  https://northeastern.org
 * Description: Configurable bidirectional sync between any two ACF relationship fields. Link fields from a settings page; editing either side keeps both in sync automatically.
 * Version:     2.0.0
 * Author:      Praise Day-Michael
 * Author URI:  https://profiles.wordpress.org/devdmp
 * License:     GPL-2.0+
 * Text Domain: nec-relationship-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NEC_RS_VERSION',     '2.0.0' );
define( 'NEC_RS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'NEC_RS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'NEC_RS_PLUGIN_FILE', __FILE__ );

require_once NEC_RS_PLUGIN_DIR . 'includes/class-nec-relationship-sync.php';
require_once NEC_RS_PLUGIN_DIR . 'includes/class-nec-rs-admin.php';

add_action( 'init', 'nec_rs_boot', 5 );

function nec_rs_boot() {
    if ( ! function_exists( 'get_field' ) ) {
        add_action( 'admin_notices', 'nec_rs_acf_missing_notice' );
        return;
    }
    NEC_Relationship_Sync::get_instance();
    NEC_RS_Admin::get_instance();
    nec_rs_maybe_migrate();
}

function nec_rs_acf_missing_notice() {
    echo '<div class="notice notice-error"><p><strong>NEC Relationship Sync:</strong> Advanced Custom Fields (ACF) must be installed and active.</p></div>';
}

/**
 * One-time migration: seed the original NEC Ministry/Service <-> Team pair as a
 * default mapping so existing sites keep working after the upgrade.
 */
function nec_rs_maybe_migrate() {
    if ( get_option( 'nec_rs_migrated' ) ) {
        return;
    }

    $engine   = NEC_Relationship_Sync::get_instance();
    $existing = $engine->get_mappings();

    if ( ! empty( $existing ) ) {
        update_option( 'nec_rs_migrated', NEC_RS_VERSION );
        return;
    }

    if ( ! function_exists( 'acf_get_field' ) ) {
        return;
    }

    $ministry = acf_get_field( 'nec_ministry_assigned_to_a_team' );
    $team     = acf_get_field( 'nec_team_assigned_page' );

    if ( is_array( $ministry ) && ! empty( $ministry['key'] )
         && is_array( $team ) && ! empty( $team['key'] ) ) {
        $engine->save_mappings( [ [
            'id'      => 'nec_default',
            'enabled' => true,
            'a_key'   => $ministry['key'],
            'a_name'  => $ministry['name'],
            'b_key'   => $team['key'],
            'b_name'  => $team['name'],
        ] ] );
        update_option( 'nec_rs_migrated', NEC_RS_VERSION );
    }
}
