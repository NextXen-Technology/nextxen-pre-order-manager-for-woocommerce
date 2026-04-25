<?php
/**
 * WooCommerce Pre-Order Manager
 *
 * @package   NPOM/Admin
 * @author    NextXen Technology
 * @copyright Copyright (c) 2015, WooThemes
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pre-Orders Admin Pre Orders class.
 */
class NPOM_Admin_Pre_Orders {

	/**
	 * The pre-orders list table object.
	 *
	 * @var NPOM_List_Table
	 */
	private $pre_orders_list_table;

	/**
	 * Mensage transient prefix.
	 *
	 * @var string
	 */
	private $message_transient_prefix = '_npom_messages_';

	/**
	 * Initialize the admin settings actions.
	 */
	public function __construct() {
		// Add 'Pre-Orders' link under WooCommerce menu.
		add_action( 'admin_menu', array( $this, 'add_menu_link' ) );

		// Pre-Orders list table settings
		add_action( 'in_admin_header', array( $this, 'load_pre_orders_list_table' ) );
		add_filter( 'set-screen-option', array( $this, 'set_pre_orders_list_option' ), 10, 3 );

		// Remove query string args from URLs in the admin.
		add_filter( 'removable_query_args', array( $this, 'remove_query_args' ) );
	}

	/**
	 * Modify query string parameters to be removed on Pre-order admin pages.
	 *
	 * Modifies the query string parameters to be removed via JavaScript when setting the canonical
	 * admin URL for the admin page.
	 *
	 * The success and product ID query string parameters are removed to reduce the chance of
	 * a user bookmarking the page with a success message or product ID in the URL.
	 *
	 * @param string[] $args Query string parameter that can be removed.
	 * @return string[] Modified query string parameters.
	 */
	public function remove_query_args( $args ) {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'admin.php' !== $pagenow || ! isset( $_GET['page'] ) || 'npom_manager' !== $_GET['page'] ) {
			return $args;
		}

		$args[] = 'success';
		$args[] = 'product_id';
		$args[] = 'cancel_pre_order_nonce';

