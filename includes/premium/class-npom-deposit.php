<?php
/**
 * WooCommerce Pre-Order Manager – Deposit (Premium Feature)
 *
 * Lets store admins collect a partial deposit at checkout instead of the full
 * product price.  The remaining balance is collected automatically when the
 * pre-order is released by creating a dedicated follow-up order.
 *
 * Flow:
 *   1. Admin enables deposit on the product and sets a fixed amount or a %.
 *   2. Customer's cart item price is reduced to the deposit amount at checkout.
 *   3. The original price, deposit paid and remaining balance are stored on the order.
 *   4. When the pre-order is completed (released), a new WooCommerce order is
 *      created for the remaining balance and the customer is notified by email.
 *
 * Product meta keys:
 *   _npom_deposit_type   – 'none' | 'fixed' | 'percent'
 *   _npom_deposit_value  – numeric value (amount or %)
 *
 * Order meta keys:
 *   _npom_deposit_enabled   – '1'
 *   _npom_deposit_paid      – float (deposit charged)
 *   _npom_original_price    – float (full product price)
 *   _npom_remaining_balance – float (still owed)
 *
 * @package NPOM/Premium
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NPOM_Deposit
 */
class NPOM_Deposit {

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	const TYPE_NONE    = 'none';
	const TYPE_FIXED   = 'fixed';
	const TYPE_PERCENT = 'percent';

	const META_TYPE      = '_npom_deposit_type';
	const META_VALUE     = '_npom_deposit_value';
	const META_ENABLED   = '_npom_deposit_enabled';
	const META_PAID      = '_npom_deposit_paid';
	const META_ORIGINAL  = '_npom_original_price';
	const META_REMAINING = '_npom_remaining_balance';

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	public function __construct() {
		// Admin: render and save the deposit fields in the product tab.
		add_action( 'npom_product_options_end', array( $this, 'render_admin_fields' ) );
		// The action fires with 3 args: $post_id, $is_enabled, $timestamp.
		add_action( 'npom_save_product_options', array( $this, 'save_admin_fields' ), 10, 3 );

		// Cart: replace the product price with the deposit amount.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_deposit_price' ), 20 );

		// Cart item meta: show "Deposit – X% of full price" to the customer.
		add_filter( 'npom_cart_item_meta', array( $this, 'add_deposit_cart_meta' ), 10, 2 );

		// Checkout: store deposit meta on the created order.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_deposit_meta' ), 10, 2 );

		// Release: create a remaining-balance follow-up order when the pre-order completes.
		add_action( 'npom_pre_order_completed', array( $this, 'handle_remaining_balance' ), 10, 1 );

		// Order: display deposit info inside the order admin detail screen.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_deposit_info_in_order' ), 10, 1 );
	}

	// -------------------------------------------------------------------------
	// Admin fields
	// -------------------------------------------------------------------------

	/**
	 * Render deposit type and value fields inside the Pre-Orders product tab.
	 */
	public function render_admin_fields() {
		global $post;

		$type  = get_post_meta( $post->ID, self::META_TYPE, true ) ?: self::TYPE_NONE;
		$value = get_post_meta( $post->ID, self::META_VALUE, true );

		// Deposit type selector.
		woocommerce_wp_select(
			array(
				'id'          => self::META_TYPE,
				'label'       => __( 'Deposit type', 'nextxen-pre-order-manager' ),
				'description' => __( 'Collect a partial deposit at checkout. The remaining balance will be collected via a follow-up order when the pre-order is released.', 'nextxen-pre-order-manager' ),
				'desc_tip'    => true,
				'options'     => array(
					self::TYPE_NONE    => __( 'No deposit (full price)', 'nextxen-pre-order-manager' ),
					self::TYPE_FIXED   => sprintf(
						/* translators: %s: currency symbol */
						__( 'Fixed amount (%s)', 'nextxen-pre-order-manager' ),
						get_woocommerce_currency_symbol()
					),
					self::TYPE_PERCENT => __( 'Percentage of product price (%)', 'nextxen-pre-order-manager' ),
				),
				'value'       => $type,
			)
		);

		// Deposit value input (shown/hidden by JS based on deposit type).
		woocommerce_wp_text_input(
			array(
				'id'                => self::META_VALUE,
				'label'             => __( 'Deposit amount / percentage', 'nextxen-pre-order-manager' ),
				'description'       => __( 'Enter the deposit amount (e.g. 20.00) or percentage (e.g. 30 for 30%).', 'nextxen-pre-order-manager' ),
				'desc_tip'          => true,
				'class'             => 'short wc_input_price',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
				'value'             => wc_format_localized_decimal( $value ),
				'placeholder'       => '0',
				'wrapper_class'     => self::TYPE_NONE === $type ? 'hidden' : '',
			)
		);

		// Inline JS to toggle value field visibility.
		?>
		<script type="text/javascript">
		( function( $ ) {
			var typeSelect  = $( '#<?php echo esc_js( self::META_TYPE ); ?>' );
			var valueWrap   = $( '#<?php echo esc_js( self::META_VALUE ); ?>' ).closest( '.form-field' );

			function toggleDepositValue() {
				if ( typeSelect.val() === '<?php echo esc_js( self::TYPE_NONE ); ?>' ) {
					valueWrap.hide();
				} else {
					valueWrap.show();
				}
			}

			toggleDepositValue();
			typeSelect.on( 'change', toggleDepositValue );
		} )( jQuery );
		</script>
		<?php
	}

