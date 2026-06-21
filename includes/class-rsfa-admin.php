<?php
/**
 * Admin settings page for Relationship Sync for ACF.
 * Settings -> Relationship Sync
 *
 * Lets an admin link any ACF relationship field to another (or to itself)
 * and keep them in sync bidirectionally.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RSFA_Admin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',                    [ $this, 'register_menu' ] );
        add_action( 'admin_post_rsfa_add_mapping',    [ $this, 'handle_add' ] );
        add_action( 'admin_post_rsfa_delete_mapping', [ $this, 'handle_delete' ] );
        add_action( 'admin_post_rsfa_toggle_mapping', [ $this, 'handle_toggle' ] );
        add_action( 'admin_post_rsfa_backfill',       [ $this, 'handle_backfill' ] );
    }

    public function register_menu() {
        add_options_page(
            __( 'Relationship Sync', 'relationship-sync-for-acf' ),
            __( 'Relationship Sync', 'relationship-sync-for-acf' ),
            'manage_options',
            'rsfa',
            [ $this, 'render_page' ]
        );
    }

    private function engine(): RSFA_Sync {
        return RSFA_Sync::get_instance();
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $engine    = $this->engine();
        $fields    = $engine->discover_relationship_fields();
        $mappings  = $engine->get_mappings();
        $acf_ready = function_exists( 'acf_get_field_groups' );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Relationship Sync for ACF', 'relationship-sync-for-acf' )
            . ' <span style="font-size:13px;font-weight:normal;color:#666;">v' . esc_html( RSFA_VERSION ) . '</span></h1>';
        echo '<p>' . esc_html__( 'Link any two ACF relationship fields so editing either side keeps both in sync automatically. Pick the same field on both sides to create a mutual (symmetric) relationship.', 'relationship-sync-for-acf' ) . '</p>';

        $this->render_notices();

        if ( ! $acf_ready ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Advanced Custom Fields is not active.', 'relationship-sync-for-acf' ) . '</p></div></div>';
            return;
        }

        if ( empty( $fields ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'No ACF relationship fields were found. Create a relationship field first, then return here.', 'relationship-sync-for-acf' ) . '</p></div>';
        }

        echo '<h2>' . esc_html__( 'Configured mappings', 'relationship-sync-for-acf' ) . '</h2>';
        if ( empty( $mappings ) ) {
            echo '<p><em>' . esc_html__( 'No mappings yet. Add one below.', 'relationship-sync-for-acf' ) . '</em></p>';
        } else {
            echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>';
            echo '<th>' . esc_html__( 'Field A', 'relationship-sync-for-acf' ) . '</th><th>' . esc_html__( 'Field B', 'relationship-sync-for-acf' ) . '</th>';
            echo '<th style="width:90px">' . esc_html__( 'Status', 'relationship-sync-for-acf' ) . '</th><th style="width:260px">' . esc_html__( 'Actions', 'relationship-sync-for-acf' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $mappings as $m ) {
                $id      = $m['id'] ?? '';
                $enabled = ! empty( $m['enabled'] );
                $self    = ( $m['a_key'] ?? '' ) === ( $m['b_key'] ?? '' );

                echo '<tr>';
                echo '<td>' . $this->field_label_html( $m['a_key'] ?? '', $fields, $m['a_name'] ?? '' ) . '</td>';
                echo '<td>' . ( $self
                    ? '<em>' . esc_html__( '(same field — symmetric)', 'relationship-sync-for-acf' ) . '</em>'
                    : $this->field_label_html( $m['b_key'] ?? '', $fields, $m['b_name'] ?? '' ) ) . '</td>';
                echo '<td>' . ( $enabled
                    ? '<span style="color:#2e7d32;font-weight:600;">' . esc_html__( 'Enabled', 'relationship-sync-for-acf' ) . '</span>'
                    : '<span style="color:#b32d2e;">' . esc_html__( 'Disabled', 'relationship-sync-for-acf' ) . '</span>' ) . '</td>';
                echo '<td>';
                $this->action_button( 'rsfa_toggle_mapping', $id, $enabled ? __( 'Disable', 'relationship-sync-for-acf' ) : __( 'Enable', 'relationship-sync-for-acf' ), 'button-small' );
                $this->action_button( 'rsfa_backfill', $id, __( 'Backfill', 'relationship-sync-for-acf' ), 'button-small button-primary', __( 'Run a one-time backfill for this mapping?', 'relationship-sync-for-acf' ) );
                $this->action_button( 'rsfa_delete_mapping', $id, __( 'Delete', 'relationship-sync-for-acf' ), 'button-small button-link-delete', __( 'Delete this mapping? (Existing data is not removed.)', 'relationship-sync-for-acf' ) );
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '<h2 style="margin-top:2em">' . esc_html__( 'Add a mapping', 'relationship-sync-for-acf' ) . '</h2>';
        if ( ! empty( $fields ) ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            wp_nonce_field( 'rsfa_add_mapping' );
            echo '<input type="hidden" name="action" value="rsfa_add_mapping">';
            echo '<table class="form-table" style="max-width:760px"><tbody>';

            echo '<tr><th><label for="rsfa_a">' . esc_html__( 'Field A', 'relationship-sync-for-acf' ) . '</label></th><td>' . $this->field_select( 'a_key', $fields ) . '</td></tr>';
            echo '<tr><th><label for="rsfa_b">' . esc_html__( 'Field B', 'relationship-sync-for-acf' ) . '</label></th><td>' . $this->field_select( 'b_key', $fields )
               . '<p class="description">' . esc_html__( 'Choose the same field as A to make it symmetric (mutual relationship).', 'relationship-sync-for-acf' ) . '</p></td></tr>';

            echo '</tbody></table>';
            submit_button( __( 'Add mapping', 'relationship-sync-for-acf' ) );
            echo '</form>';
        }

        echo '<hr><p style="color:#666;font-size:12px;">' . esc_html__( 'Tip: each mapping syncs across the post types its fields are assigned to (read from the ACF field group). Use Backfill once after adding a mapping to align existing data.', 'relationship-sync-for-acf' ) . '</p>';
        echo '</div>';
    }

    private function render_notices() {
        if ( isset( $_GET['added'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Mapping added.', 'relationship-sync-for-acf' ) . '</p></div>';
        }
        if ( isset( $_GET['deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Mapping deleted.', 'relationship-sync-for-acf' ) . '</p></div>';
        }
        if ( isset( $_GET['toggled'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Mapping updated.', 'relationship-sync-for-acf' ) . '</p></div>';
        }
        if ( isset( $_GET['backfilled'] ) ) {
            $n = (int) $_GET['backfilled'];
            echo '<div class="notice notice-success is-dismissible"><p>'
                . sprintf( esc_html__( 'Backfill complete — %d source posts processed.', 'relationship-sync-for-acf' ), $n )
                . '</p></div>';
        }
        if ( isset( $_GET['error'] ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( wp_unslash( $_GET['error'] ) ) . '</p></div>';
        }
    }

    private function field_select( string $name, array $fields ): string {
        $html  = '<select name="' . esc_attr( $name ) . '" id="rsfa_' . esc_attr( $name === 'a_key' ? 'a' : 'b' ) . '" required style="min-width:420px">';
        $html .= '<option value="">' . esc_html__( '— select a relationship field —', 'relationship-sync-for-acf' ) . '</option>';
        foreach ( $fields as $f ) {
            $types = $f['post_types'] ? ' [' . implode( ', ', $f['post_types'] ) . ']' : '';
            $label = $f['label'] . ' — ' . $f['name'] . ( $f['group'] ? ' (' . $f['group'] . ')' : '' ) . $types;
            $html .= '<option value="' . esc_attr( $f['key'] ) . '">' . esc_html( $label ) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private function field_label_html( string $key, array $fields, string $fallback_name ): string {
        if ( isset( $fields[ $key ] ) ) {
            $f     = $fields[ $key ];
            $types = $f['post_types'] ? ' <code style="font-size:11px">' . esc_html( implode( ', ', $f['post_types'] ) ) . '</code>' : '';
            return '<strong>' . esc_html( $f['label'] ) . '</strong> <code>' . esc_html( $f['name'] ) . '</code>' . $types;
        }
        $shown = $fallback_name ?: $key;
        return '<code>' . esc_html( $shown ) . '</code> <span style="color:#b32d2e">' . esc_html__( '(field not found)', 'relationship-sync-for-acf' ) . '</span>';
    }

    private function action_button( string $action, string $id, string $label, string $class, string $confirm = '' ) {
        $onclick = $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\')"' : '';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
        wp_nonce_field( $action );
        echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
        echo '<input type="hidden" name="mapping_id" value="' . esc_attr( $id ) . '">';
        echo '<button type="submit" class="button ' . esc_attr( $class ) . '" style="margin-right:4px"' . $onclick . '>' . esc_html( $label ) . '</button>';
        echo '</form>';
    }

    public function handle_add() {
        $this->guard( 'rsfa_add_mapping' );

        $engine = $this->engine();
        $fields = $engine->discover_relationship_fields();

        $a_key = isset( $_POST['a_key'] ) ? sanitize_text_field( wp_unslash( $_POST['a_key'] ) ) : '';
        $b_key = isset( $_POST['b_key'] ) ? sanitize_text_field( wp_unslash( $_POST['b_key'] ) ) : '';

        if ( ! isset( $fields[ $a_key ] ) || ! isset( $fields[ $b_key ] ) ) {
            $this->redirect_error( __( 'Please choose two valid relationship fields.', 'relationship-sync-for-acf' ) );
        }

        $mappings   = $engine->get_mappings();
        $mappings[] = [
            'id'      => 'm_' . wp_generate_password( 8, false ),
            'enabled' => true,
            'a_key'   => $a_key,
            'a_name'  => $fields[ $a_key ]['name'],
            'b_key'   => $b_key,
            'b_name'  => $fields[ $b_key ]['name'],
        ];
        $engine->save_mappings( $mappings );

        $this->redirect( 'added=1' );
    }

    public function handle_delete() {
        $this->guard( 'rsfa_delete_mapping' );
        $id     = isset( $_POST['mapping_id'] ) ? sanitize_text_field( wp_unslash( $_POST['mapping_id'] ) ) : '';
        $engine = $this->engine();

        $mappings = array_values( array_filter(
            $engine->get_mappings(),
            fn( $m ) => ( $m['id'] ?? '' ) !== $id
        ) );
        $engine->save_mappings( $mappings );

        $this->redirect( 'deleted=1' );
    }

    public function handle_toggle() {
        $this->guard( 'rsfa_toggle_mapping' );
        $id     = isset( $_POST['mapping_id'] ) ? sanitize_text_field( wp_unslash( $_POST['mapping_id'] ) ) : '';
        $engine = $this->engine();

        $mappings = $engine->get_mappings();
        foreach ( $mappings as &$m ) {
            if ( ( $m['id'] ?? '' ) === $id ) {
                $m['enabled'] = empty( $m['enabled'] );
            }
        }
        unset( $m );
        $engine->save_mappings( $mappings );

        $this->redirect( 'toggled=1' );
    }

    public function handle_backfill() {
        $this->guard( 'rsfa_backfill' );
        $id      = isset( $_POST['mapping_id'] ) ? sanitize_text_field( wp_unslash( $_POST['mapping_id'] ) ) : '';
        $engine  = $this->engine();
        $mapping = $engine->get_mapping( $id );

        if ( ! $mapping ) {
            $this->redirect_error( __( 'Mapping not found.', 'relationship-sync-for-acf' ) );
        }

        $count = $engine->backfill_mapping( $mapping );
        $this->redirect( 'backfilled=' . $count );
    }

    private function guard( string $nonce_action ) {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( $nonce_action );
    }

    private function redirect( string $query ) {
        wp_redirect( admin_url( 'options-general.php?page=rsfa&' . $query ) );
        exit;
    }

    private function redirect_error( string $message ) {
        wp_redirect( admin_url( 'options-general.php?page=rsfa&error=' . rawurlencode( $message ) ) );
        exit;
    }
}
