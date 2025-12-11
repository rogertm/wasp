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
	 * Sortable Columns
	 * @access protected
	 * @var array
	 *
	 * @since 1.1.0
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
	private function register_hooks()
	{
		foreach ( $this->post_type as $post_type ) :
			// Columns header
			add_filter( 'manage_'. $post_type .'_posts_columns', array( $this, 'filter_columns' ) );

			// Columns content
			add_action( 'manage_'. $post_type .'_posts_custom_column',array( $this, 'render_column' ), 10, 2 );

			// Optional: Sortable columns
			if ( ! empty( $this->sortable_columns ) ) :
				add_filter( 'manage_edit-'. $post_type .'_sortable_columns', array( $this, 'filter_sortable_columns' ) );
				add_action( 'pre_get_posts', array( $this, 'maybe_handle_sorting' ), 10, 1 );

				// These filters adjust JOIN/WHERE so that posts without meta_key are still included.
				// They always check the query flag to avoid changing unrelated queries.
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
			$new[$key] = $label;

			// Place custom columns after the title column
			if ( 'title' == $key ) :
				foreach ( $this->columns as $col_key => $col_label )
					$new[$col_key] = $col_label;
			endif;
		endforeach;

		return $new;
	}

	/**
	 * Render the content for the custom column
	 * @param string $column
	 * @param int 	 $post_id
	 */
	public function render_column( $column, $post_id )
	{
		// Only respond to our columns
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
	 * @param array $sortable
	 * @return array
	 *
	 * @since 1.1.0
	 */
	public function filter_sortable_columns( $sortable )
	{
		foreach ( $this->sortable_columns as $col_key => $query_var ) :
			if ( is_int( $col_key ) ) :
				$col_key = $query_var;
				$query_var = $col_key;
			endif;
			$sortable[$col_key] = $query_var;
		endforeach;

		return $sortable;
	}


	/**
	 * Automatic handler for sorting by meta keys.
	 *
	 * - Checks admin main query
	 * - Checks post type(s) registered on this instance
	 * - If orderby matches one of the sortable mappings, set meta_key + orderby appropriately
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
			if ( $post_type === $this->post_type ) :
				$matches = true;
			endif;
		endif;

		if ( ! $matches )
			return;

		$orderby = $query->get( 'orderby' );
		if ( empty( $orderby ) )
			return;

		foreach ( $this->sortable_columns as $col_key => $mapping ) :
			if ( is_string( $mapping ) ) :
				$expected_query_var = $mapping;
				$meta_key = $mapping;
				$options = array();
			elseif ( is_array( $mapping ) && ! empty( $mapping['meta_key'] ) ) :
				$expected_query_var = $mapping['meta_key'];
				$meta_key = $mapping['meta_key'];
				$options = $mapping;
			else :
				continue;
			endif;

			if ( $orderby !== $expected_query_var )
				continue;

			if ( ! empty( $options['orderby'] ) ) :
				$orderby_to_set = $options['orderby'];
			elseif ( ! empty( $options['type'] ) && 'numeric' === $options['type'] ) :
				$orderby_to_set = 'meta_value_num';
			else :
				$orderby_to_set = 'meta_value';
			endif;

			// Set meta_key + orderby as usual
			$query->set( 'meta_key', $meta_key );
			$query->set( 'orderby', $orderby_to_set );

			if ( ! empty( $options['meta_type'] ) )
				$query->set( 'meta_type', $options['meta_type'] );

			// Mark the query so posts_join/posts_where filters know which meta_key to work with.
			$query->set( 'wasp_custom_columns_sorting', $meta_key );

			// We handled sorting; exit.
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

		// Build safe escaped key for SQL (esc_sql is fine here since we inject into regex-built replacement).
		$safe_meta_key = esc_sql( $meta_key );

		// Pattern: match JOIN ...postmeta... ON ( wp_posts.ID = wp_postmeta.post_id ) with optional aliasing/whitespace
		$pattern = "/(INNER|LEFT|RIGHT)\s+JOIN\s+{$wpdb->postmeta}\s+ON\s*\(\s*{$wpdb->posts}\.ID\s*=\s*{$wpdb->postmeta}\.post_id\s*\)/i";

		$replacement = "LEFT JOIN {$wpdb->postmeta} ON ({$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '{$safe_meta_key}')";
		if ( preg_match( $pattern, $join ) ) :
			$join = preg_replace( $pattern, $replacement, $join, 1 );
		else :
			// If not matched (unusual), append a LEFT JOIN to ensure ordering still works.
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

		// Remove patterns like "AND (wp_postmeta.meta_key = 'meta_key')" or "AND wp_postmeta.meta_key = 'meta_key'"
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
