<?php
namespace WASP\Custom_Columns;

/**
 * Custom Columns
 *
 * @since 1.1.0
 */
abstract class Custom_Columns
{

	/**
	 * Post Type
	 * @access protected
	 * @var array|string
	 *
	 * @since 1.1.0
	 */
	protected $post_type = array();

	/**
	 * Columns
	 * @access protected
	 * @var array
	 *
	 * @since 1.1.0
	 */
	protected $columns = array();

	/**
	 * Sortable Columns mapping.
	 *
	 * Examples:
	 *  'col_key' => 'menu_order'                         // post field
	 *  'col_key' => 'meta_key_name'                      // meta key (string)
	 *  'col_key' => [ 'meta_key' => 'meta_key_name', 'type' => 'numeric' ] // advanced meta
	 *  'col_key' => [ 'meta_key' => 'meta_key_name', 'orderby_var' => 'my_var' ] // use custom orderby URL var
	 */
	protected $sortable_columns = array();

	/**
	 * Constructor
	 *
	 * @since 1.1.0
	 */
	public function __construct()
	{
		if ( is_string( $this->post_type ) )
			$this->post_type = array( $this->post_type );

		$this->register_hooks();
	}

	/**
	 * Register filter/action hooks for each post type.
	 *
	 * @since 1.1.0
	 */
	public function register_hooks()
	{
		if ( is_string( $this->post_type ) )
			$this->post_type = array( $this->post_type );

		if ( empty( $this->post_type ) || empty( $this->columns ) )
			return;

		foreach ( $this->post_type as $post_type ) :
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'filter_columns' ), 10, 1 );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );

			if ( ! empty( $this->sortable_columns ) ) :
				add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'filter_sortable_columns' ), 10, 1 );
				add_action( 'pre_get_posts', array( $this, 'maybe_handle_sorting' ), 10, 1 );

				// Only used when sorting by meta keys (we set a flag in the query when that happens)
				add_filter( 'posts_join', array( $this, 'posts_join_for_sorting' ), 10, 2 );
				add_filter( 'posts_where', array( $this, 'posts_where_for_sorting' ), 10, 2 );
			endif;
		endforeach;
	}

	/**
	 * Filter columns to add our custom columns
	 * @param array $existing_columns
	 * @return array
	 *
	 * @since 1.1.0
	 */
	public function filter_columns( $existing_columns )
	{
		$new = array();

		foreach ( $existing_columns as $key => $label ) :
			$new[ $key ] = $label;

			if ( 'title' === $key ) :
				foreach ( $this->columns as $col_key => $col_label )
					$new[ $col_key ] = $col_label;
			endif;
		endforeach;

		$existing_keys = array_keys( $new );
		$added = array_intersect( array_keys( $this->columns ), $existing_keys );
		if ( empty( $added ) ) :
			foreach ( $this->columns as $col_key => $col_label )
				$new[ $col_key ] = $col_label;
		endif;

		return $new;
	}

	/**
	 * Render the content for the custom column
	 * @param string $column
	 * @param int 	 $post_id
	 */
	public function render_column( $column, $post_id )
	{
		if ( ! array_key_exists( $column, $this->columns ) )
			return;

		$post = get_post( $post_id );

		if ( ! $post ) :
			echo '';
			return;
		endif;

		$value = $this->get_column_value( $column, $post );

		echo $value;
	}

	/**
	 * Filter sortable columns
	 * Build sortable columns mapping shown in the admin (column_key => query_var).
	 * Allows 'orderby_var' override when mapping is an array.
	 *
	 * @param array $sortable
	 * @return array
	 *
	 * @since 1.1.0
	 */
	public function filter_sortable_columns( $sortable )
	{
		foreach ( $this->sortable_columns as $col_key => $mapping ) :
			if ( is_string( $mapping ) )
				$query_var = $mapping;
			elseif ( is_array( $mapping ) && ! empty( $mapping['orderby_var'] ) )
				$query_var = $mapping['orderby_var'];
			elseif ( is_array( $mapping ) && ! empty( $mapping['meta_key'] ) )
				$query_var = $mapping['meta_key'];
			else
				// fallback to use col_key as query var
				$query_var = $col_key;

			$sortable[ $col_key ] = $query_var;
		endforeach;

		return $sortable;
	}

	/**
	 * Automatic handler for sorting by meta keys or post fields.
	 *
	 * If mapping refers to a post field (menu_order, post_date, post_title, etc),
	 * set 'orderby' to that field and DO NOT set meta_key nor mark the query.
	 *
	 * If mapping refers to a meta key, set meta_key + orderby and mark the query
	 * so posts_join_for_sorting / posts_where_for_sorting can adapt the JOIN to LEFT JOIN.
	 *
	 * @param \WP_Query $query
	 *
	 * @since 1.1.0
	 */
	public function maybe_handle_sorting( $query )
	{
		if ( ! is_admin() || ! $query->is_main_query() )
			return;

		$post_type = $query->get( 'post_type' );

		if ( empty( $this->post_type ) )
			return;

		$matches = false;
		if ( is_array( $this->post_type ) ) :
			if ( in_array( $post_type, $this->post_type, true ) ) :
				$matches = true;
			endif;
		else :
			if ( $post_type === $this->post_type )
				$matches = true;
		endif;

		if ( ! $matches )
			return;

		$orderby = $query->get( 'orderby' );
		if ( empty( $orderby ) )
			return;

		// Post Fields
		$post_fields = array(
			'ID',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_content',
			'post_title',
			'post_excerpt',
			'post_status',
			'comment_status',
			'ping_status',
			'post_password',
			'post_name',
			'to_ping',
			'pinged',
			'post_modified',
			'post_modified_gmt',
			'post_content_filtered',
			'post_parent',
			'guid',
			'menu_order',
			'post_type',
			'post_mime_type',
			'comment_count'
		);

		foreach ( $this->sortable_columns as $col_key => $mapping ) :

			// Determine expected query var and options
			if ( is_string( $mapping ) ) :
				$expected_query_var = $mapping;
				$options = array();
			elseif ( is_array( $mapping ) && ! empty( $mapping['meta_key'] ) ) :
				$expected_query_var = ! empty( $mapping['orderby_var'] ) ? $mapping['orderby_var'] : $mapping['meta_key'];
				$options = $mapping;
			elseif ( is_array( $mapping ) && ! empty( $mapping['orderby_var'] ) ) :
				$expected_query_var = $mapping['orderby_var'];
				$options = $mapping;
			else :
				// fallback: use col_key as expected query var
				$expected_query_var = $col_key;
				$options = is_array( $mapping ) ? $mapping : array();
			endif;

			if ( $orderby !== $expected_query_var )
				continue;

			// CASE A: mapping points to a post field (string that matches known post fields)
			// Example: 'menu_order'
			if ( is_string( $mapping ) && in_array( $mapping, $post_fields, true ) ) :
				// WP supports orderby=menu_order directly, but set explicitly to be safe
				$query->set( 'orderby', $mapping );
				// We do NOT set meta_key or the wasp flag; JOIN/WHERE filters should not run.
				return;
			endif;

			// CASE B: mapping is an array that explicitly specifies a 'post_field'
			// e.g. [ 'post_field' => 'menu_order' ]
			if ( is_array( $mapping ) && ! empty( $mapping['post_field'] ) && in_array( $mapping['post_field'], $post_fields, true ) ) :
				$query->set( 'orderby', $mapping['post_field'] );
				return;
			endif;

			// CASE C: treat as meta key (legacy behavior)
			if ( is_string( $mapping ) ) :
				$meta_key = $mapping;
				$options = array();
			elseif ( is_array( $mapping ) && ! empty( $mapping['meta_key'] ) ) :
				$meta_key = $mapping['meta_key'];
			else :
				// cannot resolve mapping; skip
				continue;
			endif;

			// Determine orderby for meta
			if ( ! empty( $options['orderby'] ) )
				$orderby_to_set = $options['orderby'];
			elseif ( ! empty( $options['type'] ) && 'numeric' === $options['type'] )
				$orderby_to_set = 'meta_value_num';
			else
				$orderby_to_set = 'meta_value';

			$query->set( 'meta_key', $meta_key );
			$query->set( 'orderby', $orderby_to_set );

			if ( ! empty( $options['meta_type'] ) )
				$query->set( 'meta_type', $options['meta_type'] );

			// Mark the query so JOIN/WHERE filters adapt to LEFT JOIN and do not filter out posts missing the meta.
			$query->set( 'wasp_custom_columns_sorting', $meta_key );

			return;

		endforeach;
	}

	/**
	 * Modify the JOIN so the meta_key check happens inside the JOIN ON and use LEFT JOIN
	 * This allows posts that don't have that meta key to still appear (meta_value = NULL).
	 *
	 * @param string   $join
	 * @param \WP_Query $query
	 * @return string
	 *
	 * @since 1.1.0
	 */
	public function posts_join_for_sorting( $join, $query )
	{
		global $wpdb;

		$meta_key = $query->get( 'wasp_custom_columns_sorting' );
		if ( empty( $meta_key ) )
			return $join;

		$safe_meta_key = esc_sql( $meta_key );

		// Replace any existing JOIN to postmeta to use LEFT JOIN and move meta_key condition into ON clause.
		$pattern = "/(INNER|LEFT|RIGHT)\s+JOIN\s+{$wpdb->postmeta}\s+ON\s*\(\s*{$wpdb->posts}\.ID\s*=\s*{$wpdb->postmeta}\.post_id\s*\)/i";
		$replacement = "LEFT JOIN {$wpdb->postmeta} ON ({$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '{$safe_meta_key}')";

		if ( preg_match( $pattern, $join ) ) :
			$join = preg_replace( $pattern, $replacement, $join, 1 );
		else :
			if ( false === stripos( $join, "LEFT JOIN {$wpdb->postmeta}" ) )
				$join .= " LEFT JOIN {$wpdb->postmeta} ON ({$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '{$safe_meta_key}')";

		endif;

		return $join;
	}

	/**
	 * Remove the meta_key condition from WHERE so the LEFT JOIN doesn't get turned into a filter.
	 *
	 * @param string    $where
	 * @param \WP_Query $query
	 * @return string
	 *
	 * @since 1.1.0
	 */
	public function posts_where_for_sorting( $where, $query )
	{
		global $wpdb;

		$meta_key = $query->get( 'wasp_custom_columns_sorting' );
		if ( empty( $meta_key ) )
			return $where;

		$pattern = "/\s+AND\s+\(?\s*{$wpdb->postmeta}\.meta_key\s*=\s*'".preg_quote( $meta_key, '/' )."'\s*\)?/i";
		$where = preg_replace( $pattern, ' ', $where );

		return $where;
	}

	/**
	 * Return the string to display for $column on $post.
	 * @param string   $column Column key
	 * @param WP_Post  $post   Post object
	 * @return string
	 *
	 * @since 1.1.0
	 */
	abstract protected function get_column_value( $column, $post );
}
