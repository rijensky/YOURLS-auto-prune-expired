<?php
/**
 * Shared logic for the YOURLS auto-prune plugin.
 *
 * This file is meant to be included only from within a bootstrapped YOURLS context.
 */

if ( ! defined( 'YOURLS_ABSPATH' ) ) {
	die();
}

// Keep everything prefixed to avoid symbol/option collisions with other plugins.
if ( ! defined( 'AUTO_PRUNE_PLUGIN_SLUG' ) ) {
	define( 'AUTO_PRUNE_PLUGIN_SLUG', 'auto-prune' );
}

if ( ! defined( 'AUTO_PRUNE_OPTION_EXPIRY_DAYS' ) ) {
	define( 'AUTO_PRUNE_OPTION_EXPIRY_DAYS', 'auto_prune_expiry_days' );
}

if ( ! defined( 'AUTO_PRUNE_OPTION_CRON_TOKEN' ) ) {
	define( 'AUTO_PRUNE_OPTION_CRON_TOKEN', 'auto_prune_cron_token' );
}

if ( ! defined( 'AUTO_PRUNE_CRON_SCHEDULE' ) ) {
	// Run once per day.
	define( 'AUTO_PRUNE_CRON_SCHEDULE', '0 3 * * *' );
}

if ( ! defined( 'AUTO_PRUNE_CRON_MARKER' ) ) {
	// Used to find the entry again when uninstalling.
	define( 'AUTO_PRUNE_CRON_MARKER', 'yourls-auto-prune-expired' );
}

if ( ! defined( 'AUTO_PRUNE_DEFAULT_EXPIRY_DAYS' ) ) {
	define( 'AUTO_PRUNE_DEFAULT_EXPIRY_DAYS', 14 );
}

/**
 * Return a sanitized expiry days integer.
 */
function auto_prune_get_expiry_days() {
	$days = (int) yourls_get_option( AUTO_PRUNE_OPTION_EXPIRY_DAYS, AUTO_PRUNE_DEFAULT_EXPIRY_DAYS );
	if ( $days < 1 ) {
		$days = AUTO_PRUNE_DEFAULT_EXPIRY_DAYS;
	}
	return $days;
}

function auto_prune_get_cron_script_path() {
	return YOURLS_ABSPATH . '/user/plugins/' . AUTO_PRUNE_PLUGIN_SLUG . '/cron.php';
}

/**
 * Generate a random token used to protect cron execution.
 */
function auto_prune_generate_token() {
	if ( function_exists( 'random_bytes' ) ) {
		return bin2hex( random_bytes( 16 ) );
	}

	if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
		$bytes = openssl_random_pseudo_bytes( 16 );
		if ( $bytes !== false ) {
			return bin2hex( $bytes );
		}
	}

	// Fallback: not as strong, but better than a fixed token.
	return sha1( uniqid( '', true ) );
}

/**
 * Get (or create) the cron token stored in YOURLS options.
 */
function auto_prune_get_cron_token( $create_if_missing = true ) {
	$token = (string) yourls_get_option( AUTO_PRUNE_OPTION_CRON_TOKEN, '' );
	if ( $token !== '' ) {
		return $token;
	}

	if ( ! $create_if_missing ) {
		return '';
	}

	$token = auto_prune_generate_token();
	// Only attempt update; ignore failure (token will be re-created next time).
	yourls_update_option( AUTO_PRUNE_OPTION_CRON_TOKEN, $token );
	return $token;
}

function auto_prune_get_crontab_contents() {
	if ( ! function_exists( 'shell_exec' ) ) {
		return '';
	}

	// "crontab -l" prints errors to stderr; capture both, then normalize.
	$out = (string) shell_exec( 'crontab -l 2>&1' );
	$out = trim( $out );

	if ( $out === '' ) {
		return '';
	}

	// Typical output when no crontab exists.
	if ( stripos( $out, 'no crontab for' ) !== false ) {
		return '';
	}

	return $out;
}

