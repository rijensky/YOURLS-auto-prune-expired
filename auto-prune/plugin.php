<?php
/*
Plugin Name: Auto Prune Expired
Plugin URI: https://github.com/
Description: Configure an expiry time (in days) and automatically remove expired links via a cron job.
Version: 1.0
Author: YOURLS Community
Author URI: https://yourls.org/
*/

// No direct call.
if ( ! defined( 'YOURLS_ABSPATH' ) ) {
	die();
}

require_once __DIR__ . '/includes/auto-prune-functions.php';

// Register the admin page.
yourls_add_action( 'plugins_loaded', 'auto_prune_init' );
function auto_prune_init() {
	yourls_register_plugin_page( AUTO_PRUNE_PLUGIN_SLUG, 'Auto Prune', 'auto_prune_admin_page' );
}

// Install the cron job when the plugin is enabled.
yourls_add_action( 'activated_plugin', 'auto_prune_on_activated_plugin' );
function auto_prune_on_activated_plugin( $plugin ) {
	$self = function_exists( 'yourls_plugin_basename' ) ? yourls_plugin_basename( __FILE__ ) : ( AUTO_PRUNE_PLUGIN_SLUG . '/plugin.php' );
	if ( $plugin !== $self ) {
		return;
	}

	// Ensure the token exists before we add the cron call.
	auto_prune_get_cron_token();
	auto_prune_install_cronjob();
}

/**
 * Draw and process the plugin admin page.
 */
function auto_prune_admin_page() {
	$days = auto_prune_get_expiry_days();
	$nonce = yourls_create_nonce( 'auto_prune_settings' );

	$message = '';
	$errors = array();

	if ( isset( $_POST['auto_prune_save'] ) ) {
		yourls_verify_nonce( 'auto_prune_settings', $_REQUEST['nonce'] ?? '' );

		$requested = isset( $_POST['expiry_days'] ) ? $_POST['expiry_days'] : '';
		$requested = yourls_sanitize_int( $requested );

		// Keep it sane to avoid accidental misconfiguration.
		if ( $requested < 1 || $requested > 36500 ) {
			$errors[] = yourls__( 'Expiry days must be between 1 and 36500.' );
		} else {
			yourls_update_option( AUTO_PRUNE_OPTION_EXPIRY_DAYS, $requested );
			$days = (int) $requested;
			$message = yourls__( 'Settings saved.' );
		}
	}

	if ( isset( $_POST['auto_prune_run_now'] ) ) {
		yourls_verify_nonce( 'auto_prune_settings', $_REQUEST['nonce'] ?? '' );
		$deleted = auto_prune_run_prune();
		$message = yourls_s( 'Prune complete. Deleted %s expired link(s).', $deleted );
	}

	$cron_installed = auto_prune_is_cron_installed();

	echo '<h2>' . yourls__( 'Auto Prune Expired' ) . '</h2>';

	if ( $message !== '' ) {
		echo '<p><strong>' . yourls_esc_attr( $message ) . '</strong></p>';
	}

	if ( ! empty( $errors ) ) {
		echo '<ul>';
		foreach ( $errors as $e ) {
			echo '<li>' . yourls_esc_attr( $e ) . '</li>';
		}
		echo '</ul>';
	}

	echo '<form method="post" action="">';
	echo '<input type="hidden" name="nonce" value="' . yourls_esc_attr( $nonce ) . '"/>';

	echo '<p>';
	echo '<label for="expiry_days">' . yourls__( 'Expire links older than (days):' ) . '</label> ';
	echo '<input id="expiry_days" name="expiry_days" type="number" min="1" max="36500" value="' . (int) $days . '" />';
	echo '</p>';

	echo '<p>';
	echo '<button type="submit" class="button button-primary" name="auto_prune_save" value="1">' . yourls__( 'Save' ) . '</button> ';
	echo '<button type="submit" class="button" name="auto_prune_run_now" value="1">' . yourls__( 'Prune now' ) . '</button>';
	echo '</p>';

	echo '</form>';

	echo '<hr/>';

	echo '<p><strong>' . yourls__( 'Cron status:' ) . '</strong> ';
	echo $cron_installed ? yourls__( 'Installed' ) : yourls__( 'Not installed' );
	echo '</p>';

	echo '<p><small>';
	echo yourls__( 'Cron runs once per day (schedule: %s).', AUTO_PRUNE_CRON_SCHEDULE );
	echo '</small></p>';
}

