<?php
/**
 * WooCommerce Pre-Order Manager
 *
 * @package   NPOM/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pre-Orders Admin Settings class.
 */
class NPOM_Admin_Settings {

	/**
	 * Settings page tab ID
	 *
	 * @var string
	 */
	private $settings_tab_id = 'pre_orders';

	/**
	 * Initialize the admin settings actions.
	 */
	public function __construct() {
		// Add 'Pre-Orders' tab to WooCommerce settings.
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 21, 1 );

		// Show settings.
		add_action( 'woocommerce_settings_tabs_' . $this->settings_tab_id, array( $this, 'show_settings' ) );

		// Save settings.
		add_action( 'woocommerce_update_options_' . $this->settings_tab_id, array( $this, 'save_settings' ) );
	}

	/**
	 * Add 'Pre-Orders' tab to WooCommerce Settings tabs
	 *
	 * @param  array $settings_tabs Tabs array sans 'Pre-Orders' tab.
	 *
	 * @return array $settings_tabs Now with 100% more 'Pre-Orders' tab!
	 */
	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs[ $this->settings_tab_id ] = __( 'Pre-Orders', 'nextxen-pre-order-manager' );

		return $settings_tabs;
	}

	/**
	 * Show the 'Pre-Orders' settings page.
	 */
	public function show_settings() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	/**
	 * Save the 'Pre-Orders' settings page.
	 */
	public function save_settings() {
		woocommerce_update_options( $this->get_settings() );
	}

	/**
	 * Returns settings array for use by output/save functions.
	 *
	 * @return array Settings.
	 */
	public function get_settings() {
		/**
		 * Filter the settings array.
		 *
		 * @since 1.0.0
		 * @param array $settings Settings.
		 */
		return apply_filters(
			'npom_settings',
			array(
				// Common actions section
				array(
					'title' => __( 'Common actions', 'nextxen-pre-order-manager' ),
					'type'  => 'title',
					'desc'  => sprintf(
						'<a href="%s" class="button button-secondary" target="_blank">%s</a>',
						esc_url( admin_url( 'admin.php?page=npom_manager&tab=manage' ) ),
						__( 'View All Pre-Orders', 'nextxen-pre-order-manager' )
					),
				),
				array( 'type' => 'sectionend' ),

				// Button text customizations
				array(
					'title' => __( 'Button text customization', 'nextxen-pre-order-manager' ),
					'type'  => 'title',
				),
				array(
					'title'    => __( '"Add to cart" button text', 'nextxen-pre-order-manager' ),
					'desc'     => __( 'Text displayed on the "Add to cart" button when a product is available for pre-order.', 'nextxen-pre-order-manager' ),
					'desc_tip' => true,
					'id'       => 'npom_add_to_cart_button_text',
					'default'  => __( 'Pre-order now', 'nextxen-pre-order-manager' ),
					'type'     => 'text',
				),
				array(
					'title'    => __( '"Place order" button text', 'nextxen-pre-order-manager' ),
					'desc'     => __( 'Text displayed on the "Place order" button when a customer is checking out with pre-ordered products.', 'nextxen-pre-order-manager' ),
					'desc_tip' => true,
					'id'       => 'npom_place_order_button_text',
					'default'  => __( 'Place pre-order now', 'nextxen-pre-order-manager' ),
					'type'     => 'text',
				),
				array( 'type' => 'sectionend' ),

				// Product messages
				array(
					'title' => __( 'Messages customization', 'nextxen-pre-order-manager' ),
					/* translators: %1$s: Opening code tag %2$s: Closing code tag */
					'desc'  => sprintf( __( 'Use %1$s{availability_date}%2$s and %1$s{availability_time}%2$s to display when products will be available.', 'nextxen-pre-order-manager' ), '<code>', '</code>' ),
					'type'  => 'title',
				),
				array(
					'title'    => __( 'Product page message', 'nextxen-pre-order-manager' ),
					'desc'     => __( 'Message displayed below the price on product pages. Use {availability_date} to show the release date. Basic HTML is allowed. Leave empty to hide.', 'nextxen-pre-order-manager' ),
					'desc_tip' => true,
					'id'       => 'npom_single_product_message',
					/* translators: %s: Availability date placeholder */
					'default'  => sprintf( __( 'This item will be released %s.', 'nextxen-pre-order-manager' ), '{availability_date}' ),
					'type'     => 'textarea',
				),
				array(
					'title'    => __( 'Product list message', 'nextxen-pre-order-manager' ),
					'desc'     => __( 'Message displayed under products in the product list. Use {availability_date} to show the release date. Basic HTML is allowed. Leave empty to hide.', 'nextxen-pre-order-manager' ),
					'desc_tip' => true,
					'id'       => 'npom_shop_loop_product_message',
					/* translators: %s: Availability date placeholder */
					'default'  => sprintf( __( 'Available %s.', 'nextxen-pre-order-manager' ), '{availability_date}' ),
					'type'     => 'textarea',
				),
				array( 'type' => 'sectionend' ),

				// Cart and checkout text
				array(
					'title' => __( 'Cart and checkout text', 'nextxen-pre-order-manager' ),
					/* translators: %1$s: Opening code tag %2$s: Closing code tag */
					'desc'  => sprintf( __( 'Use %1$s{order_total}%2$s for the order total and %1$s{availability_date}%2$s for the product release date.', 'nextxen-pre-order-manager' ), '<code>', '</code>' ),
					'type'  => 'title',
				),
				array(
					'title'    => __( 'Release date label', 'nextxen-pre-order-manager' ),
					'desc'     => __( 'Label for the release date in the cart. Leave empty to hide the date.', 'nextxen-pre-order-manager' ),
					'desc_tip' => true,
					'id'       => 'npom_availability_date_cart_title_text',
					'default'  => __( 'Available', 'nextxen-pre-order-manager' ),
					'type'     => 'text',
				),
				array(
					'title'    => __( 'Charged upon release text', 'nextxen-pre-order-manager' ),
					'desc'     => __( 'Text for orders charged on release date (pay later). Use {order_total} and {availability_date} to show details.', 'nextxen-pre-order-manager' ),
					'desc_tip' => true,
					'id'       => 'npom_upon_release_order_total_format',
					/* translators: %1$s: Order total placeholder %2$s: Availability date placeholder */
					'default'  => sprintf( __( '%1$s charged %2$s', 'nextxen-pre-order-manager' ), '{order_total}', '{availability_date}' ),
					'css'      => 'min-width: 300px;',
					'type'     => 'text',
				),
				array(
					'title'    => __( 'Charged upfront text', 'nextxen-pre-order-manager' ),
					'desc'     => __( 'Text for orders charged now (pay now). Use {order_total} to show the price.', 'nextxen-pre-order-manager' ),
					'desc_tip' => true,
					'id'       => 'npom_upfront_order_total_format',
					/* translators: %s: Order total placeholder */
					'default'  => sprintf( __( '%s charged upfront', 'nextxen-pre-order-manager' ), '{order_total}' ),
					'css'      => 'min-width: 150px;',
					'type'     => 'text',
				),
				array( 'type' => 'sectionend' ),

				// Out-of-stock settings
				array(
					'title' => __( 'Out-of-stock products', 'nextxen-pre-order-manager' ),
					'type'  => 'title',
				),
				array(
					'title'    => __( 'Enable pre-orders for out-of-stock products', 'nextxen-pre-order-manager' ),
					'desc'     => __( 'Automatically enable pre-orders when a compatible product becomes out of stock', 'nextxen-pre-order-manager' ),
					'desc_tip' => __( 'Only product types compatible with Pre-Orders (simple, variable, composite, bundle, booking, mix-and-match and subscription) will be affected. For variable products, all variations must be out of stock. Compatible out-of-stock products will be marked as "in stock" with stock management disabled.', 'nextxen-pre-order-manager' ),
					'id'       => 'npom_auto_pre_order_out_of_stock',
					'default'  => 'no',
					'type'     => 'checkbox',
					'class'    => '',
				),
				array( 'type' => 'sectionend' ),

				// Test site settings
				array(
					'title' => __( 'Test site settings', 'nextxen-pre-order-manager' ),
					'type'  => 'title',
				),
				array(
					'title'    => __( 'Disable automatic pre-order processing', 'nextxen-pre-order-manager' ),
					'desc'     => __( 'Prevent pre-orders from processing automatically (for test sites). Your system will not charge customers or complete orders.', 'nextxen-pre-order-manager' ),
					'desc_tip' => false,
					'id'       => 'npom_disable_auto_processing',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array( 'type' => 'sectionend' ),
			)
		);
	}
}

new NPOM_Admin_Settings();
