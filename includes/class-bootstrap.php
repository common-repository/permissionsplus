<?php
/**
 * Generic/Reusable class to load a WC extension plugin to allow a child class to focus on the core business logic.  Performs various hooks into the WordPress system.
 *
 * @package DigitalBodhi
 */

namespace DigitalBodhi\PermissionsPlus;

use DigitalBodhi\PermissionsPlus as db;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require_once BASE_DIR . 'class-notifier.php';

/**
 *  Static class that is never meant to be used directly.  A class should extend this to be usable.
 *
 * @since 1.0.0
 */
abstract class Bootstrap {

	/**
	 * Singleton class
	 *
	 * @var \db\Bootstrap $instance
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Flag to check for whether activation failed it's checks
	 *
	 * @var bool $activation_failed
	 * @since 1.0.0
	 */
	protected static $activation_failed = false;

	/**
	 * Performs the plugin registration and hooks the initialization
	 *
	 * @since 1.0.0
	 */
	public static function register() {
		$notifier = db\Notifier::get_instance( DISPLAY_NAME );
		register_activation_hook( BASE_FILE, array( get_called_class(), 'activate_cb' ) );
		register_deactivation_hook( BASE_FILE, array( get_called_class(), 'deactivate_cb' ) );

		// use the recommended load from: https://developer.woocommerce.com/extension-developer-guide/designing-a-simple-extension/.
		add_action(
			'plugins_loaded',
			array( get_called_class(), 'delayed_init' ),
			10
		);
	}

	/**
	 * Delay the initialization until woocommerce is loaded
	 *
	 * @since 1.0.0
	 */
	public static function delayed_init() {
		$notifier = db\Notifier::get_instance( DISPLAY_NAME );
		if ( ! class_exists( 'WooCommerce' ) ) {
			$notifier->add_error( 'Unable to load plugin because WC class does not exist!' );
			return;
		}
		$GLOBALS[ BASE_FILE ] = static::get_instance();
	}

	/**
	 * Callback for when plugin is to register
	 *
	 * @since 1.0.0
	 */
	public static function activate_cb() {
		db\Notifier::log( 'activating' );
		if ( ! static::check_environment( DISPLAY_NAME ) ) {
			static::manual_deactivate();
			static::$activation_failed = true;

			$notifier = db\Notifier::get_instance( DISPLAY_NAME );
			$errors   = $notifier->get_notices();
			if ( $errors && count( $errors ) > 0 ) {
				db\Notifier::log( $errors );
				$msg = $errors[0]['msg'];
			} else {
				$msg = esc_html( DISPLAY_NAME ) . ': Unexpected error while trying to activate plugin';
			}
			$notifier->output_notices();
			wp_die( esc_html( $msg ) );
		}
	}


	/**
	 * Deactivates the plugin when there is an error during plugin activation; this is not intended to be called by the normal deactivate plugin callback
	 *
	 * @since 1.0.0
	 */
	protected static function manual_deactivate() {
		db\Notifier::log( 'manually deactivating' );
		deactivate_plugins( BASE_FILE );

		// phpcs:disable
		if ( isset( $_REQUEST['activate'] ) ) {
			unset( $_REQUEST['activate'] );
		}
		// phpcs:enable
	}

	/**
	 * Deactivates the plugin.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate_cb() {
		db\Notifier::log( 'deactivating' );
	}


	/**
	 * Check the environment for version incompatibilities
	 *
	 * @param str $plugin_name Name of plugin.
	 * @return boolean Whether the checks passed.
	 * @since 1.0.0
	 */
	protected static function check_environment( $plugin_name ) {

		$notifier   = db\Notifier::get_instance( DISPLAY_NAME );
		$wc_version = '0.0';
		if ( class_exists( 'WooCommerce' ) ) {
			global $woocommerce;
			$wc_version = $woocommerce->version;
		}

		$checks = array(
			'PHP'         => array(
				PHP_VERSION,
				MINIMUM_PHP_VERSION,
			),
			'WordPress'   => array(
				get_bloginfo( 'version' ),
				MINIMUM_WP_VERSION,
			),
			'WooCommerce' => array(
				$wc_version,
				MINIMUM_WC_VERSION,
			),
		);
		// phpcs:ignore
		// db\Notifier::log($checks);
		$passed_checks = true;
		foreach ( $checks as $component => $versions ) {
			if ( ! version_compare( $versions[0], $versions[1], '>=' ) ) {
				$notifier->add_error( "{$plugin_name} requires {$component} version {$versions[1]} or higher.  Currently, {$component} is version {$versions[0]}." );
				$passed_checks = false;
			}
		}
		return $passed_checks;
	}

	/**
	 * Gets the main class instance.
	 * Ensures only one instance can be loaded.
	 *
	 * @since 1.0.0
	 */
	public static function get_instance() {
		// Env checks should be done at activation, but in order for this
		// to not be circumvented, upon upgrade, deactivation of the plugin
		// must occur, so we pass an instance of this class.
		//
		// Admin notices will be done by the manager, but using
		// this class.

		// phpcs:ignore
		// db\Notifier::log( static::$instance, static::$activation_failed );
		if ( null === static::$instance && ! static::$activation_failed ) {
			// phpcs:ignore
			// db\Notifier::log( 'initializing ', get_called_class());
			static::$instance = new static();
			static::$instance->initialize();
		}

		return static::$instance;
	}

	/**
	 * Function that the implementing class uses to initialize itself
	 *
	 * @since 1.0.0
	 */
	abstract public function initialize();

	/**
	 * Dummy constructor to prevent loading more than once (as a singleton)
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {}

	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( sprintf( 'You cannot clone instances of %s.', get_class( $this ) ) ), '1.0.0' );
	}

	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ) ), '1.0.0' );
	}
}
