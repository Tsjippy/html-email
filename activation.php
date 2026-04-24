<?php
namespace SIM\HTMLEMAIL;

if ( ! defined( 'ABSPATH' ) ) exit;

// run on activation
add_action( 'activated_plugin', function ( $plugin ) {
    if( $plugin != PLUGIN ) {
        return;
    }

    // Create the dbs
	$email     = new HtmlEmail();
	$email->createDbTables();
} );