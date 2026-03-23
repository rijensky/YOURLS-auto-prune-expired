<?php
/**
 * Cron runner for the auto-prune plugin.
 *
 * This script is meant to be executed by system cron (CLI).
 * It needs the token passed by the cron command we install on activation.
 */

if ( php_sapi_name() !== 'cli' ) {
	// Block web execution. We only support cron/CLI.
	http_response_code( 403 );
	exit;
}

$root = dirname( __DIR__, 3 );
require_once $root . '/includes/load-yourls.php';

require_once __DIR__ . '/includes/auto-prune-functions.php';

$provided_token = '';
foreach ( $argv as $arg ) {
	if ( strpos( (string) $arg, '--token=' ) === 0 ) {
		$provided_token = substr( (string) $arg, strlen( '--token=' ) );
		break;
	}
}

if ( $provided_token === '' ) {
	fwrite( STDERR, "auto-prune: missing --token argument\n" );
	exit( 1 );
}

$expected_token = auto_prune_get_cron_token( false );

// Avoid subtle timing leaks; hash_equals is available on modern PHP versions.
if ( function_exists( 'hash_equals' ) ) {
	$token_ok = hash_equals( $expected_token, $provided_token );
} else {
	$token_ok = (string) $expected_token === (string) $provided_token;
}

if ( $expected_token === '' || ! $token_ok ) {
	fwrite( STDERR, "auto-prune: invalid token\n" );
	exit( 1 );
}

$deleted = auto_prune_run_prune();

// Useful when viewing cron logs.
echo 'auto-prune: deleted ' . $deleted . " expired link(s)\n";

