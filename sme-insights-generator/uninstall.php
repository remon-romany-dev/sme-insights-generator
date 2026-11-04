<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package SME_Insights_Generator
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'sig_settings' );

// Clear scheduled cron events.
wp_clear_scheduled_hook( 'sig_generate_content_event' );

// Clear any transients.
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_' ) . '%sig%',
		$wpdb->esc_like( '_transient_timeout_' ) . '%sig%'
	)
);

// Note: We intentionally do NOT delete generated posts or their meta data.
// This allows users to keep their content if they uninstall the plugin.

