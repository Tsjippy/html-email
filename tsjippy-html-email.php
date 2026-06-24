<?php

namespace TSJIPPY\HTMLEMAIL;

use TSJIPPY;

/**
 * Plugin Name:          Tsjippy HTML E-mail
 * Description:          This plugin will place all e-mails sent in a nice format.<br>It will also add a warning to the bottom of the e-mail about it being an automated e-mail.<br>If there is no complementary close in the e-mail it will add oneIt will also monitor how often an e-mail is opened.<br>
 * Version:              10.3.4
 * Author:               Ewald Harmsen
 * AuthorURI:            harmseninnigeria.nl
 * Requires at least:    6.3
 * Requires PHP:         8.3
 * Tested up to:         6.9
 * Plugin URI:            https://github.com/Tsjippy/htmlemail/
 * Tested:                6.9
 * TextDomain:            tsjippy
 * Requires Plugins:    
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @author Ewald Harmsen
 */
if (! defined('ABSPATH')) {
    exit;
}



// Define constants
define(__NAMESPACE__ . '\PLUGIN', plugin_basename(__FILE__));
define(__NAMESPACE__ . '\PLUGINPATH', __DIR__ . '/');
define(__NAMESPACE__ . '\PLUGINVERSION', get_plugin_data(__FILE__, false, false)['Version']);
define(__NAMESPACE__ . '\PLUGINSLUG', str_replace('tsjippy-', '', basename(__FILE__, '.php')));
define(__NAMESPACE__ . '\SETTINGS', get_option('tsjippy_htmlemail_settings', []));

// run right before activation
add_action('activated_plugin', function ($plugin) {
    if ($plugin != PLUGIN) {
        return;
    }

    // Create the dbs
    $email     = new HtmlEmail();
    $email->createDbTables();
});

// Load shared code
if(file_exists(__DIR__  . '/shared-functionality/loader.php')){
    require_once(__DIR__  . '/shared-functionality/loader.php');
}