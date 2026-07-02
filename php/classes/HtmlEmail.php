<?php

namespace TSJIPPY\HTMLEMAIL;

use TSJIPPY;

if (! defined('ABSPATH')) {
    exit;
}

class HtmlEmail
{
    public string $mailTable;
    public string $mailEventTable;
    public string $mailTrackerUrl;
    public string $subject;
    public string $recipients;
    public array $headers;
    public string $message;
    public int $emailId;
    public string $footer;

    /**
     * Constructor for the HtmlEmail class.
     * Initializes the database table names and mail tracker URL.
     */
    public function __construct()
    {
        global $wpdb;

        $this->mailTable        = $wpdb->prefix . "tsjippy_emails";
        $this->mailEventTable   = $wpdb->prefix . "tsjippy_email_events";
        $this->mailTrackerUrl   = TSJIPPY\SITEURL . "/wp-json/" . TSJIPPY\RESTAPIPREFIX . "/mailtracker";
    }

    /**
     * Creates the tables for this plugin
     */
    public function createDbTables()
    {
        if (!function_exists('maybe_create_table')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        //only create db if it does not exist
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();

        //Email overview
        $sql = "CREATE TABLE $this->mailTable (
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          subject tinytext NOT NULL,
          recipients longtext NOT NULL,
          time_send text NOT NULL,
          PRIMARY KEY  (id)
       ) $charsetCollate;";

        maybe_create_table($this->mailTable, $sql);

        // Clicked links
        $sql = "CREATE TABLE $this->mailEventTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email_id int NOT NULL,
            type text NOT NULL,
            time text NOT NULL,
            url text NOT NULL,
            PRIMARY KEY  (id)
         ) $charsetCollate;";

