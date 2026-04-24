<?php
namespace SIM\HTMLEMAIL;
use SIM;

add_action( 'rest_api_init',  __NAMESPACE__.'\restApiInit');
function restApiInit() {
	//Route for e-mail tracking of today
	register_rest_route(
		RESTAPIPREFIX,
		'/mailtracker',
		array(
			'methods' 				=> 'GET',
			'callback' 				=> __NAMESPACE__.'\mailTracker',
			'permission_callback' 	=> '__return_true',
		)
	);

	//Route for e-mail tracking of today
	register_rest_route(
		RESTAPIPREFIX,
		'/mailfailed',
		array(
			'methods' 				=> \WP_REST_Server::ALLMETHODS,
			'callback' 				=> __NAMESPACE__.'\mailTracking',
			'permission_callback' 	=> '__return_true',
		)
	);
}

function mailTracking($wpRestRequest){
	//SIM\printArray($wpRestRequest->get_params());
	return $wpRestRequest->get_params();
}

// Make mailtracker rest api url publicy available
add_filter('sim_allowed_rest_api_urls', __NAMESPACE__.'\allowedRestApiUrls');
function allowedRestApiUrls($urls){
	$urls[]	= RESTAPIPREFIX.'/mailtracker';
	$urls[]	= RESTAPIPREFIX.'/mailfailed';

	return $urls;
}

/**
 * Tracks if an e-mail is opened or not using an image with a url
 */
function mailTracker(\WP_REST_Request $request) {
	global $wpdb;

	$mailId		= $request->get_param('mailid');
	$url		= strval(urldecode($request->get_param('url')));

	// Store mail open or link clicked in db
	if(is_numeric($mailId)){
		if(empty($url)){
			$type	= 'mail-opened';
		}else{
			$type	= 'link-clicked';
		}

		$html     = new HtmlEmail();

		// Add e-mail to e-mails db
		$wpdb->insert(
			$html->mailEventTable,
			array(
				'email_id'		=> $mailId,
				'type'			=> $type,
				'time'			=> current_time('U'),
				'url'			=> str_replace(SITEURL, '', $url)
			)
		);

		if($wpdb->last_error !== ''){
			SIM\printArray($wpdb->last_error);
		}
	}

	if(empty($url)){
		// redirect to picture
		$url = SIM\pathToUrl(MODULE_PATH.'pictures/transparent.png').'?ver='.time();
	}

	wp_redirect( $url );
	exit();
}