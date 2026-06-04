<?php
namespace TSJIPPY\HTMLEMAIL;
use TSJIPPY;

if ( ! defined('ABSPATH')) {
    exit;
}

// Filter any wp_email
add_filter('wp_mail', __NAMESPACE__ . '\mailFilter', 10, 1);
/**
 * Filter the arguments passed to wp_mail().
 *
 * @param array $args The arguments passed to wp_mail().
 * @return array The modified arguments.
 */
function mailFilter($args) {
    $html     = new HtmlEmail();
    $args     = $html->filterMail($args);

    return $args;
}

// skip mail if there are no recipients and mark as succesfull
add_filter('pre_wp_mail', __NAMESPACE__ . '\beforeMail', 99, 2);
/**
 * Filter whether to short-circuit wp_mail() and return a result instead.
 * This is used primarily to short-circuit wp_mail() when there are no recipients, but can be used for other purposes as well.
 *
 * @param bool $shouldSkip Whether to short-circuit wp_mail() and return a result instead. Default false.
 * @param array $atts The arguments passed to wp_mail(), including 'to', 'subject', 'message', and 'headers' .
 *
 * @return bool Whether to short-circuit wp_mail() and return a result instead.
 */
function beforeMail($shouldSkip, $atts) {
    if (
        empty($atts['to'])        ||
        (
            !empty(SETTINGS['no-localhost']) &&
            wp_get_environment_type() === 'local'
       )
   ) {
        return true;
    }

    return $shouldSkip;
}

// show wp_mail() errors
add_action('wp_mail_failed', __NAMESPACE__ . '\onMailFailed');
/**
 * Action fired when wp_mail() fails.
 *
 * @param \WP_Error $wpError The error object.
 */
function onMailFailed($wpError) {
    if (!isset($wpError->errors['wp_mail_failed'][0]) || $wpError->errors['wp_mail_failed'][0] != 'You must provide at least one recipient email address. ') {
        TSJIPPY\printArray($wpError);
    }
}

add_action('wp_mail_smtp_mailcatcher_send_failed', __NAMESPACE__ . '\mailCatcher', 10, 3);
/**
 * Action fired when wp_mail() fails with SMTP mailcatcher.
 *
 * @param string $errorMessage The error message.
 * @param object $instance The mail instance.
 * @param object $mailMailer The mailer object.
 */
function mailCatcher($errorMessage, $instance, $mailMailer) {
    TSJIPPY\printArray($errorMessage);
    TSJIPPY\printArray($instance);
    TSJIPPY\printArray($mailMailer);
}

add_shortcode('email_stats', __NAMESPACE__ . '\emailStats');
function emailStats() {
    $adminMenu  = new AdminMenu(SETTINGS, 'e-mail');

    return $adminMenu->emailStats();
}