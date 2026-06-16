<?php

namespace TSJIPPY\HTMLEMAIL;

use TSJIPPY;

if (! defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init',  __NAMESPACE__ . '\restApiInit');
function restApiInit()
{
    //Route for e-mail tracking of today
    register_rest_route(
        TSJIPPY\RESTAPIPREFIX,
        '/mailtracker',
        array(
            'methods'                 => 'GET',
            'callback'                 => __NAMESPACE__ . '\mailTracker',
            'permission_callback'     => '__return_true',                    // Allow public access
        )
    );

    //Route for e-mail tracking of today
    register_rest_route(
        TSJIPPY\RESTAPIPREFIX,
        '/mailfailed',
        array(
            'methods'                 => \WP_REST_Server::ALLMETHODS,
            'callback'                 => __NAMESPACE__ . '\mailTracking',
            'permission_callback'     => '__return_true',                    // Allow public access, this is just for testing purposes, we can change this later if we want to restrict access to this endpoint
        )
    );
}

/**
 * Tracks e-mail opens and link clicks
 *
 * @param \WP_REST_Request $wpRestRequest    The REST request object containing the parameters for the request
 *
 * @return array    The parameters of the request, this is just for testing purposes, we can change this later to return something more useful if needed
 */
function mailTracking($wpRestRequest)
{
    //TSJIPPY\printArray($wpRestRequest->get_params());
    return $wpRestRequest->get_params();
}

// Make mailtracker rest api url publicy available
add_filter('tsjippy-allowed-rest-api-urls', __NAMESPACE__ . '\allowedRestApiUrls');

/**
 * Adds the mail tracker URLs to the list of allowed REST API URLs
 *
 * @param array $urls    The list of allowed REST API URLs
 *
 * @return array    The updated list of allowed REST API URLs
 */
function allowedRestApiUrls($urls)
{
    $urls[]    = TSJIPPY\RESTAPIPREFIX . '/mailtracker';
    $urls[]    = TSJIPPY\RESTAPIPREFIX . '/mailfailed';

    return $urls;
}

/**
 * Tracks if an e-mail is opened or not using an image with a url
 */
function mailTracker(\WP_REST_Request $request)
{
    global $wpdb;

    $mailId        = $request->get_param('mailid');
    $url        = $request->get_param('url');

    if (!empty($url)) {
        $url        = strval(urldecode($request->get_param('url')));
    }

    // Store mail open or link clicked in db
    if (is_numeric($mailId)) {
        if (empty($url)) {
            $type    = 'mail-opened';
            $url    = '';
        } else {
            $type    = 'link-clicked';
        }

        $html     = new HtmlEmail();

        // Add e-mail to e-mails db
        $wpdb->insert(
            $html->mailEventTable,
            array(
                'email_id' => $mailId,
                'type'     => $type,
                'time'     => current_time('U'),
                'url'      => str_replace(TSJIPPY\SITEURL, '', $url)
            )
        );

        if ($wpdb->last_error !== '') {
            TSJIPPY\printArray($wpdb->last_error);
        }
    }

    if (empty($url)) {
        // redirect to non-existing page
        $url = TSJIPPY\SITEURL . '/tsjippy-email-tracking';
    }

    wp_redirect($url);
    exit();
}
