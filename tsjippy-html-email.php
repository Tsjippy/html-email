<?php
namespace TSJIPPY\HTMLEMAIL;
use TSJIPPY;

/**
 * Plugin Name:  		Tsjippy HTML E-mail
 * Description:  		This plugin will place all e-mails sent in a nice format.<br>It will also add a warning to the bottom of the e-mail about it being an automated e-mail.<br>If there is no complementary close in the e-mail it will add oneIt will also monitor how often an e-mail is opened.<br>
 * Version:      		10.0.0
 * Author:       		Ewald Harmsen
 * AuthorURI:			harmseninnigeria.nl
 * Requires at least:	6.3
 * Requires PHP: 		8.3
 * Tested up to: 		6.9
 * Plugin URI:			https://github.com/Tsjippy/htmlemail/
 * Tested:				6.9
 * TextDomain:			tsjippy
 * Requires Plugins:	tsjippy-shared-functionality
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @author Ewald Harmsen
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pluginData = get_plugin_data(__FILE__, false, false);

// Define constants
define(__NAMESPACE__ .'\PLUGIN', plugin_basename(__FILE__));
define(__NAMESPACE__ .'\PLUGINPATH', __DIR__.'/');
define(__NAMESPACE__ .'\PLUGINVERSION', $pluginData['Version']);
define(__NAMESPACE__ .'\PLUGINSLUG', str_replace('tsjippy-', '', basename(__FILE__, '.php')));
define(__NAMESPACE__ .'\SETTINGS', get_option('tsjippy_htmlemail_settings', []));

// run on activation
add_action( 'activated_plugin', function ( $plugin ) {
    if( $plugin != PLUGIN ) {
        return;
    }

    // Create the dbs
	$email     = new HtmlEmail();
	$email->createDbTables();
} );

add_action( 'activated_plugin', function($plugin){
	// Redirect to settings page after plugin activation
    if($plugin == PLUGIN && wp_safe_redirect( esc_url(admin_url('admin.php?page=tsjippy-'.PLUGINSLUG) )  ) ){
		exit();
	}
});