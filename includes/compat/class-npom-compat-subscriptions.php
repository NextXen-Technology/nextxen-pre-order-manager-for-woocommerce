<?php

/**
 * WooCommerce Pre-Order Manager
 *
 * @package     NPOM/Subscriptions Compatibility
 */

class NPOM_Compat_Subscriptions {

	/**
	 * Date format used by WooCommerce Subscriptions.
	 */
	const SUBS_DATE_TIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Date format used by WooCommerce Pre-Order Manager.
	 */
	const PRE_ORDERS_DATE_TIME_FORMAT = 'Y-m-d H:i';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_hooks' ) );
	}

	/**
	 * Register hooks.
	 *
	 * Bails early if WooCommerce Subscriptions is not active to
	 * allow the remaining methods within this class to presume the
	 * extension is active.
	 */
	public function add_hooks() {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		// Status update hooks.
		add_filter( 'wcs_subscription_statuses', array( $this, 'add_pre_order_status' ) );
		// Allow any status to be changed to pre-ordered.
		add_filter( 'woocommerce_can_subscription_be_updated_to_pre-ordered', '__return_true' );
		// Whether to allow status updates from pre-ordered to active or completed.
		add_filter( 'woocommerce_can_subscription_be_updated_to_completed', array( $this, 'allow_status_update_from_pre_ordered_to_completed' ), 10, 2 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_active', array( $this, 'allow_status_update_from_pre_ordered_to_active' ), 10, 2 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_active', array( $this, 'allow_status_update_from_on_hold_to_active' ), 10, 2 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_cancelled', array( $this, 'allow_status_update_from_pre_ordered_to_cancelled' ), 10, 2 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_on-hold', array( $this, 'allow_status_update_from_pre_ordered_to_on_hold' ), 10, 2 );
		add_filter( 'wcs_can_user_resubscribe_to_subscription', array( $this, 'prevent_resubscribe_for_cancelled_pre_orders' ), 10, 2 );
		// Prevent admins processing renewals for pre-ordered subscriptions.
		add_filter( 'woocommerce_order_actions', array( $this, 'prevent_admin_generated_renewals_for_pre_orders' ), 20, 2 );

		// Order manipulation hooks.
		// Set the subscription start date to the pre-order availability date.
		add_filter( 'wcs_recurring_cart_start_date', array( $this, 'set_recurring_cart_start_date' ), 10, 2 );
		// Prevent a pre-order from activating the subscription when the order is paid for (upfront pre-orders only).
		add_filter( 'wcs_is_subscription_order_completed', array( $this, 'prevent_activate_subscription_on_payment' ), 10, 5 );
		// Update subscription dates when a upfront pre-order is completed/processed.
		add_filter( 'wcs_is_subscription_order_completed', array( $this, 'allow_activate_subscription_on_pre_order_to_processing' ), 10, 5 );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'update_payment_complete_order_status' ), 20, 3 );

		// These run at priority 5 to ensure the date is updated prior to the email being sent at priority 10.
		add_action( 'npom_pre_order_date_changed', array( $this, 'pre_order_date_changed' ), 5 );
		add_action( 'npom_pre_order_completed', array( $this, 'pre_order_completed' ), 5 );
		add_action( 'npom_pre_order_cancelled', array( $this, 'pre_order_cancelled' ), 5 );
		// Prevent out of date info in the cancellation emails.
		add_action( 'wc_pre_order_status_active_to_cancelled', array( $this, 'remove_subscription_info_from_cancellation_emails' ), 1 );

		add_action( 'wc_pre_order_status_active', array( $this, 'pre_order_status_active' ) );

		// Remove Pre-order data from the subscription objects.
		add_filter( 'wc_subscriptions_renewal_order_data', array( $this, 'remove_pre_order_status_from_subscription_objects' ) );
		add_filter( 'wc_subscriptions_subscription_data', array( $this, 'remove_pre_order_status_from_subscription_objects' ) );

		// Add faux actions row to customer subscription actions.
		add_action( 'woocommerce_subscription_after_actions', array( $this, 'after_subscriptions_actions' ) );

		add_filter( 'wcs_setup_cart_for_subscription_initial_payment', array( $this, 'prevent_recreation_of_initial_cart_for_pre_orders' ), 10, 2 );

		add_action( 'npom_after_product_options_updated', array( $this, 'store_pre_order_completion_date_on_subscription_variants' ), 10, 3 );

		add_filter( 'npom_admin_script_data', array( $this, 'add_product_data_to_admin_script' ), 10, 2 );

		// Show a notice when an incompatible subscription product is set.
		add_action( 'npom_product_options_start', array( $this, 'show_notice_subscriptions_feature_compatibility_above_pre_orders' ) );
		add_action( 'woocommerce_variable_product_before_variations', array( $this, 'show_notice_subscriptions_feature_compatibility_above_product_pricing' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'show_notice_subscriptions_feature_compatibility_above_product_pricing' ) );

		// Prevent the purchase of pre-orders for incompatible subscription products.
		add_filter( 'woocommerce_is_purchasable', array( $this, 'is_purchasable' ), 10, 2 );
		add_filter( 'woocommerce_single_product_summary', array( $this, 'show_notice_subscriptions_feature_compatibility' ), 20 );
		add_filter( 'npom_product_supports_pre_order_feature', array( $this, 'product_supports_pre_order_feature' ), 10, 2 );
	}

	/**
	 * Modifies whether a product supports the pre-order feature.
	 *
	 * Disables the pre-order feature for subscription products with incompatible features.
	 *
	 * Runs on the `npom_product_supports_pre_order_feature` filter.
	 *
	 * @param bool       $supports_pre_order_feature Whether the product supports the pre-order feature.
	 * @param WC_Product $product                    The product object.
	 */
	public function product_supports_pre_order_feature( $supports_pre_order_feature, $product ) {
		if ( false === (bool) $supports_pre_order_feature ) {
			// Pre-orders are not supported for this product, no need to check for incompatibility.
			return $supports_pre_order_feature;
		}

		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return $supports_pre_order_feature;
		}

		if ( ! $product->is_type( array( 'subscription', 'variable-subscription' ) ) ) {
			// Not a subscription product, no need to check for incompatibility.
			return $supports_pre_order_feature;
		}

		if ( self::product_has_synced_subs( $product ) && ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) ) {
			// Synchronized subscriptions are not supported.
			return false;
		}

		if ( self::product_has_trial_period( $product ) && ! self::is_subscriptions_feature_supported( 'trial-periods' ) ) {
			// Subscription trial periods are not supported.
			return false;
		}

		// No change to passed value.
		return $supports_pre_order_feature;
	}

	/**
	 * Show store managers warning a subscription product is incompatible with pre-orders.
	 *
	 * Displays a message on the product page if the product is a subscription product
	 * with an incompatible feature for pre-orders.
	 *
	 * Runs on the `woocommerce_single_product_summary` action.
	 */
	public function show_notice_subscriptions_feature_compatibility() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			// Only show the notice to store managers.
			return;
		}

		// Get the current global product.
		$product     = wc_get_product();
		$show_notice = false;

		if ( ! $product ) {
			// No product, no need to show the notice.
			return;
		}

		$is_purchaseable = $product->is_purchasable();

		if ( $is_purchaseable ) {
			// The product is purchasable, no need to show the notice.
			return;
		}

		if ( ! $product->is_type( array( 'subscription', 'variable-subscription' ) ) ) {
			// Not a subscription product, no need to show notice.
			return;
		}

		if ( ! NPOM_Product::product_can_be_pre_ordered( $product ) ) {
			// Pre-orders are not enabled for this product, no need to show notice.
			return;
		}

		if ( ! self::is_subscriptions_supported() ) {
			// Subscriptions is not supported, no need to show notice.
			return;
		}

		if ( self::product_has_synced_subs( $product ) && ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) ) {
			// Synchronized subscriptions are not supported, show the notice.
			$show_notice = true;
		} elseif ( self::product_has_trial_period( $product ) && ! self::is_subscriptions_feature_supported( 'trial-periods' ) ) {
			// Subscription trial periods are not supported, show the notice.
			$show_notice = true;
		}

		if ( ! $show_notice ) {
			// No need to show the notice.
			return;
		}

		if ( ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) && ! self::is_subscriptions_feature_supported( 'trial-periods' ) ) {
			$unsupported_text = __( 'Synchronized subscription renewal dates and subscription trial periods are not available for pre-orders.', 'nextxen-pre-order-manager' );
		} elseif ( ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) ) {
			$unsupported_text = __( 'Synchronized subscription renewal dates are not available for pre-orders.', 'nextxen-pre-order-manager' );
		} elseif ( ! self::is_subscriptions_feature_supported( 'trial-periods' ) ) {
			$unsupported_text = __( 'Subscription trial periods are not available for pre-orders.', 'nextxen-pre-order-manager' );
		}

		$notice_text  = '<p>' . __( 'Subscription unavailable for pre-order.', 'nextxen-pre-order-manager' ) . '</p>';
		$notice_text .= "<p>$unsupported_text</p>";
		$notice_text .= '<p>' . sprintf(
			// translators: %1$s and %2$s are placeholders for the opening and closing link tags to https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/
			__( 'Please visit the %1$sWooCommerce Pre-Order Manager%2$s documentation for more information.', 'nextxen-pre-order-manager' ),
			'<a href="https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/">',
			'</a>'
		) . '</p>';

		$notice_text .= '<p>' . sprintf(
			// translators: %1$s and %2$s are placeholders for the opening and closing link tags to https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/
			__( 'You can %1$sedit this product%2$s in the dashboard. This notice is only visible to store managers.', 'nextxen-pre-order-manager' ),
			'<a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">',
			'</a>'
		) . '</p>';

		wc_print_notice( $notice_text, 'error' );
	}

	/**
	 * Determine whether a subscription product can be pre-ordered.
	 *
	 * Runs on the `woocommerce_is_purchasable` filter.
	 *
	 * Prevent the pre-order of a subscription product if the product
	 * is using features that are incompatible with pre-orders.
	 *
	 * The product interface in the dashboard prevents the configuration
	 * of incompatible features for pre-orders so this should only be
	 * needed for products created via the CLI or REST API.
	 *
	 * @param bool $is_purchaseable Whether the product is purchasable.
	 * @param WC_Product $product The product object.
	 * @return bool Modified purchasable status.
	 */
	public function is_purchasable( $is_purchaseable, $product ) {
		// DO NOT use wc_get_product() === false here as it may cause an infinite loop.
		if ( ! $product instanceof WC_Product ) {
			// Not a product, no need to check for incompatibility.
			return $is_purchaseable;
		}

		if ( ! $product || ! $is_purchaseable ) {
			// No need to check for incompatibility if the product is not purchasable.
			return $is_purchaseable;
		}

		if ( ! $product->is_type( array( 'subscription', 'variable-subscription' ) ) ) {
			// Not a subscription product, no need to check for incompatibility.
			return $is_purchaseable;
		}

		if ( ! NPOM_Product::product_can_be_pre_ordered( $product ) ) {
			// Pre-orders are not enabled for this product, no need to check for incompatibility.
			return $is_purchaseable;
		}

		if ( ! self::is_subscriptions_supported() ) {
			// Subscriptions is not supported, the product is not purchasable.
			return false;
		}

		if ( self::product_has_synced_subs( $product ) && ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) ) {
			// Synchronized subscriptions are not supported, the product is not purchasable.
			return false;
		}

		if ( self::product_has_trial_period( $product ) && ! self::is_subscriptions_feature_supported( 'trial-periods' ) ) {
			// Subscription trial periods are not supported, the product is not purchasable.
			return false;
		}

		// Use existing value if no incompatibility is found.
		return $is_purchaseable;
	}

	/**
	 * Show a notice on the variations page when an incompatible subscription product is set.
	 *
	 * Warn the merchant that the pre-order function is unavailable for certain
	 * subscription products.
	 *
	 * Runs on the actions:
	 * - woocommerce_variable_product_before_variations
	 * - woocommerce_product_options_general_product_data
	 */
	public function show_notice_subscriptions_feature_compatibility_above_product_pricing() {
		$product = wc_get_product( get_post() );
		if (
			$product
			&& $product->is_type( array( 'subscription', 'variable-subscription' ) )
			&& (
				( self::product_has_synced_subs( $product ) && ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) )
				|| ( self::product_has_trial_period( $product ) && ! self::is_subscriptions_feature_supported( 'trial-periods' ) )
			)
		) {
			$display = 'block';
		} else {
			$display = 'none';
		}

		if ( ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) && ! self::is_subscriptions_feature_supported( 'trial-periods' ) ) {
			$unsupported_text = __( 'Synchronized subscription renewal dates and subscription trial periods are not available for pre-orders.', 'nextxen-pre-order-manager' );
		} elseif ( ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) ) {
			$unsupported_text = __( 'Synchronized subscription renewal dates are not available for pre-orders.', 'nextxen-pre-order-manager' );
		} elseif ( ! self::is_subscriptions_feature_supported( 'trial-periods' ) ) {
			$unsupported_text = __( 'Subscription trial periods are not available for pre-orders.', 'nextxen-pre-order-manager' );
		}

		?>
		<p class="notice npom-notice-subscriptions-compatibility npom-notice-subscriptions-compatibility--pricing-page" style="display: <?php echo esc_attr( $display ); ?>">
			<?php echo esc_html( $unsupported_text ); ?>
			<br />
			<?php
			printf(
				// translators: %1$s and %2$s are placeholders for the opening and closing link tags to https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/
				esc_html__(
					'Please visit the %1$sWooCommerce Pre-Order Manager%2$s documentation for more information.',
					'nextxen-pre-order-manager'
				),
				'<a href="https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/">',
				'</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Show a notice when an incompatible subscription product is set.
	 *
	 * Warn the merchant that the pre-order function is unavailable for certain
	 * subscription products.
	 *
	 * Runs on the `npom_product_options_start` action.
	 */
	public function show_notice_subscriptions_feature_compatibility_above_pre_orders() {
		$product = wc_get_product( get_post() );
		if (
			$product
			&& $product->is_type( array( 'subscription', 'variable-subscription' ) )
			&& (
				( self::product_has_synced_subs( $product ) && ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) )
				|| ( self::product_has_trial_period( $product ) && ! self::is_subscriptions_feature_supported( 'trial-periods' ) )
			)
		) {
			$display = 'block';
		} else {
			$display = 'none';
		}

		if ( ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) && ! self::is_subscriptions_feature_supported( 'trial-periods' ) ) {
			$unsupported_text = __( 'Pre-orders are not available for synchronized subscription renewal dates or subscription trial periods.', 'nextxen-pre-order-manager' );
		} elseif ( ! self::is_subscriptions_feature_supported( 'synchronized-subscriptions' ) ) {
			$unsupported_text = __( 'Pre-orders are not available for synchronized subscription renewal dates.', 'nextxen-pre-order-manager' );
		} elseif ( ! self::is_subscriptions_feature_supported( 'trial-periods' ) ) {
			$unsupported_text = __( 'Pre-orders are not available for subscription trial periods.', 'nextxen-pre-order-manager' );
		}

		?>
		<p class="notice npom-notice-subscriptions-compatibility npom-notice-subscriptions-compatibility--pre-orders" style="display: <?php echo esc_attr( $display ); ?>">
			<?php echo esc_html( $unsupported_text ); ?>
			<br />
			<?php
			printf(
				// translators: %1$s and %2$s are placeholders for the opening and closing link tags to https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/
				esc_html__(
					'Please visit the %1$sWooCommerce Pre-Order Manager%2$s documentation for more information.',
					'nextxen-pre-order-manager'
				),
				'<a href="https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/">',
				'</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Add details about the product to the admin script data.
	 *
	 * @param array  $script_data The script data.
	 * @param string $hook_suffix The current screen hook suffix.
	 * @return array The modified script data.
	 */
	public function add_product_data_to_admin_script( $script_data, $hook_suffix ) {
		global $typenow;

		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return $script_data;
		}

		if ( 'product' !== $typenow ) {
			return $script_data;
		}

		$product = wc_get_product( get_the_ID() );

		if ( ! $product ) {
			return $script_data;
		}

		$has_synced_subs  = self::product_has_synced_subs( $product );
		$has_trial_period = self::product_has_trial_period( $product );

		$script_data['product_data']       = array(
			'product_id'       => $product->get_id(),
			'product_type'     => $product->get_type(),
			'is_pre_order'     => NPOM_Product::product_can_be_pre_ordered( $product ),
			'has_synced_subs'  => $has_synced_subs,
			'has_trial_period' => $has_trial_period,
		);
		$script_data['product_admin_page'] = admin_url( 'edit.php?post_type=product' );

		return $script_data;
	}

	/**
	 * Get whether the product has synchronized subscriptions.
	 *
	 * @param bool|int|WC_Product $product Post object or post ID of the product. Defaults to the current post.
	 * @return bool Whether the product has synchronized subscriptions.
	 */
	public static function product_has_synced_subs( $product = false ) {
		// DO NOT use wc_get_product() === false here as it may cause an infinite loop.
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}
		$product_id = $product->get_id();

		if ( 'subscription' === $product->get_type() ) {
			return ( (int) get_post_meta( $product_id, '_subscription_payment_sync_date', true ) !== 0 );
		}

		if ( 'variable-subscription' === $product->get_type() ) {
			// DO NOT use $product->get_available_variations() here as it may cause an infinite loop.
			$variation_ids = $product->get_children();
			foreach ( $variation_ids as $variation_id ) {
				if ( (int) get_post_meta( $variation_id, '_subscription_payment_sync_date', true ) !== 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get whether the product has a trial period.
	 *
	 * @param bool|int|WC_Product $product Post object or post ID of the product. Defaults to the current post.
	 * @return bool Whether the product has a trial period.
	 */
	public static function product_has_trial_period( $product = false ) {
		// DO NOT use wc_get_product() === false here as it may cause an infinite loop.
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}
		$product_id = $product->get_id();

		if ( 'subscription' === $product->get_type() ) {
			return ( (int) get_post_meta( $product_id, '_subscription_trial_length', true ) !== 0 );
		}

		if ( 'variable-subscription' === $product->get_type() ) {
			// DO NOT use $product->get_available_variations() here as it may cause an infinite loop.
			$variation_ids = $product->get_children();
			foreach ( $variation_ids as $variation_id ) {
				if ( (int) get_post_meta( $variation_id, '_subscription_trial_length', true ) !== 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Store maturity date for pre-orders on subscription variations.
	 *
	 * Runs on the `npom_after_product_options_updated` action.
	 *
	 * @todo Modify for synchronized subscriptions when support is added.
	 *
	 * @param int $product_id The product ID.
	 * @param bool $is_enabled Whether pre-orders are enabled for the product.
	 * @param int $timestamp The maturity timestamp of the pre-order's parent product.
	 */
	public function store_pre_order_completion_date_on_subscription_variants( $product_id, $is_enabled, $timestamp ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			// Product does not exist, do nothing.
			return;
		}

		if ( ! $product->is_type( 'variable-subscription' ) ) {
			// Not a subscription variation, no need to do anything.
			return;
		}

		$variations    = $product->get_available_variations();
		$variation_ids = wp_list_pluck( $variations, 'variation_id' );

		if ( ! $is_enabled ) {
			// Pre-orders are disabled, remove the pre-order status from the variations.
			foreach ( $variation_ids as $variation_id ) {
				delete_post_meta( $variation_id, '_npom_availability_datetime' );
				update_post_meta( $variation_id, '_npom_enabled', 'no' );
			}

			return;
		}

		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}
			update_post_meta( $variation_id, '_npom_availability_datetime', $timestamp );
			update_post_meta( $variation_id, '_npom_enabled', 'yes' );
		}
	}

	/**
	 * Prevent subscriptions from being recreated when the initial payment is paid for.
	 *
	 * This accounts for pay upon release pre-orders using the pay-later payment method.
	 * Allowing subscriptions to recreate the order prevents the subscription being purchased
	 * if the pre-order was completed prior to the product's initial maturity date.
	 *
	 * Runs on the filter `wcs_recreate_initial_payment_order`.
	 *
	 * @param bool     $recreate_cary Whether to recreate the cart.
	 * @param WC_Order $order         The order object.
	 */
	public function prevent_recreation_of_initial_cart_for_pre_orders( $recreate_order, $order ) {

		$order_status     = $order->get_status();
		$pre_order_status = $order->get_meta( '_npom_status', true );
		$when_to_charge   = $order->get_meta( '_npom_when_charged', true );

		if (
			'completed' === $pre_order_status
			&& 'upon_release' === $when_to_charge
			&& 'pre_orders_pay_later' === $order->get_payment_method()
			&& 'pending' === $order_status
		) {
			// Allow the pre-order to be paid for using the pay for order endpoint.
			return false;
		}

		return $recreate_order;
	}

	/**
	 * Remove the renewal actions from pre-ordered subscriptions.
	 *
	 * When editing a pre-ordered subcscription in the admin the renewal actions are removed:
	 *    - Process renewal
	 *    - Create pending renewal
	 *
	 * Prior to a pre-order becoming active, these actions are unavailable as they require status
	 * transitions that are prevented by the pre-order status.
	 *
	 * Runs on the filter `woocommerce_order_actions, 20`.
	 *
	 * @param array           $actions     The order actions.
	 * @param WC_Subscription $subscription The subscription object.
	 */
	public function prevent_admin_generated_renewals_for_pre_orders( $actions, $subscription ) {
		if ( ! wcs_is_subscription( $subscription ) ) {
			// Not a subscription, no need to do anything.
			return $actions;
		}

		$subscription_status = $subscription->get_status();

		if ( 'pre-ordered' === $subscription_status ) {
			// Subscription is pre-ordered, remove the renewal actions.
			unset( $actions['wcs_process_renewal'] );
			unset( $actions['wcs_create_pending_renewal'] );
			unset( $actions['wcs_create_pending_parent'] );
		}

		return $actions;
	}

	/**
	 * Check if the subscriptions extension is supported.
	 *
	 * Pre-orders requires Subscriptions 6.7.0 or later.
	 *
	 * Pre-orders makes use of code introduced in the following upstream pull requests:
	 *
	 * @link https://github.com/Automattic/woocommerce-subscriptions-core/pull/579
	 * @link https://github.com/Automattic/woocommerce-subscriptions-core/pull/594
	 * @link https://github.com/Automattic/woocommerce-subscriptions-core/pull/647
	 *
	 * @return bool Whether the subscriptions extension available and a supported version.
	 */
	public static function is_subscriptions_supported() {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return false;
		}

		$subscriptions_plugin_version = WC_Subscriptions::$version;

		// Only support version 6.7.0 and above.
		return version_compare( $subscriptions_plugin_version, '6.7.0', '>=' );
	}

	/**
	 * Check if a subscription feature is supported.
	 *
	 * @param string $feature The feature to check support for, accepts 'synchronized-subscriptions' and 'trial-periods'.
	 * @return false Whether the feature is supported.
	 */
	public static function is_subscriptions_feature_supported( $feature ) {
		// Default to false to allow for future features to be added.
		$supported = 'false';

		if ( ! self::is_subscriptions_supported() ) {
			// Subscriptions is not supported, the feature is not either.
			return false;
		}

		$subscriptions_plugin_version = WC_Subscriptions::$version;

		/*
		 * When updating this array the feature value can be a boolean or a version string.
		 *
		 * Example values:
		 * - true: The feature is supported.
		 * - false: The feature is not supported.
		 * - '6.2.0': The feature is supported in Subscriptions version 6.2.0 and above.
		 */
		$features = array(
			'synchronized-subscriptions' => false,
			'trial-periods'              => false,
		);

		if ( isset( $features[ $feature ] ) ) {
			$feature = $features[ $feature ];

			if ( is_bool( $feature ) ) {
				$supported = $feature;
			} elseif ( is_string( $feature ) ) {
				$supported = version_compare( $subscriptions_plugin_version, $feature, '>=' );
			}
		}

		return $supported;
	}


	/**
	 * Add a note to the customer subscription actions if the subscription is pre-ordered.
	 *
	 * This adds a note to the customer subscription actions explaining that they will be
	 * able to modify their subscription once it comes out of pre-order state.
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 */
	public function after_subscriptions_actions( $subscription ) {
		$actions = wcs_get_all_user_actions_for_subscription( $subscription, get_current_user_id() );

		if ( ! empty( $actions ) ) {
			// The user can manage their subscription.
			return;
		}

		$order = $subscription->get_parent();
		if ( ! $order || 'pre-ordered' !== $order->get_status() ) {
			// Not a pre-order, not this extensions responsibility.
			return;
		}

		// Add a note the user will be able to manage their subscription later.
		?>
			<tr>
				<td><?php esc_html_e( 'Actions', 'nextxen-pre-order-manager' ); ?></td>
				<td>
					<p>
					<?php
					esc_html_e( 'This subscription has been pre-ordered. Once order becomes available you will be able to manage your subscription.', 'nextxen-pre-order-manager' );

					echo '</p><p>';

					printf(
						// translators: %1$s and %2$s are placeholders for the opening and closing link tags to the pre-orders page.
						esc_html__(
							'If you wish to manage your pre-order, please visit the %1$sPre-Orders%2$s page.',
							'nextxen-pre-order-manager'
						),
						'<a href="' . esc_url( wc_get_endpoint_url( 'pre-orders' ) ) . '">',
						'</a>'
					);

					?>
					</p>
				</td>
			</tr>
		<?php
	}

	/**
	 * Prevent the subscriptions and subscription renewal orders from being marked as pre-ordered.
	 *
	 * Remove the pre-order meta data from the subscription objects.
	 *
	 * For new subscriptions this prevents the subscription from being treated as an order
	 * in the pre-order bulk actions.
	 *
	 * For renewals this ensures that the renewal orders to not go through the pre-order
	 * status and related workflows.
	 *
	 * Runs on the `wc_subscriptions_renewal_order_data` and
	 * `wc_subscriptions_subscription_data` filters.
	 *
	 * @param mixed[] $data Array of meta data for the renewal order.
	 * @return mixed[] The modified array excluding the pre-order meta data.
	 */
	public function remove_pre_order_status_from_subscription_objects( $data ) {
		// Remove the pre-ordered status from the renewal order.
		unset( $data['_npom_is_pre_order'], $data['_npom_when_charged'] );
		return $data;
	}

	/**
	 * Set the subscription status to pre-ordered for active pre-orders.
	 *
	 * Runs on the `wc_pre_order_status_active` action.
	 *
	 * @param int $order_id The order ID.
	 */
	public function pre_order_status_active( $order_id ) {
		$order         = wc_get_order( $order_id );
		$subscriptions = wcs_get_subscriptions_for_order( $order );

		if ( empty( $subscriptions ) ) {
			// No subscriptions in this order.
			return;
		}

		// Update the status to active for each subscription.
		foreach ( $subscriptions as $subscription ) {
			$subscription->update_status( 'pre-ordered' );
		}
	}

	/**
	 * Active a subscription when the status changes from pre-ordered to processing.
	 *
	 * Runs on the `wcs_is_subscription_order_completed` filter.
	 *
	 * @param bool              $order_completed  Whether the order is considered completed.
	 * @param string            $new_order_status The new order status.
	 * @param string            $old_order_status The old order status.
	 * @param WC_Subscription[] $subscriptions    The subscriptions in the order.
	 * @param WC_Order          $order            The order object.
	 */
	public function allow_activate_subscription_on_pre_order_to_processing( $order_completed, $new_order_status, $old_order_status, $subscriptions, $order ) {
		// If the new order status is not processing, no need to do anything.
		if ( 'processing' !== $new_order_status ) {
			return $order_completed;
		}

		// If the old order status is not pre-ordered, no need to do anything.
		if ( 'pre-ordered' !== $old_order_status ) {
			return $order_completed;
		}

		$pre_order_status = $order->get_meta( '_npom_status', true );

		if ( ! $pre_order_status ) {
			// It's not actually a pre-order.
			return $order_completed;
		}

		// Allow the subscription to be activated.
		return true;
	}

	/**
	 * Prevent a pre-order status from marking a subscription as complete/active.
	 *
	 * Prevents the Woo Subscriptions from activating the subscription following payment.
	 *
	 * Runs on the `wcs_is_subscription_order_completed` filter.
	 *
	 * @param bool              $order_completed  Whether the order is considered completed.
	 * @param string            $new_order_status The new order status.
	 * @param string            $old_order_status The old order status.
	 * @param WC_Subscription[] $subscriptions    The subscriptions in the order.
	 * @param WC_Order          $order            The order object.
	 */
	public function prevent_activate_subscription_on_payment( $order_completed, $new_order_status, $old_order_status, $subscriptions, $order ) {
		// If the order status is not pre-ordered, no need to do anything
		if ( 'pre-ordered' !== $new_order_status ) {
			return $order_completed;
		}

		// This should only affect upfront pre-orders.
		$when_to_charge = $order->get_meta( '_npom_when_charged', true );
		if ( 'upfront' !== $when_to_charge ) {
			return $order_completed;
		}

		foreach ( $order->get_items( 'line_item' ) as $order_item ) {
			// Reset product ID at the beginning of the loop.
			$product_id = null;
			if ( ! empty( $order_item['variation_id'] ) ) {
				$product_id = $order_item['variation_id'];
			} elseif ( ! empty( $order_item['product_id'] ) ) {
				$product_id = $order_item['product_id'];
			}

			if ( ! empty( $product_id ) ) {
					// Pre-orders can only be for a single product.
					$product = wc_get_product( $product_id );
					break;
			}
		}

		if ( empty( $product ) ) {
			// This shouldn't happen, but if it does, bail early.
			return $order_completed;
		}

		$pre_order_availability = NPOM_Product::get_utc_availability_datetime_timestamp( $product );
		if ( $pre_order_availability > time() ) {
			return false;
		}

		return $order_completed;
	}

	/**
	 * Add the pre-ordered status to the list of subscription statuses.
	 *
	 * @param string[] $statuses The list of subscription statuses.
	 * @return string[] The modified list of subscription statuses.
	 */
	public function add_pre_order_status( $statuses ) {
		$statuses['wc-pre-ordered'] = _x( 'Pre-ordered', 'Subscription status', 'nextxen-pre-order-manager' );
		return $statuses;
	}

	/**
	 * Determine if the status can be updated from pre-ordered to complete.
	 *
	 * This is hooked in to the checks for updating a subscription status to
	 * completed. If the current status is pre-ordered then it's determined whether
	 * to allow the status change.
	 *
	 * @param bool $can_be_updated Whether the subscription status can be updated.
	 * @param WC_Subscription $subscription The subscription object.
	 * @return bool Whether the subscription status can be updated.
	 */
	public function allow_status_update_from_pre_ordered_to_completed( $can_be_updated, $subscription ) {
		if ( ! $subscription->has_status( 'pre-ordered' ) ) {
			// Not a pre-order, no need to override.
			return $can_be_updated;
		}

		$order = $subscription->get_parent();
		if ( ! $order ) {
			/*
			 * Subscription only, not part of an order.
			 *
			 * As the pre-orders require an order object, the subscription can be
			 * assumed to have been created via the admin or another direct method
			 * that doesn't include a pre-order. The pre-order status is not relevant.
			 */
			return $can_be_updated;
		}

		$pre_order_status = $order->get_meta( '_npom_status', true );

		if ( 'completed' === $pre_order_status ) {
			// Allow the status change from pre-ordered to completed.
			return true;
		}

		// No change to the default value.
		return $can_be_updated;
	}

	/**
	 * Determine if the status can be updated from pre-ordered to active.
	 *
	 * This is hooked in to the checks for updating a subscription status to
	 * active. If the current status is pre-ordered then it's determined whether
	 * to allow the status change.
	 *
	 * @param bool $can_be_updated Whether the subscription status can be updated.
	 * @param WC_Subscription $subscription The subscription object.
	 * @return bool Whether the subscription status can be updated.
	 */
	public function allow_status_update_from_pre_ordered_to_active( $can_be_updated, $subscription ) {
		if ( ! $subscription->has_status( 'pre-ordered' ) ) {
			// Not a pre-order, no need to override.
			return $can_be_updated;
		}

		$order = $subscription->get_parent();
		if ( ! $order ) {
			/*
			 * Subscription only, not part of an order.
			 *
			 * As the pre-orders require an order object, the subscription can be
			 * assumed to have been created via the admin or another direct method
			 * that doesn't include a pre-order. The pre-order status is not relevant.
			 */
			return $can_be_updated;
		}

		$order_status     = $order->get_status();
		$pre_order_status = $order->get_meta( '_npom_status', true );

		if ( 'completed' === $pre_order_status ) {
			// Completed pre-orders should always activate the subscription.
			return true;
		}

		if ( 'active' === $pre_order_status && 'processing' === $order_status && current_user_can( 'manage_woocommerce' ) ) {
			/*
			 * Allow admins to change the status to change from pre-ordered to processing.
			 *
			 * Once a pre-order is "active", changing the order status to "processing"
			 * should activate the subscription as it is being done by the store admin.
			 */
			return true;
		}

		// No change to the default value.
		return $can_be_updated;
	}

	/**
	 * Determine if the status can be updated from on-hold to active.
	 *
	 * Generally this is permitted based on the capabilities of the payment gateway. When
	 * activating a pre-ordered subscription, the transition needs to be made during the
	 * pay for order page.
	 *
	 * @param bool $can_be_updated Whether the subscription status can be updated.
	 * @param WC_Subscription $subscription The subscription object.
	 * @return bool Whether the subscription status can be updated.
	 */
	public function allow_status_update_from_on_hold_to_active( $can_be_updated, $subscription ) {
		if ( ! $subscription->has_status( 'on-hold' ) ) {
			// Not on-hold, no need to override.
			return $can_be_updated;
		}

		$order = $subscription->get_parent();
		if ( ! $order ) {
			/*
			 * Subscription only, not part of an order.
			 *
			 * As the pre-orders require an order object, the subscription can be
			 * assumed to have been created via the admin or another direct method
			 * that doesn't include a pre-order. The pre-order status is not relevant.
			 */
			return $can_be_updated;
		}

		$order_status     = $order->get_status();
		$pre_order_status = $order->get_meta( '_npom_status', true );
		$when_to_charge   = $order->get_meta( '_npom_when_charged', true );

		if (
			'completed' === $pre_order_status
			&& 'upon_release' === $when_to_charge
			&& in_array( $order_status, array( 'processing', 'completed' ), true )
			&& count( $subscription->get_related_orders() ) === 1
		) {
			/*
			 * Allow the status change from on-hold to active for pre-orders that are completed.
			 *
			 * This allows the subscription to be charged once the pre-order is completed for
			 * for orders that are paid on release.
			 */
			return true;
		}

		// No change to the default value.
		return $can_be_updated;
	}

	/**
	 * Determine if the status can be updated from pre-ordered to on-hold.
	 *
	 * This is hooked in to the checks for updating a subscription status to
	 * on-hold. If the current status is pre-ordered then it's determined whether
	 * to allow the status change.
	 *
	 * @param bool $can_be_updated Whether the subscription status can be updated.
	 * @param WC_Subscription $subscription The subscription object.
	 * @return bool Whether the subscription status can be updated.
	 */
	public function allow_status_update_from_pre_ordered_to_on_hold( $can_be_updated, $subscription ) {
		if ( ! $subscription->has_status( 'pre-ordered' ) ) {
			// Not a pre-order, no need to override.
			return $can_be_updated;
		}

		$order = $subscription->get_parent();
		if ( ! $order ) {
			/*
			 * Subscription only, not part of an order.
			 *
			 * As the pre-orders require an order object, the subscription can be
			 * assumed to have been created via the admin or another direct method
			 * that doesn't include a pre-order. The pre-order status is not relevant.
			 */
			return $can_be_updated;
		}

		$order_status     = $order->get_status();
		$pre_order_status = $order->get_meta( '_npom_status', true );

		if ( 'completed' === $pre_order_status ) {
			// Completed pre-orders should always allow updating the subscription.
			return true;
		}

		if ( 'failed' === $order_status || 'on-hold' === $order_status ) {
			// Allow the status to be changed for orders that have failed or use a manual payment method (on-hold).
			return true;
		}

		// No change to the default value.
		return $can_be_updated;
	}

	/**
	 * Modify whether a user can resubscribe to a subscription.
	 *
	 * Prevents a customer from resubscribing to a subscription that was cancelled
	 * prior to the pre-order date completing. This is to account for situations in
	 * which the cancellation is due to the product launch no longer proceeding.
	 *
	 * Runs on the `wcs_can_user_resubscribe_to_subscription` filter.
	 *
	 * @param bool            $can_user_resubscribe Whether the user can resubscribe.
	 * @param WC_Subscription $subscription         The subscription object.
	 * @return bool Modified value of whether the user can resubscribe.
	 */
	public function prevent_resubscribe_for_cancelled_pre_orders( $can_user_resubscribe, $subscription ) {
		$order = $subscription->get_parent();
		if ( ! $order ) {
			/*
			 * Subscription only, not part of an order.
			 *
			 * As the pre-orders require an order object, the subscription can be
			 * assumed to have been created via the admin or another direct method
			 * that doesn't include a pre-order. The pre-order status is not relevant.
			 */
			return $can_user_resubscribe;
		}

		$pre_order_status    = $order->get_meta( '_npom_status', true );
		$subscription_status = $subscription->get_status();

		if ( 'cancelled' === $pre_order_status && 'cancelled' === $subscription_status ) {
			// Prevent cancelled pre-order from being resubscribed too.
			$can_user_resubscribe = false;
		}

		return $can_user_resubscribe;
	}

	/**
	 * Determine if the status can be updated from pre-ordered to cancelled.
	 *
	 * This is hooked in to the checks for updating a subscription status to
	 * cancelled. If the current status is pre-ordered then it's determined whether
	 * to allow the status change.
	 *
	 * @param bool $can_be_updated Whether the subscription status can be updated.
	 * @param WC_Subscription $subscription The subscription object.
	 * @return bool Whether the subscription status can be updated.
	 */
	public function allow_status_update_from_pre_ordered_to_cancelled( $can_be_updated, $subscription ) {
		if ( ! $subscription->has_status( 'pre-ordered' ) ) {
			// Not a pre-order, no need to override.
			return $can_be_updated;
		}

		$order = $subscription->get_parent();
		if ( ! $order ) {
			/*
			 * Subscription only, not part of an order.
			 *
			 * As the pre-orders require an order object, the subscription can be
			 * assumed to have been created via the admin or another direct method
			 * that doesn't include a pre-order. The pre-order status is not relevant.
			 */
			return $can_be_updated;
		}

		$pre_order_status = $order->get_meta( '_npom_status', true );

		if ( 'active' === $pre_order_status ) {
			// Do not allow the status change from pre-ordered to cancelled.
			return false;
		} elseif ( 'cancelled' === $pre_order_status ) {
			// Allow the status change from pre-ordered to cancelled.
			return true;
		}

		// No change to the default value.
		return $can_be_updated;
	}

	/**
	 * Update the subscription start date and first renewal date when the pre-order is paid for.
	 *
	 * This is to ensure the subscription status is correct when the product is first ordered and
	 * paid for but prior to the pre-order availability date.
	 *
	 * Runs on the `woocommerce_payment_complete_order_status` filter.
	 *
	 * @param string $new_status The new order status.
	 * @param int    $order_id    The order ID.
	 * @param object $order       The order object.
	 * @return string The modified order status.
	 */
	public function update_payment_complete_order_status( $new_status, $order_id, $order ) {
		// Not a pre-order, no need to do anything.
		if ( 'pre-ordered' !== $new_status ) {
			return $new_status;
		}

		/*
		 * The woocommerce_payment_complete_order_status hook fires during the pre-order cancel action.
		 *
		 * This prevents the subscription from being returned to the pre-order status during the
		 * bulk cancellation of pre-orders.
		 */
		$pre_order_status = $order->get_meta( '_npom_status', true );
		if ( 'completed' === $pre_order_status || 'cancelled' === $pre_order_status ) {
			// The pre-order has been completed or cancelled, no need to do anything.
			return $new_status;
		}

		/*
		 * NPOM_Checkout::update_payment_complete_order_status has determined this is a pre-order.
		 *
		 * The pre-order completion action will update the subscription start date and the first renewal date.
		 */
		$subscriptions = wcs_get_subscriptions_for_order( $order_id );

		if ( empty( $subscriptions ) ) {
			// No subscriptions in this order.
			return $new_status;
		}

		foreach ( $subscriptions as $subscription ) {
			$subscription->update_status( 'pre-ordered' );

			$subscription_items = $subscription->get_items();
			$product            = reset( $subscription_items )->get_product();
			$base_product_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			// Get the pre-order timestamp for the product.
			$pre_order_availability = NPOM_Product::get_utc_availability_datetime_timestamp( $base_product_id );
			$new_availability_gmt   = gmdate( self::SUBS_DATE_TIME_FORMAT, $pre_order_availability );

			$first_renewal_date = WC_Subscriptions_Product::get_first_renewal_payment_date( $product, $new_availability_gmt );

			// Update the start and next payment (renewal) date for the new availability date.
			$subscription->update_dates(
				array(
					'start_date'        => $new_availability_gmt,
					'next_payment_date' => $first_renewal_date,
				)
			);

			$subscription->save();
		}

		return $new_status;
	}

	/**
	 * Set the subscription start date to the pre-order availability date.
	 *
	 * @param string  $start_date The recurring cart UTC start date in the format YYYY-MM-DD HH:MM:SS.
	 * @param WC_Cart $cart       The cart object.
	 * @return string The modified start date for the subscription.
	 */
	public function set_recurring_cart_start_date( $start_date, $cart ) {
		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			if ( ! NPOM_Product::product_can_be_pre_ordered( $product_id ) ) {
				continue;
			}

			/*
			 * Get the pre-order availability date for the product.
			 *
			 * The pre-order availability date is stored as a unix timestamp in the product meta.
			 * For product variations, the meta data is stored on the parent product so this checks
			 * against the `product_id` rather than the `variation_id`.
			 */
			$pre_order_availability = NPOM_Product::get_utc_availability_datetime_timestamp( $product_id );
			if ( is_numeric( $pre_order_availability ) && $pre_order_availability >= time() ) {
				/*
				 * Set the subscription start date to the pre-order availability date.
				 *
				 * Subscriptions will propagate the first renewal data based on the start date.
				 */
				$start_date = gmdate( self::SUBS_DATE_TIME_FORMAT, $pre_order_availability );
				break;
			}
		}

		return $start_date;
	}

	/**
	 * Update the start date and next payment date for subscriptions when the pre-order availability date changes.
	 *
	 * @param array $args {
	 *     The arguments passed to the npom_pre_order_date_changed action.
	 *
	 *     @type WC_Order $order The order object.
	 *     @type int $availability_date The new availability date.
	 *     @type string $message The email message sent to the customer.
	 * }
	 */
	public function pre_order_date_changed( $args ) {
		list( 'order' => $order, 'availability_date' => $new_availability_date ) = $args;

		// Does this order contain any subscriptions?
		$subscriptions = wcs_get_subscriptions_for_order( $order );

		if ( empty( $subscriptions ) ) {
			// No subscriptions in this order.
			return;
		}

		/*
		 * New availability date is in the format YYYY-MM-DD HH:MM in the WP timezone.
		 *
		 * WooCommerce Subscriptions expects the date to be in the format YYYY-MM-DD HH:MM:SS in the UTC timezone.
		 */
		$new_availability_local = date_create_immutable_from_format( self::PRE_ORDERS_DATE_TIME_FORMAT, $new_availability_date, wp_timezone() );
		$new_availability_gmt   = $new_availability_local->setTimezone( new DateTimeZone( 'UTC' ) )->format( self::SUBS_DATE_TIME_FORMAT );

		// Update the first renewal date for each subscription.
		foreach ( $subscriptions as $subscription ) {
			// Get the subscription product.
			$subscription_items = $subscription->get_items();
			$product            = reset( $subscription_items )->get_product();
			$first_renewal_date = WC_Subscriptions_Product::get_first_renewal_payment_date( $product, $new_availability_gmt );

			// Update the start and next payment (renewal) date for the new availability date.
			$subscription->update_dates(
				array(
					'start_date'        => $new_availability_gmt,
					'next_payment_date' => $first_renewal_date,
				)
			);
		}
	}

	/**
	 * Update the subscription when the order is completed.
	 *
	 * Update the subscription status to active when the order is completed. To account
	 * for store owners manually completing pre-orders, the subscription start date is
	 * updated to the current time.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function pre_order_completed( $order ) {
		// Does this order contain any subscriptions?
		$subscriptions = wcs_get_subscriptions_for_order( $order );

		if ( empty( $subscriptions ) ) {
			// No subscriptions in this order.
			return;
		}

		$order_status = $order->get_status();
		// COD orders are on-hold by default.
		$new_subscription_status = 'on-hold';

		/*
		 * Activate subscriptions for paid orders.
		 *
		 * Orders paid for upfront or via a payment token go in to the processing
		 * or completed state immediately. As these orders are paid for, the
		 * subscription can immediately be activated.
		 */
		if ( in_array( $order_status, array( 'processing', 'completed' ), true ) ) {
			$new_subscription_status = 'active';
		}

		// Update the status to active for each subscription.
		foreach ( $subscriptions as $subscription ) {
			// Activate the subscription.
			$subscription->update_status( $new_subscription_status );
		}

		/*
		 * Date change doesn't run on cron jobs.
		 *
		 * The completion action fires when the pre-order is both manually and
		 * automatically completed via wp-cron. Subscription start dates do not
		 * need to be updated when the completion is triggered by wp-cron as it
		 * happens at the scheduled start time.
		 */
		if ( wp_doing_cron() ) {
			return;
		}

		// Create new date for the current time in the WP timezone.
		$new_availability_local = date_create_immutable( 'now', wp_timezone() )->format( self::PRE_ORDERS_DATE_TIME_FORMAT );
		$new_availability_gmt   = date_create_immutable_from_format( self::PRE_ORDERS_DATE_TIME_FORMAT, $new_availability_local, new DateTimeZone( 'UTC' ) )->format( self::SUBS_DATE_TIME_FORMAT );

		$this->pre_order_date_changed(
			array(
				'order'             => $order,
				'availability_date' => $new_availability_local,
			)
		);
	}

	/**
	 * Cancel the subscription when the pre-order is cancelled.
	 *
	 * Update the subscription status to cancelled when the order is cancelled.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function pre_order_cancelled( $order ) {
		// Does this order contain any subscriptions?
		$subscriptions = wcs_get_subscriptions_for_order( $order );

		if ( empty( $subscriptions ) ) {
			// No subscriptions in this order.
			return;
		}

		// Update the status to cancelled for each subscription.
		foreach ( $subscriptions as $subscription ) {
			$subscription->update_status( 'cancelled' );
		}
	}

	/**
	 * Remove subscription info section from cancellation emails.
	 *
	 * This deregisters the hook adding subscription info to the cancellation emails
	 * of pre-orders. Due to an order of operations issue, the subscription info is
	 * out of date when the email is sent.
	 *
	 * This runs on the `wc_pre_order_status_active_to_cancelled` hook.
	 */
	public function remove_subscription_info_from_cancellation_emails() {
		remove_action( 'woocommerce_email_after_order_table', 'WC_Subscriptions_Order::add_sub_info_email', 15, 3 );
	}
}