		return $args;
	}

	/**
	 * Get pre-orders tabs.
	 *
	 * @return array
	 */
	protected function get_tabs() {
		return array(
			'manage'  => __( 'Manage', 'nextxen-pre-order-manager' ),
			'actions' => __( 'Actions', 'nextxen-pre-order-manager' ),
		);
	}

	/**
	 * Add 'Pre-Orders' sub-menu link under 'WooCommerce' top level menu.
	 */
	public function add_menu_link() {

		$hook = add_submenu_page(
			'woocommerce',
			__( 'Pre-orders', 'nextxen-pre-order-manager' ),
			__( 'Pre-orders', 'nextxen-pre-order-manager' ),
			'manage_woocommerce',
			'npom_manager',
			array( $this, 'show_sub_menu_page' )
		);

		// add the Pre-Orders list Screen Options
		add_action( 'load-woocommerce_page_npom_manager', array( $this, 'add_pre_orders_list_options' ) );
		add_action( 'load-' . $hook, array( $this, 'process_actions' ) );
	}

	/**
	 * Show Pre-Orders Manage/Actions page content.
	 */
	public function show_sub_menu_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = ( empty( $_GET['tab'] ) ) ? 'manage' : urldecode( sanitize_text_field( wp_unslash( $_GET['tab'] ) ) );

		echo '<div class="wrap woocommerce">';
		echo '<div id="icon-woocommerce" class="icon32-woocommerce-users icon32"><br /></div>';
		echo '<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">';

		// Display tabs.
		foreach ( $this->get_tabs() as $tab_id => $tab_title ) {

			$class = ( $tab_id === $current_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			$url   = add_query_arg( 'tab', $tab_id, admin_url( 'admin.php?page=npom_manager' ) );

			printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_attr( $tab_title ) );
		}

		echo '</h2>';

		// Allow premium features (e.g. CSV export) to inject page-level action buttons.
		do_action( 'npom_admin_page_actions' );

		// Show any messages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['success'] ) ) {
			$notice_type     = 'notice-success';
			$extended_notice = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			switch ( $_GET['success'] ) {

				case 'email':
					$message = __( 'Pre-order customers emailed successfully', 'nextxen-pre-order-manager' );
					break;

				case 'change-date':
					$message = __( 'Pre-order date changed', 'nextxen-pre-order-manager' );
					break;

				case 'complete':
					$message = __( 'Pre-orders completed', 'nextxen-pre-order-manager' );
					break;

				case 'cancel':
					$message = __( 'Pre-orders cancelled', 'nextxen-pre-order-manager' );
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- not required, does not modify data
					if ( isset( $_GET['product_id'] ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- not required, does not modify data
						$product_id = (int) $_GET['product_id'];

						if ( NPOM_Product::product_is_charged_upfront( $product_id ) ) {
							$notice_type = 'notice-error';

							$extended_notice[] = sprintf(
								/* translators: 1: The name of the product that was cancelled. */
								__( 'The product %1$s is set to charge upfront. You must manually refund the customer within each order.', 'nextxen-pre-order-manager' ),
								wp_strip_all_tags( wc_get_product( $product_id )->get_name() )
							);
						} else {
							$extended_notice[] = sprintf(
								/* translators: 1: The name of the product that was cancelled. */
								__( 'The product %1$s is set to charge upon release. If the product was previously set to charge customers upfront, these orders will need to be manually refunded.', 'nextxen-pre-order-manager' ),
								wp_strip_all_tags( wc_get_product( $product_id )->get_name() )
							);
						}

						$extended_notice[] = sprintf(
							/* translators: 1: Opening link tag to manage pre-orders page; 2: closing link tag */
							__( 'You can view a list of %1$scancelled orders for the product%2$s on the manage pre-orders page.', 'nextxen-pre-order-manager' ),
							'<a href="' . add_query_arg( '_product', (int) $product_id, admin_url( 'admin.php?page=npom_manager&pre_order_status=cancelled' ) ) . '">',
							'</a>'
						);
					}

					break;

				default:
					$message = '';
					break;
			}

			if ( $message ) {
				echo '<div id="message" class="' . sanitize_html_class( $notice_type ) . ' notice fade">';
				echo '<p><strong>' . wp_kses_post( $message ) . '</strong></p>';

				if ( $extended_notice ) {
					$extended_notice = (array) $extended_notice;
					foreach ( $extended_notice as $paragraph ) {
						echo '<p>' . wp_kses_post( $paragraph ) . '</p>';
					}
				}

				echo '</div>';
			}
		}

		// Display tab content, default to 'Manage' tab.
		if ( 'actions' === $current_tab ) {
			$this->show_actions_tab();
		} else {
			$this->show_manage_tab();
		}

		echo '</div>';
	}

	/**
	 * Add the Pre-Orders list table Screen Options.
	 */
	public function add_pre_orders_list_options() {
		$args = array(
			'label'   => __( 'Pre-orders', 'nextxen-pre-order-manager' ),
			'default' => 20,
			'option'  => 'npom_edit_pre_orders_per_page',
		);

		add_screen_option( 'per_page', $args );
	}

	/**
	 * Processes the cancelling of individual pre-order.
	 *
	 * @since 1.4.6
	 * @version 1.4.7
	 * @return bool
	 */
	public function process_cancel_pre_order_action() {
		if ( empty( $_GET['action'] ) || 'cancel_pre_order' !== $_GET['action'] ) {
			return;
		}

		if (
			empty( $_GET['cancel_pre_order_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['cancel_pre_order_nonce'] ) ), 'cancel_pre_order' )
		) {
			wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'nextxen-pre-order-manager' ) );
		}

		// User check.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have the correct permissions to do this.', 'nextxen-pre-order-manager' ) );
		}

		$order_id = ( ! empty( $_GET['order_id'] ) ) ? absint( $_GET['order_id'] ) : '';

		NPOM_Manager::cancel_pre_order( $order_id );

		/* translators: %s = order id */
		$message = sprintf( __( 'Pre-order #%s cancelled.', 'nextxen-pre-order-manager' ), $order_id );
		if ( ! NPOM_Order::order_will_be_charged_upon_release( $order_id ) ) {
			$message .= ' ';
			$message .= __( 'The order was paid upfront, you will need to manually process a refund for this order.', 'nextxen-pre-order-manager' );
		}

		$this->_redirect_with_notice( $message );
	}

	/**
	 * Process the actions from the 'Actions' tab.
	 */
	public function process_actions_tab() {
		global $npom_manager;

		if ( empty( $_POST['npom_action'] ) ) {
			return;
		}

		// Security check.
		if (
			! isset( $_POST['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'npom-process-actions' )
		) {
			wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'nextxen-pre-order-manager' ) );
		}

		// User check.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have the correct permissions to do this.', 'nextxen-pre-order-manager' ) );
		}

		// Get parameters.
		$raw_action            = isset( $_POST['npom_action'] ) ? sanitize_key( wp_unslash( $_POST['npom_action'] ) ) : '';
		$action                = in_array( $raw_action, array( 'email', 'change-date', 'complete', 'cancel' ), true ) ? $raw_action : '';
		$product_id            = ( ! empty( $_POST['npom_action_product'] ) ) ? absint( $_POST['npom_action_product'] ) : '';
		$send_email            = ( isset( $_POST['npom_action_enable_email_notification'] ) && '1' === $_POST['npom_action_enable_email_notification'] ) ? true : false;
		$email_message         = ( ! empty( $_POST['npom_action_email_message'] ) ) ? wp_kses_post( wp_unslash( $_POST['npom_action_email_message'] ) ) : '';
		$new_availability_date = ( ! empty( $_POST['npom_action_new_availability_date'] ) ) ? sanitize_text_field( wp_unslash( $_POST['npom_action_new_availability_date'] ) ) : '';

		if ( ! $action || ! $product_id ) {
			return;
		}

		switch ( $action ) {

			// Email all pre-ordered customers.
			case 'email':
				NPOM_Manager::email_all_pre_order_customers( $product_id, $email_message );

				break;

			// Change the release date for all pre-orders.
			case 'change-date':
				// Remove email notification if disabled.
				if ( ! $send_email ) {
					remove_action( 'npom_pre_order_date_changed', array( $npom_manager, 'send_transactional_email' ), 10 );
				}

				NPOM_Manager::change_release_date_for_all_pre_orders( $product_id, $new_availability_date, $email_message );

				break;

			// Complete all pre-orders.
			case 'complete':
				// Remove email notification if disabled.
				if ( ! $send_email ) {
					remove_action( 'wc_pre_order_status_completed', array( $npom_manager, 'send_transactional_email' ), 10 );
				}

				NPOM_Manager::complete_all_pre_orders( $product_id, $email_message );

				break;

			// Cancel all pre-orders.
			case 'cancel':
				// Remove email notification if disabled.
				if ( ! $send_email ) {
					remove_action( 'wc_pre_order_status_active_to_cancelled', array( $npom_manager, 'send_transactional_email' ), 10 );
				}

				NPOM_Manager::cancel_all_pre_orders( $product_id, $email_message );

				break;

			default:
				break;
		}

		wp_safe_redirect(
			esc_url_raw(
				add_query_arg(
					array(
						'action_default_product' => false, // Remove.
						'success'                => wp_unslash( sanitize_key( $_POST['npom_action'] ) ),
						'product_id'             => $product_id,
					)
				)
			)
		);
		exit;
	}

	/**
	 * Process the actions from the 'Manage' tab.
	 */
	public function process_manage_tab() {
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-pre-orders' ) ) {
			return;
		}

		// Get the current action (if any).
		$action = $this->current_action();

		// Cancellation of individual pre-order should be handled by
		// self::process_cancel_pre_order_action.
		if ( 'cancel_pre_order' === $action ) {
			return;
		}

		// Get the set of orders to operate on.
		$order_ids = isset( $_REQUEST['order_id'] ) ? array_map( 'absint', $_REQUEST['order_id'] ) : array();

		$message = $this->get_current_customer_message();

		// No action, or invalid action.
		if ( isset( $_GET['page'] ) && 'npom_manager' === $_GET['page'] ) {

			if ( false === $action || empty( $order_ids ) ) {
				if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
					// remove _wp_http_referer/_wp_nonce/action params
					$request_uri = ! empty( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					wp_safe_redirect(
						esc_url_raw(
							remove_query_arg(
								array( '_wp_http_referer', '_wpnonce', 'action', 'action2' ),
								$request_uri
							)
						)
					);
					exit;
				}
				return;
			}

			$success_count = 0;
			$error_count   = 0;
			$paid_upfront  = array();

			// Process the orders
			foreach ( $order_ids as $order_id ) {

				$order = new WC_Order( $order_id );

				// Perform the action.
				switch ( $action ) {
					case 'cancel':
						if ( NPOM_Manager::can_pre_order_be_changed_to( 'cancelled', $order ) ) {
							$success_count++;
							if ( ! NPOM_Order::order_will_be_charged_upon_release( $order ) ) {
								$paid_upfront[] = $order->get_id();
							}
							NPOM_Manager::cancel_pre_order( $order, $message );
						} else {
							$error_count++;
						}
						break;

					case 'complete':
						if ( NPOM_Manager::can_pre_order_be_changed_to( 'completed', $order ) ) {
							$success_count++;
							NPOM_Manager::complete_pre_order( $order, $message );
						} else {
							$error_count++;
						}
						break;

					case 'message':
						NPOM_Manager::email_pre_order_customer( $order_id, $message );
						break;
				}
			}

			$messages = array();

			switch ( $action ) {
				case 'cancel':
					if ( $success_count > 0 ) {
						/* translators: %d = success count */
						$messages[] = sprintf( _n( '%d pre-order cancelled.', '%d pre-orders cancelled.', $success_count, 'nextxen-pre-order-manager' ), $success_count );
					}
					if ( $error_count > 0 ) {
						/* translators: %d = error count */
						$messages[] = sprintf( _n( '%d pre-order could not be cancelled.', '%d pre-orders could not be cancelled.', $error_count, 'nextxen-pre-order-manager' ), $error_count );
					}

					if ( count( $paid_upfront ) > 0 ) {
						$messages[] = sprintf(
							/* translators: $d number of orders paid for upfront */
							_n( '%d order was paid upfront, you will need to manually process a refund for this order.', '%d orders were paid upfront, you will need to manually process refunds for these orders.', count( $paid_upfront ), 'nextxen-pre-order-manager' ),
							count( $paid_upfront )
						);
					}

					break;

				case 'complete':
					if ( $success_count > 0 ) {
						/* translators: %d = success count */
						$messages[] = sprintf( _n( '%d pre-order completed.', '%d pre-orders completed.', $success_count, 'nextxen-pre-order-manager' ), $success_count );
					}
					if ( $error_count > 0 ) {
						/* translators: %d = error count */
						$messages[] = sprintf( _n( '%d pre-order could not be completed.', '%d pre-orders could not be completed.', $error_count, 'nextxen-pre-order-manager' ), $error_count );
					}
					break;

				case 'message':
					/* translators: %d = The count of emails dispatched */
					$messages[] = sprintf( _n( '%d email dispatched.', '%d emails dispatched.', count( $order_ids ), 'nextxen-pre-order-manager' ), count( $order_ids ) );
					break;
			}

			$this->_redirect_with_notice( implode( '  ', $messages ) );
		}
	}

	/**
	 * Get the current action selected from the bulk actions dropdown, verifying
	 * that it's a valid action to perform.
	 *
	 * @see WP_List_Table::current_action()
	 *
	 * @return string|bool The action name or False if no action was selected.
	 */
	public function current_action() {
		$current_action = false;

		if ( isset( $_REQUEST['action'] ) && -1 !== sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( isset( $_REQUEST['action2'] ) && -1 !== sanitize_text_field( wp_unslash( $_REQUEST['action2'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_action = sanitize_text_field( wp_unslash( $_REQUEST['action2'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$valid_actions   = array_keys( $this->get_bulk_actions() );
		$valid_actions[] = 'cancel_pre_order';

		if ( $current_action && ! in_array( $current_action, $valid_actions ) ) {
			return false;
		}

		return $current_action;
	}

	/**
	 * Dispatch actions from Manage tab and Actions tab.
	 *
	 * @since 1.0
	 */
	public function process_actions() {
		$this->process_actions_tab();
		$this->process_manage_tab();
		$this->process_cancel_pre_order_action();
	}

	/**
	 * Gets the bulk actions available for pre-orders: complete, cancel or message.
	 *
	 * @see WP_List_Table::get_bulk_actions()
	 *
	 * @return array associative array of action_slug => action_title.
	 */
	public function get_bulk_actions() {
		$actions = array(
			'cancel'   => __( 'Cancel', 'nextxen-pre-order-manager' ),
			'complete' => __( 'Complete', 'nextxen-pre-order-manager' ),
			'message'  => __( 'Customer message', 'nextxen-pre-order-manager' ),
		);

		return $actions;
	}

	/**
	 * Gets the current customer message which is used for bulk actions.
	 *
	 * @return string the current customer message.
	 */
	public function get_current_customer_message() {
		if ( isset( $_REQUEST['customer_message'] ) && sanitize_text_field( wp_unslash( $_REQUEST['customer_message'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( $_REQUEST['customer_message'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( isset( $_REQUEST['customer_message2'] ) && sanitize_text_field( wp_unslash( $_REQUEST['customer_message2'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( $_REQUEST['customer_message2'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return null;
	}

	/**
	 * Loads the pre-orders list table so the columns can be hidden/shown from
	 * the page Screen Options dropdown (this must be done prior to Screen Options
	 * being rendered).
	 */
	public function load_pre_orders_list_table() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && 'npom_manager' === $_GET['page'] ) {
			$this->get_pre_orders_list_table();
		}
	}

	/**
	 * Gets the pre-orders list table object.
	 *
	 * @return NPOM_List_Table the pre-orders list table object
	 */
	private function get_pre_orders_list_table() {
		global $npom_manager;

		if ( ! isset( $this->pre_orders_list_table ) ) {

			$class_name = apply_filters( 'npom_list_table_class_name', 'NPOM_List_Table' );

			require $npom_manager->get_plugin_path() . '/includes/class-npom-list-table.php';
			$this->pre_orders_list_table = new $class_name();
		}

		return $this->pre_orders_list_table;
	}

	/**
	 * Show the Pre-Orders > Actions tab content.
	 */
	private function show_actions_tab() {
		global $woocommerce;

		// Load file for woocommerce_admin_fields() usage.
		if ( file_exists( $woocommerce->plugin_path() . '/includes/admin/wc-admin-functions.php' ) ) {
			require_once $woocommerce->plugin_path() . '/includes/admin/wc-admin-functions.php';
		} else {
			require_once $woocommerce->plugin_path() . '/admin/woocommerce-admin-settings.php';
		}

		$has_pre_order_products = NPOM_Manager::has_pre_order_enabled_products();
		if ( ! $has_pre_order_products ) {
			?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'There is no pre-order product currently. List of pre-order products will appear in the drop-down Product below.', 'nextxen-pre-order-manager' ); ?></p>
			</div>
			<?php
		}

		// Add 'submit_button' woocommerce_admin_fields() field type.
		add_action( 'woocommerce_admin_field_submit_button', array( $this, 'generate_submit_button' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_section = ( empty( $_REQUEST['section'] ) ) ? 'email' : urldecode( sanitize_text_field( wp_unslash( $_REQUEST['section'] ) ) );

		$actions = array(
			'email'       => __( 'Email', 'nextxen-pre-order-manager' ),
			'change-date' => __( 'Change release date', 'nextxen-pre-order-manager' ),
			'complete'    => __( 'Complete', 'nextxen-pre-order-manager' ),
			'cancel'      => __( 'Cancel', 'nextxen-pre-order-manager' ),
		);

		foreach ( $actions as $action_id => $action_title ) {
			$current = ( $action_id === $current_section ) ? ' class="current"' : '';

			$links[] = sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'section' => $action_id ), admin_url( 'admin.php?page=npom_manager&tab=actions' ) ), $current, $action_title );
		}

		echo '<ul class="subsubsub"><li>' . wp_kses_post( implode( ' | </li><li>', $links ) ) . '</li></ul><br class="clear" />';
		echo '<form method="post" id="mainform" action="" enctype="multipart/form-data">';
		woocommerce_admin_fields( $this->get_action_fields( $current_section ) );
		wp_nonce_field( 'npom-process-actions' );
		echo '<input type="hidden" name="npom_action" value="' . esc_attr( $current_section ) . '" /></form>';
	}

	/**
	 * Show the Pre-Orders > Manage tab content.
	 */
	private function show_manage_tab() {
		// Setup 'Manage Pre-Orders' list table and prepare the data.
		$manage_table = $this->get_pre_orders_list_table();
		$manage_table->prepare_items();

		echo '<form method="get" id="mainform" action="" enctype="multipart/form-data">';
		// title/search result string
		echo '<h2>' . esc_html__( 'Manage pre-orders', 'nextxen-pre-order-manager' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['s'] ) && sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) {
			$search_query = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			/* translators: %s = The search query */
			echo '<span class="subtitle">' . sprintf( esc_html__( 'Search results for "%s"', 'nextxen-pre-order-manager' ), esc_attr( $search_query ) ) . '</span>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		echo '</h2>';

		// display any action messages
		$manage_table->render_messages();

		// Display the views
		$manage_table->views();
		$manage_table->search_box( __( 'Search pre-orders', 'nextxen-pre-order-manager' ), 'pre_order' );

		$pre_order_status = isset( $_REQUEST['pre_order_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pre_order_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$_page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! empty( $pre_order_status ) ) {
			echo '<input type="hidden" name="pre_order_status" value="' . esc_attr( $pre_order_status ) . '" />';
		}

		if ( ! empty( $_page ) ) {
			echo '<input type="hidden" name="page" value="' . esc_attr( $_page ) . '" />';
		}

		// display the list table
		$manage_table->display();
		echo '</form>';
	}

	/**
	 * Get the fields to display for the selected action, in the format required by woocommerce_admin_fields().
	 *
	 * @param  string $section The current section to get fields for.
	 *
	 * @return array
	 */
	private function get_action_fields( $section ) {

		$products = array();
		$fields   = array(

			'email'       => array(

				array(
					'name' => __( 'Email pre-order customers', 'nextxen-pre-order-manager' ),
					/* translators: %1$s = Opening anchor tag for WooCommerce email customer note,  %2$s = Closing anchor tag */
					'desc' => sprintf( __( 'You may send an email message to all customers who have pre-ordered a specific product. This will use the default template specified for the %1$sCustomer Note%2$s Email.', 'nextxen-pre-order-manager' ), '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=email&section=wc_email_customer_note' ) . '">', '</a>' ),
					'type' => 'title',
				),

				array(
					'id'                => 'npom_action_product',
					'name'              => __( 'Product', 'nextxen-pre-order-manager' ),
					'desc_tip'          => __( 'Select which product to email all pre-ordered customers.', 'nextxen-pre-order-manager' ),
					'default'           => ' ',
					'options'           => $products,
					'type'              => 'select',
					'class'             => 'wc-product-search',
					'custom_attributes' => array(
						'required'         => 'required',
						'data-action'      => 'npom_json_search_products',
						'data-placeholder' => __( 'Search for a product&hellip;', 'nextxen-pre-order-manager' ),
					),
				),

				array(
					'id'                => 'npom_action_email_message',
					'name'              => __( 'Message', 'nextxen-pre-order-manager' ),
					'desc_tip'          => __( 'Enter a message to include in the email notification to customer. Limited HTML allowed.', 'nextxen-pre-order-manager' ),
					'css'               => 'min-width: 300px;',
					'default'           => '',
					'type'              => 'textarea',
					'custom_attributes' => array(
						'required' => 'required',
					),
				),

				array( 'type' => 'sectionend' ),

				array(
					'name' => __( 'Send emails', 'nextxen-pre-order-manager' ),
					'type' => 'submit_button',
				),
			),

			'change-date' => array(

				array(
					'name' => __( 'Change the pre-order release date', 'nextxen-pre-order-manager' ),
					'desc' => __( 'You may change the release date for all pre-orders of a specific product. This will send an email notification to each customer informing them that the pre-order release date was changed, along with the new release date.', 'nextxen-pre-order-manager' ),
					'type' => 'title',
				),

				array(
					'id'                => 'npom_action_product',
					'name'              => __( 'Product', 'nextxen-pre-order-manager' ),
					'desc_tip'          => __( 'Select which product to change the release date for.', 'nextxen-pre-order-manager' ),
					'default'           => ( ! empty( $_GET['action_default_product'] ) ) ? absint( $_GET['action_default_product'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					'options'           => $products,
					'type'              => 'select',
					'class'             => 'wc-product-search',
					'custom_attributes' => array(
						'required'         => 'required',
						'data-action'      => 'npom_json_search_products',
						'data-placeholder' => __( 'Search for a product&hellip;', 'nextxen-pre-order-manager' ),
					),
				),

				array(
					'id'                => 'npom_action_new_availability_date',
					'name'              => __( 'New availability date', 'nextxen-pre-order-manager' ),
					'desc_tip'          => __( 'The new availability date for the product. This must be later than the current availability date.', 'nextxen-pre-order-manager' ),
					'default'           => '',
					'type'              => 'text',
					'custom_attributes' => array(
						'required' => 'required',
					),
				),

				array(
					'id'      => 'npom_action_enable_email_notification',
					'name'    => __( 'Send email notification', 'nextxen-pre-order-manager' ),
					'desc'    => __( 'Uncheck this to prevent email notifications from being sent to customers.', 'nextxen-pre-order-manager' ),
					'default' => 'yes',
					'type'    => 'checkbox',
				),

				array(
					'id'       => 'npom_action_email_message',
					'name'     => __( 'Message', 'nextxen-pre-order-manager' ),
					'desc_tip' => __( 'Enter a message to include in the email notification to customer.', 'nextxen-pre-order-manager' ),
					'default'  => '',
					'css'      => 'min-width: 300px;',
					'type'     => 'textarea',
				),

				array( 'type' => 'sectionend' ),

				array(
					'name' => __( 'Change release date', 'nextxen-pre-order-manager' ),
					'type' => 'submit_button',
				),
			),

			'complete'    => array(

				array(
					'name' => __( 'Complete pre-orders', 'nextxen-pre-order-manager' ),
					'desc' => __( 'You may complete all pre-orders for a specific product. This will charge the customer\'s card the pre-ordered amount, change their order status to completed, and send them an email notification.', 'nextxen-pre-order-manager' ),
					'type' => 'title',
				),

				array(
					'id'                => 'npom_action_product',
					'name'              => __( 'Product', 'nextxen-pre-order-manager' ),
					'desc_tip'          => __( 'Select which product to complete all pre-orders for.', 'nextxen-pre-order-manager' ),
					'default'           => ' ',
					'options'           => $products,
					'type'              => 'select',
					'class'             => 'wc-product-search',
					'custom_attributes' => array(
						'required'         => 'required',
						'data-action'      => 'npom_json_search_products',
						'data-placeholder' => __( 'Search for a product&hellip;', 'nextxen-pre-order-manager' ),
					),
				),

				array(
					'id'      => 'npom_action_enable_email_notification',
					'name'    => __( 'Send email notification', 'nextxen-pre-order-manager' ),
					'desc'    => __( 'Uncheck this to prevent email notifications from being sent to customers.', 'nextxen-pre-order-manager' ),
					'default' => 'yes',
					'type'    => 'checkbox',
				),

				array(
					'id'       => 'npom_action_email_message',
					'name'     => __( 'Message', 'nextxen-pre-order-manager' ),
					'desc_tip' => __( 'Enter a message to include in the email notification to customer.', 'nextxen-pre-order-manager' ),
					'default'  => '',
					'css'      => 'min-width: 300px;',
					'type'     => 'textarea',
				),

				array( 'type' => 'sectionend' ),

				array(
					'name' => __( 'Complete pre-orders', 'nextxen-pre-order-manager' ),
					'type' => 'submit_button',
				),
			),

			'cancel'      => array(
				array(
					'name' => __( 'Cancel pre-orders', 'nextxen-pre-order-manager' ),
					'desc' => __( 'You may cancel all pre-orders for a specific product. This will mark the order as cancelled and send the customer an email notification. If pre-orders were charged upfront, you must manually refund the orders.', 'nextxen-pre-order-manager' ),
					'type' => 'title',
				),

				array(
					'id'                => 'npom_action_product',
					'name'              => __( 'Product', 'nextxen-pre-order-manager' ),
					'desc_tip'          => __( 'Select which product to cancel all pre-orders for.', 'nextxen-pre-order-manager' ),
					'default'           => ' ',
					'options'           => $products,
					'type'              => 'select',
					'class'             => 'wc-product-search',
					'custom_attributes' => array(
						'required'         => 'required',
						'data-action'      => 'npom_json_search_products',
						'data-placeholder' => __( 'Search for a product&hellip;', 'nextxen-pre-order-manager' ),
					),
				),

				array(
					'id'      => 'npom_action_enable_email_notification',
					'name'    => __( 'Send email notification', 'nextxen-pre-order-manager' ),
					'desc'    => __( 'Uncheck this to prevent email notifications from being sent to customers.', 'nextxen-pre-order-manager' ),
					'default' => 'yes',
					'type'    => 'checkbox',
				),

				array(
					'id'       => 'npom_action_email_message',
					'name'     => __( 'Message', 'nextxen-pre-order-manager' ),
					'desc_tip' => __( 'Enter a message to include in the email notification to customer.', 'nextxen-pre-order-manager' ),
					'default'  => '',
					'css'      => 'min-width: 300px;',
					'type'     => 'textarea',
				),

				array( 'type' => 'sectionend' ),

				array(
					'name' => __( 'Cancel pre-orders', 'nextxen-pre-order-manager' ),
					'type' => 'submit_button',
				),
			),
		);

		return ( isset( $fields[ $section ] ) ) ? $fields[ $section ] : array();
	}

	/**
	 * Generate a submit button, called via a do_action() inside woocommerce_admin_fields() for non-default field types.
	 *
	 * @param array $field The field info.
	 */
	public function generate_submit_button( $field ) {
		submit_button( $field['name'] );
	}

	/**
	 * Save our list option.
	 *
	 * @param  string $status unknown.
	 * @param  string $option the option name.
	 * @param  string $value the option value.
	 *
	 * @return string
	 */
	public function set_pre_orders_list_option( $status, $option, $value ) {
		if ( 'npom_edit_pre_orders_per_page' === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Redirect with message notice.
	 *
	 * @since 1.4.7
	 *
	 * @param string $message Message to display
	 */
	protected function _redirect_with_notice( $message ) {
		$message_nonce = wp_create_nonce( __FILE__ );

		set_transient( $this->message_transient_prefix . $message_nonce, array( 'messages' => $message ), 60 * 60 );

		// Get our next destination, stripping out all actions and other unneeded parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['_wp_http_referer'] ) ) {
			$redirect_url = sanitize_text_field( wp_unslash( $_REQUEST['_wp_http_referer'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$redirect_url = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'action', 'action2', 'order_id', 'customer_message', 'customer_message2' ), sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}

		wp_safe_redirect( esc_url_raw( add_query_arg( 'message', $message_nonce, $redirect_url ) ) );
		exit;
	}
}

new NPOM_Admin_Pre_Orders();
