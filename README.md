This plugin will convert all e-mails sent to html.<br>
It will also add a warning to the bottom of the e-mail about it being an automated e-mail.<br>
If there is no complementary close in the e-mail it will add one
It will also monitor how often an e-mail is opened.<br>

== Hooks == 
# FILTERS
- apply_filters('sim_email_footer_url', [
	'url'   => SITEURL,
	'text'  => SITEURL
])
- apply_filters('sim_email_footer', $this->footer, $this->message);