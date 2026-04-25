<?php
/**
 * WooCommerce Pre-Order Manager
 *
 * @package     NPOM/Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Pre-Orders Cart class
 *
 * Customizes the cart
 *
 * @since 1.0
 */
class NPOM_Cart {


	/**
	 * Add hooks / filters
	 *
	 * @since 1.0
	 * @return NPOM_Cart
	 */
	public function __construct() {

		// Remove other products from the cart when adding a pre-order
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_cart' ), 15, 2 );

		// Maybe add pre-order fees when calculating totals
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'maybe_add_pre_order_fee' ) );

		// Modify formatted totals
		add_filter( 'woocommerce_cart_total', array( $this, 'get_formatted_cart_total' ) );

		// Modify line item display in cart/checkout to show availability date/time
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );

		// Clear pre-order meta data when an item is removed from the cart.
		add_action( 'woocommerce_cart_item_removed', array( $this, 'clear_order_meta_when_item_removed_from_cart' ) );
	}

	/**
	 * Clears the pre-order meta data stored in an order when an item is removed from the cart.
	 *
	 * This is to ensure that adding a non-preorder item to the cart after the
	 * pre-order is removed doesn't cause the order to be treated as a pre-order.
	 *
	 * @see https://github.com/woocommerce/woocommerce-pre-order-manager/issues/434
	 */
	public function clear_order_meta_when_item_removed_from_cart() {
		$current_session_order_id = isset( WC()->session->order_awaiting_payment ) ? absint( WC()->session->order_awaiting_payment ) : absint( WC()->session->get( 'store_api_draft_order', 0 ) );

		if ( $current_session_order_id && ! self::cart_contains_pre_order() ) {
			$order = wc_get_order( $current_session_order_id );
			if ( ! $order ) {
				// Order stored in session data does not exist.
				return;
			}
			$order->delete_meta_data( '_npom_is_pre_order' );
			$order->delete_meta_data( '_npom_when_charged' );
			$order->save();
		}
	}

	/**
	 * Get the order total formatted to show when the order will be charged
	 *
	 * @since 1.0
	 * @param string $total price string ( note: this is already formatted by woocommerce_price() )
	 * @return string the formatted order total price string
	 */
	public function get_formatted_cart_total( $total ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary -- needed for recurring totals in subs.
		$backtrace = wp_debug_backtrace_summary( null, 0, false );
		/*
		 * Do nothing for the recurring totals on the cart or checkout (shortcode).
		 *
		 * This prevents the formatted total from displaying the start date in
		 * the recurring totals during the checkout flow. The recurring totals are
		 * handled by Woo Subscriptions.
		 */
		if ( in_array( 'wcs_cart_totals_order_total_html', $backtrace, true ) ) {
			return $total;
		}

		// this check prevents a formatted total from display anywhere but the cart/checkout page
		if ( $this->cart_contains_pre_order() ) {
			$total = NPOM_Manager::get_formatted_pre_order_total( $total, self::get_pre_order_product() );
		}

		return $total;
	}


	/**
	 * Get item data to display on cart/checkout pages that shows the availability date of the pre-order
	 *
	 * @since 1.0
	 * @param array $item_data any existing item data
	 * @param array $cart_item the cart item
	 * @return array
	 */
	public function get_item_data( $item_data, $cart_item ) {

		// only modify pre-orders on cart/checkout page
		if ( ! $this->cart_contains_pre_order() ) {
			return $item_data;
		}

		// get title text
		$name = get_option( 'npom_availability_date_cart_title_text' );

		// don't add if empty
		if ( ! $name ) {
			return $item_data;
		}

		$pre_order_meta = apply_filters(
			'npom_cart_item_meta',
			array(
				'name'    => $name,
				'display' => NPOM_Product::get_localized_availability_date( $cart_item['data'] ),
			),
			$cart_item
		);

		// add title and localized date
		if ( ! empty( $pre_order_meta ) ) {
			$item_data[] = $pre_order_meta;
		}

		return $item_data;
	}

	/**
	 * Redirect to the cart
	 */
	public function redirect_to_cart() {

		$data = array(
			'error'       => true,
			'product_url' => wc_get_cart_url(),
		);

		wp_send_json( $data );
	}

	/**
	 * When a pre-order is added to the cart, remove any other products
	 *
	 * @since 1.0
	 * @param bool $valid
	 * @param $product_id
	 * @return bool
	 */
	public function validate_cart( $valid, $product_id ) {
		global $woocommerce;

		if ( NPOM_Product::product_can_be_pre_ordered( $product_id ) ) {

			// if a pre-order product is being added to cart, check if the cart already contains other products and empty it if it does
			if ( $woocommerce->cart->get_cart_contents_count() >= 1 ) {

				$woocommerce->cart->empty_cart();

				$string = __( 'Your previous cart was emptied because pre-orders must be purchased separately.', 'nextxen-pre-order-manager' );

				// Backwards compatible (pre 2.1) for outputting notice
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( $string );
				} else {
					$woocommerce->add_message( $string );
				}

				// when adding via ajax, redirect to cart page so that above notices show up
				add_action( 'woocommerce_ajax_added_to_cart', array( $this, 'redirect_to_cart' ) );
			}

			// return what was passed in, allowing the pre-order to be added
			return $valid;

		} else {

			// if there's a pre-order in the cart already, prevent anything else from being added
			if ( $this->cart_contains_pre_order() ) {

				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( __( 'This product cannot be added to your cart because it already contains a pre-order, which must be purchased separately.', 'nextxen-pre-order-manager' ), 'error' );
				} else {
					// Backwards compatible (pre 2.1) for outputting notice.
					$woocommerce->add_error( __( 'This product cannot be added to your cart because it already contains a pre-order, which must be purchased separately.', 'nextxen-pre-order-manager' ) );
				}

				$valid = false;
			}
		}

		return $valid;
	}


	/**
	 * Add any applicable pre-order fees when calculating totals
	 *
	 * @since 1.0
	 */
	public function maybe_add_pre_order_fee() {
		global $woocommerce;

		// Only add pre-order fees if the cart contains a pre-order
		if ( ! $this->cart_contains_pre_order() ) {
			return;
		}

		// Make sure the pre-order fee hasn't already been added
		if ( $this->cart_contains_pre_order_fee() ) {
			return;
		}

		$product = self::get_pre_order_product();
		$fee     = $this->generate_fee( $product );

		if ( null !== $fee ) {
			$woocommerce->cart->add_fee( $fee['label'], $fee['amount'], $fee['tax_status'] );
		}

	}

	/**
	 * Generates fee
	 *
	 * @since 1.6.0
	 * @param WC_Product|int $product
	 * @return array|null
	 */
	public function generate_fee( $product ) {

		// Get pre-order amount
		$amount = NPOM_Product::get_pre_order_fee( $product );

		if ( 0 >= $amount ) {
			return;
		}

		return apply_filters(
			'npom_fee',
			array(
				'label'      => __( 'Pre-order fee', 'nextxen-pre-order-manager' ),
				'amount'     => $amount,
				'tax_status' => NPOM_Product::get_pre_order_fee_tax_status( $product ), // pre order fee inherits tax status of product
			)
		);
	}

	/**
	 * Checks if the current cart contains a product with pre-orders enabled
	 *
	 * @since 1.0
	 * @return bool true if the cart contains a pre-order, false otherwise
	 */
	public static function cart_contains_pre_order() {
		global $woocommerce;

		$contains_pre_order = false;

		if ( ! empty( $woocommerce->cart->cart_contents ) ) {

			foreach ( $woocommerce->cart->cart_contents as $cart_item ) {
				$product_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
				if ( NPOM_Product::product_can_be_pre_ordered( $product_id ) ) {

					$contains_pre_order = true;
					break;
				}
			}
		}

		return $contains_pre_order;
	}


	/**
	 * Checks if the current cart contains a pre-order fee
	 *
	 * @since 1.0
	 * @return bool true if the cart contains a pre-order fee, false otherwise
	 */
	public static function cart_contains_pre_order_fee() {
		global $woocommerce;

		foreach ( $woocommerce->cart->get_fees() as $fee ) {

			if ( is_object( $fee ) && 'pre-order-fee' == $fee->id ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Since a cart may only contain a single pre-ordered product, this returns the pre-ordered product object or
	 * null if the cart does not contain a pre-order
	 *
	 * @since 1.0
	 * @return object|null the pre-ordered product object, or null if the cart does not contain a pre-order
	 */
	public static function get_pre_order_product() {
		global $woocommerce;

		if ( self::cart_contains_pre_order() ) {

			foreach ( $woocommerce->cart->cart_contents as $cart_item ) {

				$ordered_product_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];

				if ( NPOM_Product::product_can_be_pre_ordered( $ordered_product_id ) ) {

					// return the product object
					return wc_get_product( $ordered_product_id );
				}
			}
		} else {

			// cart doesn't contain pre-order
			return null;
		}
	}

} // end \NPOM_Cart class
