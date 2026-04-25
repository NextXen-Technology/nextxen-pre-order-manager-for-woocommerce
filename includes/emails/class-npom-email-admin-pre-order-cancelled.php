<?php
/**
 * WooCommerce Pre-Order Manager
 *
 * @package     NPOM/Email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Admin Pre-Order Cancelled Email
 *
 * An email sent to the admin when a pre-order is cancelled.
 */
class NPOM_Email_Admin_Pre_Order_Cancelled extends WC_Email {

	/** @var string optional message to include in email */
	private $message;


	/**
	 * Constructor
	 */
	public function __construct() {

		global $npom_manager;

		$this->id          = 'npom_admin_pre_order_cancelled';
		$this->title       = __( 'Pre-order Cancelled (Admin)', 'nextxen-pre-order-manager' );
		$this->description = __( 'This is an order notification sent to the admin after a pre-order is cancelled.', 'nextxen-pre-order-manager' );

		$this->heading = __( 'Pre-order Cancelled', 'nextxen-pre-order-manager' );
		$this->subject = __( '[{site_title}] pre-order from {order_date} has been cancelled', 'nextxen-pre-order-manager' );

		$this->template_base  = $npom_manager->get_plugin_path() . '/templates/';
		$this->template_html  = 'emails/admin-pre-order-cancelled.php';
		$this->template_plain = 'emails/plain/admin-pre-order-cancelled.php';

		$this->customer_email = false;

		// Triggers for this email.
		add_action( 'wc_pre_order_status_active_to_cancelled_notification', array( $this, 'trigger' ), 10, 2 );

		// Call parent constructor.
		parent::__construct();

		// Other settings.
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}


	/**
	 * Dispatch the email
	 */
	public function trigger( $order_id, $message ) {
		if ( $order_id ) {
			$this->object    = new WC_Order( $order_id );
			$this->recipient = get_option( 'admin_email' );
			$this->message   = $message;

			$this->placeholders = array_merge(
				array(
					'{order_date}'   => date_i18n(
						wc_date_format(),
						strtotime(
							$this->object->get_date_created()
								? gmdate( 'Y-m-d H:i:s', $this->object->get_date_created()->getOffsetTimestamp() )
								: ''
						)
					),
					'{release_date}' => NPOM_Product::get_localized_availability_date( NPOM_Order::get_pre_order_product( $this->object ) ),
					'{order_number}' => $this->object->get_order_number(),
				),
				$this->placeholders
			);
		}

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}


	/**
	 * Gets the email HTML content
	 *
	 * @return string the email HTML content
	 */
	public function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'message'            => $this->message,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}


	/**
	 * Gets the email plain content
	 *
	 * @return string the email plain content
	 */
	public function get_content_plain() {
		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'message'            => $this->message,
				'plain_text'         => true,
				'email'              => $this,
				'sent_to_admin'      => true,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}
}
