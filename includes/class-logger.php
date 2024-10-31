<?php
/**
 * Manages logging; used by Notifier; most classes should use Notifier
 *
 * @package DigitalBodhi
 */

namespace DigitalBodhi\PermissionsPlus;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 *  Handles logging which utilizes the MODULE variable
 *  and exposes the ability to traceback the levels to focus on
 *  issuing the function call that initiated the logger
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Component name storage
	 *
	 * @var str $component Name of module.
	 * @since 1.0.0
	 */
	private static $component = null;

	/**
	 * Default function call stack level, if not using log_at_level
	 *
	 * @var int $level Level of logging.
	 * @since 1.0.0
	 */
	private static $level = 0;

	/**
	 * Sets the name of the component and the level
	 *
	 * @param str $component Name of module.
	 * @param int $level Level of logging.
	 * @since 1.0.0
	 */
	public static function set( $component, $level = 1 ) {
		// we can only set this once.
		if ( ! self::$component ) {
			self::$component = $component;
			self::$level     = $level;
		} else {
			self::log(
				'attempt to set component to: ' . $component . ', but component has been set as: ' . self::$component
			);
		}
	}

	/**
	 * Raw logging command which allows user to specifically set the function
	 * level call stack
	 *
	 * @param int   $level Level of logging.
	 * @param array ...$data Items to log (objects or strings).
	 * @since 1.0.0
	 */
	public static function log_at_level( $level, ...$data ) {
		if ( false === WP_DEBUG ) {
			return;
		}
		// phpcs:ignore
		$dbt   = debug_backtrace()[ $level ];
		$class = '';
		if ( isset( $dbt['class'] ) ) {
			$class = $dbt['class'] . $dbt['type'];
		}
		$output = '[' . self::$component . '] ' .
				$class . $dbt['function'] . '(): ';
		foreach ( $data as $d ) {
			if ( is_array( $d ) || is_object( $d ) ) {
				// phpcs:ignore
				$output .= print_r( $d, true );
			} else {
				$output .= $d;
			}
			$output .= ' ';
		}
		// phpcs:ignore
		error_log( $output );
		return $output;
	}

	/**
	 * Wrapper logging command
	 *
	 * @param array ...$data Items to log (objects or strings).
	 * @since 1.0.0
	 */
	public static function log( ...$data ) {
		return self::log_at_level( self::$level + 1, ...$data );
	}
}
