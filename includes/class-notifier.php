<?php
/**
 * Handles logging and admin notices
 *
 * @package DigitalBodhi
 */

namespace DigitalBodhi\PermissionsPlus;

use DigitalBodhi\PermissionsPlus as db;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require_once BASE_DIR . 'class-logger.php';

/**
 *  Handles logging and admin notices by using the db\Logger class
 *
 * @since 1.0.0
 */
class Notifier {

	const TRANSIENT_SUFFIX = '_notifier';

	/**
	 * Name to store transient value (MODULE + TRANSIENT_SUFFIX)
	 *
	 * @var str $transient_name
	 * @since 1.0.0
	 */
	private $transient_name;

	/**
	 * Name to store display name of module
	 *
	 * @var str $component
	 * @since 1.0.0
	 */
	private $component;

	/**
	 * Array of msg and associated level (class) of an admin notice
	 *
	 * @var array $notices
	 * @since 1.0.0
	 */
	private $notices = array();

	/**
	 * Singleton class
	 *
	 * @var \db\Notifier $instance
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Function to ensure singleton nature of class
	 *
	 * @param str $component The module to display in the logs as well as providing uniqueness.
	 * @since 1.0.0
	 */
	public static function get_instance( $component = '' ) {
		if ( null === static::$instance ) {
			static::$instance = new Notifier( $component );
			add_action(
				'admin_notices',
				array(
					static::$instance,
					'output_notices',
				)
			);
			add_action(
				'shutdown',
				array(
					static::$instance,
					'save_notices',
				)
			);
		}
		return static::$instance;
	}

	/**
	 * Constructor
	 *
	 * @param str $component The module name.
	 * @throws Exception If the name of the transient is too big.
	 * @since 1.0.0
	 */
	protected function __construct( $component ) {
		$checkable_full_name  = MODULE . self::TRANSIENT_SUFFIX;
		$this->transient_name = $checkable_full_name;
		$this->component      = $component;
		// set the module name for our logger.
		// we set the level to 2 since we add another layer to the callstack.
		db\Logger::set( $component, 2 );

		// 172 char limit per WordPress codex.
		if ( strlen( $checkable_full_name ) > 172 ) {
			throw new Exception(
				'invalid transient name: ' . $checkable_full_name
			);
		}
	}

	/**
	 * Function to add an error msg
	 *
	 * @param str $msg The message to display on the admin notice.
	 * @since 1.0.0
	 */
	public function add_error( $msg ) {
		$this->add_notice( 'ERROR: ' . $msg, 'error' );
	}

	/**
	 * Function to add a warning msg
	 *
	 * @param str $msg The message to display on the admin notice.
	 * @since 1.0.0
	 */
	public function add_warning( $msg ) {
		$this->add_notice( 'WARNING: ' . $msg, 'warning' );
	}

	/**
	 * Function to add a success msg
	 *
	 * @param str $msg The message to display on the admin notice.
	 * @since 1.0.0
	 */
	public function add_success( $msg ) {
		$this->add_notice( $msg, 'success' );
	}

	/**
	 * Function to add an info msg
	 *
	 * @param str $msg The message to display on the admin notice.
	 * @since 1.0.0
	 */
	public function add_info( $msg ) {
		$this->add_notice( $msg, 'info' );
	}

	/**
	 * General function used by the add_* functions
	 *
	 * @param str $msg The message to display on the admin notice.
	 * @param str $class The level of the message (e.g., error vs. warning).
	 * @since 1.0.0
	 */
	private function add_notice( $msg, $class ) {
		$this->notices[] = array(
			'msg'   => '[' . $this->component . '] ' . $msg,
			'class' => $class,
		);
	}

	/**
	 * Static function used commonly for logging to debug.log
	 *
	 * @param array ...$data Variable list of strings, or objects to be logged.
	 * @since 1.0.0
	 */
	public static function log( ...$data ) {
		db\Logger::log_at_level( 2, ...$data );
	}

	/**
	 * Allows us to increment or decrement call stack for logging
	 *
	 * @param int   $levelinc the number of levels to add or subtract from the current logging level.
	 * @param array ...$data Variable list of strings, or objects to be logged.
	 * @since 1.0.0
	 */
	public static function log_at_level( $levelinc, ...$data ) {
		db\Logger::log_at_level( $levelinc + 2, ...$data );
	}


	/**
	 * Saves the notices prior to shutdown
	 *
	 * @since 1.0.0
	 */
	public function save_notices() {
		if ( empty( $this->notices ) ) {
			return;
		}

		// run a delete so that we don't just refresh the timeout.
		delete_transient( $this->transient_name );
		// we show them for 60 seconds.
		db\Logger::log_at_level( 1, 'saving transient: ', $this->notices );
		if ( ! set_transient( $this->transient_name, $this->notices, 60 ) ) {
			db\Logger::log_at_level(
				1,
				'failed to set_transient: ',
				$this->transient_name
			);
		} else {
			// once we save them, let's clear them out.
			$this->notices = array();
		}
	}

	/**
	 * Retrieves the current set of notices; will either retrieve what is in the instance or what has been saved, if none exist.
	 *
	 * @since 1.0.0
	 */
	public function get_notices() {
		if ( count( $this->notices ) > 0 ) {
			return $this->notices;
		} else {
			return maybe_unserialize( get_transient( $this->transient_name ) );
		}
	}

	/**
	 * Outputs the admin notices to the admin screens
	 *
	 * @since 1.0.0
	 */
	public function output_notices() {
		$notices = $this->get_notices();

		$count = 0;

		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			if ( 'error' === $notice['class'] ) {
				db\Logger::log_at_level( 1, 'admin error: ', $notice['msg'] );
			}
			echo "<div id='wcdr_admin_notices_" . esc_attr( $count ) . "' class='notice notice-" . esc_attr( $notice['class'] ) . " is-dismissible'>";
			// strip html in the output for error.
			echo '<p>' . wp_kses_post( $notice['msg'] ) . '</p>';
			echo '</div>';
			$count++;
		}
		// Clear the local copy.
		$this->notices = array();

		// Clear the transient.
		delete_transient( $this->transient_name );
	}
}
