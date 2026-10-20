<?php
/**
 * Autoloads plugin classes from their DKBCE_ prefix.
 *
 * @package BulkCOGSEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin class autoloader.
 */
class DKBCE_Autoloader {
	/**
	 * Register the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a plugin class when its name uses the DKBCE_ prefix.
	 *
	 * Class names after the prefix are converted from underscores to hyphens
	 * and matched to admin/class-dkbce-{name}.php.
	 *
	 * @param string $class_name The requested class name.
	 * @return void
	 */
	public static function load( $class_name ): void {
		$prefix = 'DKBCE_';

		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$class_suffix = substr( $class_name, strlen( $prefix ) );

		// Class names are identifiers; reject anything that cannot map to a safe filename.
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_]+\z/', $class_suffix ) ) {
			return;
		}

		$file_name = 'class-dkbce-' . str_replace( '_', '-', strtolower( $class_suffix ) ) . '.php';
		$file_path = DKBCE_PLUGIN_DIR . 'admin/' . $file_name;

		if ( is_file( $file_path ) ) {
			require_once $file_path;
		}
	}
}
