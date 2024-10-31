=== Permissions Plus ===
Contributors: digbodhi
Donate link: https://digitalbodhi.io/
Tags: permissions,roles,custom,menus,admin
Requires at least: 4.4
Tested up to: 6.2
Stable tag: 1.0.0
Requires PHP: 7.2
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A plugin to manage access of the menus in wordpress/woocommerce admin view by role.  This also allows non-admin roles to create lesser users.

== Description ==

Currently the plugin recognizes four roles, in order of seniority:

    * Administrator - the built-in wordpress admin role
    * Shop manager - the built-in woocommerce shop manager role
    * Processor - a role created by this plugin that is meant to be a lesser shop manager role
    * Customer - the built-in role that doesn't have access to the admin views

As a result, the plugin does the following:

    * Enables Shop manager to add and edit users of the Customer role 
    * Enables Processor to add and edit users of the Customer role 
    * Enables Administrator to control which wp-admin menus the Shop manager or Processor can see

== Screenshots ==

1. Config screen to hide/show menus

== Install/Config ==

    * Install the plugin
    * Go to WooCommerce > Permissions Plus > Settings
    * Select the dropdown role you wish to edit (admin is blocked for safety purpose)
    * Select the menu checkboxes you wish to hide based on selected role
    * Hit Save

== Frequently Asked Questions ==

= Will you allow the ability to specify custom role groups? = 

Yes, potentially in a later version

== Changelog ==

= 1.0.0 =
* Initial version

== Upgrade Notice ==

= 1.0.0 =
No notices at this time!

