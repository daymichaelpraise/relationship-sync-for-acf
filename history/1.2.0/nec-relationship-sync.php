<?php
/**
 * Plugin Name: NEC Relationship Sync
 * Plugin URI:  https://northeastern.org
 * Description: Bidirectional ACF relationship sync between NEC Team posts and Ministry/Service posts. Editing from either side keeps both in sync automatically.
 * Version:     1.2.0
 * Author:      Praise Day-Michael
 * Author URI:  https://profiles.wordpress.org/devdmp
 * License:     GPL-2.0+
 * Text Domain: nec-relationship-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NEC_RS_VERSION',     '1.2.0' );
define( 'NEC_RS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'NEC_RS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'NEC_RS_PLUGIN_FILE', __FILE__ );

require_once NEC_RS_PLUGIN_DIR . 'includes/class-nec-relationship-sync.php';
require_once NEC_RS_PLUGIN_DIR . 'includes/class-nec-rs-debug.php';

/**
 * Boot on init (priority 5) so ACF is definitely loaded by then.
 */
add_action( 'init', 'nec_rs_boot', 5 );

function nec_rs_boot() {
    if ( ! function_exists( 'get_field' ) ) {
        add_action( 'admin_notices', 'nec_rs_acf_missing_notice' );
        return;
    }
    NEC_Relationship_Sync::get_instance();
    NEC_RS_Debug::get_instance();
}

function nec_rs_acf_missing_notice() {
    echo '<div class="notice notice-error"><p><strong>NEC Relationship Sync:</strong> Advanced Custom Fields (ACF) must be installed and active.</p></div>';
}
