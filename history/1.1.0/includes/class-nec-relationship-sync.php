<?php
/**
 * Core sync class for NEC Relationship Sync plugin.
 *
 * Ministry / Service  →  nec_ministry_assigned_to_a_team  (points to NEC Team)
 * NEC Team            →  nec_team_assigned_page            (points to Ministry + Service)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NEC_Relationship_Sync {

    const MINISTRY_POST_TYPES = [ 'nec-ministry', 'nec-service' ];
    const TEAM_POST_TYPE      = 'nec-team';
    const MINISTRY_FIELD      = 'nec_ministry_assigned_to_a_team';
    const TEAM_FIELD          = 'nec_team_assigned_page';

    private static $instance  = null;
    private $is_syncing       = false;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Priority 20 = after ACF has committed the values to the DB.
        add_action( 'acf/save_post', [ $this, 'handle_save' ], 20 );
    }

    // ── Entry ───────────────────────────────────────────────────

    public function handle_save( $post_id ) {
        if ( $this->is_syncing )      return;
        if ( ! is_numeric( $post_id ) ) return;

        // Skip auto-saves and revisions.
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;

        $post_type = get_post_type( (int) $post_id );

        if ( in_array( $post_type, self::MINISTRY_POST_TYPES, true ) ) {
            $this->sync_from_ministry( (int) $post_id );
        } elseif ( $post_type === self::TEAM_POST_TYPE ) {
            $this->sync_from_team( (int) $post_id );
        }
    }

    // ── Ministry / Service saved → update Team posts ──────────────────────

    public function sync_from_ministry( int $ministry_id ) {
        $this->is_syncing = true;

        $selected_team_ids = $this->get_related_ids( self::MINISTRY_FIELD, $ministry_id );
        $all_team_ids      = $this->get_all_post_ids( self::TEAM_POST_TYPE );

        foreach ( $all_team_ids as $team_id ) {
            $current          = $this->get_related_ids( self::TEAM_FIELD, $team_id );
            $should_link      = in_array( $team_id, $selected_team_ids, true );
            $already_linked   = in_array( $ministry_id, $current, true );

            if ( $should_link && ! $already_linked ) {
                $current[] = $ministry_id;
                $this->save_field( self::TEAM_FIELD, array_unique( $current ), $team_id );

            } elseif ( ! $should_link && $already_linked ) {
                $current = array_values( array_filter( $current, fn( $id ) => $id !== $ministry_id ) );
                $this->save_field( self::TEAM_FIELD, $current, $team_id );
            }
        }

        $this->is_syncing = false;
    }

    // ── Team saved → update Ministry / Service posts ───────────────────────

    public function sync_from_team( int $team_id ) {
        $this->is_syncing = true;

        $selected_page_ids = $this->get_related_ids( self::TEAM_FIELD, $team_id );
        $all_page_ids      = array_merge(
            $this->get_all_post_ids( 'nec-ministry' ),
            $this->get_all_post_ids( 'nec-service' )
        );

        foreach ( $all_page_ids as $page_id ) {
            $current        = $this->get_related_ids( self::MINISTRY_FIELD, $page_id );
            $should_link    = in_array( $page_id, $selected_page_ids, true );
            $already_linked = in_array( $team_id, $current, true );

            if ( $should_link && ! $already_linked ) {
                $current[] = $team_id;
                $this->save_field( self::MINISTRY_FIELD, array_unique( $current ), $page_id );

            } elseif ( ! $should_link && $already_linked ) {
                $current = array_values( array_filter( $current, fn( $id ) => $id !== $team_id ) );
                $this->save_field( self::MINISTRY_FIELD, $current, $page_id );
            }
        }

        $this->is_syncing = false;
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Read an ACF relationship field and always return plain integer IDs,
     * regardless of whether the field's Return Format is Post Object or Post ID.
     */
    public function get_related_ids( string $field_name, int $post_id ): array {
        // Use the raw meta value to avoid any ACF formatting / caching issues.
        $raw = get_post_meta( $post_id, $field_name, true );

        if ( empty( $raw ) ) {
            return [];
        }

        // get_post_meta returns a string for single values, array for multiples.
        if ( ! is_array( $raw ) ) {
            $raw = [ $raw ];
        }

        return array_values( array_unique( array_map( 'intval', array_filter( $raw ) ) ) );
    }

    private function get_all_post_ids( string $post_type ): array {
        return array_map( 'intval', get_posts( [
            'post_type'      => $post_type,
            'post_status'    => [ 'publish', 'private', 'draft' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] ) );
    }

    /**
     * Write directly to post meta (bypasses ACF's field-key lookup which can
     * fail if the field group hasn't been registered yet at save time).
     */
    private function save_field( string $field_name, array $ids, int $post_id ): void {
        update_post_meta( $post_id, $field_name, array_values( $ids ) );
    }
}
