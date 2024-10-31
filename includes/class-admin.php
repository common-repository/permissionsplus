<?php
/**
 * Displays the HTML for the admin side
 *
 * @package DigitalBodhi
 */

namespace DigitalBodhi\PermissionsPlus;

use DigitalBodhi\PermissionsPlus as db;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require_once BASE_DIR . 'class-roles.php';
require_once BASE_DIR . 'class-settings.php';

/**
 * Displays the HTML for the admin side
 *
 * @since 1.0.0
 */
class Admin {

	const DEFAULT_TAB = 'Status';
	const TABS        = array(
		self::DEFAULT_TAB => 'display_main_screen',
		'Settings'        => 'display_settings_screen',
	);
	/**
	 * Displays the HTML for the admin side
	 *
	 * @since 1.0.0
	 */
	public static function render() {
		// validate the user can view this.
		$user = Manager::check_security( Roles::ADMIN );
		if ( ! $user || ! Manager::check_nonce() ) {
			return;
		}

		$active_tab_slug = static::get_tab_slug( static::DEFAULT_TAB );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['tab'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$active_tab_slug = sanitize_text_field(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_unslash( $_GET['tab'] )
			);
		}
		static::display_nav( static::TABS, $active_tab_slug );
	}

	/**
	 * Displays settings in the WC settings tab
	 *  used when there is nothing to display
	 *
	 * @since 1.0.0
	 */
	public static function render_settings_only() {
		echo( 'settings' );
	}


	/**
	 * Displays the navigation tabs
	 *
	 * @param array $tabs list of tabs as defined in this class.
	 * @param str   $active_tab_slug the active tab.
	 * @since 1.0.0
	 */
	private static function display_nav( $tabs, $active_tab_slug ) {
		echo( '<div><nav class="nav-tab-wrapper woo-nav-tab-wrapper">' );

		$base_url = static::get_current_url( array( 'tab' ) );
		foreach ( $tabs as $tab_label => $tab_func ) {
			$tab_slug      = static::get_tab_slug( $tab_label );
			$activated_tab = '';
			if ( $tab_slug === $active_tab_slug ) {
				$activated_tab = ' nav-tab-active';
			}
			echo( '<a href="' . esc_url( $base_url . '&tab=' . $tab_slug ) . '" class="nav-tab' . esc_attr( $activated_tab ) . '">' . esc_html( $tab_label ) . '</a>' );
		}
		echo( '</nav>' );

		$func_key = ucwords( str_replace( '_', ' ', $active_tab_slug ) );
		call_user_func( get_called_class() . '::' . $tabs[ $func_key ] );
		echo( '</div>' );
	}

	/**
	 * Gets the current url
	 *
	 * @param str $query_arg_removals optional query string parameters to remove.
	 * @since 1.0.0
	 */
	private static function get_current_url( $query_arg_removals = array() ) {
		$url = '';
		if ( isset( $_SERVER['HTTP_HOST'] ) &&
			isset( $_SERVER['REQUEST_URI'] ) ) {
			$protocol = is_ssl() ? 'https://' : 'http://';
			$url      = esc_url_raw(
				$protocol . wp_unslash( $_SERVER['HTTP_HOST'] ) .
				wp_unslash( $_SERVER['REQUEST_URI'] )
			);
		}
		foreach ( $query_arg_removals as $rem ) {
			$url = remove_query_arg( $rem, $url );
		}
		return $url;
	}

	/**
	 * Slugifies a tab name
	 *
	 * @param str $tab_label The tab label.
	 * @since 1.0.0
	 */
	private static function get_tab_slug( $tab_label ) {
		return str_replace( ' ', '_', strtolower( $tab_label ) );
	}

	/**
	 * Displays main screen/tab
	 *
	 * @since 1.0.0
	 */
	private static function display_main_screen() {
		echo( '<h4>The following roles are modified/created with corresponding permissions:</h4>' );
		echo( '<ul style="list-style-type:disc; padding-left:5px;">' );
		foreach ( array_reverse( Roles::ALL_ROLES ) as $display_role ) {
			echo( '<li>' . esc_html( $display_role ) );
			$perms = Roles::allowed_permissions( $display_role );
			if ( $perms ) {
				echo( '<ul style="list-style-type:circle; padding-left:20px;">' );
				foreach ( $perms as $perm ) {
					echo( '<li>' . esc_html( $perm ) . ' </li>' );
				}
				echo( '</ul>' );
			}
			echo( '</li>' );
		}
		echo( '</ul>' );
	}

	/**
	 * Displays settings screen/tab
	 *
	 * @since 1.0.0
	 */
	private static function display_settings_screen() {
		Settings::render();
	}
}
