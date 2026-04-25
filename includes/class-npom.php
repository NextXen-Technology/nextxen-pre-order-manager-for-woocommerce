<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class
 *
 * @since 1.0
 */
class NPOM {
	/**
	 * The single instance of the class.
	 *
	 * @var $_instance
	 * @since 1.13.0
	 */
	protected static $_instance = null;

	/**
	 * Plugin file path without trailing slash
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Plugin url without trailing slash
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * WC_Logger instance
	 *
	 * @var object
	 */
	private $logger;

	/**
	 * @var string __FILE__ of the base plugin file
	 */
	private $base_file;

	/**
	 * @var NPOM_Cron
	 */
	private $cron = null;

	/**
	 * @var NPOM_Manager
	 */
	private $manager = null;

	/**
	 * @var NPOM_Product
	 */
	private $product = null;

	/**
	 * @var NPOM_Cart
	 */
	private $cart = null;

	/**
	 * @var NPOM_Checkout
	 */
	private $checkout = null;

	/**
	 * @var NPOM_Order
	 */
	private $order = null;

	/**
	 * @var NPOM_Compat_Subscriptions
	 */
	private $subscriptions = null;

	/**
	 * Setup main plugin class
	 *
	 * @return \NPOM
	 * @since  1.0
	 */
	public function __construct( $base_file ) {
		$this->base_file = $base_file;

		// load core classes
		$this->load_classes();

		// load classes that require WC to be loaded
		add_action( 'woocommerce_init', array( $this, 'init' ) );

		// add pre-order notification emails
		add_filter( 'woocommerce_email_classes', array( $this, 'add_email_classes' ) );

		// add 'pay later' payment gateway
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_pay_later_gateway' ) );

		// Declare compatibility with various Woo features.
		add_action( 'before_woocommerce_init', array( $this, 'declare_feature_compatibility' ) );

		// Hook up emails
		$emails = array(
			'wc_pre_order_status_new_to_active',
			'wc_pre_order_status_completed',
			'wc_pre_order_status_active_to_cancelled',
			'npom_pre_order_date_changed',
			'npom_pre_ordered',
			'npom_pre_order_available',
		);
		foreach ( $emails as $action ) {
			add_action( $action, array( $this, 'send_transactional_email' ), 10, 2 );
			add_action(
				'woocommerce_order_action_send_email_' . $action,
				function ( $order ) use ( $action ) {
					return $this->sendmail( $action, $order );
				}
			);
		}

		// Un-schedule events on plugin deactivation
		register_deactivation_hook( $this->base_file, array( $this, 'deactivate' ) );
	}

	/**
	 * Main Instance.
	 *
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @return NPOM
	 * @since 1.5.25
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * This function will send email based on action name.
	 *
	 * @param string $email Action name
	 * @param WC_Order $order
	 */
	public function sendmail( $email, $order ) {
		// Convert action name to class name
		$email = implode( '_', array_map( 'ucfirst', explode( '_', $email ) ) );
		$email = str_replace( 'Wc_Pre_Orders_', 'NPOM_Email_', $email );

		$emails = WC()->mailer->emails;

		if ( ! isset( $emails[ $email ] ) ) {
			return;
		}

		$mail = $emails[ $email ];

		$mail->trigger( $order->get_id() );

		/* translators: %s: email title */
		$order->add_order_note( sprintf( __( '%s email notification manually sent.', 'nextxen-pre-order-manager' ), $mail->title ), false, true );
	}

