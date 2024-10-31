<?php
/**
 * Core business logic for this plugin
 *
 * @package DigitalBodhi
 */

namespace DigitalBodhi\PermissionsPlus;

use DigitalBodhi\PermissionsPlus as db;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require_once BASE_DIR . 'class-hooks.php';
require_once BASE_DIR . 'class-settings.php';
require_once BASE_DIR . 'class-admin.php';

/**
 *  Implements the Hooks abstract class
 *
 * @since 1.0.0
 */
class Manager extends Hooks {

	// list of CSS scripts used.
	public const CSS_SCRIPTS = array(
		// phpcs:disable
		// array(
		// 'script'  => ASSET_URL . 'bootstrap/css/bootstrap.min.css',
		// 'version' => '5.2.1',
		// ),
		// array(
		// 'script'  => ASSET_URL . 'jquery-ui/themes/base/jquery-ui.min.css',
		// 'version' => '1.13.2',
		// ),
		// phpcs:enable
		array(
			'script'       => ASSET_URL . MODULE . '/style.css',
			'version'      => VERSION,
			'dependencies' => array( 'woocommerce_admin_styles' ),
		),
	);


	// list of javascripts.
	public const JS_SCRIPTS = array(
		// phpcs:disable
		// array(
		// 'script'  => ASSET_URL . 'bootstrap/js/bootstrap.min.js',
		// 'footer'  => false,
		// 'version' => '5.2.1',
		// ),
		// array(
		// 'script'      => ASSET_URL . 'jquery/jquery.min.js',
		// 'footer'      => false,
		// 'version'     => '3.6.1',
		// 'extern_only' => true,
		// ),
		// array(
		// 'script'      => ASSET_URL . 'jquery-ui/jquery-ui.min.js',
		// 'footer'      => false,
		// 'version'     => '1.13.2',
		// 'extern_only' => true,
		// ),
		// phpcs:enable
		array(
			'script'       => ASSET_URL . MODULE . '/script.js',
			'footer'       => true,
			'version'      => VERSION,
			'dependencies' => array(
				'jquery',
				'jquery-ui-sortable',
				'jquery-ui-datepicker', // dates.
				'wc-enhanced-select', // product_select.
				'jquery-tiptip',  // wc_help_tip.
			),
		),
	);

	/**
	 *  Loads the hooks that are needed.  Each requested hook should have the callback defined in this class, or an exception will be thrown.
	 *
	 * @since 1.0.0
	 */
	public function initialize() {
		static::add_admin(
			array(
				'menu',
				'settings',
				'scripts',
				'header',
			)
		);
	}

	/**
	 * Override the Bootstrap::check_environment function to check for roles
	 * this happens during plugin activation.
	 *
	 * @param str $plugin_name the name of the plugin to check for.
	 * @since 1.0.0
	 */
	protected static function check_environment( $plugin_name ) {
		return parent::check_environment( $plugin_name ) &&
			Roles::add_and_validate_roles( true );
	}

	/**
	 * Override the Bootstrap::deactivate_cb function to undo added roles
	 * this happens during plugin deactivation
	 *
	 * @since 1.0.0
	 */
	public static function deactivate_cb() {
		parent::deactivate_cb();
		Roles::remove_special_capabilities();
		Settings::delete_all_settings();
	}

	/**
	 * Actual callback to add an admin menu item
	 *
	 * @since 1.0.0
	 */
	public static function admin_menu_cb() {
		add_submenu_page(
			'woocommerce',
			DISPLAY_NAME,
			DISPLAY_NAME,
			'manage_woocommerce',
			INTERNAL_NAME,
			array( Admin::class, 'render' ),
			2 // right after the orders menu item.
		);
		// phpcs:ignore
		// Admin::render_settings_only();
	}

	/**
	 * Actual callback to display and load admin settings.
	 *
	 * We also use this to control viewing of certain menu pages for
	 * different roles as this is what WC does
	 * rather than in admin_menu, see an example at :
	 * plugins/woocommerce/src/Internal/Admin/Orders/PageController.php
	 *
	 * @since 1.0.0
	 */
	public static function admin_settings_cb() {
		$settings = Settings::get_instance();
		$settings->register();

		// remove submenus if not allowed.
		Roles::restrict_admin_menus();
		// even if they use the direct url, it won't work.
		Roles::restrict_admin_menu_access();
		// update the Add User combobox with our roles.
		Roles::restrict_add_user_role_selection();
	}


