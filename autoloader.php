<?php
/**
 * Class Autoloader 101
 *
 * @since 1.0.0
 */
spl_autoload_register( function( $class ) {
	static $base_dir   = null;
	static $subfolders = null;

	if ( $base_dir === null ) {
		$base_dir   = plugin_dir_path( __FILE__ ) . 'classes/';
		$subfolders = array_filter(
			scandir( $base_dir ),
			function( $item ) use ( $base_dir ) {
				return $item[0] !== '.' && is_dir( $base_dir . $item );
			}
		);
	}

	$parts		= explode( '\\', $class );
	$class_name	= array_pop( $parts );

	$normalized = strtolower(
		str_replace( '_', '-', preg_replace( '/^Wasp_/', '', $class_name ) )
	);


	foreach ( $subfolders as $folder ) {
		foreach ( [ 'class', 'interface' ] as $type ) {
			$file = sprintf(
				'%s%s/%s-wasp-%s.php',
				$base_dir,
				$folder,
				$type,
				$normalized
			);

			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
} );
