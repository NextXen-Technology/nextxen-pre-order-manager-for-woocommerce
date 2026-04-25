<?php
/**
 * Fired when the plugin is deleted (not deactivated).
 *
 * Removes plugin-specific wp_options rows and transients.
 * Order meta and product meta are intentionally preserved — they are business
 * records that belong to the store's orders/products, not the plugin itself.
 *
 * @package NextXen_Pre_Order_Manager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ── Plugin settings (stored via WooCommerce Settings API) ────────────────────
$options = array(
	'npom_add_to_cart_button_text',
	'npom_place_order_button_text',
	'npom_single_product_message',
	'npom_shop_loop_product_message',
	'npom_availability_date_cart_title_text',
	'npom_upon_release_order_total_format',
	'npom_upfront_order_total_format',
	'npom_auto_pre_order_out_of_stock',
	'npom_disable_auto_processing',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// ── Scheduled events ─────────────────────────────────────────────────────────
wp_clear_scheduled_hook( 'npom_completion_check' );
wp_clear_scheduled_hook( 'npom_process_batch' );
wp_clear_scheduled_hook( 'npom_complete_pre_order' );

// ── Transients ────────────────────────────────────────────────────────────────
global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_npom_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_npom_' ) . '%'
	)
);
// phpcs:enable