function auto_prune_write_crontab_contents( $contents ) {
	if ( ! function_exists( 'shell_exec' ) ) {
		return false;
	}

	$tmp = tempnam( sys_get_temp_dir(), 'yourls-auto-prune-cron-' );
	if ( $tmp === false ) {
		return false;
	}

	// Ensure trailing newline (crontab is line-oriented).
	file_put_contents( $tmp, rtrim( (string) $contents ) . "\n" );

	$cmd = 'crontab ' . escapeshellarg( $tmp ) . ' 2>&1';
	$shell_out = shell_exec( $cmd );
	unlink( $tmp );

	// crontab usually outputs nothing on success.
	return ( $shell_out === null || $shell_out === '' );
}

function auto_prune_is_cron_installed() {
	$current = auto_prune_get_crontab_contents();
	return ( strpos( $current, AUTO_PRUNE_CRON_MARKER ) !== false );
}

function auto_prune_install_cronjob() {
	// If crontab isn't accessible, just fail silently so plugin activation doesn't break.
	if ( ! function_exists( 'shell_exec' ) ) {
		return false;
	}

	$php = defined( 'PHP_BINARY' ) && PHP_BINARY ? PHP_BINARY : 'php';
	$token = auto_prune_get_cron_token();
	$cron_script = auto_prune_get_cron_script_path();

	$cmd = escapeshellarg( $php ) . ' -q ' . escapeshellarg( $cron_script ) . ' --token=' . escapeshellarg( $token );
	$line = AUTO_PRUNE_CRON_SCHEDULE . ' ' . $cmd . ' # ' . AUTO_PRUNE_CRON_MARKER;

	$current = auto_prune_get_crontab_contents();
	$lines = $current === '' ? array() : preg_split( '/\r\n|\r|\n/', $current );
	if ( ! is_array( $lines ) ) {
		$lines = array();
	}

	// Remove any existing entries for this plugin before re-adding.
	$filtered = array();
	foreach ( $lines as $l ) {
		if ( strpos( (string) $l, AUTO_PRUNE_CRON_MARKER ) === false ) {
			$filtered[] = $l;
		}
	}

	$filtered[] = $line;
	$new_contents = implode( "\n", $filtered );

	return auto_prune_write_crontab_contents( $new_contents );
}

function auto_prune_uninstall_cronjob() {
	if ( ! function_exists( 'shell_exec' ) ) {
		return false;
	}

	$current = auto_prune_get_crontab_contents();
	if ( $current === '' ) {
		return true;
	}

	$lines = preg_split( '/\r\n|\r|\n/', $current );
	$filtered = array();
	foreach ( $lines as $l ) {
		if ( strpos( (string) $l, AUTO_PRUNE_CRON_MARKER ) === false ) {
			$filtered[] = $l;
		}
	}

	$new_contents = implode( "\n", $filtered );
	// If the resulting crontab is empty, we still write it (crontab will clear).
	return auto_prune_write_crontab_contents( $new_contents );
}

/**
 * Actually prune expired links (by age, using urls.timestamp).
 *
 * @return int Number of rows deleted (best effort).
 */
function auto_prune_run_prune( $days = null ) {
	$days = $days === null ? auto_prune_get_expiry_days() : (int) $days;
	if ( $days < 1 ) {
		$days = AUTO_PRUNE_DEFAULT_EXPIRY_DAYS;
	}

	$threshold_ts = time() - ( $days * 86400 );
	$threshold_dt = date( 'Y-m-d H:i:s', $threshold_ts );

	$ydb = yourls_get_db( 'write-auto-prune' );
	$sql = 'DELETE FROM `' . YOURLS_DB_TABLE_URL . '` WHERE `timestamp` < :threshold';
	$binds = array( 'threshold' => $threshold_dt );

	// fetchAffected returns affected row count for DELETE/UPDATE queries.
	$deleted = $ydb->fetchAffected( $sql, $binds );
	return (int) $deleted;
}

