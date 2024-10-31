<?php
/**
 * Manages settings and key constants used throughout the plugin
 *
 * @package DigitalBodhi
 */

namespace DigitalBodhi\PermissionsPlus;

use DigitalBodhi\PermissionsPlus as db;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require_once BASE_DIR . 'class-notifier.php';
require_once BASE_DIR . 'class-roles.php';

/**
 *  Sets up the plugin settings, and rendering of settings
 *  Also contains key constants
 *
 * @since 1.0.0
 */
class Settings {

	const SECTIONS = array(
		'access'   => array(
			'display'    => 'Admin Menu Access',
			'desc'       => 'Enables or disables access to specific WP/WC admin menus',
			'admin_only' => true,
			'fields'     => array(
				array(
					'name'    => 'role',
					'display' => 'Select menus to hide for a given role',
					'default' => '',
					'class'   => 'role_select',
					'help'    => 'Choose a role and select the menu items to restrict access to',
					'presave' => 'save_role_select',
				),
			),
		),
		'advanced' => array(
			'display'    => 'Advanced Settings',
			'desc'       => 'Advanced settings for additional configuration',
			'admin_only' => true,
			'fields'     => array(
				array(
					'name'    => 'first_run',
					'display' => 'Initial Settings Configured? (DO NOT REMOVE)',
					'default' => '1',
					'class'   => 'hidden',
				),
			),
		),
	);

	// this should correspond to the SECTIONS name and fields name
	// joined with an underscore.
	const FIRST_RUN_SECTION = 'advanced';
	const FIRST_RUN_FIELD   = 'first_run';

	/**
	 *  Caching fetches for settings
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $cached_settings = array();

	/**
	 *  Flag indicating whether settings has been loaded
	 *  if false, then goes to DB to load
	 *
	 * @var bool
	 * @since 1.0.0
	 */
	private $loaded;

	/**
	 *  Singleton
	 *
	 * @var Settings
	 * @since 1.0.0
	 */
	private static $instance = null;


	/**
	 * Function to ensure singleton nature of class
	 *
	 * @since 1.0.0
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new Settings();
		}
		return self::$instance;
	}

	/**
	 * Sets the flag indicating that this case has been loaded.
	 * This is called when FIRST_RUN setting flag has been set.
	 *
	 * @since 1.0.0
	 */
	public function loaded() {
		return $this->loaded;
	}

	/**
	 * Dummy constructor
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		// this is loaded in the "meta" section of the settings.
		$this->loaded = get_option(
			db\MODULE . '_' .
									self::FIRST_RUN_SECTION . '_' .
			self::FIRST_RUN_FIELD
		);
	}

	/**
	 *  Retrieve the raw setting using the cache
	 *
	 * @param str $section the name of the section that this field belongs.
	 * @param str $field the name of the field for the setting.
	 * @since 1.0.0
	 */
	public function get_setting( $section, $field ) {
		if ( ! $this->loaded ) {
			db\Notifier::log(
				'WARNING: settings "first run" is missing. ' .
				'Settings not retrieved for: ' .
				$section . '|' . $field
			);
			return null;
		}
		if ( isset( $this->cached_settings[ $section . $field ] ) ) {
			return $this->cached_settings[ $section . $field ];
		} else {
			$ret                                        = get_option( db\MODULE . '_' . $section . '_' . $field );
			$this->cached_settings[ $section . $field ] = $ret;
			return $ret;
		}
	}

	/**
	 *  Save the raw setting and update the cache
	 *
	 * @param str $section the name of the section that this field belongs.
	 * @param str $field the name of the field for the setting.
	 * @param str $value the value of the field for the setting.
	 * @since 1.0.0
	 */
	public function save_setting( $section, $field, $value ) {
		$ret                                        = update_option( db\MODULE . '_' . $section . '_' . $field, $value );
		$this->cached_settings[ $section . $field ] = $value;
	}

