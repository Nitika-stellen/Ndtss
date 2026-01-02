=== Database Manager - WP Adminer ===
Contributors: pexlechris
Plugin Name: Database Manager - WP Adminer
Author: Pexle Chris
Author URI: https://www.pexlechris.dev
Tags: Adminer, Database, sql, mysql, mariadb
Version: 4.3.2
Stable tag: 4.3.2
Adminer version: 5.4.1
Requires at least: 4.7.0
Tested up to: 6.9
Requires PHP: 7.0
Tested up to PHP: 8.3
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage the database from your WordPress Dashboard using Adminer.

== Description ==

The best database management tool for the best CMS.

This plugin uses the tool [Adminer](https://www.adminer.org/), in order to give database access to administrators directly from the Dashboard.
As simple as the previous sentence!

I am not the author of Adminer. I am only the author who does the WordPress integration with Adminer.
Author of Adminer is Jakub Vrana and you can donate him from [there](https://www.paypal.com/donate/?item_name=Donation+to+Adminer&cmd=_donations&business=jakub%40vrana.cz).

Compatible also with WordPress Multisite installations


== WP Adminer access positions ==
You can access the WP Adminer from the below positions:
1. WP Adminer URL in the Admin Bar
2. WP Adminer Tools Page (Dashboard > Tools > WP Adminer)

== Explore my other plugins ==
* [Library Viewer](https://www.pexlechris.dev/library-viewer/wp-wpadminer): With Library Viewer, you can display the containing files and the containing folders of a “specific folder” of your (FTP) server to your users in the front-end.
* [Gift Wrapping for WooCommerce](https://wordpress.org/plugins/gift-wrapping-for-woocommerce): This plugin allows customers to select a gift wrapper for their orders, via a checkbox in the checkout page.


== Screenshots ==

1. The WP Adminer opened from Admin Bar
2. The new dropdown admin_bar items

== Frequently Asked Questions ==

 = Is it safe? =
 Yes, because only administrators have access to WP Adminer. If a guest tries to access the WP Adminer URL, a 404 page will be shown up.


 = Who has access to WP Adminer? =
&nbsp;

 * In the case of single site WordPress installations, only Administrators have access in WP Adminer, because by default only administrator have the `manage_options` capability.
 * In the case of WordPress Multisite installations, only Super Admins have access in WP Adminer, because by default only Super Admins have the `manage_network_options` capability.
 * In all cases, the `manage_wp_adminer` capability now (versions **4.3.0** and above) grants access to WP Adminer. You can assign this capability to a role via code or through the User Role Editor plugin.

 = How to allow other capabilities or roles to have access to WP Adminer? =
 Just use the filter `pexlechris_adminer_access_capabilities` and return the array of desired capabilities that you want to have access to WP Adminer.
 For roles, just use the corresponding capabilities, while checking against particular roles in place of a capability is supported in part, this practice is discouraged as it may produce unreliable results.


 = WP Adminer is stuck in an endless loop, constantly refreshing the page without stopping. What is the issue? =
 This issue maybe is due to the caching engine that your browser OR server uses!
 * You can try to whitelist the WP Adminer URL, OR
 * You can change the WP Adminer URL to a URL that is already whitelisted. For example:
 `define( 'PEXLECHRIS_ADMINER_SLUG', 'wp-admin/adminer');`


 = How to add my own JS and/or CSS in adminer head? =
 You need to use action `pexlechris_adminer_head` as follows:
 `
 add_action('pexlechris_adminer_head', function(){
    // Use the appropriate get_nonce() function based on your WP Adminer version
    $nonce = function_exists('Adminer\get_nonce')
        ? Adminer\get_nonce() // For WP Adminer v4.0.0 and newer
        : get_nonce(); // For WP Adminer versions below 4.0.0
    ?>
    <script nonce="<?php echo esc_attr( $nonce )?>"> // get_nonce is an adminer function
       // Place your JS code here
    </script>
    <style>
       /* Place your CSS code here */
    </style>
    <?php
 });
 `

 = How can I change the WP Adminer children items at the admin bar? =
 You can do this using WP filter: `pexlechris_adminer_adminbar_dropdown_items`.
 Filter's PHPDoc:
 `
/**
 * The dropdown items.
 *
 * @param array $dropdown_items{
 *      @type string $name. The table name.
 *      @type string $label. The label of the dropdown item.
 *      @type array $args. The array of extra url parameters. Parameters will not be encoded.
 *                         Developers could avoid defining args in the array
 * }
 */
$dropdown_items = apply_filters('pexlechris_adminer_adminbar_dropdown_items', $dropdown_items);
 `


 = How can I add other Adminer plugins or Adminer extensions? =
 In Adminer's website there is documentation about [Adminer plugins](https://www.adminer.org/en/plugins/) and [Adminer extensions](https://www.adminer.org/en/extension/).
 In order to define function adminer_object() before this plugin define it, you need to define it inside the hook `pexlechris_adminer_before_adminer_loads`.
 More in the phpDoc below:
 `
/**
 * adminer_object can be overridden, in WP action pexlechris_adminer_before_adminer_loads.
 * If a developer want to make his/her own changes (adding plugins, extensions or customizations),
 * it is strongly recommended to include_once the class Pexlechris_Adminer and extend it and
 * make adminer_object function return his/her new class.
 *
 * It is strongly recommended, because Pexlechris_Adminer class contains WordPress/Adminer integration (auto login with WordPress credentials)
 *
 * If a developer want to add just JS and/or CSS in head, he/she can just use the action pexlechris_adminer_head.
 * See plugin's FAQs, for more.
 *
 * @since 2.1.0
 *
 * @link https://www.adminer.org/en/plugins/#use Documentation URL.
 * @link https://www.adminer.org/en/plugins/ Adminer' plugins Documentation URL.
 * @link https://www.adminer.org/en/extension/ Adminer' extensions Documentation URL.
 */
 `


 = Can I limit access to some table/DB ? =
 The answer in this question is complicated.
 The best solution is to create a Database User with the appropriate privileges.
 Maybe you can do it also with WordPress actions.
 Read more in [this support ticket](https://wordpress.org/support/topic/limit-access-to-some-table-db/).


 = How can I access other databases in the same server and same database user? =
 By default, you haven't access to any database other than the site's database. In order to enable access, you need to add the following line code `define('PEXLECHRIS_ADMINER_HAVE_ACCESS_ONLY_IN_WP_DB', false);` in the wp-config.php file.


 = Why is Adminer better than phpMyAdmin? =
 Replace **phpMyAdmin** with **Adminer** and you will get a tidier user interface, better support for MySQL features, higher performance and more security. [See detailed comparison](https://www.adminer.org/en/phpmyadmin/).
 Adminer development priorities are: 1. Security, 2. User experience, 3. Performance, 4. Feature set, 5. Size.

== Installation ==

1. Download the plugin from [Official WP Plugin Repository](https://wordpress.org/plugins/pexlechris-adminer/)
2. Upload Plugin from your WP Dashboard ( Plugins > Add New > Upload Plugin ) the pexlechris-adminer.zip file.
3. Activate the plugin through the 'Plugins' menu in WordPress Dashboard



== Changelog ==
= 4.3.2 =
* [Enhancement]: Minor UI fixes

= 4.3.1 =
* [Enhancement]: Minor UI fixes

= 4.3.0 =
* Tested up to: 6.9
* Tested up to PHP: 8.3
* [Enhancement]: Support added for plain permalinks.
* [Enhancement]: The WP Adminer dropdown links have been added also in WP Adminer Tolls Page as buttons.
* [Enhancement]: In case of an Adminer login error, the system now automatically retries the login up to 3 times (After version 4.0.4, the number of retries had been reduced to 1).
* [Enhancement]: Add `manage_wp_adminer` in capabilities that give access to WP Adminer.
* [Enhancement]: Added polyfill for the deprecated each() function for environments where get_magic_quotes_gpc() exists and returns true.
* [Bug Fix]: Respect PEXLECHRIS_ADMINER_HAVE_ACCESS_ONLY_IN_WP_DB to not show move database button if is this const is set to true.

= 4.2.0 =
* [New]: Update Adminer version to 5.4.1 [See Adminer 5.4.1 Release Notes](https://github.com/vrana/adminer/releases/tag/v5.4.1)
* [New]: Added quick access links at the top-left corner of the Adminer UI (WP Admin, Home).
* [New]: Filter `pexlechris_adminer_sticky_links` introduced in order to filter the above quick access links.
* [Bug Fix]: Fixed issue where Adminer URL would break when `home_url()` contained query parameters (e.g., WPML language parameter: https://example.com?lang=en)

= 4.1.3 =
* Tested up to: 6.8.2
* [Bug Fix]: In rare cases, WP Adminer was loading without its CSS and JS assets. This issue is now resolved by clearing the output buffer before including the Adminer file.
Special thanks to Alexia Kaklamani and [Jakub Vrána](https://wordpress.org/support/users/jakubvrana/) for their help in identifying and fixing the bug.

= 4.1.2 =
* Tested up to: 6.8.1
* [New]: Update Adminer version to 5.3.0 [See Adminer 5.3.0 Release Notes](https://github.com/vrana/adminer/releases/tag/v5.3.0)
* [Enhancement]: A key has been added for permanent login.

= 4.1.1 =
* [Enhancement]: Restore the light mode **red** link color to its original appearance.
* [Enhancement]: Permanent Adminer login enabled!
* [Bug Fix]: When including the `adminer.php` file, always prioritize the plugin's own version instead of accidentally including a root-level `adminer.php` if it exists.


= 4.1.0 =
* [New]: Updated Adminer to version 5.2.1. [See Adminer 5.2.1 Release Notes](https://github.com/vrana/adminer/releases/tag/v5.2.1)
* [New]: Dark Mode Switcher introduced! Click the ☀ icon at the bottom right to toggle Dark Mode.
* [Enhancement]: Contrast issues for icons and fonts in the default Dark Mode theme have been fixed.
* [Enhancement]: Introduced new methods for better code organization and modularization.
* [Removed]: The `pexlechris_adminer_head` object method has been removed.
If you were using it to override styles and scripts, please use the `pexlechris_adminer_head` action hook instead to add your own CSS and JS.

= 4.0.4 =
* [Enhancement]: Better UI to help error reporting when Database login failed.
* [Bug Fix]: Fix `No such file or directory` login error when DB_HOST has been defined with the default MySql/MariaDB port 3306.

= 4.0.3 =
* Tested up to WP 6.8
* [Bug fix]: WP Adminer home URL structure updated — a `=` is now correctly added after the username parameter (e.g., https://example.com/wp-adminer?username=).

= 4.0.2 =
* Bug fix introduced in Adminer 5.2.0 when selecting (exact equal) a value.

= 4.0.1 =
* [New]: Update Adminer version to 5.2.0
* [Enhancement]: Now login form class `pexle_loginForm` is added by new method Pexlechris_Adminer::loginForm()
* [Bug Fix]: In some cases, users with access to WP Adminer (e.g., administrators) would encounter a fatal error when viewing the frontend while the admin bar was loaded.

= 4.0.0 =
* ⚠️ Important for Developers:
  **Adminer 5.1.1** now loads under the `Adminer` namespace.
  If you’ve made any customizations to the plugin, please read carefully:
  > The main class is now `Adminer\Adminer`.
  > If you're extending my class `Pexlechris_Adminer`, everything will continue to work fine!
  > However, if you’ve used `get_nonce()` to include custom JavaScript, you **must** update it to `Adminer\get_nonce()`.
    For more details, check the FAQ: “**How to add my own JS and/or CSS in Adminer head?**”
* [New]: New Adminer version 5.1.1! Update from original [Adminer](https://www.adminer.org/en/).
  The previously used AdminerEVO fork is no longer maintained and has been discontinued. It has now been replaced with the default Adminer (v5.1.1).
* [New]: New function `get_pexlechris_adminer_url()`. Can be filtered by new below hook:
* [New]: New filter `pexlechris_adminer_url` that change the returned value of function `get_pexlechris_adminer_url()`.
* [New]: New dropdown choices under WP Adminer admin topbar URL. Can be filtered by bellow hook:
* [New]: New filter `pexlechris_adminer_adminbar_dropdown_items` in order to change the dropdown items.
* [Enhancement]: More user-friendly mobile UI.
* [Enhancement]: Adminer UI customizations, select links at the left sidebar are back as icons.
* [Enhancement]: Admin topbar URL via function `get_pexlechris_adminer_url()`. Can be filtered by new hook `pexlechris_adminer_url`.
* [Enhancement]: The plugin now respects the `pexlechris_adminer_mu_plugin_version` option even after plugin deactivation. If a developer sets this value to `0` or an empty string, the must-use plugin will no longer be automatically created or updated — including during deactivation, which was previously not prevented.
* [Enhancement]: Translation pot file updated with new strings.

= 3.1.2 =
* Tested up to WP 6.7.2
* Now Requires PHP 7.0
* [New]: The `pexlechris_adminer_access_capabilities` filter can now return the capability as a string. Returning an array with the capability is no longer required.
* [Bug Fix]: In some cases, when the WP Adminer's must-use plugin is executed, the SECURE_AUTH_COOKIE cookie is not set, causing a fatal error when accessing WP Adminer. Now fixed!

 = 3.1.1 =
* Bug Fix: Resolved an issue where the feature for passing the WordPress user's language setting (locale) to WP Adminer was not working correctly.

 = 3.1.0 =
* Enhancement: The plugin now sends the WordPress user's language setting (locale) to WP Adminer, but only if Adminer supports that language. Hook introduced: `pexlechris_adminer_locale`

 = 3.0.3.1 =
* New plugin name: **Database Manager - WP Adminer**

 = 3.0.3 =
* Fix bug that produced when the plugin has been deactivated but the relevant MU plugin has not been deleted. Thanks [peopleinside](https://wordpress.org/support/users/peopleinside/) for reporting

 = 3.0.2 =
* Change name from `Database Management tool - Adminer` to `Adminer for WP - The Database Management tool`
* Add an `Open WP Adminer` link in plugin's action links
* Improved Deactivation Behavior: Deactivating the plugin now removes the `pexlechris_adminer_mu_plugin_version option`, ensuring the MU plugin is reinstalled upon reactivation.

 = 3.0.1 =
* Fixes the bug introduced in version 3.0.0, where a disabled plugin named **pexlechris_adminer_avoid_conflicts_with_other_plugins.php** was displayed in the list of plugins.

 = 3.0.0 =
* New Adminer Version Included: Updated to 4.8.4, forked from the original Adminer 4.8.1 due to lack of maintenance for over two years.
  Learn more about the fork and updates at [AdminerEVO Website](https://docs.adminerevo.org/#history).
* Tested up to PHP: 8.2
* Tested up to WP: 6.7.1
* From version 2.2.0 there is a must-use plugin, that disables on the fly all other plugins to avoid conflicts.
    From now and then the version and the mu plugins updates is controlled by option pexlechris_adminer_mu_plugin_version.
    You can delete the option to reinstall it, or set option to 0 to ignore version updates forever
* Fix load_plugin_textdomain file path and current language
* Fix Notice "Function _load_textdomain_just_in_time was called incorrectly" in WP 6.7.0 and above
* Filter pexlechris_adminer_access_capabilities can be used only in a must-use plugin!
* Fixed deprecated notices in non-standard environments or command-line scripts for server variables when using PHP 8.2.
* Fix errors that occur in some cases, when a user tries to load Adminer without being logged in.

 = 2.2.2 =
* Before version 2.2, if PEXLECHRIS_ADMINER_SLUG ends with a slash, WP Adminer was not working.

 = 2.2.1 =
* From now on, this plugin requires WordPress version at least 4.7.0 or later. According to Wordfence, versions below 4.7.0 have a vulnerability that can allow site takeover.

 = 2.2.0 =
* Tested up to 6.4.2
* SOS: From now when WP Adminer runs, only WP Adminer plugin is running (a must-use plugin is automatically installed and is being deleted on plugin deactivation).
So the only way to extend WP Adminer plugin's functionalities using wp hooks is using a must-use plugin.
Helpful Guide: [How to add PHP Hooks in your WordPress Site using a must-use plugin](https://www.pexlechris.dev/how-to-add-php-hooks-in-your-wordpress-site/)
* Hide php errors even if WP_DEBUG_DISPLAY is enabled, in action pexlechris_adminer_before_adminer_loads with priority 100

 = 2.1.1 =
* Tested up to 6.3.2
* From now on, the PEXLECHRIS_ADMINER_SLUG can contain slashes. For example, you can use as below
`define( 'PEXLECHRIS_ADMINER_SLUG', 'wp-admin/wp-adminer');`
* Load textdomain before WP Adminer loads
* Hide php errors even if WP_DEBUG_DISPLAY is enabled, AFTER action pexlechris_adminer_before_adminer_loads
* FAQ on how to fix WP Adminer endless loop has been added.

 = 2.1.0 =
* Tested up to 6.1
* Code Refactoring
* Hide php errors even if WP_DEBUG_DISPLAY is enabled
* Fix Adminer warning `Undefined variable $Ah`
* FAQ on how to add your CSS & JS code in adminer interface has been added.
* FAQ on how to customize adminer has been added.
* FAQ on how to limit access to some table/DB has been added.

 = 2.0.1 =
*   Adminer is an admin tool, so now is considered as admin interface. is_admin() function now return true, when Adminer is viewed

 = 2.0.0 =
*   Tested up to 6.0.1
*   PLEASE UPDATE NOW! Vulnerability issue with password as plain text fixed.
*   SOS! All functions and actions have been renamed. Please have a look in the code to find the new names, if you have written your own customization code before
*   Logout button have been hidden.
*   Adminer has been removed from Tools Page because iframes are not allowed in admin pages
*   print_css_inside_wp_adminer_tools_page action has been removed
*   print_js_inside_wp_adminer_tools_page action has been removed
*   print_js_inside_wp_adminer action has been removed
*   print_css_inside_wp_adminer action has been removed
*   From this version and then, developers can make their Adminer' customizations using the function adminer_object
    in the NEW pexlechris_adminer_before_adminer_loads action
    and to print code in head, developers can use the NEW action pexlechris_adminer_head
*	From this version and then, this plugin is also compatible with WordPress Multisite installations
*   From this version and then, you can change the slug of adminer using the constant PEXLECHRIS_ADMINER_SLUG (By default, adminer loads from www.site.com/wp-adminer )
*   From this version and then, you can log in even if the password is empty string (For some local setups)
*   From this version and then, by default you can only show wordpress database (to enable managing of other databases in same server see FAQ)


 = 1.0.0 =
*	Initial Release.
