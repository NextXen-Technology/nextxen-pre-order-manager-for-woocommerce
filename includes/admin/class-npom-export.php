<?php
/**
 * WooCommerce Pre-Order Manager – CSV Export (Premium Feature)
 *
 * Adds an "Export CSV" button to the Pre-Orders admin list table.
 * When clicked, the browser downloads a CSV file containing one row
 * per pre-order with the following columns:
 *
 *   Order ID, Status, Customer Name, Customer Email, Product,
 *   Release Date, When Charged, Deposit Paid, Remaining Balance,
 *   Order Total, Date Created
 *
 * The export is triggered via a GET request to the existing admin page
 * with ?action=export_csv appended.  A nonce is used for security.
 *
 * @package NPOM/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NPOM_Export
 */
class NPOM_Export {

	/**
	 * Nonce action string.
	 */
	const NONCE_ACTION = 'npom_export_csv';

	/**
	 * Boot hooks.
	 */
	public function __construct() {
		// Handle the CSV download request (fires early so we can stream headers).
		add_action( 'admin_init', array( $this, 'maybe_export' ) );

		// Inject the "Export CSV" button into the Pre-Orders admin page header.
		add_action( 'npom_admin_page_actions', array( $this, 'render_export_button' ) );
	}

	// -------------------------------------------------------------------------
	// Export button
	// -------------------------------------------------------------------------

	/**
	 * Output the Export CSV button markup.
	 * Hooked to npom_admin_page_actions (fired from admin page view).
	 */
	public function render_export_button() {
		$url = wp_nonce_url(
			admin_url( 'admin.php?page=npom_manager&action=export_csv' ),
			self::NONCE_ACTION
		);
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( $url ),
			esc_html__( 'Export CSV', 'nextxen-pre-order-manager' )
		);
	}

	// -------------------------------------------------------------------------
	// Export handler
	// -------------------------------------------------------------------------

	/**
	 * If the current request is an export request, stream the CSV and exit.
	 */
	public function maybe_export() {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			! isset( $_GET['page'], $_GET['action'] ) ||
			'npom_manager' !== $_GET['page'] ||
			'export_csv' !== $_GET['action']
		) {
			return;
		}
		// phpcs:enable

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export pre-orders.', 'nextxen-pre-order-manager' ) );
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'nextxen-pre-order-manager' ) );
		}

		$this->stream_csv();
		exit;
	}

	// -------------------------------------------------------------------------
	// CSV generation
	// -------------------------------------------------------------------------

	/**
	 * Collect all pre-orders, build CSV rows and stream the file to the browser.
	 */
	private function stream_csv() {
		// sanitize_file_name strips special characters that date_i18n() might
		// produce in some locales, preventing header-injection in Content-Disposition.
		$filename = sanitize_file_name( 'pre-orders-' . date_i18n( 'Y-m-d-His' ) . '.csv' );

		// Headers to trigger browser download.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// BOM for Excel UTF-8 compatibility.
		fputs( $output, "\xEF\xBB\xBF" );

		// Column headers.
		fputcsv(
			$output,
			array(
				__( 'Order ID', 'nextxen-pre-order-manager' ),
				__( 'Pre-Order Status', 'nextxen-pre-order-manager' ),
				__( 'Order Status', 'nextxen-pre-order-manager' ),
				__( 'Customer Name', 'nextxen-pre-order-manager' ),
				__( 'Customer Email', 'nextxen-pre-order-manager' ),
				__( 'Product', 'nextxen-pre-order-manager' ),
				__( 'Release Date', 'nextxen-pre-order-manager' ),
				__( 'When Charged', 'nextxen-pre-order-manager' ),
				__( 'Deposit Paid', 'nextxen-pre-order-manager' ),
				__( 'Remaining Balance', 'nextxen-pre-order-manager' ),
				__( 'Order Total', 'nextxen-pre-order-manager' ),
				__( 'Date Created', 'nextxen-pre-order-manager' ),
			)
		);

		// Fetch all pre-orders (batched to avoid memory exhaustion).
		$page = 1;

		do {
			$orders = wc_get_orders(
				array(
					'meta_key'     => '_npom_is_pre_order',
					'meta_value'   => 1,
					'meta_compare' => '=',
					'return'       => 'objects',
					'limit'        => 100,
					'paged'        => $page,
				)
			);

			foreach ( $orders as $order ) {
				fputcsv( $output, $this->build_row( $order ) );
			}

			$page++;
		} while ( count( $orders ) === 100 );

		fclose( $output );
	}

	/**
	 * Build a single CSV data row from a WC_Order object.
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	private function build_row( $order ) {
		// Pre-order status (active / completed / cancelled).
		$pre_order_status = $order->get_meta( '_npom_status', true );

		// Customer name.
		$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		// Product and release date.
		$product      = NPOM_Order::get_pre_order_product( $order );
		$product_name = $product ? $product->get_name() : __( 'N/A', 'nextxen-pre-order-manager' );
		$release_date = $product
			? NPOM_Product::get_localized_availability_date( $product, __( 'No date set', 'nextxen-pre-order-manager' ) )
			: __( 'N/A', 'nextxen-pre-order-manager' );

		// When charged.
		$when_charged_raw = $order->get_meta( '_npom_when_charged', true );
		if ( 'upon_release' === $when_charged_raw ) {
			$when_charged = __( 'Upon release', 'nextxen-pre-order-manager' );
		} elseif ( 'upfront' === $when_charged_raw ) {
			$when_charged = __( 'Upfront', 'nextxen-pre-order-manager' );
		} else {
			$when_charged = __( 'N/A', 'nextxen-pre-order-manager' );
		}

		// Deposit fields (empty strings when no deposit was used).
		$deposit_paid      = $order->get_meta( '_npom_deposit_paid', true );
		$remaining_balance = $order->get_meta( '_npom_remaining_balance', true );

		return array(
			$order->get_id(),
			$pre_order_status ?: __( 'N/A', 'nextxen-pre-order-manager' ),
			wc_get_order_status_name( $order->get_status() ),
			$customer_name ?: __( 'Guest', 'nextxen-pre-order-manager' ),
			$order->get_billing_email(),
			$product_name,
			$release_date,
			$when_charged,
			$deposit_paid !== '' ? wc_format_decimal( $deposit_paid, 2 ) : '',
			$remaining_balance !== '' ? wc_format_decimal( $remaining_balance, 2 ) : '',
			wc_format_decimal( $order->get_total(), 2 ),
			$order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ) : '',
		);
	}
}