        maybe_create_table($this->mailEventTable, $sql);
    }

    /**
     * Store the e-mail in the db for statistics
     */
    private function storeEmail()
    {
        global $wpdb;
        if (SETTINGS['no-statistics']) {
            // Add e-mail to e-mails db
            $this->emailId   = TSJIPPY\insertInDb(
                $this->mailTable,
                array(
                    'subject'        => $this->subject,
                    'recipients'    => $this->recipients,
                    'time_send'     => current_time('U')
                ),
                [
                    '%s',
                    '%s',
                    '%d'
                ],
                'html-email'
            );
        }
    }

    /**
     * Filters all WP_Mail arguments
     *
     * @param   array   $args   the array of wp_mail arguments
     *
     * @return  array           The filtered args
     */
    public function filterMail($args)
    {
        $this->subject      = &$args['subject'];

        $this->recipients   = &$args['to'];

        //Do not send an e-mail when the adres contains .empty, or is localhost or is staging
        $empty  = false;
        if (is_array($this->recipients)) {
            foreach ($this->recipients as $index => $recipient) {
                if (str_contains($recipient, '.empty')) {
                    unset($this->recipients[$index]);
                }
            }

            if (empty($this->recipients)) {
                $empty  = true;
            } else {
                $this->recipients   = implode(',', $this->recipients);
            }
        } elseif (str_contains($this->recipients, '.empty')) {
            $empty  = true;
        }

        if ($empty) {
            $args['to'] = '';
            return $args;
        }

        if (!is_array($args['headers'])) {
            $args['headers'] = [];
        }
        $this->headers      = &$args['headers'];

        $this->message      = &$args['message'];

        if (is_array($this->message)) {
            TSJIPPY\printArray($this->message);
        } else {
            $this->message = trim($this->message ?? '');
        }

        // max attachment size
        $totalSize  = 0;
        $maxSize    = SETTINGS['maxsize'] ?? 20;
        $remaining  = [];
        if (!$maxSize) {
            $maxSize    = 20;
        }

        // check if the total attachment size is past the limit
        if(is_array($args['attachments'])){
            foreach ($args['attachments'] as $index => $attach) {
                $totalSize   += filesize($attach);

                // if this is more than the limit
                if (number_format($totalSize / 1048576, 2) >= $maxSize) {
                    $remaining[]    = $attach;
                    unset($args['attachments'][$index]);
                }
            }
        }else{
            TSJIPPY\printArray($args);
        }

        if (!empty($remaining)) {
            // Send an e-mail with the remaining e-mails
            $explode    = explode(' - ', $this->subject);
            if (is_numeric(end($explode))) {
                $number = end($explode) + 1;

                // remove the last element
                array_pop($explode);

                // Build the subject again without the last number
                $subject    = implode(' ', $explode) . ' - ' . $number;
            } else {
                $subject    = "$this->subject - 1";
            }

            wp_mail($this->recipients, $subject, $this->message, $args['headers'], $remaining);
        }

        $this->storeEmail();

        //force html e-mail
        if (!in_array("Content-Type: text/html; charset=UTF-8", $this->headers)) {
            $this->headers[]    = "Content-Type: text/html; charset=UTF-8";
        }

        // Add site greetings if not given
        $defaultGreeting    = SETTINGS['closing'] ?? '';
        if (!$defaultGreeting) {
            $defaultGreeting    = 'Kind regards,';
        }
        if (
            !str_contains(strtolower($this->message), strtolower($defaultGreeting)) &&
            !str_contains(strtolower($this->message), 'regards,') &&
            !str_contains(strtolower($this->message), 'blessings,') &&
            !str_contains(strtolower($this->message), 'cheers,')
        ) {
            $this->message    .= "<br><br>$defaultGreeting<br><br>" . TSJIPPY\SITENAME;
        }

        // Mention that this is an automated message
        $footerUrl     = apply_filters('tsjippy-email-footer-url', [
            'url'   => TSJIPPY\SITEURL,
            'text'  => TSJIPPY\SITEURL
        ]);

        $url            = $footerUrl['url'];
        $text           = str_replace(['https://www. ', 'https://', 'http://www. ', 'http://'], '', $footerUrl['text']);
        $this->footer    = "<span style='font-size:10px'>This is an automated e-mail originating from <a href='$url'>$text</a></span>";

        // Convert message to html
        if (!str_contains($this->message, '<!doctype html>')) {
            $this->htmlEmail();
        }

        return $args;
    }

    /**
     * Replace any images to tracebale ones
     *
     * @param   array   $matches    Matches from a regex
     *
     * @return  string              Replace html
     */
    public function checkEmailImages($matches)
    {
        if (empty($matches)) {
            return false;
        }

        // Convert to array in case of a pure url
        if (!is_array($matches)) {
            $matches    = [$matches, $matches];
        }

        $html        = $matches[0];
        $url        = $matches[1];

        // add a hash so image is also readible when not logged in
        if (str_contains($url, '/private/')) {
            // create the random string
            $str    = wp_rand();
            $hash   = md5($str);

            // store hash in db for a month
            set_transient("tsjippy_$hash", basename($url), MONTH_IN_SECONDS);

            $html        = str_replace($url, "$url?imagehash=$hash", $html);
        }

        return $html;
    }

    /**
     * Enable link tracking
     *
     * @param   array   $matches    Matches from a regex
     *
     * @return  string              Replace html
     */
    public function urlReplace($matches)
    {
        if (empty($matches) || !SETTINGS['no-statistics'] ?? false) {
            return false;
        }

        // Convert to array in case of a pure url
        if (!is_array($matches)) {
            $matches    = [$matches, $matches];
        }

        $html        = $matches[0];
        $url        = $matches[1];

        $url        = str_replace(['http://', 'https://', 'http://https://'], 'https://', $url);
        $url        = str_replace('https://https://', 'https://', $url);


        // Change to rest-api url
        $newUrl    = "$this->mailTrackerUrl?mailid=$this->emailId&url=" . urlencode($url);

        $html        = str_replace($url, $newUrl, $html);

        return $html;
    }

    /**
     * Converts plain text e-mail message to html
     */
    public function htmlEmail()
    {
        // Get the logo url and make public if private
        $headerImageId    = SETTINGS['picture-ids']['header_image'] ?? false;
        if (!$headerImageId) {
            $headerImageId = get_theme_mod('custom_logo');
        }
        $logoUrl    = wp_get_attachment_url($headerImageId);

        // Process any images in the content
        $pattern = "/<img\s*src=[\"|']([^\"']*)[\"|']/i";
        $message = preg_replace_callback($pattern, array($this, 'checkEmailImages'), $this->message);

        if (SETTINGS['no-statistics'] ?? false) {
            //Enable e-mail tracking
            $pattern = "/href=[\"|']([^\"']*)[\"|']/i";
            $message = preg_replace_callback($pattern, array($this, 'urlReplace'), $message);
        }

        //Replace all newline characters with html new line <br>
        if (!str_contains($message, '<br>') && !str_contains($message, '<p>') && !str_contains($message, '<div>')) {
            $message  = str_replace("\n", '<br>', $message);
        }

        ob_start();
?>
        <!doctype html>
        <html lang="en">

        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width">
            <title><?php echo esc_attr($this->subject); ?></title>
            <style type="text/css">
                @media only screen and (max-width: 599px) {
                    table.body .container {
                        width: 95% !important;
                    }

                    .header {
                        padding: 15px 15px 12px 15px !important;
                    }

                    .header img {
                        width: 200px !important;
                        height: auto !important;
                    }

                    .content,
                    .aside {
                        padding: 30px 40px 20px 40px !important;
                    }
                }
            </style>
        </head>

        <body style="height: 100% !important; width: 100% !important; min-width: 100%; -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; -webkit-font-smoothing: antialiased !important; -moz-osx-font-smoothing: grayscale !important; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; background-color: #f1f1f1; text-align: center;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%" class="body" style="border-collapse: collapse; border-spacing: 0; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; height: 100% !important; width: 100% !important; min-width: 100%; -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; -webkit-font-smoothing: antialiased !important; -moz-osx-font-smoothing: grayscale !important; background-color: #f1f1f1; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; text-align: left; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%;">
                <tr style="padding: 0; vertical-align: top; text-align: left;">
                    <td align="center" valign="top" class="body-inner" style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; text-align: center;">
                        <!-- Container -->
                        <table border="0" cellpadding="0" cellspacing="0" class="container" style="border-collapse: collapse; border-spacing: 0; padding: 0; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; width: 600px; margin: 0 auto 30px auto; Margin: 0 auto 30px auto; text-align: inherit;">
                            <!-- Header -->
                            <tr style="padding: 0; vertical-align: top; text-align: left;">
                                <td align="center" valign="middle" class="header" style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; margin: 0; Margin: 0; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; text-align: center; padding: 30px 30px 22px 30px;">
                                    <a href="<?php echo TSJIPPY\SITEURL; ?>"><img src="<?php echo esc_attr($logoUrl); ?>" alt="Site Logo" style="outline: none; text-decoration: none; max-width: 100%; clear: both; -ms-interpolation-mode: bicubic; display: inline-block !important; width: 250px;"></a>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr style="padding: 0; vertical-align: top; text-align: left;">
                                <td align="left" valign="top" class="content" style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; margin: 0; Margin: 0; text-align: left; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; background-color: #ffffff; padding: 60px 75px 45px 75px; border-right: 1px solid #ddd; border-bottom: 1px solid #ddd; border-left: 1px solid #ddd;">
                                    <h1 style="color:#241c15;font-family:Georgia,Times,'Times New Roman',serif;font-size:28px;font-style:normal;font-weight:400;line-height:36px;letter-spacing:normal;margin:0px 0px 20px 0px;padding:0;text-align:center">
                                        <?php echo esc_attr($this->subject); ?>
                                    </h1>
                                    <?php echo esc_attr($message); ?>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr style="padding: 0; vertical-align: top; text-align: left;">
                                <td align="left" valign="top" class="content" style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; margin: 0; Margin: 0; text-align: left; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; padding: 20px 0px; text-align: center;">
                                    <?php
                                    echo wp_kses_post(apply_filters('tsjippy-email-footer', $this->footer, $this->message));

                                    if (SETTINGS['no-statistics'] ?? false) {
                                        $url    = "$this->mailTrackerUrl?mailid=$this->emailId&ver=$this->emailId";

                                        ?>
                                        <img src='<?php echo esc_url($url);?>' alt=' . ' width='1px' height='1px'>
                                        <?php
                                    }
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>

        </html>

<?php
        $this->message = ob_get_clean();
    }

    /**
     * Get all e-mail statistics from the db
     *
     * @return  object      all query results
     */
    public function getEmailStatistics()
    {
        global $wpdb;

        $query     =  "SELECT ";
        $vars      = [];

        if (($_POST['type'] ?? '') == 'link-clicked') {
            $type  = 'link-clicked';
        } else {
            $type  = 'mail-opened';
            $query .= "COUNT(events.email_id) AS viewcount, ";
        }

        $query    .= "events.url, events.type, emails.recipients, emails.time_send, emails.subject FROM %i as emails";
        $vars[]    = $this->mailTable;

        $query    .= " LEFT JOIN %i as events ON events.email_id=emails.id";
        $vars[]    = $this->mailEventTable;

        $query    .= " WHERE events.type = %s ";
        $vars[]    = $type;

        if (empty($_POST)) {
            $query  .= "AND emails.time_send >= %s";
            $vars[] = strtotime("-7 days");
        } elseif (!empty($_POST['s']) || isset($_POST['recipient'])) {
            if (isset($_POST['recipient'])) {
                $search  = TSJIPPY\sanitize($_POST['recipient']);
            } else {
                $search  = TSJIPPY\sanitize($_POST['s']);
            }

            $query  .= "AND emails.recipients LIKE %s OR emails.subject LIKE %s";
            $vars[] = "%{$wpdb->esc_like($search)}%";
            $vars[] = "%{$wpdb->esc_like($search)}%";
        } else {
            if (!empty($_POST['date'])) {
                $maxTime   = strtotime(TSJIPPY\sanitize($_POST['date']));
            } elseif (!empty($_POST['date-start'])) {
                $maxTime   = strtotime(TSJIPPY\sanitize($_POST['date-start']));
            } else {
                if (empty($_POST['timespan'])) {
                    $timespan   = '7';
                } else {
                    $timespan   = (int) $_POST['timespan'];
                }

                $maxTime   = strtotime("-$timespan days");
            }
            $query  .= "AND emails.time_send >= %s";
            $vars[] = $maxTime;

            if (!empty($_POST['date-end'])) {
                $maxTime    = strtotime(TSJIPPY\sanitize($_POST['date-end']));
                $query  .= " AND emails.time_send <= %s";
                $vars[] = $maxTime;
            }
        }

        if ($type != 'link-clicked') {
            $query  .= " GROUP BY emails.id, events.url";
        }
        $query  .= " ORDER BY emails.time_send DESC";

        return TSJIPPY\getFromDb('email-stats', 'html-email', $query, $vars);
    }

    /**
     * Clear all e-mail tables
     */
    public function clearTables()
    {
        global $wpdb;

        $wpdb->query("TRUNCATE $this->mailTable");
        $wpdb->query("TRUNCATE events");

        /**
         * Flush db cache
         */
        if(wp_cache_supports( 'flush_group' )){
            wp_cache_flush_group('html-email');
        }else{
            wp_cache_flush();
        }
    }
}
