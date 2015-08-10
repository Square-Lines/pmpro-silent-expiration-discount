<?php
/*
Plugin Name: Paid Memberships Pro - Silent Expiration by Discount Code Add On
Plugin URI: http://www.square-lines.com/
Description: Add an option to discount codes whereby members who sign up with that code will not receive expiration e-mails.
Version: 1.0
Author: Square Lines LLC
AUthor URI: http://www.square-lines.com
 
Note that this plugin has been tested with PMPro 1.8.5.x. Your experience may vary when using different versions.
*/

// when the plug-in is activated, ensure that there's a column in wpdb->pmpro_discount_codes for the silent expiration. If not, add it.


function pmprosdc_activate() {
	global $wpdb;
	
	$thecol = $wpdb->get_row("SHOW COLUMNS FROM $wpdb->pmpro_discount_codes LIKE 'silentexpiry'");
	if(! $thecol) {
		$status = $wpdb->query("ALTER TABLE cw_pmpro_discount_codes ADD silentexpiry INT");
		if(! $status) {
			trigger_error('To activate the Discount Code Add-On plug-in, you need to be able to write to the database. We weren\'t able to do that!', E_USER_ERROR);
		}
	}
}

register_activation_hook( __FILE__, 'pmprosdc_activate');

// add a checkbox to the discount code screen to indicate if this one will provide for silent expiration
// edit is either false (if it's a new code) or a discount code number (if we're editing). level is the membership level it applies to.
// (See discountcodes.php line 535.)
function pmprosdc_show_discount_code_checkbox($edit, $level) {
	global $wpdb;
	
	$ischecked = '';
	if($edit>0) {
		$issilent = $wpdb->get_var("SELECT silentexpiry FROM $wpdb->pmpro_discount_codes WHERE id=$edit");
		if($issilent && $issilent>0) {
			$ischecked = 'checked';
		}
	}
	?>
	<h3>Suppress Expiration Reminders?</h3>
	<div>
		<p>If checked, then people who sign up with this discount code will not receive the normal e-mail reminders when their membership is about to expire, or when it has expired. They will expire silently.</p>
		<p><input type="checkbox" id="suppress_reminders" name="suppress_reminders" <?php echo $ischecked; ?> value=1> Silent Expiration</p>
	</div>
	<?php
}

add_action('pmpro_discount_code_after_level_settings', 'pmprosdc_show_discount_code_checkbox');


// on submission of the discount code, store the setting for silent expiration
// saveid is the id of the discount code that has been saved or updated. (See discountcodes.php like 213.)
function pmprosdc_update_discount_code_expiry_setting($saveid) {
	global $wpdb;
	
	$theid = intval($saveid, 10);
	
	if(array_key_exists('suppress_reminders', $_REQUEST) && $_REQUEST['suppress_reminders']>0) {
		$wpdb->query("UPDATE $wpdb->pmpro_discount_codes SET silentexpiry=1 WHERE id=$theid");
	} else {
		$wpdb->query("UPDATE $wpdb->pmpro_discount_codes SET silentexpiry=0 WHERE id=$theid");
	}
}

add_action('pmpro_save_discount_code', 'pmprosdc_update_discount_code_expiry_setting');

// intercede before e-mails are sent to ensure this person didn't sign up with the silencing discount code
// done through filters in the cronjob - scheduled/crons.php. We're including the trial expiry too, even though
// it's currently commented out. It may come back in the future - and we'll be ready!
function pmprosdc_check_if_we_should_email($inbool, $inuserid) {
	global $wpdb;
	
	$inuserid = intval($inuserid, 10); // just in case
	
	$issilent = 0;
	$issilent = $wpdb->get_var("SELECT silentexpiry FROM $wpdb->pmpro_discount_codes WHERE id IN (SELECT code_id FROM $wpdb->pmpro_memberships_users WHERE user_id=$inuserid ORDER BY enddate DESC LIMIT 1) LIMIT 1");
	if($issilent>0) { return false; }
	return true;
	
}

add_filter('pmpro_send_expiration_email','pmprosdc_check_if_we_should_email', 10, 2);
add_filter('pmpro_send_expiration_warning_email','pmprosdc_check_if_we_should_email', 10, 2);
add_filter('pmpro_send_trial_ending_email','pmprosdc_check_if_we_should_email', 10, 2);
