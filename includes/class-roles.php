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
 * Performs role management
 *
 * @since 1.0.0
 */
class Roles {

	public const CUSTOMER  = 'Customer';
	public const PROCESSOR = 'Processor';
	public const MANAGER   = 'Shop manager';
	public const ADMIN     = 'Administrator';

	/**
	 * List of all roles managed, in priority order of least to greatest seniority
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public const ALL_ROLES = array(
		self::CUSTOMER,
		self::PROCESSOR,
		self::MANAGER,
		self::ADMIN,
	);


	/**
	 *  Array of the user roles needed for this plugin
	 *
	 * @var array $user_roles
	 * @since 1.0.0
	 */
	public static $user_roles = array(
		self::PROCESSOR => array(
			'read'                       => true,
			// users (no promote, delete/remove) - and restricted to customer and driver in.
			// in create_edit_user() and restrict_role_selection().
			'create_users'               => true,
			'list_users'                 => true,
			'edit_users'                 => true,
			'promote_users'              => true,
			// orders (no delete).
			'edit_posts'                 => true,
			'read_shop_order'            => true,
			'read_private_shop_orders'   => true,
			'edit_shop_order'            => true,
			'edit_shop_orders'           => true,
			'edit_others_shop_orders'    => true,
			'edit_private_shop_orders'   => true,
			'edit_published_shop_orders' => true,
			// phpcs:disable
			// 'edit_shop_order_terms' => true,
			// 'assign_shop_order_terms' => true,
			// 'manage_shop_order_terms' => true,
			// 'publish_shop_orders' => true,
			// phpcs:enable
			// products (only read).
			'read_product'               => true,
			'read_private_products'      => true,
			// there is a bug on this.
			// https://github.com/woocommerce/woocommerce/issues/31733.
			// manage wc (to access orders),
			// controlled by hide_wc_admin_menus().
			'manage_woocommerce'         => true,
			// no access to reports.
			// not done here, we removed from prod directly.
			// phpcs:disable
			// 'view_woocommerce_reports' => false,
			// 'export' => false,
			// 'export_shop_reports' => false,
			// phpcs:enable
		),
		self::MANAGER   => array(
			// users (no delete/remove) - and restricted to customer, driver, processor.
			// in create_edit_user() and restrict_role_selection().
			'create_users'                 => true,
			'list_users'                   => true,
			'edit_users'                   => true,
			'promote_users'                => true,
			// no delete orders.
			'delete_shop_orders'           => false,
			'delete_private_shop_orders'   => false,
			'delete_published_shop_orders' => false,
			'delete_others_shop_orders'    => false,
		),
	);

	/**
	 * Multidimensional array that indicates which user role can edit who
	 *
	 * @var array $user_roles_editable
	 * @since 1.0.0
	 */
	public static $user_roles_editable = array(
		self::PROCESSOR => array(
			self::CUSTOMER,
		),
		self::MANAGER   => array(
			self::CUSTOMER,
			self::PROCESSOR,
		),
	);

	/**
	 * Multidimensional array that groups capabilities into human
	 * readable groups
	 *
	 * @var array $permission_groups
	 * @since 1.0.0
	 */
	private static $permission_groups = array(
		'order: view (created by self)'          => array(
			'read' => true,
		),
		'order: view (created by others)'        => array(
			'read_shop_order'          => true,
			'read_private_shop_orders' => true,
		),
		'order: edit'                            => array(
			'edit_posts'                 => true,
			'edit_shop_order'            => true,
			'edit_shop_orders'           => true,
			'edit_others_shop_orders'    => true,
			'edit_private_shop_orders'   => true,
			'edit_published_shop_orders' => true,
		),
		'orders: delete'                         => array(
			'delete_shop_orders'           => true,
			'delete_private_shop_orders'   => true,
			'delete_published_shop_orders' => true,
			'delete_others_shop_orders'    => true,
		),
		'product: view'                          => array(
			'read_product'          => true,
			'read_private_products' => true,
		),
		'product: edit'                          => array(
			'edit_products'         => true,
			'edit_private_products' => true,
		),
		'users: manage'                          => array(
			'create_users'  => true,
			'list_users'    => true,
			'edit_users'    => true,
			'promote_users' => true,
		),
		'admin: view admin menus (see settings)' => array(
			'manage_woocommerce' => true,
		),
	);

