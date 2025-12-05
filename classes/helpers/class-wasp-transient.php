<?php
namespace WASP\Helpers;

/**
 * Transients
 *
 * @since 1.1.0
 */
class Transient
{

	/**
	 * Transient registry
	 *
	 * @since 1.1.0
	 */
	static private $transients_registry;

	/**
	 * Constructor
	 *
	 * @since 1.1.0
	 */
	public function __construct()
	{
		self::$transients_registry = 'wasp_transients_registry';

		add_action( 'transition_post_status', array( __CLASS__, 'delete_transients' ), 10, 3 );
	}

	/**
	 * Set transient
	 *
	 * @since 1.1.0
	 */
	public static function set_transient( $transient, $value, $expiration = 0 )
	{
		// Save the transient
		set_transient( $transient, $value, $expiration );

		// Keep a registry of all plugin transients so we can delete them reliably later.
		$registry = get_option( self::$transients_registry, array() );

		if ( ! in_array( $transient, $registry, true ) ) :
			$registry[] = $transient;
			update_option( self::$transients_registry, $registry );
		endif;
	}

	/**
	 * Get all transients
	 *
	 * @since 1.1.0
	 */
	public static function get_transients()
	{
		return get_option( self::$transients_registry );
	}

	/**
	 * Delete all transients on post publish
	 *
	 * @since 1.1.0
	 */
	public static function delete_transients( $new_status, $old_status, $post )
	{
		if ( 'publish' === $new_status && 'publish' !== $old_status ) :
			$registry = get_option( self::$transients_registry, array() );

			if ( empty( $registry ) )
				return;

			foreach ( $registry as $transient )
				delete_transient( $transient );

			delete_option( self::$transients_registry );
		endif;
	}
}
