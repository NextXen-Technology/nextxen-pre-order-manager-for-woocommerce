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
 * Customer Pre-Order Available Email
 *
 * An email sent to the customer when a pre-order is available.
 *
 * @since 1.0
 */
class NPOM_Email_Pre_Order_Available extends WC_Email {


	/** @var string optional message to include in email */
	private $message;


	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct() {

		global $npom_manager;

		$this->id          = 'npom_pre_order_available';
		$this->title       = __( 'Pre-order available', 'nextxen-pre-order-manager' );
		$this->description = __( 'This is an order notification sent to the customer once a pre-order is complete.', 'nextxen-pre-order-manager' );

		$this->heading = __( 'Pre-order available', 'nextxen-pre-order-manager' );
		$this->subject = __( 'Your {site_title} pre-order from {order_date} is now available', 'nextxen-pre-order-manager' );

		$this->template_base  = $npom_manager->get_plugin_path() . '/templates/';
		$this->template_html  = 'emails/customer-pre-order-available.php';
		$this->template_plain = 'emails/plain/customer-pre-order-available.php';

		$this->customer_email = true;

		// Triggers for this email.
		add_action( 'wc_pre_order_status_completed_notification', array( $this, 'trigger' ), 10, 2 );

		// Call parent constructor.
		parent::__construct();
	}


	/**
	 * Dispatch the email
	 *
	 * @since 1.0
	 */
	public function trigger( $order_id, $message = '' ) {
		if ( $order_id ) {
			$this->object    = new WC_Order( $order_id );
			$this->recipient = $this->object->get_billing_email();
			$this->message   = $message;

			$this->placeholders = array_merge(
				array(
					'{order_date}'   => date_i18n(
						wc_date_format(),
						strtotime(
							(
							$this->object->get_date_created() ?
							gmdate( 'Y-m-d H:i:s', $this->object->get_date_created()->getOffsetTimestamp() )
							: ''
							)
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
	 * @since 1.0
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
				'sent_to_admin'      => false,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}


	/**
	 * Gets the email plain content
	 *
	 * @since 1.0
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
				'sent_to_admin'      => false,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}
}