	/**
	 *  Provides the permisions that a certain role has.
	 *
	 * @param str $display_role the display name for the role.
	 * @since 1.0.0
	 */
	public static function allowed_permissions( $display_role ) {
		$valid_groups = array();
		$role_name    = self::get_internal_slug( $display_role );
		$role         = get_role( $role_name );
		if ( $role ) {
			foreach ( static::$permission_groups as $group => $perms ) {
				$all_perms = true;
				foreach ( $perms as $permk => $permv ) {
					if ( ! isset( $role->capabilities ) || ! isset( $role->capabilities[ $permk ] ) || ! $role->capabilities[ $permk ] || ! $permv === $role->capabilities[ $permk ] ) {
						$all_perms = false;
						break;
					}
				}
				if ( $all_perms ) {
					if ( 'users: manage' === $group ) {
						if ( self::ADMIN === $display_role ) {
							$group .= ' all roles';
						} else {
							$group .= ' certain roles (' . implode(
								', ',
								static::$user_roles_editable[ $display_role ]
							);
							$group .= ')';
						}
					}
					$valid_groups[] = $group;
				}
			}
		}
		return $valid_groups;
	}

	// phpcs:disable
	/*
	 * Based on WC_Install::get_core_capabilities in plugins/woocommerce/includes/class-wc-install.php
	 * Shop manager additions:
	 *   create customer/user
	 *   edit customer/user
	 * Shop processor must be able to:
	 *   create customer/user
	 *   read customer/user
	 *   create order
	 *   edit order
	 *   read product
	 *   Shop processor can not:
	 *     edit customer/user
	 *     edit product
	 *     edit product inventory
	 *     access any admin settings
	 *     access messaging tool
	 * Delivery Manager must be able to:
	 *   processor
	 *   use delivery tool
	 *   create/edit drivers
	 *   view/create/edit customers
	 *   driver EoD
	 *   reporting
	 *

	 * users=======================
	 'create_users' => false,
	 'list_users' => false,
	 'edit_users' => false,
	 'promote_users' => false,
	 'delete_users' => false,
	 'remove_users' => false,

	 * orders=========================
	 'read_shop_order' => false,
	 'read_private_shop_orders' => false,
	 'edit_shop_order' => false,
	 'edit_shop_orders' => false,
	 'edit_others_shop_orders' => false,
	 'edit_private_shop_orders' => false,
	 'edit_published_shop_orders' => false,
	 'edit_shop_order_terms' => false,
	 'assign_shop_order_terms' => false,
	 'manage_shop_order_terms' => false,
	 'publish_shop_orders' => false,
	 'delete_shop_order' => false,
	 'delete_shop_orders' => false,
	 'delete_others_shop_orders' => false,
	 'delete_private_shop_orders' => false,
	 'delete_published_shop_orders' => false,
	 'delete_shop_order_terms' => false,

	 * products=======================
	 'read_product' => false,
	 'read_private_products' => false,
	 'edit_product' => false,
	 'edit_products' => false,
	 'edit_others_products' => false,
	 'edit_private_products' => false,
	 'edit_product_terms' => false,
	 'edit_published_products' => false,
	 'assign_product_terms' => false,
	 'manage_product_terms' => false,
	 'publish_products' => false,
	 'delete_product' => false,
	 'delete_products' => false,
	 'delete_others_products' => false,
	 'delete_private_products' => false,
	 'delete_product_terms' => false,
	 'delete_published_products' => false,

	 */
	// phpcs:enable

	/**
	 *  Restrict which admin menus can be seen based on what is defined
	 *  in the settings
	 *
	 * @since 1.0.0
	 */
	public static function restrict_admin_menus() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$user     = wp_get_current_user();
			$max_role = static::get_max_role( $user );