	/**
	 * Load core classes
	 *
	 * @since 1.0
	 */
	public function load_classes() {
		if( is_admin() ){
			// Load admin filters.
			require 'admin/filters.php';
			include_once NPOM_PLUGIN_PATH . '/includes/class-npom-privacy.php';
		}

		// Premium gate helper – always loaded so the gate is available everywhere.
		require_once NPOM_PLUGIN_PATH . '/includes/class-npom-premium.php';

		// Premium features – only instantiated when a valid Freemius license is active.
		if ( NPOM_Premium::is_active() ) {
			require_once NPOM_PLUGIN_PATH . '/includes/premium/class-npom-quantity-limit.php';
			new NPOM_Quantity_Limit();

			require_once NPOM_PLUGIN_PATH . '/includes/premium/class-npom-deposit.php';
			new NPOM_Deposit();
		}

		// load wp-cron hooks for scheduled events
		require 'class-npom-cron.php';
		$this->cron = new NPOM_Cron();

		// load manager class to process pre-order actions
		require 'class-npom-manager.php';
		$this->manager = new NPOM_Manager();

		// load product customizations / tweaks
		require 'class-npom-product.php';
		$this->product = new NPOM_Product();

		// Load cart customizations / overrides
		require 'class-npom-cart.php';
		$this->cart = new NPOM_Cart();

		// Load checkout customizations / overrides
		require 'class-npom-checkout.php';
		$this->checkout = new NPOM_Checkout();

		// Load order hooks
		require 'class-npom-order.php';
		$this->order = new NPOM_Order();

		// Always require the subscriptions compat class so its static helper methods
		// (e.g. is_subscriptions_supported()) are available everywhere, including
		// get_supported_product_types() which runs for all users.
		// The class is only *instantiated* (hooks registered) for premium installs.
		require 'compat/class-npom-compat-subscriptions.php';
		if ( NPOM_Premium::is_active() ) {
			$this->subscriptions = new NPOM_Compat_Subscriptions();
		}

		include_once 'class-npom-my-pre-orders.php';
	}

	/**
	 * Loads the classes for the integration with WooCommerce Blocks.
	 */
	public static function load_block_classes() {

		if ( NPOM_GUTENBERG_EXISTS ) {
			require_once __DIR__ . '/blocks/class-npom-blocks-integration.php';
			require_once __DIR__ . '/blocks/class-npom-extend-store-api.php';
			new NextXen\Pre_Order_Manager\Blocks\NPOM_Blocks_Integration();
		}
	}

	/**
	 * Load actions and filters that require WC to be loaded
	 *
	 * @since 1.0
	 */
	public function init() {

		if ( is_admin() ) {
			// Load admin.
			if ( defined( 'DOING_AJAX' ) ) {
				require 'admin/class-npom-admin-ajax.php';
			} else {
				require 'admin/class-npom-admin.php';
			}

			// add a 'Configure' link to the plugin action links
			add_filter(
				'plugin_action_links_' . plugin_basename( $this->base_file ),
				array(
					$this,
					'plugin_action_links',
				)
			);
		} else {

			// Watch for cancel URL action
			add_action( 'init', array( $this->manager, 'check_cancel_pre_order' ) );

			// add countdown shortcode
			$this->add_countdown_shortcode();
		}
	}

	/**
	 * Add the 'pay later' gateway, which replaces gateways that do not support pre-orders when the pre-order is charged
	 * upon release
	 *
	 * @since 1.0
	 */
	public function add_pay_later_gateway( $gateways ) {
		require_once 'gateways/class-npom-gateway-pay-later.php';

		$gateways[] = 'NPOM_Gateway_Pay_Later';

		return $gateways;
	}

	/**
	 * Adds the countdown shortcode.
	 */
	private function add_countdown_shortcode() {
		require_once 'shortcodes/class-npom-shortcode-countdown.php';
		add_shortcode( 'npom_countdown', array( 'NPOM_Shortcode_Countdown', 'get_pre_order_countdown_shortcode_content' ) );
	}

	/**
	 * Adds Pre-Order email classes
	 *
	 * @since 1.0
	 */
	public function add_email_classes( $email_classes ) {

		foreach (
			array(
				'new-pre-order',
				'pre-order-available',
				'pre-order-cancelled',
				'admin-pre-order-cancelled',
				'pre-order-date-changed',
				'pre-ordered',
			) as $class_file_name
		) {
			require_once "emails/class-npom-email-{$class_file_name}.php";
		}

		$email_classes['NPOM_Email_New_Pre_Order']             = new NPOM_Email_New_Pre_Order();
		$email_classes['NPOM_Email_Pre_Ordered']               = new NPOM_Email_Pre_Ordered();
		$email_classes['NPOM_Email_Pre_Order_Date_Changed']    = new NPOM_Email_Pre_Order_Date_Changed();
		$email_classes['NPOM_Email_Pre_Order_Cancelled']       = new NPOM_Email_Pre_Order_Cancelled();
		$email_classes['NPOM_Email_Admin_Pre_Order_Cancelled'] = new NPOM_Email_Admin_Pre_Order_Cancelled();
		$email_classes['NPOM_Email_Pre_Order_Available']       = new NPOM_Email_Pre_Order_Available();

		return $email_classes;
	}

	/**
	 * Sends transactional email by hooking into pre-order status changes
	 *
	 * @since 1.0
	 */
	public function send_transactional_email( $args = array(), $message = '' ) {
		global $woocommerce;

		$woocommerce->mailer();

		do_action( current_filter() . '_notification', $args, $message );
	}

