<?php
/**
 * Minimal PSR-4 autoloader when Composer vendor is not installed.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder;

/**
 * Registers a classmap-free PSR-4 autoloader for the UnOrder namespace.
 */
final class Autoloader {

	/**
	 * PSR-4 base directory for UnOrder\ classes.
	 *
	 * @var string
	 */
	private static string $base_dir;

	/**
	 * PSR-4 namespace prefix (with trailing separator).
	 *
	 * @var string
	 */
	private const PREFIX = 'UnOrder\\';

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Absolute path to the `includes` directory.
	 * @return void
	 */
	public static function register( string $base_dir ): void {
		self::$base_dir = rtrim( $base_dir, "/\\" ) . DIRECTORY_SEPARATOR;
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a class file if it exists.
	 *
	 * @param string $class The fully qualified class name.
	 * @return void
	 */
	public static function load( string $class ): void {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file     = self::$base_dir . $relative . '.php';

		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
}
