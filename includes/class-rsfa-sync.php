<?php
/**
 * Core sync engine for Relationship Sync for ACF.
 *
 * Generic, configuration-driven bidirectional sync between any two ACF
 * relationship fields. Each "mapping" links field A <-> field B; when a post
 * on either side is saved, the matching value on the other side is kept in
 * sync automatically. A mapping where A and B are the same field behaves
 * symmetrically (a "mutual relationship": A points to B implies B points to A).
 *
 * Mappings are stored in the `rsfa_mappings` option and managed from
 * Settings -> Relationship Sync.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RSFA_Sync {

    const OPTION_MAPPINGS = 'rsfa_mappings';

    private static $instance = null;
    private $is_syncing      = false;

    /** Per-request caches. */
    private $field_key_cache = []; // field name/key => resolved ACF field key
    private $post_type_cache = []; // field key      => array of post types
    private $name_cache      = []; // field key      => field name

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

    // ── Mapping storage ──────────────────────────────────────────────────────

    /**
     * Return all configured mappings. Each mapping is an array:
     *   [ 'id', 'enabled', 'a_key', 'a_name', 'b_key', 'b_name' ]
     */
    public function get_mappings(): array {
        $mappings = get_option( self::OPTION_MAPPINGS, [] );
        return is_array( $mappings ) ? array_values( $mappings ) : [];
    }

    public function save_mappings( array $mappings ): void {
        update_option( self::OPTION_MAPPINGS, array_values( $mappings ) );
    }

    public function get_mapping( string $id ) {
        foreach ( $this->get_mappings() as $m ) {
            if ( ( $m['id'] ?? '' ) === $id ) {
                return $m;
            }
        }
        return null;
    }

    // ── Save entry point ──────────────────────────────────────────────────────

    public function handle_save( $post_id ) {
        if ( $this->is_syncing )       return;
        if ( ! is_numeric( $post_id ) ) return; // ignore options/user/term saves

        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;

        $this->sync_post( (int) $post_id );
    }

    /**
     * Run every enabled mapping that involves the given post.
     */
    public function sync_post( int $post_id ) {
        $post_type = get_post_type( $post_id );
        if ( ! $post_type ) return;

        $this->is_syncing = true;

        try {
            foreach ( $this->get_mappings() as $m ) {
                if ( empty( $m['enabled'] ) ) continue;

                $a_key = $m['a_key'] ?? '';
                $b_key = $m['b_key'] ?? '';
                if ( ! $a_key || ! $b_key ) continue;

                $a_name = $this->field_name( $a_key, $m['a_name'] ?? '' );
                $b_name = $this->field_name( $b_key, $m['b_name'] ?? '' );
                if ( ! $a_name || ! $b_name ) continue;

                $a_types = $this->field_post_types( $a_key );
                $b_types = $this->field_post_types( $b_key );

                if ( $a_key === $b_key ) {
                    // Symmetric / mutual relationship.
                    if ( in_array( $post_type, $a_types, true ) ) {
                        $this->reconcile_symmetric( $a_name, $post_id, $a_types );
                    }
                    continue;
                }

                // Distinct fields — run whichever side(s) the post belongs to.
                if ( in_array( $post_type, $a_types, true ) ) {
                    $this->reconcile( $a_name, $post_id, $b_name, $b_types );
                }
                if ( in_array( $post_type, $b_types, true ) ) {
                    $this->reconcile( $b_name, $post_id, $a_name, $a_types );
                }
            }
        } finally {
            $this->is_syncing = false;
        }
    }

    // ── Reconcilers ────────────────────────────────────────────────────────────

    /**
     * Mirror $source's selections onto the matching $target field.
     */
    private function reconcile( string $source_field, int $source_id, string $target_field, array $target_types ): void {
        $selected = $this->get_related_ids( $source_field, $source_id );
        $targets  = $this->get_post_ids( $target_types );

        foreach ( $targets as $target_id ) {
            if ( $target_id === $source_id ) continue;

            $current     = $this->get_related_ids( $target_field, $target_id );
            $should_link = in_array( $target_id, $selected, true );
            $already     = in_array( $source_id, $current, true );

            if ( $should_link && ! $already ) {
                $current[] = $source_id;
                $this->save_relationship( $target_field, array_unique( $current ), $target_id );
            } elseif ( ! $should_link && $already ) {
                $current = array_values( array_filter( $current, fn( $id ) => $id !== $source_id ) );
                $this->save_relationship( $target_field, $current, $target_id );
            }
        }
    }

    /**
     * Symmetric variant: same field on both ends (mutual relationship).
     */
    private function reconcile_symmetric( string $field, int $source_id, array $types ): void {
        $selected = $this->get_related_ids( $field, $source_id );
        $targets  = $this->get_post_ids( $types );

        foreach ( $targets as $target_id ) {
            if ( $target_id === $source_id ) continue;

            $current     = $this->get_related_ids( $field, $target_id );
            $should_link = in_array( $target_id, $selected, true );
            $already     = in_array( $source_id, $current, true );

            if ( $should_link && ! $already ) {
                $current[] = $source_id;
                $this->save_relationship( $field, array_unique( $current ), $target_id );
            } elseif ( ! $should_link && $already ) {
                $current = array_values( array_filter( $current, fn( $id ) => $id !== $source_id ) );
                $this->save_relationship( $field, $current, $target_id );
            }
        }
    }

    // ── Backfill ────────────────────────────────────────────────────────────────

    /**
     * Propagate all existing data for a mapping so both sides agree.
     * Returns the number of source posts processed.
     */
    public function backfill_mapping( array $m ): int {
        $a_key = $m['a_key'] ?? '';
        $b_key = $m['b_key'] ?? '';
        if ( ! $a_key || ! $b_key ) return 0;

        $a_name = $this->field_name( $a_key, $m['a_name'] ?? '' );
        $b_name = $this->field_name( $b_key, $m['b_name'] ?? '' );
        if ( ! $a_name || ! $b_name ) return 0;

        $a_types = $this->field_post_types( $a_key );
        $b_types = $this->field_post_types( $b_key );

        $processed = 0;
        $this->is_syncing = true;

        try {
            if ( $a_key === $b_key ) {
                foreach ( $this->get_post_ids( $a_types ) as $pid ) {
                    $this->reconcile_symmetric( $a_name, $pid, $a_types );
                    $processed++;
                }
            } else {
                foreach ( $this->get_post_ids( $a_types ) as $pid ) {
                    $this->reconcile( $a_name, $pid, $b_name, $b_types );
                    $processed++;
                }
            }
        } finally {
            $this->is_syncing = false;
        }

        return $processed;
    }

    // ── ACF discovery ────────────────────────────────────────────────────────────

    /**
     * Discover all top-level ACF relationship fields across every field group.
     * Returns an array keyed by field key:
     *   [ field_key => [ 'key','name','label','group','post_types' ] ]
     */
    public function discover_relationship_fields(): array {
        $out = [];
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            return $out;
        }

        foreach ( acf_get_field_groups() as $group ) {
            $fields = acf_get_fields( $group['key'] );
            if ( ! is_array( $fields ) ) continue;

            foreach ( $fields as $field ) {
                if ( ( $field['type'] ?? '' ) !== 'relationship' ) continue;

                $out[ $field['key'] ] = [
                    'key'        => $field['key'],
                    'name'       => $field['name'],
                    'label'      => $field['label'],
                    'group'      => $group['title'] ?? '',
                    'post_types' => $this->field_post_types( $field['key'] ),
                ];
            }
        }

        return $out;
    }

    // ── Field helpers ──────────────────────────────────────────────────────────

    public function get_related_ids( string $field_name, int $post_id ): array {
        $raw = get_post_meta( $post_id, $field_name, true );
        if ( empty( $raw ) ) {
            return [];
        }
        if ( ! is_array( $raw ) ) {
            $raw = [ $raw ];
        }
        return array_values( array_unique( array_map( 'intval', array_filter( $raw ) ) ) );
    }

    /**
     * Resolve a field's meta name from its key (falls back to a stored name).
     */
    public function field_name( string $field_key, string $fallback = '' ): string {
        if ( isset( $this->name_cache[ $field_key ] ) ) {
            return $this->name_cache[ $field_key ];
        }
        $name = $fallback;
        if ( function_exists( 'acf_get_field' ) ) {
            $field = acf_get_field( $field_key );
            if ( is_array( $field ) && ! empty( $field['name'] ) ) {
                $name = $field['name'];
            }
        }
        return $this->name_cache[ $field_key ] = $name;
    }

    /**
     * Resolve the ACF field key for a field name or key. Used so we can write
     * the `_field_name` reference meta that ACF needs to render the value.
     */
    public function get_field_key( string $field_name_or_key ): string {
        if ( isset( $this->field_key_cache[ $field_name_or_key ] ) ) {
            return $this->field_key_cache[ $field_name_or_key ];
        }
        $key = '';
        if ( function_exists( 'acf_get_field' ) ) {
            $field = acf_get_field( $field_name_or_key );
            if ( is_array( $field ) && ! empty( $field['key'] ) ) {
                $key = $field['key'];
            }
        }
        return $this->field_key_cache[ $field_name_or_key ] = $key;
    }

    /**
     * Determine which post types a field applies to, from its field group's
     * location rules (param=post_type, operator===). Falls back to all public
     * post types if no explicit post_type rule is found.
     */
    public function field_post_types( string $field_key ): array {
        if ( isset( $this->post_type_cache[ $field_key ] ) ) {
            return $this->post_type_cache[ $field_key ];
        }

        $types = [];

        if ( function_exists( 'acf_get_field' ) ) {
            $field = acf_get_field( $field_key );

            // Walk up to the owning field group (handles nested ancestry).
            $parent = is_array( $field ) ? ( $field['parent'] ?? '' ) : '';
            $guard  = 0;
            while ( $parent && strpos( (string) $parent, 'group_' ) !== 0 && $guard < 10 ) {
                $pf     = acf_get_field( $parent );
                $parent = is_array( $pf ) ? ( $pf['parent'] ?? '' ) : '';
                $guard++;
            }

            if ( $parent && function_exists( 'acf_get_field_group' ) ) {
                $group = acf_get_field_group( $parent );
                if ( is_array( $group ) && ! empty( $group['location'] ) ) {
                    foreach ( $group['location'] as $or_group ) {
                        foreach ( (array) $or_group as $rule ) {
                            if ( ( $rule['param'] ?? '' ) === 'post_type'
                                 && ( $rule['operator'] ?? '' ) === '==' ) {
                                $types[] = $rule['value'];
                            }
                        }
                    }
                }
            }
        }

        $types = array_values( array_unique( array_filter( $types ) ) );

        if ( empty( $types ) ) {
            // Could not derive — target all public post types as a safe default.
            $types = array_values( get_post_types( [ 'public' => true ] ) );
        }

        return $this->post_type_cache[ $field_key ] = $types;
    }

    private function get_post_ids( array $post_types ): array {
        $post_types = array_values( array_filter( array_unique( $post_types ) ) );
        if ( empty( $post_types ) ) {
            return [];
        }
        return array_map( 'intval', get_posts( [
            'post_type'      => $post_types,
            'post_status'    => [ 'publish', 'private', 'draft' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] ) );
    }

    /**
     * Write a relationship value plus the ACF field-key reference so the value
     * renders correctly in the editor UI.
     */
    public function save_relationship( string $field_name, array $ids, int $post_id ): void {
        update_post_meta( $post_id, $field_name, array_values( $ids ) );

        $field_key = $this->get_field_key( $field_name );
        if ( $field_key ) {
            update_post_meta( $post_id, '_' . $field_name, $field_key );
        }
    }
}
