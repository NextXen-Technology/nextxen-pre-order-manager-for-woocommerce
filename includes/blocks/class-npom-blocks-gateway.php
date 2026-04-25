<?php
/**
 * WooCommerce Pre-Order Manager Payment Gateway for WooCommerce Blocks.
 *
 * A class to extend the payment methods type class provided by WooCommerce Blocks.
 *
 * @package WooCommerce Pre-orders
 */

namespace NextXen\Pre_Order_Manager\Blocks;

use Automattic\WooCommerce\Blocks\Assets\Api;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use NPOM_Cart;
use NPOM_Compat_Subscriptions;

/**
 * Pre-orders Gateway method integration.
*/
final class NPOM_Blocks_Gateway extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'pre_orders_pay_later';

	/**
	 * An instance of the Asset Api.
	 *
	 * @var Api
	 */
	private $asset_api;

	/**
	 * Is the payment method enabled.
	 *
	 * @var Api
	 */
	private $enabled;

	/**
	 * Constructor
	 *
	 * @param Api $asset_api An instance of Api.
	 */
	public function __construct( Api $asset_api ) {
		$this->asset_api = $asset_api;
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {

		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$is_enabled = isset( $payment_gateways['pre_orders_pay_later'] ) ? $payment_gateways['pre_orders_pay_later']->enabled : 'no';

		$this->enabled = $is_enabled;
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {

		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$base_path  = NPOM_PLUGIN_PATH;
		$asset_url  = NPOM_PLUGIN_URL . '/build/gateway/index.js';
		$css_url    = NPOM_PLUGIN_URL . '/build/gateway/index.css';
		$version    = NPOM_VERSION;
		$asset_path = $base_path . '/build/gateway/index.asset.php';

		$dependencies = array();
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}

		wp_register_script(
			'pre_orders_pay_later',
			$asset_url,
			$dependencies,
			$version,
			true
		);

		wp_enqueue_style(
			'pre_orders_pay_later_css',
			$css_url,
			array(),
			$version
		);

		return array( 'pre_orders_pay_later' );
	}

	/**
	 * Gets payment method supported features.
	 *
	 * @return array
	 */
	public function get_supported_features() {
		$features = array( 'products', 'pre-orders' );
		if ( NPOM_Compat_Subscriptions::is_subscriptions_supported() ) {
			$features[] = 'subscriptions';
		}
		return $features;
	}

	/**
	 * Get the options for the pre-orders pay later gateway.
	 *
	 * If an option name is provided, it will return the value of that option.
	 * If no option name is provided, it will return all options as an array.
	 *
	 * @since 2.2.8
	 *
	 * @param string|null $option_name The name of the option to retrieve.
	 * @return string|array The value of the option if $option_name is provided, or an array of all options if not.
	 */
	public function get_option( $option_name = null ) {
		$options = get_option( 'npom_pay_later_settings', array() );
		if ( is_null( $option_name ) ) {
			return $options;
		}

		if ( isset( $options[ $option_name ] ) ) {
			return $options[ $option_name ];
		}

		return '';
	}

	/**
	 * Get the title text for the payment method.
	 *
	 * @since 2.2.8
	 *
	 * @return string The title text.
	 */
	public function get_title_text() {
		$default = __( 'Pay later', 'nextxen-pre-order-manager' );
		$title   = $this->get_option( 'title' );

		if ( $title ) {
			return $title;
		}
		return $default;
	}

	/**
	 * Get the description text for the payment method.
	 *
	 * @since 2.2.8
	 *
	 * @return string The description text.
	 */
	public function get_description_text() {
		$default     = __( 'You will receive an email when the pre-order is available along with instructions on how to complete your order.', 'nextxen-pre-order-manager' );
		$description = $this->get_option( 'description' );

		if ( $description ) {
			return $description;
		}
		return $default;
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'                   => wp_strip_all_tags( $this->get_title_text() ),
			'description'             => wp_strip_all_tags( $this->get_description_text() ),
			'supports'                => $this->get_supported_features(),
			'is_enabled'              => NPOM_Blocks_Integration::is_pre_order_and_charged_upon_release(),
			'cart_contains_pre_order' => NPOM_Cart::cart_contains_pre_order(),
		);
	}
}
