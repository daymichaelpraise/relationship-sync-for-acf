<?php
/**
 * Debug & diagnostic admin page for NEC Relationship Sync.
 * WP Admin → Tools → NEC Sync Debug
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NEC_RS_Debug {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',             [ $this, 'register_menu' ] );
        add_action( 'admin_post_nec_rs_manual_sync',  [ $this, 'handle_manual_sync' ] );
        add_action( 'admin_post_nec_rs_backfill',     [ $this, 'handle_backfill' ] );
    }

    public function register_menu() {
        add_management_page(
            'NEC Sync Debug',
            'NEC Sync Debug',
            'manage_options',
            'nec-rs-debug',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $sync    = NEC_Relationship_Sync::get_instance();
        $message = '';

        if ( isset( $_GET['synced'] ) ) {
            $pid  = (int) $_GET['synced'];
            $message = '<div class="notice notice-success is-dismissible"><p>Sync triggered for post #' . $pid . ' (' . esc_html( get_post_type( $pid ) ) . ').</p></div>';
        }

        if ( isset( $_GET['backfilled'] ) ) {
            $count   = (int) $_GET['backfilled'];
            $message = '<div class="notice notice-success is-dismissible"><p>Backfill complete — ' . $count . ' team posts updated.</p></div>';
        }

        $team_ids     = $this->get_ids( 'nec-team' );
        $ministry_ids = $this->get_ids( 'nec-ministry' );
        $service_ids  = $this->get_ids( 'nec-service' );
        $empty_teams  = array_filter( $team_ids, fn( $id ) => empty( get_post_meta( $id, 'nec_team_assigned_page', true ) ) );

        echo '<div class="wrap"><h1>NEC Relationship Sync — Debug v' . esc_html( NEC_RS_VERSION ) . '</h1>';
        echo $message;

        echo '<table class="form-table" style="max-width:700px">';
        echo '<tr><th>ACF active</th><td>' . ( function_exists( 'get_field' ) ? 'Yes' : 'No' ) . '</td></tr>';
        echo '<tr><th>Hook registered</th><td>' . ( has_action( 'acf/save_post', [ $sync, 'handle_save' ] ) ? 'Yes' : 'No' ) . '</td></tr>';
        echo '<tr><th>NEC Team posts</th><td>' . count( $team_ids ) . '</td></tr>';
        echo '<tr><th>Ministry posts</th><td>' . count( $ministry_ids ) . '</td></tr>';
        echo '<tr><th>Service posts</th><td>' . count( $service_ids ) . '</td></tr>';
        echo '<tr><th>Empty team posts</th><td>' . count( $empty_teams ) . '</td></tr>';
        echo '</table><hr>';

        echo '<h2>Bulk Backfill — Ministry/Service → Team</h2>';
        if ( count( $empty_teams ) === 0 ) {
            echo '<p>All team posts already have data — backfill not needed.</p>';
        } else {
            echo '<form method="post" action="' . admin_url( 'admin-post.php' ) . '">';
            wp_nonce_field( 'nec_rs_backfill' );
            echo '<input type="hidden" name="action" value="nec_rs_backfill">';
            echo '<button type="submit" class="button button-primary">Run Backfill (' . count( $empty_teams ) . ' empty team posts)</button>';
            echo '</form>';
        }
        echo '<hr>';

        echo '<h2>Manual Single-Post Sync</h2>';
        echo '<form method="post" action="' . admin_url( 'admin-post.php' ) . '">';
        wp_nonce_field( 'nec_rs_manual_sync' );
        echo '<input type="hidden" name="action" value="nec_rs_manual_sync">';
        echo '<input type="number" name="nec_rs_post_id" placeholder="Post ID" required style="width:120px;">';
        echo '<button type="submit" class="button button-primary">Run Sync</button>';
        echo '</form>';

        echo '</div>';
    }

    public function handle_manual_sync() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'nec_rs_manual_sync' );

        $post_id   = isset( $_POST['nec_rs_post_id'] ) ? (int) $_POST['nec_rs_post_id'] : 0;
        if ( ! $post_id ) wp_die( 'Invalid post ID.' );

        $sync      = NEC_Relationship_Sync::get_instance();
        $post_type = get_post_type( $post_id );

        if ( in_array( $post_type, [ 'nec-ministry', 'nec-service' ], true ) ) {
            $sync->sync_from_ministry( $post_id );
        } elseif ( $post_type === 'nec-team' ) {
            $sync->sync_from_team( $post_id );
        } else {
            wp_die( 'Post #' . $post_id . ' has unexpected type: ' . esc_html( $post_type ) );
        }

        wp_redirect( admin_url( 'tools.php?page=nec-rs-debug&synced=' . $post_id ) );
        exit;
    }

    /**
     * Bulk backfill: read every Ministry + Service post's assigned teams
     * and write the reverse back onto each Team post via the field-key-safe path.
     */
    public function handle_backfill() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'nec_rs_backfill' );

        $sync = NEC_Relationship_Sync::get_instance();

        $map      = [];
        $page_ids = array_merge( $this->get_ids( 'nec-ministry' ), $this->get_ids( 'nec-service' ) );

        foreach ( $page_ids as $page_id ) {
            $raw = get_post_meta( $page_id, 'nec_ministry_assigned_to_a_team', true );
            if ( empty( $raw ) ) continue;
            $team_ids = is_array( $raw ) ? array_map( 'intval', $raw ) : [ (int) $raw ];
            foreach ( $team_ids as $team_id ) {
                if ( ! $team_id ) continue;
                if ( ! isset( $map[ $team_id ] ) ) $map[ $team_id ] = [];
                $map[ $team_id ][] = $page_id;
            }
        }

        $updated = 0;
        foreach ( $map as $team_id => $new_page_ids ) {
            $existing = get_post_meta( $team_id, 'nec_team_assigned_page', true );
            $existing = is_array( $existing ) ? array_map( 'intval', $existing ) : [];
            $merged   = array_values( array_unique( array_merge( $existing, $new_page_ids ) ) );
            $sync->save_relationship( 'nec_team_assigned_page', $merged, (int) $team_id );
            $updated++;
        }

        wp_redirect( admin_url( 'tools.php?page=nec-rs-debug&backfilled=' . $updated ) );
        exit;
    }

    private function get_ids( string $post_type ): array {
        return array_map( 'intval', get_posts( [
            'post_type'      => $post_type,
            'post_status'    => [ 'publish', 'private', 'draft' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] ) );
    }
}
