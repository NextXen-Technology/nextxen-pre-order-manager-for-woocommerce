<?php
/**
 * Plugin Name:       NextXen Pre-Order Manager for WooCommerce
 * Plugin URI:        https://nextxentech.com/
 * Description:       Accept pre-orders in WooCommerce — set release dates, choose upfront or pay-on-release payment, send automated emails, and let customers reserve products before they launch. Upgrade to Premium for deposits, quantity limits, dashboard stats, CSV export, and Subscriptions support.
 * Author:            NextXen Technology
 * Author URI:        https://nextxentech.com
 * Version:           2.0.0
 * Text Domain:       nextxen-pre-order-manager
 * Domain Path:       /languages/
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * PHP tested up to:  8.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   10.6
 *
 * Copyright:         © 2026 NextXen Technology
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Documentation:     https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Freemius SDK Bootstrap ───────────────────────────────────────────────────
if ( ! function_exists( 'npom_fs' ) ) {
    // Create a helper function for easy SDK access.
    function npom_fs() {
        global $npom_fs;

        if ( ! isset( $npom_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';

            $npom_fs = fs_dynamic_init( array(
                'id'                  => '28432',
                'slug'                => 'nextxen-pre-order-manager',
                'premium_slug'        => 'nextxen-pre-order-manager-pro',
                'type'                => 'plugin',
                'public_key'          => 'pk_cc418a15afefd037ba3e4ac9bd17c',
                'is_premium'          => false,
                'premium_suffix'      => 'Pro',
                // If your plugin is a serviceware, set this option to false.
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'is_org_compliant'    => true,
                // Automatically removed in the free version. If you're not using the
                // auto-generated free version, delete this line before uploading to wp.org.
                'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
                'trial'               => array(
                    'days'               => 14,
                    'is_require_payment' => false,
                ),
                'menu'                => array(
                    'support' => false,
                ),
            ) );
        }

        return $npom_fs;
    }

    // Init Freemius.
    npom_fs();
    // Signal that SDK was initiated.
    do_action( 'npom_fs_loaded' );
}

// ── Freemius uninstall cleanup (replaces uninstall.php) ───────────────────────
function npom_fs_uninstall_cleanup() {
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

    wp_clear_scheduled_hook( 'npom_completion_check' );
    wp_clear_scheduled_hook( 'npom_process_batch' );
    wp_clear_scheduled_hook( 'npom_complete_pre_order' );

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
}
npom_fs()->add_action( 'after_uninstall', 'npom_fs_uninstall_cleanup' );
// ─────────────────────────────────────────────────────────────────────────────

/**
 * WooCommerce fallback notice.
 *
 * @since 1.0.1
 */
function npom_missing_wc_notice() {
	/* translators: %s WC download URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Pre-orders require WooCommerce to be installed and active. You can download %s here.', 'nextxen-pre-order-manager' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

// When plugin is activated.
register_activation_hook( __FILE__, 'npom_activate' );

/**
 * Actions to perform when plugin is activated.
 *
 * @since 1.4.7
 */
function npom_activate() {
	add_rewrite_endpoint( 'pre-orders', EP_ROOT | EP_PAGES );
	flush_rewrite_rules();
}

if ( ! class_exists( 'NPOM' ) ) :
	define( 'NPOM_VERSION', '2.0.0' );
	define( 'NPOM_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
	define( 'NPOM_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ), basename( __FILE__ ) ) ) ) );
	define( 'NPOM_GUTENBERG_EXISTS', function_exists( 'register_block_type' ) ? true : false );
	require 'includes/class-npom.php';
endif;

/**
 * Initializes the extension.
 *
 * @return Object Instance of the extension.
 * @since 1.0.1
 */
function npom_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'npom_missing_wc_notice' );

		return;
	}

	$GLOBALS['npom_manager'] = new NPOM( __FILE__ );
}

add_action( 'plugins_loaded', 'npom_init' );

add_filter( 'woocommerce_translations_updates_for_' . basename( __FILE__, '.php' ), '__return_true' );

/**
 * Loads the classes for the integration with WooCommerce Blocks.
 */
function npom_load_block_classes() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) && version_compare( \Automattic\WooCommerce\Blocks\Package::get_version(), '6.5.0', '>' ) ) {
		\NPOM::load_block_classes();
	}
}

add_action( 'woocommerce_blocks_loaded', 'npom_load_block_classes' );
