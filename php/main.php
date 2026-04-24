<?php
namespace SIM\FANCYEMAIL;
use SIM;

// Filter any wp_email
add_filter('wp_mail', __NAMESPACE__.'\mailFilter', 10, 1);
function mailFilter($args){
    $fancyEmail     = new FancyEmail();
    $args           = $fancyEmail->filterMail($args);

    return $args;
}

// skip mail if there are no recipients and mark as succesfull
add_filter( 'pre_wp_mail', __NAMESPACE__.'\beforeMail', 99, 2);
function beforeMail($shouldSkip, $atts ){
    if(
        empty($atts['to'])        ||
        (
            SIM\getModuleOption(MODULE_SLUG, 'no-localhost') &&
            wp_get_environment_type() === 'local'
        )                                                   ||
        (
            SIM\getModuleOption(MODULE_SLUG, 'no-staging') &&
            get_option("wpstg_is_staging_site") == "true"
        )
    ){
        return true;
    }

    //SIM\printArray($atts);
    return $shouldSkip;
}

// show wp_mail() errors
add_action( 'wp_mail_failed', __NAMESPACE__.'\onMailFailed');
function onMailFailed( $wpError ) {
    if(!isset($wpError->errors['wp_mail_failed'][0]) || $wpError->errors['wp_mail_failed'][0] != 'You must provide at least one recipient email address.'){
        SIM\printArray($wpError);
    }
}

add_action( 'wp_mail_smtp_mailcatcher_send_failed', __NAMESPACE__.'\mailCatcher', 10, 3 );
function mailCatcher($errorMessage, $instance, $mailMailer){
    SIM\printArray($errorMessage);
    SIM\printArray($instance);
    SIM\printArray($mailMailer);
}