	/**
	 *  Deletes all settings created; we use the prefix since sometimes
	 *  we change the settings names.
	 *
	 * @since 1.0.0
	 */
	public static function delete_all_settings() {
		global $wpdb;
		// Get all option names with the specified prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$option_names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", db\MODULE . '_%' ) );

		// Delete each option.
		foreach ( $option_names as $option_name ) {
			delete_option( $option_name );
		}
	}

	/**
	 *  Renders the html to edit settings in the admin area.
	 *  Nonce should be checked prior to executing this function.
	 *
	 * @since 1.0.0
	 */
	public static function render() {
		// validate the user can view this.
		$user = Manager::check_security( Roles::PROCESSOR );
		if ( ! $user || ! Manager::check_nonce() ) {
			return;
		}

		// check if the user have submitted the settings. WordPress will add the "settings-updated" $_GET parameter to the url.
		$setting_slug = db\MODULE . '_messages';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['settings-updated'] ) ) {
			// add settings saved message with the class of "updated".
			add_settings_error(
				$setting_slug,
				esc_attr( $setting_slug ),
				__( 'Settings Saved', 'digitalbodhi' ),
				'updated'
			);
		}

		settings_errors( $setting_slug );

		echo( '<div class="wrap"><h4>' . esc_html( get_admin_page_title() ) .
			'</h4><form action="options.php" method="post">' );
		wp_nonce_field();
		// output security fields for the registered setting.
		settings_fields( db\MODULE );
		// output setting sections and their fields.
		// sections are registered for the module.
		// each field is registered to a specific section).
		do_settings_sections( db\MODULE );
		// output save settings button.
		submit_button( __( 'Save Settings', 'digitalbodhi' ) );
		echo( '</form></div>' );
	}

	/**
	 *  Registers the settings with WordPress hooks
	 *  Called by the Manager class.
	 *
	 * @since 1.0.0
	 */
	public function register() {

		if ( ! Roles::validate_min_role( Roles::PROCESSOR ) ) {
			return;
		}

		$is_admin = Roles::validate_min_role( Roles::ADMIN );

		// this allows normal users to be able to view/edit these settings.
		if ( ! $is_admin ) {
			add_filter(
				'option_page_capability_' . db\MODULE,
				array( get_called_class(), 'enable_nonadmin_cb' )
			);
		}

		foreach ( $this::SECTIONS as $section_name => $section ) {
			if ( $section['admin_only'] && ! $is_admin ) {
				// don't display admin sections if this isn't an admin user.
				continue;
			}
			$this->add_section(
				$section_name,
				$section['display'],
				$section['desc']
			);
			foreach ( $section['fields'] as $field ) {
				$class = 'normal';
				if ( isset( $field['class'] ) ) {
					$class = $field['class'];
				}
				$help = '';
				if ( isset( $field['help'] ) ) {
					$help = $field['help'];
				}
				$options = array();
				if ( isset( $field['options'] ) ) {
					$options = $field['options'];
				}
				$callback = null;
				if ( isset( $field['presave'] ) ) {
					$callback = $field['presave'];
				}
				$this->add_field(
					$section_name,
					$field['name'],
					$field['display'],
					$field['default'],
					$class,
					$help,
					$options,
					$callback,
				);
			}
		}
	}

	/**
	 *  Callback to allow normal users to edit/view settings
	 */
	public static function enable_nonadmin_cb() {
		return 'edit_pages';
	}

	/**
	 *  Helper function for register() to add each section
	 *
	 * @param str $name internal name of section.
	 * @param str $display display name for section.
	 * @param str $desc description for section.
	 * @since 1.0.0
	 */
	private function add_section( $name, $display, $desc ) {
		$id                    = db\MODULE . '_' . $name;
		$this->sections[ $id ] = $desc;
		add_settings_section(
			$id,
			$display,
			array( $this, 'render_section' ),
			db\MODULE
		);
	}

	/**
	 *  Renders a section and associated description
	 *
	 * @param array $args list given in the add_settings_section param 3.
	 * @since 1.0.0
	 */
	public function render_section( array $args ) {
		echo( '<p>' );
		echo( esc_html( $this->sections[ $args['id'] ] ) );
		echo( '</p>' );
	}

	/**
	 *  Helper function for register() to add each section field
	 *
	 * @param str $section internal name of section.
	 * @param str $name internal name of field.
	 * @param str $display display name for field.
	 * @param str $default default value for field.
	 * @param str $class type of input element (not HTML class).
	 * @param str $help text to display to guide user.
	 * @param str $options options (choices) for a select control.
	 * @param str $callback function to call to customize validation/save.
	 * @since 1.0.0
	 */
	private function add_field( $section, $name, $display, $default,
								$class, $help, $options, $callback ) {
		$section = db\MODULE . '_' . $section;
		$id      = $section . '_' . $name;

		if ( $callback ) {
			register_setting(
				db\MODULE,
				$id,
				array(
					'sanitize_callback' =>
					array( get_called_class(), $callback ),
				)
			);
		} else {
			register_setting( db\MODULE, $id );
		}
		// add the help.
		if ( ! empty( $help ) ) {
			$help = wc_help_tip( $help );
		}

		add_settings_field(
			$id,
			$display . $help,
			array( $this, 'render_field' ),
			db\MODULE,
			$section,
			array(
				'field'   => $id,
				'default' => $default,
				'class'   => $class,
				'options' => $options,
			)
		);
	}

	/**
	 *  Renders a field and associated description
	 *
	 * @param array $args list given in the add_settings_field param 6.
	 * @throws Exception When there is an unknown class passed.
	 * @since 1.0.0
	 */
	public function render_field( array $args ) {
		$setting = get_option( $args['field'] );
		$value   = ( $setting && '' !== $setting ) ? $setting : $args['default'];
		// phpcs:ignore
		// db\Notifier::log($args['field'], $setting, $value);

		$class = $args['class'];
		if ( 'normal' === $class ) {
			echo( "<input type='text' id='" . esc_attr( $args['field'] ) . "' name='" . esc_attr( $args['field'] ) . "' value='" . esc_attr( $value ) . "'>" );
		} elseif ( 'select' === $class ) {
			echo( "<select id='" . esc_attr( $args['field'] ) . "' name='" . esc_attr( $args['field'] ) . "'>" );
			foreach ( $args['options'] as $k => $v ) {
				echo( '<option value="' . esc_attr( $v ) . '" ' . selected( $v, $value, false ) . '>' . esc_html( $k ) . '</option>' );
			}
			echo( '</select>' );
		} elseif ( 'hidden' === $class ) {
			echo( "<input type='text' id='" . esc_attr( $args['field'] ) . "' name='" . esc_attr( $args['field'] ) . "' value='" . esc_attr( $value ) . "'>" );
		} elseif ( 'js_button' === $class ) {
			echo( "<input type='button' id='" . esc_attr( $args['field'] ) . "' value='execute' onclick='" . esc_attr( db\MODULE ) . '_ajax.' . esc_attr( $value ) . "'>" );
		} elseif ( 'product_select' === $class ) {
			static::render_product_select( $args['field'], $value );
		} elseif ( 'role_select' === $class ) {
			static::render_role_select( $args['field'], $value );
		} else {
			throw new Exception( 'unknown input class' );
		}
	}

	/**
	 * Renders a select control that autocompletes products
	 *
	 * @param str $field The field being rendered.
	 * @param str $value The value of the field.
	 * @since 1.0.0
	 **/
	private function render_product_select( $field, $value ) {
		echo( '<select class="wc-product-search" multiple="multiple" style="width: 50%;" name="' . esc_attr( $field ) . '[]" data-placeholder="Search for a product…" data-action="woocommerce_json_search_products_and_variations" >' );
		if ( '' === $value ) {
			$value = array();
		}
		$hidden = array();
		foreach ( $value as $product_id ) {
			$product = wc_get_product( $product_id );
			echo( '<option value="' . esc_attr( $product->get_id() ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>' );
			if ( $product->get_catalog_visibility() !== 'visible' ) {
				$hidden[] = $product->get_formatted_name();
			}
			if ( $product->get_status() !== 'publish' ) {
				$hidden[] = $product->get_formatted_name();
			}
		}
		echo( '</select>' );
		if ( count( $hidden ) > 0 ) {
			echo( '<div style="color:red;"><strong>Warning</strong>: the following items are not viewable by customers: <ul class="normalul">' );
			foreach ( $hidden as $prod ) {
				echo( '<li>' . esc_html( $prod ) . '</li>' );
			}
			echo( '</ul>' );
		}
	}

	/**
	 * Callback to interrupt the saving of a role
	 *
	 * @param array $args The args passed to save.
	 * @since 1.0.0
	 **/
	public static function save_role_select( $args ) {
		// db\Notifier::log('called', $args);
		// TODO: we could create a duplicate lookup array here.
		// this would make check_screen faster.
		return $args;
	}

	/**
	 * Renders a select control and checkboxes for hiding menu items.
	 *
	 * @param str $field The field being rendered.
	 * @param str $value The value of the field.
	 * @since 1.0.0
	 **/
	private function render_role_select( $field, $value ) {
		echo( '<select id="selected-role" class="select" >' );
		echo( '<option value="" selected></option>' );
		foreach ( Roles::ALL_ROLES as $display_role ) {
			// we remove admin since if anything is hidden for admin.
			// it hides for all users.
			if ( Roles::ADMIN === $display_role || Roles::CUSTOMER === $display_role ) {
				continue;
			}
			$int_role = Roles::get_internal_slug( $display_role );
			echo( '<option value="' . esc_attr( $int_role ) . '">' . esc_html( $display_role ) . '</option>' );
		}
		echo( '</select><br><br>' );

		$values = array();
		if ( $value ) {
			foreach ( $value as $val ) {
				$values[ $val ] = true;
			}
		}
		// phpcs:ignore
		// db\Notifier::log($field, $values);

		foreach ( Roles::ALL_ROLES as $display_role ) {
			// we remove admin since if anything is hidden for admin.
			// it hides for all users.
			if ( Roles::ADMIN === $display_role || Roles::CUSTOMER === $display_role ) {
				continue;
			}
			$visible  = Roles::get_menus_as_role( $display_role );
			$int_role = Roles::get_internal_slug( $display_role );
			// phpcs:ignore
			// db\Notifier::log($visible);

			echo( '<ul id="' . esc_attr( $int_role ) . '-role-menu"  style="padding-left:5px;" class="role-menus">' );
			foreach ( $visible as $slug => $data ) {
				$name     = $data['name'];
				$murl     = $data['url'];
				$submenus = $data['submenus'];
				$val      = $int_role . '|' . $murl . '|' . $name;

				$disabled = '';
				if ( Roles::ADMIN === $display_role && 'woocommerce' === $murl ) {
					$disabled = ' disabled';
				}

				echo( '<li><label><input type="checkbox" name="' . esc_attr( $field ) . '[]" value="' . esc_attr( $val ) . '"' );
				if ( isset( $values[ $val ] ) ) {
					echo( ' checked' );
				}
				echo( esc_attr( $disabled ) . '>' . esc_html( $name ) . '</label>' );
				if ( count( $submenus ) > 0 ) {
					echo( '<ul style="padding-left:20px;">' );
					foreach ( $submenus as $submenu => $url ) {
						$sval     = $val . '|' . $url . '|' . $submenu;
						$disabled = '';
						if ( Roles::ADMIN === $display_role && db\DISPLAY_NAME === $submenu ) {
							$disabled = ' disabled';
						}

						echo( '<li><label><input type="checkbox" name="' . esc_attr( $field ) . '[]" value="' . esc_attr( $sval ) . '"' );
						if ( isset( $values[ $sval ] ) ) {
							echo( ' checked' );
						}
						echo( esc_attr( $disabled ) . '>' . esc_html( $submenu ) . '</label></li>' );
					}
					echo( '</ul>' );
				}
				echo( '</li>' );
			}
			echo( '</ul>' );
		}
	}
}