			$settings = Settings::get_instance();
			$menus    = $settings->get_setting( 'access', 'role' );
			if ( ! $menus ) {
				$menus = array();
			}

			foreach ( $menus as $menu ) {
				$terms = explode( '|', $menu );
				if ( isset( $terms[0] ) ) {
					if ( $terms[0] !== $max_role ) {
						// phpcs:ignore
						// db\Notifier::log('passing on: '. $menu);
						continue;
					}
				}
				if ( count( $terms ) === 5 ) {
					// phpcs:ignore
					// db\Notifier::log('removing sub: '. $menu, $max_role);
					remove_submenu_page( $terms[1], $terms[3] );
				} elseif ( count( $terms ) === 3 ) {
					// phpcs:ignore
					// db\Notifier::log('removing: '. $menu, $max_role, $GLOBALS['menu']);
					if ( isset( $GLOBALS['menu'] ) ) {
						remove_menu_page( $terms[1] );
					}
				} else {
					db\Notifier::log( 'ERROR: invalid menu item: ' . $menu );
				}
			}
		}
	}

	/**
	 *  Add a callback so we can validate the permissions
	 *
	 * @since 1.0.0
	 */
	public static function restrict_admin_menu_access() {
		add_action(
			'current_screen',
			array( get_called_class(), 'check_screen' )
		);
	}

	/**
	 *  Add a callback so we can adjust the role combobox in Add User
	 *
	 * @since 1.0.0
	 */
	public static function restrict_add_user_role_selection() {
		add_filter( 'editable_roles', array( get_called_class(), 'restrict_role_selection' ) );
	}

	/**
	 *  Checks whether a user has access to this screen based on role permissions.
	 *
	 * @param str $screen the url trying to be accessed.
	 * @since 1.0.0
	 */
	public static function check_screen( $screen ) {
		$url = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$url = str_replace(
				'/wp-admin/',
				'',
				esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			);
		}
		// phpcs:ignore
		// db\Notifier::log($screen);
		// phpcs:ignore
		// db\Notifier::log($screen->base);
		// phpcs:ignore
		// db\Notifier::log($screen->id);
		// phpcs:ignore
		// db\Notifier::log($url);

		$settings = Settings::get_instance();
		$menus    = $settings->get_setting( 'access', 'role' );
		if ( ! $menus ) {
			$menus = array();
		}

		$user     = wp_get_current_user();
		$max_role = static::get_max_role( $user );
		foreach ( $menus as $menu ) {
			$terms = explode( '|', $menu );
			if ( $max_role === $terms[0] ) {
				if ( count( $terms ) === 5 ) {
					if ( $terms[3] === $url ) {
						wp_die( 'Access denied.' );
						break;
					}
				} elseif ( count( $terms ) === 3 ) {
					if ( $terms[1] === $url ) {
						wp_die( 'Access denied.' );
						break;
					}
				}
			}
		}
	}

	// TODO update the user list to show only the roles they can see.
	// https://stackoverflow.com/questions/14904987/restrict-wordpress-user-to-only-edit-users-with-same-meta-key-value.
	/**
	 *  Validates the user's profile data has permissions to save
	 *
	 * @param array    $errors the list of WP_Errors.
	 * @param bool     $update whether this is a user update.
	 * @param \WP_User $user the user's profile being updated.
	 * @since 1.0.0
	 */
	public static function validate_user_profile_data( $errors, $update, $user ) {
		// first check our current user.
		$active_user = wp_get_current_user();
		$max_role    = static::get_max_role( $active_user );
		$max_role    = static::get_external_label( $max_role );

		// we exit this if we are admin.
		if ( self::ADMIN === $max_role ) {
			return;
		}
		// if we are updating, then we check to make sure the active user has permissions to do so.
		if ( $update ) {
			$obj          = get_user_by( 'id', $user->ID );
			$obj_max_role = static::get_max_role( $obj );
			$obj_max_role = static::get_external_label( $obj_max_role );
			// phpcs:ignore
			// db\Notifier::log($obj, $user->ID, $max_role, $obj_max_role, static::$user_roles_editable);
			// check the list to see if the role has users it can edit.
			if ( $obj_max_role && array_key_exists( $max_role, static::$user_roles_editable ) ) {
				// phpcs:ignore
				// db\Notifier::log('"'.$obj_max_role.'"', static::$user_roles_editable[$max_role], in_array($obj_max_role, static::$user_roles_editable[$max_role], true));
				// if it does, then see if any of them are in the array.
				if ( in_array( $obj_max_role, static::$user_roles_editable[ $max_role ], true ) ) {
					return;
				}
			}
			$errors->add( 'permissions error', __( 'Update Failed: You do not have permissions to edit this users of this role' ) );
		} else {
			// if this is a new user, just set the default role for new created user.
			if ( self::PROCESSOR === $max_role ) {
				$user->role = static::get_internal_slug( self::CUSTOMER );
			}
		}
	}

	/**
	 *  Determines whether a user can edit another user
	 *
	 * @param int $editor_id the id of the user object trying to edit.
	 * @param int $user_id the id of the user object trying to be edited.
	 * @since 1.0.0
	 */
	public static function can_edit( $editor_id, $user_id ) {
		$editor              = get_userdata( $editor_id );
		$editor_max_role_int = static::get_max_role( $editor );
		$editor_max_role     = static::get_external_label( $editor_max_role_int );
		// phpcs:ignore
		// db\Notifier::log($editor_max_role);
		if ( array_key_exists( $editor_max_role, static::$user_roles_editable ) ) {
			$editable_roles = static::$user_roles_editable[ $editor_max_role ];
			// phpcs:ignore
			// db\Notifier::log($editable_roles);

			$user              = get_userdata( $user_id );
			$user_max_role_int = static::get_max_role( $user );
			$user_max_role     = static::get_external_label( $user_max_role_int );
			// phpcs:ignore
			// db\Notifier::log($user_max_role, in_array($user_max_role, $editable_roles, true));
			if ( in_array( $user_max_role, $editable_roles, true ) ) {
				return 1;
			} else {
				return -1;
			}
		}
		return 0; // do nothing.
	}


	/**
	 *  Gets the max role if a user has more than one
	 *  this relies on the correct ordering in Role::ALL_ROLES
	 *  which is least role first
	 *
	 * @param \WP_User $user the user object to get max role of.
	 * @since 1.0.0
	 */
	public static function get_max_role( $user ) {
		$max_role     = null;
		$max_role_idx = -1;
		if ( $user && ! empty( $user->roles ) && is_array( $user->roles ) ) {
			foreach ( $user->roles as $role ) {
				// phpcs:ignore
				// db\Notifier::log($role);
				$count = count( static::ALL_ROLES );
				for ( $i = 0; $i < $count; $i++ ) {
					if ( static::get_internal_slug( static::ALL_ROLES[ $i ] ) === $role && $i > $max_role_idx ) {
						$max_role_idx = $i;
						$max_role     = $role;
						break;
					}
				}
			}
		}
		return $max_role;
	}

	/**
	 * Controls what the role drop down has as options.
	 *
	 * @param array $editable_roles array of roles that are editable.
	 * @since 1.0.0
	 */
	public static function restrict_role_selection( $editable_roles ) {
		// we only apply this is promote_users capability is present.
		// phpcs:ignore
		// db\Notifier::log('editable roles', $editable_roles);
		if ( current_user_can( 'promote_users' ) ) {
			$user          = wp_get_current_user();
			$max_role_int  = static::get_max_role( $user );
			$max_role      = static::get_external_label( $max_role_int );
			$invalid_roles = array();
			if ( self::ADMIN !== $max_role ) {
				$invalid_roles = static::get_roles_equal_to_or_above( $max_role );
				// phpcs:ignore
				// db\Notifier::log('invalid roles', $invalid_roles);
			}

			// add back missing roles.
			if ( array_key_exists( $max_role, static::$user_roles_editable ) ) {
				// phpcs:ignore
				// db\Notifier::log($max_role, static::$user_roles_editable);

				foreach ( static::$user_roles_editable[ $max_role ] as $add_role ) {
					// phpcs:ignore
					// db\Notifier::log($add_role);
					$add_role_int = static::get_internal_slug( $add_role );
					if ( ! in_array( $add_role_int, $editable_roles, true ) ) {
						$editable_roles[ $add_role_int ] = array(
							'name'         => $add_role,
							'capabilities' => array( 'read' => 1 ),
						);
					}
				}
			}

			// remove the invalid keys.
			foreach ( $invalid_roles as $invalid_role ) {
				foreach ( $editable_roles as $role => $data ) {
					if ( $data['name'] === $invalid_role ) {
						// phpcs:ignore
						// db\Notifier::log('removing:', $data['name'], $invalid_role);
						unset( $editable_roles[ $role ] );
					}
				}
			}
		}

		return $editable_roles;
	}


	/**
	 * Function to get the valid roles >= the role given in the parameter
	 *
	 * @param str $display_role the capitalized, spaced version of the role name.
	 * @since 1.0.0
	 */
	public static function get_roles_equal_to_or_above( $display_role ) {
		$len = count( static::ALL_ROLES );
		$i   = 0;
		while ( $i < $len ) {
			if ( static::ALL_ROLES[ $i ] === $display_role ) {
				break;
			}
			$i++;
		}
		return array_slice( static::ALL_ROLES, $i );
	}

	/**
	 * Function to check the minimum role needed
	 *
	 * @param str      $display_role the capitalized, spaced version of the role name.
	 * @param \WP_User $user optional user object.
	 * @since 1.0.0
	 */
	public static function validate_min_role( $display_role, $user = null ) {
		$roles = static::get_roles_equal_to_or_above( $display_role );
		return static::validate_roles( $roles, $user );
	}

	/**
	 * Function to check if a user has one or more of the inputted roles
	 *
	 * @param array    $display_roles the capitalized, spaced version of the role name.
	 * @param \WP_User $user optional user object.
	 * @since 1.0.0
	 */
	public static function validate_roles( $display_roles, $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		}
		$found = false;
		foreach ( $display_roles as $display_role ) {
			$role = static::get_internal_slug( $display_role );
			foreach ( $user->roles as $given_role ) {
				// we have to use strtolower because to WP,
				// "Group-name" == "group-name".
				if ( strtolower( $given_role ) === $role ) {
					$found = true;
					break;
				}
			}
		}

		return $found;
	}

	/**
	 * Removes capabilities that were added by the plugin
	 *
	 * @since 1.0.0
	 */
	public static function remove_special_capabilities() {
		// iterate through the roles and create them.
		foreach ( static::$user_roles as $display_role => $capabilities ) {
			$role_name = static::get_internal_slug( $display_role );
			$role      = get_role( $role_name );
			if ( $role ) {
				// if the role exists, update it.
				foreach ( $capabilities as $key => $value ) {
					if ( $value ) {
						$role->remove_cap( $key );
					} else {
						$role->add_cap( $key );
					}
				}
			}
		}
	}

	/**
	 * Adds roles to WP and if they exist, will send error messages
	 *
	 * @param bool $notify whether to send an admin notice to user.
	 * @since 1.0.0
	 */
	public static function add_and_validate_roles( $notify = false ) {
		$wp_roles = wp_roles();
		$notifier = null;
		if ( $notify ) {
			$notifier = db\Notifier::get_instance();
		}

		// iterate through the roles and create them.
		foreach ( static::$user_roles as $display_role => $capabilities ) {
			$role_name = static::get_internal_slug( $display_role );
			if ( ! $wp_roles->is_role( $role_name ) ) {
				// if the role doesn't exist, create it.
				add_role( $role_name, $display_role, $capabilities );
			} else {
				$role = get_role( $role_name );
				// if the role exists, update it.
				foreach ( $capabilities as $key => $value ) {
					if ( $value ) {
						$role->add_cap( $key );
					} else {
						$role->remove_cap( $key );
					}
				}
			}
		}

		return 1;
	}


	/**
	 * Adds roles to WP and if they exist, will send error messages
	 *
	 * @param str $display_role the role name (display version).
	 * @since 1.0.0
	 */
	public static function get_menus_as_role( $display_role ) {
		// Get the original user's ID and username.
		$original_user_id = get_current_user_id();

		$role_name = self::get_internal_slug( $display_role );

		$user_data = array(
			'user_login' => db\MODULE . '_tempuser',
			'user_pass'  => db\MODULE . '_tempP@$$',
			'user_email' => db\MODULE . '_tempuser@digitalbodhi.io',
			'role'       => $role_name, // Set the role of the temp user.
		);

		// Insert the user into the WordPress user database.
		$user_id = wp_insert_user( $user_data );

		if ( ! is_wp_error( $user_id ) ) {
			$user = get_user_by( 'id', $user_id );
			// phpcs:ignore
			// db\Notifier::log('user_id: ', $user_id, ', roles: ', $user->roles);

		} else {
			$user = get_user_by( 'email', $user_data['user_email'] );

			if ( $user ) {
				$user->set_role( $role_name );
				$user_id = $user->ID;
				// phpcs:ignore
				// db\Notifier::log('set role for existing user');
			}
		}

		$visible = array();
		if ( $user_id ) {
			// Set the current user to the temporary user.
			wp_set_current_user( $user_id );
			// phpcs:ignore
			// db\Notifier::log($GLOBALS['menu']);

			foreach ( $GLOBALS['menu'] as $menu ) {

				if ( $menu[0] && current_user_can( $menu[1] ) ) {
					$m_name             = preg_replace(
						'/ <.*$/',
						'',
						$menu[0]
					);
					$m_slug             = $menu[2];
					$visible[ $m_slug ] = array(
						'name'     => $m_name,
						'url'      => $m_slug,
						'submenus' => array(),
					);
					if ( isset( $GLOBALS['submenu'][ $m_slug ] ) ) {
						foreach ( $GLOBALS['submenu'][ $m_slug ] as $submenu ) {
							$sm_name                                    = preg_replace(
								'/ <.*$/',
								'',
								$submenu[0]
							);
							$visible[ $m_slug ]['submenus'][ $sm_name ] = $submenu[2];
						}
					}
				}
			}

			// we need to add back any hidden menus.
			$settings = Settings::get_instance();
			$menus    = $settings->get_setting( 'access', 'role' );
			if ( ! $menus ) {
				$menus = array();
			}
			foreach ( $menus as $menu ) {
				$terms = explode( '|', $menu );
				if ( $terms[0] === $role_name ) {
					// make sure the menu item exists.
					if ( ! isset( $visible[ $terms[1] ] ) ) {
						$visible[ $terms[1] ] = array(
							'name'     => $terms[2],
							'url'      => $terms[1],
							'submenus' => array(),
						);
					}
					if ( count( $terms ) === 5 ) {
						$visible[ $terms[1] ]['submenus'][ $terms[4] ] = $terms[3];
					} else {
						if ( count( $terms ) !== 3 ) {
							db\Notifier::log( 'ERROR: invalid menu item: ' . $menu );
						}
					}
				}
			}

			// Set the current user back to the original user.
			wp_set_current_user( $original_user_id );
		} else {
			db\Notifier::log( 'ERROR: unable to create temp role: ', $user_id );
		}

		// Delete the temp user.
		wp_delete_user( $user_id );

		return $visible;
	}


	/**
	 * Returns the internal slug used for WordPress internals (e.g., statuses, roles)
	 *
	 * @param str $label A display value corresponding to internal name.
	 * @since 1.0.0
	 */
	public static function get_internal_slug( $label ) {
		return str_replace( ' ', '_', strtolower( $label ) );
	}

	/**
	 * Returns the external slug used for WordPress internals (e.g., statuses, roles)
	 *
	 * @param str $slug A display value corresponding to external name.
	 * @since 1.0.0
	 */
	public static function get_external_label( $slug ) {
		return str_replace( '_', ' ', ucfirst( $slug ) );
	}

}
