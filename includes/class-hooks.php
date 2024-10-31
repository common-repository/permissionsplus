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

require_once BASE_DIR . 'class-bootstrap.php';
require_once BASE_DIR . 'class-notifier.php';

/**
 *  Organizes all of the hooks into the WP system
 *
 * @since 1.0.0
 */
abstract class Hooks extends Bootstrap {

	public const ADMIN_ACTIONS = array(
		'menu'     => array(
			'hook' => 'admin_menu',
			'args' => 0,
		),
		'settings' => array(
			'hook' => 'admin_init',
			'args' => 0,
		),
		'scripts'  => array(
			'hook' => 'admin_enqueue_scripts',
			'args' => 0,
		),
		'header'   => array(
			'hook' => 'admin_head',
			'args' => 0,
		),
	);

	/**
	 * Adds all of the admin callbacks
	 *
	 * @param array $actions the list of callbacks to execute.
	 * @since 1.0.0
	 */
	public static function add_admin( $actions ) {
		foreach ( $actions as $action ) {
			if ( isset( self::ADMIN_ACTIONS[ $action ] ) ) {
				$hook = self::ADMIN_ACTIONS[ $action ];
				add_action(
					$hook['hook'],
					array(
						get_called_class(),
						'admin_' . $action . '_cb',
					)
				);
			}
		}

	}

	/**
	 * Admin Menu Callback
	 *
	 * @throws \Exception Since it is not implemented.
	 * @since 1.0.0
	 */
	public static function admin_menu_cb() {
		throw new \Exception(
			'please override function: ' .
			get_called_class() . '::' . __FUNCTION__ .
			'() or remove from Initialize()'
		);
	}

	/**
	 * Admin Settings Callback
	 *
	 * @throws \Exception Since it is not implemented.
	 * @since 1.0.0
	 */
	public static function admin_settings_cb() {
		throw new \Exception(
			'please override function: ' .
			get_called_class() . '::' . __FUNCTION__ .
			'() or remove from Initialize()'
		);
	}

	/**
	 * Admin Header Callback
	 *
	 * @throws \Exception Since it is not implemented.
	 * @since 1.0.0
	 */
	public static function admin_header_cb() {
		throw new \Exception(
			'please override function: ' .
			get_called_class() . '::' . __FUNCTION__ .
			'() or remove from Initialize()'
		);
	}
}
