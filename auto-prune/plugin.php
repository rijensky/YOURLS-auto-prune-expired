<?php
/*
Plugin Name: Auto Prune Expired
Plugin URI: https://github.com/
Description: Configure an expiry time (in days) and automatically remove expired links via a cron job.
Version: 1.0
Author: Itay Rijensky
Author URI: https://rijensky.com/
*/

// No direct call.
if (!defined('YOURLS_ABSPATH')) {
	die();
}

require_once __DIR__ . '/includes/auto-prune-functions.php';

// Register the admin page.
yourls_add_action('plugins_loaded', 'auto_prune_init');
function auto_prune_init()
{
	yourls_register_plugin_page(AUTO_PRUNE_PLUGIN_SLUG, 'Auto Prune', 'auto_prune_admin_page');
}

// Install the cron job when the plugin is enabled.
yourls_add_action('activated_plugin', 'auto_prune_on_activated_plugin');
function auto_prune_on_activated_plugin($plugin)
{
	$self = function_exists('yourls_plugin_basename') ? yourls_plugin_basename(__FILE__) : (AUTO_PRUNE_PLUGIN_SLUG . '/plugin.php');
	if ($plugin !== $self) {
		return;
	}

	// Ensure the token exists before we add the cron call.
	auto_prune_get_cron_token();
	auto_prune_install_cronjob();
}

/**
 * Draw and process the plugin admin page.
 */