	/**
	 * Remove terms and scheduled events on plugin deactivation
	 *
	 * @since 1.0
	 */
	public function deactivate() {

		// Remove scheduling function before removing scheduled hook, or else it will get re-added
		remove_action( 'init', array( $this->cron, 'add_scheduled_events' ) );

		// clear pre-order completion check event
		wp_clear_scheduled_hook( 'npom_completion_check' );
	}

	/**
	 * Return the plugin action links shown on the Plugins list table.
	 *
	 * Free users see an Upgrade link; premium users see nothing extra.
	 *
	 * @param array $actions Existing action links.
	 * @return array
	 */
	public function plugin_action_links( $actions ) {
		$plugin_actions = array(
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wc-settings&tab=pre_orders' ) ),
				__( 'Settings', 'nextxen-pre-order-manager' )
			),
			'manage' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=npom_manager' ) ),
				__( 'Manage Pre-Orders', 'nextxen-pre-order-manager' )
			),
			'docs' => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( 'https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/' ),
				__( 'Docs', 'nextxen-pre-order-manager' )
			),
		);

		// Show an "Upgrade to Premium" link for free users.
		if ( function_exists( 'npom_fs' ) && ! NPOM_Premium::is_active() ) {
			$plugin_actions['upgrade'] = sprintf(
				'<a href="%s" target="_blank" style="color:#7f54b3;font-weight:700;">%s</a>',
				esc_url( npom_fs()->get_upgrade_url() ),
				__( 'Upgrade to Premium', 'nextxen-pre-order-manager' )
			);
		}

		return array_merge( $plugin_actions, $actions );
	}

	/**
	 * Returns the plugin's path without a trailing slash
	 *
	 * @return string the plugin path
	 * @since  1.0
	 *
	 */
	public function get_plugin_path() {
		if ( $this->plugin_path ) {
			return $this->plugin_path;
		}

		$this->plugin_path = untrailingslashit( plugin_dir_path( $this->base_file ) );

		return $this->plugin_path;
	}


	/**
	 * Returns the plugin's url without a trailing slash
	 *
	 * @return string the plugin url
	 * @since  1.0
	 *
	 */
	public function get_plugin_url() {
		if ( $this->plugin_url ) {
			return $this->plugin_url;
		}

		$this->plugin_url = plugins_url( basename( plugin_dir_path( $this->base_file ) ), basename( $this->base_file ) );

		return $this->plugin_url;
	}

	/**
	 * Log errors to WooCommerce log
	 *
	 * @param string $message message to log
	 *
	 * @since 1.0
	 *
	 */
	public function log( $message ) {
		global $woocommerce;

		if ( ! is_object( $this->logger ) ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->logger = new WC_Logger();
			} else {
				$this->logger = $woocommerce->logger();
			}
		}

		$this->logger->add( 'pre-orders', $message );
	}

	/**
	 * Get supported product types.
	 *
	 * @return array
	 */
	public static function get_supported_product_types() {
		$product_types = array(
			'simple',
			'variable',
			'composite',
			'bundle',
			'booking',
			'mix-and-match',
		);

		/*
		 * Pre-orders requires Subscriptions 6.2.0 or later.
		 *
		 * Pre-orders makes use of the Subscriptions filters `wcs_is_subscription_order_completed`
		 * and `wcs_setup_cart_for_subscription_initial_payment` introduced in Subscriptions 6.1.0
		 * and 6.2.0 respectively.
		 *
		 * @link https://github.com/Automattic/woocommerce-subscriptions-core/pull/579
		 * @link https://github.com/Automattic/woocommerce-subscriptions-core/pull/594
		 */
		if ( NPOM_Compat_Subscriptions::is_subscriptions_supported() ) {
			$product_types[] = 'subscription';
			$product_types[] = 'variable-subscription';
		}

		return apply_filters( 'npom_supported_product_types', $product_types );
	}

	/**
	 * Declare compatibility with various Woo features.
	 *
	 * @since 1.9.0
	 */
	public function declare_feature_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->base_file, true );

			require_once 'admin/class-npom-product-editor-compatibility.php';
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', $this->base_file, true );
		}
	}

	/**
	 * Check if High-performance Order Storage ("HPOS") enable
	 *
	 * @return bool Whether enabled or not
	 */
	public static function is_hpos_enabled() {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}
}
