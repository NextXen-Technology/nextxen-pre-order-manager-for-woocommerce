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
 * Customer Pre-Order Date Changed Email
 *
 * An email sent to the customer when a pre-order release date is changed
 *
 * @since 1.0
 */
class NPOM_Email_Pre_Order_Date_Changed extends WC_Email {


	/** @var string optional message to include in email */
	private $message;

	/** @var localized availability date for pre-order  */
	private $availability_date;


	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct() {

		global $npom_manager;

		$this->id          = 'npom_pre_order_date_changed';
		$this->title       = __( 'Pre-order release date changed', 'nextxen-pre-order-manager' );
		$this->description = __( 'This is an order notification sent to the customer after a pre-order release date is changed.', 'nextxen-pre-order-manager' );

		$this->heading = __( 'Pre-order release date changed', 'nextxen-pre-order-manager' );
		$this->subject = __( 'The release date for your {site_title} pre-order from {order_date} has been changed', 'nextxen-pre-order-manager' );

		$this->template_base  = $npom_manager->get_plugin_path() . '/templates/';
		$this->template_html  = 'emails/customer-pre-order-date-changed.php';
		$this->template_plain = 'emails/plain/customer-pre-order-date-changed.php';

		$this->customer_email = true;

		// Triggers for this email.
		add_action( 'npom_pre_order_date_changed_notification', array( $this, 'trigger' ), 10 );

		// Call parent constructor.
		parent::__construct();
	}


	/**
	 * Dispatch the email
	 *
	 * @since 2.0.7 Update logic to handle function first argument as array.
	 *              First argument is same as for npom_pre_order_date_changed action hook.
	 * @since 1.0
	 */
	public function trigger( array $args ) {
		if (
			! array_key_exists( 'order', $args )
			|| ! array_key_exists( 'message', $args )
			|| ! $this->is_enabled()
		) {
			return;
		}

		$this->object            = $args['order'];
		$this->recipient         = $this->object->get_billing_email();
		$this->message           = $args['message'];
		$this->availability_date = NPOM_Product::get_localized_availability_date( NPOM_Order::get_pre_order_product( $this->object ) );

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

		if ( $this->get_recipient() ) {
			$this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);
		}
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
				'availability_date'  => $this->availability_date,
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
				'availability_date'  => $this->availability_date,
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