	/**
	 * Actual callback to enqueue admin scripts
	 * https://digwp.com/2009/06/including-jquery-in-wordpress-the-right-way/
	 *
	 * @param str $hook name of calling page.
	 * @since 1.0.0
	 */
	public static function admin_scripts_cb( $hook ) {
		if ( 'woocommerce_page_' . INTERNAL_NAME !== $hook ) {
			return;
		}

		foreach ( self::CSS_SCRIPTS as $item ) {
			if ( isset( $item['extern_only'] ) ) {
				continue;
			}
			$pi   = pathinfo( $item['script'] );
			$deps = array();
			if ( isset( $item['dependencies'] ) ) {
				$deps = $item['dependencies'];
			}
			wp_enqueue_style(
				db\MODULE . '-' . $pi['filename'],
				$item['script'],
				$deps,
				$item['version']
			);
		}
		foreach ( self::JS_SCRIPTS as $item ) {
			if ( isset( $item['extern_only'] ) ) {
				continue;
			}
			$pi   = pathinfo( $item['script'] );
			$deps = array();
			if ( isset( $item['dependencies'] ) ) {
				$deps = $item['dependencies'];
			}
			wp_enqueue_script(
				db\MODULE . '-' . $pi['filename'],
				$item['script'],
				$deps,
				$item['version'],
				$item['footer']
			);
		}
	}

	/**
	 * Actual callback to add header information to edit.php.  Primarily for connecting PHP variables to javascript
	 *
	 * @since 1.0.0
	 */
	public static function admin_header_cb() {
		echo( "<script type='text/javascript'>\n" );
		echo( '</script>' );
	}


	/**
	 * Validates the user has appropriate permissions to continue.
	 * Ensures a user is logged in and then checks the roles.
	 * Upon success, returns the user object; otherwise null on failure
	 *
	 * @param str $min_valid_role the minimum role that the user must belong to in order to pass security.
	 * @since 1.0.0
	 */
	public static function check_security( $min_valid_role ) {
		$user = wp_get_current_user();
		$name = $user->display_name;

		if ( ! is_user_logged_in() ) {
			echo( '<p>You are not logged in.  Click here to <a href="' . esc_html( wp_login_url() ) . '?redirect_to=/' . esc_html( self::REDIRECT_NAME ) . '?page=home">' . esc_html( __( 'Log In' ) ) . '</a></p>' );
			return null;
		} elseif ( ! Roles::validate_min_role( $min_valid_role, $user ) ) {
			echo( '<p>You are logged in as <b>' . esc_html( $name ) . '</b>, but to view this page you must be part of one of these roles: ' . esc_html( implode( ', ', Roles::get_roles_equal_to_or_above( $min_valid_role ) ) ) . '.   Click here to <a href="' . esc_html( wp_logout_url() ) . '">' . esc_html( __( 'Log Out' ) ) . '</a></p>' );
			return null;
		}
		return $user;
	}

	/**
	 * Reusable function to validate the nonce.
	 *
	 * @param str $nonce_name customizable to the generated nonce, defaults to the WP default.
	 * @since 1.0.0
	 */
	public static function check_nonce( $nonce_name = '_wpnonce' ) {
		// we can skip the nonce check if it wasn't passed.
		if ( isset( $_REQUEST[ $nonce_name ] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST[ $nonce_name ] ) );

			// for wp_list_table, use the output of:
			// $this->_args['plural'] in the wp_list_table class,
			// to determine the appropriate nonce arg.
			if ( ! ( wp_verify_nonce( $nonce ) || wp_verify_nonce( $nonce, 'bulk-woocommerce_page_' . static::get_internal_name() ) ) ) {
				echo( esc_html__( 'Invalid cookie, please logout and login again' ) );
				return false;
			}
		}
		return true;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		// create this one time, subsequent invocations will reuse obj.
		$notifier = Notifier::get_instance( DISPLAY_NAME );
	}


}
