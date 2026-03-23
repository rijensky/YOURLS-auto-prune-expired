<?php
/**
 * Uninstall script.
 *
 * Executed when the plugin is deactivated on YOURLS 1.8.3+.
 */

// No direct call.
if ( ! defined( 'YOURLS_ABSPATH' ) ) {
	die();
}

// No direct call.
if ( ! defined( 'YOURLS_UNINSTALL_PLUGIN' ) ) {
	return;
}

require_once __DIR__ . '/includes/auto-prune-functions.php';

// Remove the cron job and cleanup stored options.
auto_prune_uninstall_cronjob();
yourls_delete_option( AUTO_PRUNE_OPTION_EXPIRY_MODE );
yourls_delete_option( AUTO_PRUNE_OPTION_EXPIRY_VALUE );
yourls_delete_option( AUTO_PRUNE_OPTION_EXPIRY_DAYS );
yourls_delete_option( AUTO_PRUNE_OPTION_CRON_HOUR );
yourls_delete_option( AUTO_PRUNE_OPTION_CRON_MINUTE );
yourls_delete_option( AUTO_PRUNE_OPTION_CRON_TOKEN );

