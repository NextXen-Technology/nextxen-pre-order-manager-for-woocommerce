<?php
/**
 * WooCommerce Pre-Order Manager – Quantity Limit (Premium Feature)
 *
 * Allows store admins to cap the number of pre-orders accepted per product.
 * When the cap is reached:
 *   - Further add-to-cart attempts are blocked with a clear notice.
 *   - A "Only X slots remaining!" warning is shown on the product page when
 *     fewer than 10 slots are left.
 *   - Pre-orders are auto-closed once the cap is hit.
 *
 * @package NPOM/Premium
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NPOM_Quantity_Limit
 */
class NPOM_Quantity_Limit {

	/**
	 * Low-stock threshold: show a warning when remaining slots fall at or below this number.
	 */
	const LOW_SLOTS_THRESHOLD = 10;

	/**
	 * Product meta key for the maximum allowed pre-order quantity.
	 */
	const META_KEY = '_npom_max_quantity';

	/**
	 * Boot hooks.
	 */
	public function __construct() {
		// Validate cart before a pre-order is added.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_quantity_limit' ), 20, 2 );

		// Show remaining slots on the single-product page (after the availability message).
		add_action( 'woocommerce_single_product_summary', array( $this, 'show_remaining_slots' ), 12 );

		// Render the admin field inside the Pre-Orders product tab.
		add_action( 'npom_product_options_end', array( $this, 'render_admin_field' ) );

		// Persist the admin field when the product is saved.
		// The action fires with 3 args: $post_id, $is_enabled, $timestamp.
		add_action( 'npom_save_product_options', array( $this, 'save_admin_field' ), 10, 3 );
	}

	// -------------------------------------------------------------------------
	// Cart validation
	// -------------------------------------------------------------------------

	/**
	 * Prevent adding to cart when the pre-order slot limit has been reached.
	 *
	 * @param bool $valid      Whether the add-to-cart action is currently valid.
	 * @param int  $product_id The ID of the product being added.
	 * @return bool
	 */
	public function validate_quantity_limit( $valid, $product_id ) {
		if ( ! $valid ) {
			return $valid;
		}

		if ( ! NPOM_Product::product_can_be_pre_ordered( $product_id ) ) {
			return $valid;
		}

		$max = $this->get_max_quantity( $product_id );

		if ( ! $max ) {
			return $valid; // No limit configured – allow.
		}

		$count = self::get_active_pre_order_count( $product_id );

		if ( $count >= $max ) {
			wc_add_notice(
				__( 'Sorry, this pre-order is no longer available. The maximum number of pre-orders has been reached.', 'nextxen-pre-order-manager' ),
				'error'
			);
			return false;
		}

		return $valid;
	}

	// -------------------------------------------------------------------------
	// Front-end: remaining-slots notice
	// -------------------------------------------------------------------------

	/**
	 * Display a "Only X slots remaining!" notice on the single-product page.
	 * Only shown when the limit is configured and fewer than LOW_SLOTS_THRESHOLD
	 * slots remain.
	 */
	public function show_remaining_slots() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! NPOM_Product::product_can_be_pre_ordered( $product ) ) {
			return;
		}

		$max = $this->get_max_quantity( $product->get_id() );

		if ( ! $max ) {
			return;
		}

		$count     = self::get_active_pre_order_count( $product->get_id() );
		$remaining = max( 0, $max - $count );

		if ( $remaining <= 0 ) {
			echo '<p class="npom-slots-remaining npom-slots-sold-out">'
				. esc_html__( 'Pre-orders are now closed for this product.', 'nextxen-pre-order-manager' )
				. '</p>';
		} elseif ( $remaining <= self::LOW_SLOTS_THRESHOLD ) {
			echo '<p class="npom-slots-remaining npom-slots-low">'
				. esc_html(
					sprintf(
						/* translators: %d: number of remaining pre-order slots */
						_n(
							'Only %d pre-order slot remaining – order soon!',
							'Only %d pre-order slots remaining – order soon!',
							$remaining,
							'nextxen-pre-order-manager'
						),
						$remaining
					)
				)
				. '</p>';
		}
	}

	// -------------------------------------------------------------------------
	// Admin field
	// -------------------------------------------------------------------------

	/**
	 * Render the "Max pre-orders" text input inside the Pre-Orders product tab.
	 * Hooked to npom_product_options_end.
	 */
	public function render_admin_field() {
		global $post;

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_KEY,
				'label'             => __( 'Max pre-orders (optional)', 'nextxen-pre-order-manager' ),
				'description'       => __( 'Maximum number of pre-orders accepted for this product. Leave blank or 0 for unlimited.', 'nextxen-pre-order-manager' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'value'             => get_post_meta( $post->ID, self::META_KEY, true ),
				'placeholder'       => __( 'Unlimited', 'nextxen-pre-order-manager' ),
			)
		);
	}

	/**
	 * Persist the "Max pre-orders" value when the product is saved.
	 *
	 * Nonce is verified upstream by WooCommerce's woocommerce_process_product_meta_* hook
	 * before do_action( 'npom_save_product_options' ) fires.
	 *
	 * @param int      $post_id    Product post ID.
	 * @param bool     $is_enabled Whether pre-orders are enabled for this product.
	 * @param int|false $timestamp  Availability timestamp, or false if not set.
	 */
	public function save_admin_field( $post_id, $is_enabled, $timestamp ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce upstream.
		$max = isset( $_POST[ self::META_KEY ] ) ? absint( $_POST[ self::META_KEY ] ) : 0;
		update_post_meta( $post_id, self::META_KEY, $max );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the configured maximum pre-order quantity for a product (0 = unlimited).
	 *
	 * @param int $product_id
	 * @return int
	 */
	public function get_max_quantity( $product_id ) {
		return absint( get_post_meta( $product_id, self::META_KEY, true ) );
	}

	/**
	 * Count the number of currently active pre-orders for a given product.
	 *
	 * Supports both classic post-meta storage and HPOS (custom order tables).
	 *
	 * @param int $product_id
	 * @return int
	 */
	public static function get_active_pre_order_count( $product_id ) {
		global $wpdb;

		$product_id = absint( $product_id );

		if ( NPOM::is_hpos_enabled() ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT orders.id)
					FROM {$wpdb->prefix}wc_orders            AS orders
					INNER JOIN {$wpdb->prefix}woocommerce_order_items    AS items
						ON orders.id = items.order_id
					INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS item_meta
						ON items.order_item_id = item_meta.order_item_id
					INNER JOIN {$wpdb->prefix}wc_orders_meta             AS order_meta
						ON orders.id = order_meta.order_id
					WHERE items.order_item_type       = 'line_item'
					  AND item_meta.meta_key          = '_product_id'
					  AND item_meta.meta_value        = %d
					  AND order_meta.meta_key         = '_npom_status'
					  AND order_meta.meta_value       = 'active'",
					$product_id
				)
			);
		} else {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT posts.ID)
					FROM {$wpdb->posts}                              AS posts
					INNER JOIN {$wpdb->prefix}woocommerce_order_items    AS items
						ON posts.ID = items.order_id
					INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS item_meta
						ON items.order_item_id = item_meta.order_item_id
					INNER JOIN {$wpdb->postmeta}                         AS order_meta
						ON posts.ID = order_meta.post_id
					WHERE items.order_item_type  = 'line_item'
					  AND item_meta.meta_key     = '_product_id'
					  AND item_meta.meta_value   = %d
					  AND order_meta.meta_key    = '_npom_status'
					  AND order_meta.meta_value  = 'active'",
					$product_id
				)
			);
		}

		return (int) $count;
	}
}
