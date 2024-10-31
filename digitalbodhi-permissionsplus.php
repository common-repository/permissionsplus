<?php
/**
 * Plugin Name: PermissionsPlus
 * Text Domain: digitalbodhi_permissionsplus
 * Description: A plugin to manage user roles, and what they can view in the WordPress and woocommerce admin views.
 * Plugin URI: https://digitalbodhi.io/solutions/permissionsplus
 * Version: 1.0.0
 * Author: digitalbodhi
 * Author URI: https://digitalbodhi.io/
 * Developer: info@digitalbodhi.io
 * Developer URI: https://digitalbodhi.io/
 * Domain Path: /languages
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package DigitalBodhi\PermissionsPlus
 */

namespace DigitalBodhi\PermissionsPlus;

use DigitalBodhi\PermissionsPlus as db;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// plugin version.
define( __NAMESPACE__ . '\VERSION', '1.0.0' );
// plugin database version.
define( __NAMESPACE__ . '\DB_VERSION', '1.0.0' );

// plugin module prefix.
define( __NAMESPACE__ . '\MODULE', 'db_pp' );
// plugin display name.
define( __NAMESPACE__ . '\DISPLAY_NAME', 'Permissions Plus' );
// plugin internal name.
define( __NAMESPACE__ . '\INTERNAL_NAME', 'wc-permissionsplus' );
// plugin redirect name.
define( __NAMESPACE__ . '\REDIRECT_NAME', 'permissionsplus' );

// Minimum PHP version required by this plugin.
define( __NAMESPACE__ . '\MINIMUM_PHP_VERSION', '7.2.0' );
// Minimum WordPress version required by this plugin.
define( __NAMESPACE__ . '\MINIMUM_WP_VERSION', '4.4' );
// Minimum WooCommerce version required by this plugin.
define( __NAMESPACE__ . '\MINIMUM_WC_VERSION', '6.5.1' );

// File organization.
define( __NAMESPACE__ . '\BASE_FILE', plugin_basename( __FILE__ ) );
define( __NAMESPACE__ . '\BASE_URL', plugin_dir_url( __FILE__ ) );
define( __NAMESPACE__ . '\ASSET_URL', plugin_dir_url( __FILE__ ) . 'assets/' );
define( __NAMESPACE__ . '\BASE_DIR', plugin_dir_path( __FILE__ ) . 'includes/' );
define( __NAMESPACE__ . '\ADMIN_DIR', BASE_DIR . 'admin/' );
define( __NAMESPACE__ . '\SHARED_DIR', BASE_DIR . 'shared/' );
define( __NAMESPACE__ . '\EXTERN_DIR', BASE_DIR . 'extern/' );

// Plugin Registration.
require_once BASE_DIR . 'class-manager.php';
db\Manager::register();