	/**
	 * Persist deposit fields when the product is saved.
	 *
	 * Nonce is verified upstream by WooCommerce's woocommerce_process_product_meta_* hook.
	 *
	 * @param int       $post_id    Product post ID.
	 * @param bool      $is_enabled Whether pre-orders are enabled.
	 * @param int|false $timestamp  Availability timestamp, or false if not set.
	 */
	public function save_admin_fields( $post_id, $is_enabled, $timestamp ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$type = isset( $_POST[ self::META_TYPE ] ) ? sanitize_key( $_POST[ self::META_TYPE ] ) : self::TYPE_NONE;
		if ( ! in_array( $type, array( self::TYPE_NONE, self::TYPE_FIXED, self::TYPE_PERCENT ), true ) ) {
			$type = self::TYPE_NONE;
		}
		update_post_meta( $post_id, self::META_TYPE, $type );

		$value = isset( $_POST[ self::META_VALUE ] ) ? wc_format_decimal( wc_clean( wp_unslash( $_POST[ self::META_VALUE ] ) ) ) : '';
		update_post_meta( $post_id, self::META_VALUE, $value );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	// -------------------------------------------------------------------------
	// Cart: apply deposit price
	// -------------------------------------------------------------------------

	/**
	 * Replace the pre-order product price in the cart with the deposit amount.
	 *
	 * Session data is keyed by product ID (not cart-item key) so that other
	 * methods can retrieve it without iterating the full cart.
	 *
	 * @param WC_Cart $cart
	 */
	public function apply_deposit_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product    = $cart_item['data'];
			$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

			if ( ! NPOM_Product::product_can_be_pre_ordered( $product_id ) ) {
				continue;
			}

			// Use get_price() before set_price() to capture the true customer price
			// (accounts for sale prices, dynamic pricing plugins, etc.).
			$original_price = (float) $product->get_price();
			$deposit_amount = $this->calculate_deposit_amount( $product_id, $original_price );

			if ( null === $deposit_amount ) {
				continue; // No deposit configured.
			}

			// Key session data by product ID so other methods can look it up
			// without re-iterating the cart (avoids object-reference issues).
			WC()->session->set(
				'wc_pre_order_deposit_product_' . $product_id,
				array(
					'original_price'    => $original_price,
					'deposit_paid'      => $deposit_amount,
					'remaining_balance' => max( 0.0, $original_price - $deposit_amount ),
				)
			);

			$product->set_price( $deposit_amount );
		}
	}

	// -------------------------------------------------------------------------
	// Cart item display
	// -------------------------------------------------------------------------

	/**
	 * Append deposit information to cart item meta.
	 *
	 * Reads the session data that was stored (keyed by product ID) during
	 * apply_deposit_price() to avoid recalculating and to stay consistent
	 * with the actual amount that will be charged.
	 *
	 * @param array $meta      Existing cart item meta array.
	 * @param array $cart_item Cart item data.
	 * @return array
	 */
	public function add_deposit_cart_meta( $meta, $cart_item ) {
		$product    = $cart_item['data'];
		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

		$type = get_post_meta( $product_id, self::META_TYPE, true );
		if ( ! $type || self::TYPE_NONE === $type ) {
			return $meta;
		}

		$session_data = WC()->session->get( 'wc_pre_order_deposit_product_' . $product_id );
		if ( ! $session_data ) {
			return $meta;
		}

		$meta[] = array(
			'name'    => __( 'Deposit charged now', 'nextxen-pre-order-manager' ),
			'display' => wc_price( $session_data['deposit_paid'] ),
		);

		$meta[] = array(
			'name'    => __( 'Remaining balance (due on release)', 'nextxen-pre-order-manager' ),
			'display' => wc_price( $session_data['remaining_balance'] ),
		);

		return $meta;
	}

	// -------------------------------------------------------------------------
	// Order: save deposit meta
	// -------------------------------------------------------------------------

	/**
	 * Store deposit details on the order at checkout time.
	 *
	 * Because the cart allows only one pre-order product at a time (enforced by
	 * NPOM_Cart::validate_cart), there is at most one deposit
	 * session entry to process.
	 *
	 * @param WC_Order $order
	 * @param array    $data  Checkout POST data.
	 */
	public function save_order_deposit_meta( $order, $data ) {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product    = $cart_item['data'];
			$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

			$session_data = WC()->session->get( 'wc_pre_order_deposit_product_' . $product_id );

			if ( ! $session_data ) {
				continue;
			}

			$order->update_meta_data( self::META_ENABLED, '1' );
			$order->update_meta_data( self::META_ORIGINAL, $session_data['original_price'] );
			$order->update_meta_data( self::META_PAID, $session_data['deposit_paid'] );
			$order->update_meta_data( self::META_REMAINING, $session_data['remaining_balance'] );
			$order->save();

			// Clear session so it doesn't linger.
			WC()->session->__unset( 'wc_pre_order_deposit_product_' . $product_id );
			break; // Only one pre-order product per cart.
		}
	}

	// -------------------------------------------------------------------------
	// Release: create remaining-balance order
	// -------------------------------------------------------------------------

	/**
	 * When a deposit pre-order is released, create a follow-up order for the
	 * remaining balance and notify the customer.
	 *
	 * @param WC_Order $order The completed pre-order.
	 */
	public function handle_remaining_balance( $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return;
		}

		if ( '1' !== $order->get_meta( self::META_ENABLED, true ) ) {
			return; // Not a deposit order.
		}

		$remaining = (float) $order->get_meta( self::META_REMAINING, true );

		if ( $remaining <= 0 ) {
			return;
		}

		// Guard against duplicate follow-up orders (meta check + transient lock).
		if ( $order->get_meta( '_npom_balance_order_id', true ) ) {
			return;
		}

		$lock_key = 'wc_pom_deposit_lock_' . $order->get_id();
		if ( get_transient( $lock_key ) ) {
			return; // Another process is already creating the balance order.
		}
		set_transient( $lock_key, 1, 60 ); // 60-second lock.

		$balance_order = $this->create_balance_order( $order, $remaining );

		if ( ! $balance_order ) {
			return;
		}

		// Link the follow-up order to the original.
		$order->update_meta_data( '_npom_balance_order_id', $balance_order->get_id() );
		$order->add_order_note(
			sprintf(
				/* translators: 1: remaining amount, 2: follow-up order ID */
				__( 'Pre-order deposit: remaining balance of %1$s due. Follow-up order #%2$s created.', 'nextxen-pre-order-manager' ),
				wc_price( $remaining ),
				$balance_order->get_id()
			)
		);
		$order->save();

		// Email the customer about the new order.
		do_action( 'woocommerce_checkout_order_created', $balance_order );
	}

	/**
	 * Build a new pending WooCommerce order for the remaining balance.
	 *
	 * @param WC_Order $original_order
	 * @param float    $remaining
	 * @return WC_Order|false
	 */
	private function create_balance_order( $original_order, $remaining ) {
		try {
			$balance_order = wc_create_order(
				array(
					'customer_id' => $original_order->get_customer_id(),
					'status'      => 'pending',
				)
			);

			// Copy billing/shipping address.
			foreach ( $original_order->get_address( 'billing' ) as $key => $value ) {
				$setter = "set_billing_{$key}";
				if ( method_exists( $balance_order, $setter ) ) {
					$balance_order->$setter( $value );
				}
			}
			foreach ( $original_order->get_address( 'shipping' ) as $key => $value ) {
				$setter = "set_shipping_{$key}";
				if ( method_exists( $balance_order, $setter ) ) {
					$balance_order->$setter( $value );
				}
			}

			// Add a fee line item for the remaining balance.
			$fee        = new WC_Order_Item_Fee();
			$fee->set_name(
				sprintf(
					/* translators: %d: original pre-order order ID */
					__( 'Pre-order remaining balance (order #%d)', 'nextxen-pre-order-manager' ),
					$original_order->get_id()
				)
			);
			$fee->set_amount( $remaining );
			$fee->set_total( $remaining );
			$fee->set_tax_status( 'none' );

			$balance_order->add_item( $fee );

			// Link back to the original pre-order.
			$balance_order->update_meta_data( '_npom_original_order_id', $original_order->get_id() );
			$balance_order->update_meta_data( '_npom_is_balance_order', '1' );

			$balance_order->calculate_totals();
			$balance_order->save();

			return $balance_order;

		} catch ( Exception $e ) {
			global $npom_manager;
			if ( is_object( $npom_manager ) ) {
				$npom_manager->log( 'Deposit balance order creation failed: ' . $e->getMessage() );
			}
			return false;
		}
	}

	// -------------------------------------------------------------------------
	// Order admin: display deposit info
	// -------------------------------------------------------------------------

	/**
	 * Show deposit summary in the WooCommerce order edit screen.
	 *
	 * @param WC_Order $order
	 */
	public function display_deposit_info_in_order( $order ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( '1' !== $order->get_meta( self::META_ENABLED, true ) ) {
			return;
		}

		$original  = (float) $order->get_meta( self::META_ORIGINAL, true );
		$paid      = (float) $order->get_meta( self::META_PAID, true );
		$remaining = (float) $order->get_meta( self::META_REMAINING, true );
		$balance_order_id = $order->get_meta( '_npom_balance_order_id', true );

		echo '<div class="npom-deposit-info" style="margin-top:12px;padding:10px;background:#f8f9fa;border-left:4px solid #0071a1;">';
		echo '<strong>' . esc_html__( 'Pre-order Deposit', 'nextxen-pre-order-manager' ) . '</strong><br>';
		/* translators: %s: formatted price */
		echo esc_html( sprintf( __( 'Original price: %s', 'nextxen-pre-order-manager' ), wc_price( $original ) ) ) . '<br>';
		/* translators: %s: formatted price */
		echo esc_html( sprintf( __( 'Deposit paid: %s', 'nextxen-pre-order-manager' ), wc_price( $paid ) ) ) . '<br>';
		/* translators: %s: formatted price */
		echo esc_html( sprintf( __( 'Remaining balance: %s', 'nextxen-pre-order-manager' ), wc_price( $remaining ) ) );

		if ( $balance_order_id ) {
			$balance_order_url = admin_url( 'post.php?post=' . absint( $balance_order_id ) . '&action=edit' );
			echo '<br>' . sprintf(
				/* translators: %s: order link */
				wp_kses(
					__( 'Balance order: %s', 'nextxen-pre-order-manager' ),
					array( 'a' => array( 'href' => array(), 'target' => array() ) )
				),
				'<a href="' . esc_url( $balance_order_url ) . '" target="_blank">#' . absint( $balance_order_id ) . '</a>'
			);
		}

		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Calculate the deposit amount for a product given its current price.
	 *
	 * @param int   $product_id
	 * @param float $price Full product price.
	 * @return float|null Deposit amount, or null if no deposit is configured.
	 */
	public function calculate_deposit_amount( $product_id, $price ) {
		$type  = get_post_meta( $product_id, self::META_TYPE, true );
		$value = (float) get_post_meta( $product_id, self::META_VALUE, true );

		if ( ! $type || self::TYPE_NONE === $type || $value <= 0 ) {
			return null;
		}

		if ( self::TYPE_FIXED === $type ) {
			return min( $value, $price ); // Deposit cannot exceed product price.
		}

		// Percentage.
		return round( $price * ( min( 100, $value ) / 100 ), wc_get_price_decimals() );
	}

}