function auto_prune_admin_page()
{
	$nonce = yourls_create_nonce('auto_prune_settings');

	$message = '';
	$errors = array();

	$expiry_mode = auto_prune_get_expiry_mode();
	$expiry_value = auto_prune_get_expiry_value();
	$cron_hour = auto_prune_get_cron_hour();
	$cron_minute = auto_prune_get_cron_minute();
	$cron_installed = auto_prune_is_cron_installed();

	$is_posted = (
		isset($_POST['auto_prune_save']) ||
		isset($_POST['auto_prune_run_now']) ||
		isset($_POST['auto_prune_install_cron']) ||
		isset($_POST['auto_prune_uninstall_cron'])
	);

	$save_clicked = isset($_POST['auto_prune_save']);
	$run_now_clicked = isset($_POST['auto_prune_run_now']);
	$install_cron_clicked = isset($_POST['auto_prune_install_cron']);
	$uninstall_cron_clicked = isset($_POST['auto_prune_uninstall_cron']);

	if ($is_posted) {
		yourls_verify_nonce('auto_prune_settings', $_REQUEST['nonce'] ?? '');

		$posted_mode = isset($_POST['expiry_mode']) ? strtolower(trim((string) $_POST['expiry_mode'])) : $expiry_mode;
		if ($posted_mode !== AUTO_PRUNE_EXPIRY_MODE_DAYS && $posted_mode !== AUTO_PRUNE_EXPIRY_MODE_MINUTES) {
			$posted_mode = AUTO_PRUNE_DEFAULT_EXPIRY_MODE;
		}

		$posted_value_raw = isset($_POST['expiry_value']) ? $_POST['expiry_value'] : '';
		$posted_value = yourls_sanitize_int($posted_value_raw);

		$posted_hour = isset($_POST['cron_hour']) ? yourls_sanitize_int($_POST['cron_hour']) : $cron_hour;
		$posted_minute = isset($_POST['cron_minute']) ? yourls_sanitize_int($_POST['cron_minute']) : $cron_minute;

		if ($posted_mode === AUTO_PRUNE_EXPIRY_MODE_DAYS) {
			// Keep it sane to avoid accidental misconfiguration.
			if ($posted_value < 1 || $posted_value > 36500) {
				$errors[] = yourls__('Expiry days must be between 1 and 36500.');
			} else {
				yourls_update_option(AUTO_PRUNE_OPTION_EXPIRY_MODE, AUTO_PRUNE_EXPIRY_MODE_DAYS);
				yourls_update_option(AUTO_PRUNE_OPTION_EXPIRY_VALUE, $posted_value);
				yourls_update_option(AUTO_PRUNE_OPTION_EXPIRY_DAYS, $posted_value); // legacy compat
				$expiry_mode = $posted_mode;
				$expiry_value = $posted_value;
			}
		} else {
			if ($posted_value < 1 || $posted_value > 525600) {
				$errors[] = yourls__('Expiry minutes must be between 1 and 525600.');
			} else {
				yourls_update_option(AUTO_PRUNE_OPTION_EXPIRY_MODE, AUTO_PRUNE_EXPIRY_MODE_MINUTES);
				yourls_update_option(AUTO_PRUNE_OPTION_EXPIRY_VALUE, $posted_value);
				$expiry_mode = $posted_mode;
				$expiry_value = $posted_value;
			}
		}

		if ($posted_hour < 0 || $posted_hour > 23) {
			$posted_hour = AUTO_PRUNE_DEFAULT_CRON_HOUR;
		}
		if ($posted_minute < 0 || $posted_minute > 59) {
			$posted_minute = AUTO_PRUNE_DEFAULT_CRON_MINUTE;
		}

		if (empty($errors)) {
			yourls_update_option(AUTO_PRUNE_OPTION_CRON_HOUR, $posted_hour);
			yourls_update_option(AUTO_PRUNE_OPTION_CRON_MINUTE, $posted_minute);
			$cron_hour = $posted_hour;
			$cron_minute = $posted_minute;
		}

		if (empty($errors)) {
			if ($save_clicked) {
				$message = yourls__('Settings saved.');
				// Apply cron time changes immediately when cron is installed.
				if ($cron_installed) {
					auto_prune_install_cronjob();
				}
			}

			if ($install_cron_clicked) {
				$ok = auto_prune_install_cronjob();
				$message = $ok ? yourls__('Cron installed.') : yourls__('Could not install cron.');
			}

			if ($uninstall_cron_clicked) {
				$ok = auto_prune_uninstall_cronjob();
				$message = $ok ? yourls__('Cron uninstalled.') : yourls__('Could not uninstall cron.');
			}

			if ($run_now_clicked) {
				$deleted = auto_prune_run_prune();
				$message = yourls_s('Prune complete. Deleted %s expired link(s).', $deleted);
			}
		}

		$cron_installed = auto_prune_is_cron_installed();
	}

	echo '<h2>' . yourls__('Auto Prune Expired') . '</h2>';

	if ($message !== '') {
		echo '<p><strong>' . yourls_esc_attr($message) . '</strong></p>';
	}

	if (!empty($errors)) {
		echo '<ul>';
		foreach ($errors as $e) {
			echo '<li>' . yourls_esc_attr($e) . '</li>';
		}
		echo '</ul>';
	}

	echo '<form method="post" action="">';
	echo '<input type="hidden" name="nonce" value="' . yourls_esc_attr($nonce) . '"/>';

	echo '<p>';
	echo '<label for="expiry_mode">' . yourls__('Expire links by:') . '</label> ';
	echo '<select id="expiry_mode" name="expiry_mode">';
	echo '<option value="' . AUTO_PRUNE_EXPIRY_MODE_DAYS . '" ' . ( $expiry_mode === AUTO_PRUNE_EXPIRY_MODE_DAYS ? 'selected' : '' ) . '>' . yourls__('Days') . '</option>';
	echo '<option value="' . AUTO_PRUNE_EXPIRY_MODE_MINUTES . '" ' . ( $expiry_mode === AUTO_PRUNE_EXPIRY_MODE_MINUTES ? 'selected' : '' ) . '>' . yourls__('Minutes') . '</option>';
	echo '</select> ';
	echo '</p>';

	if ($expiry_mode === AUTO_PRUNE_EXPIRY_MODE_DAYS) {
		$max = 36500;
		$label = yourls__('Expire links older than (days):');
	} else {
		$max = 525600;
		$label = yourls__('Expire links older than (minutes):');
	}

	echo '<p>';
	echo '<label for="expiry_value">' . $label . '</label> ';
	echo '<input id="expiry_value" name="expiry_value" type="number" min="1" max="' . (int) $max . '" value="' . (int) $expiry_value . '" />';
	echo '</p>';

	echo '<p>';
	echo '<label>' . yourls__('Cron runs daily at:') . '</label> ';
	echo '<select name="cron_hour">';
	for ($h = 0; $h <= 23; $h++) {
		$sel = ($h === (int) $cron_hour) ? 'selected' : '';
		echo '<option value="' . $h . '" ' . $sel . '>' . str_pad((string) $h, 2, '0', STR_PAD_LEFT) . '</option>';
	}
	echo '</select>:';
	echo '<select name="cron_minute">';
	for ($m = 0; $m <= 59; $m++) {
		$sel = ($m === (int) $cron_minute) ? 'selected' : '';
		echo '<option value="' . $m . '" ' . $sel . '>' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . '</option>';
	}
	echo '</select>';
	echo '</p>';

	echo '<p>';
	echo '<button type="submit" class="button button-primary" name="auto_prune_save" value="1">' . yourls__('Save') . '</button> ';
	echo '<button type="submit" class="button" name="auto_prune_run_now" value="1">' . yourls__('Prune now') . '</button>';
	echo '</p>';

	echo '<hr/>';

	echo '<p><strong>' . yourls__('Cron status:') . '</strong> ';
	echo $cron_installed ? yourls__('Installed') : yourls__('Not installed');
	echo '</p>';

	echo '<p><small>';
	echo yourls_s( 'Cron runs daily at: %s.', auto_prune_get_cron_time_hm() );
	echo '</small></p>';

	if ($cron_installed) {
		echo '<p>';
		echo '<button type="submit" class="button" name="auto_prune_uninstall_cron" value="1">' . yourls__('Uninstall cron') . '</button>';
		echo '</p>';
	} else {
		echo '<p>';
		echo '<button type="submit" class="button button-primary" name="auto_prune_install_cron" value="1">' . yourls__('Install cron') . '</button>';
		echo '</p>';
	}

	echo '</form>';
}

