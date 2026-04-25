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
 * New Pre-Order Email
 *
 * An email sent to the admin when a new pre-order is received
 *
 * @since 1.0
 */
class NPOM_Email_New_Pre_Order extends WC_Email {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct() {

		global $npom_manager;

		$this->id          = 'npom_new_pre_order';
		$this->title       = __( 'New pre-order', 'nextxen-pre-order-manager' );
		$this->description = __( 'New pre-order emails are sent when a pre-order is received.', 'nextxen-pre-order-manager' );

		$this->heading = __( 'New pre-order: #{order_number}', 'nextxen-pre-order-manager' );
		$this->subject = __( '[{site_title}] New customer pre-order ({order_number}) - {order_date}', 'nextxen-pre-order-manager' );

		$this->template_base  = $npom_manager->get_plugin_path() . '/templates/';
		$this->template_html  = 'emails/admin-new-pre-order.php';
		$this->template_plain = 'emails/plain/admin-new-pre-order.php';

		// Triggers for this email.
		add_action( 'wc_pre_order_status_new_to_active_notification', array( $this, 'trigger' ) );

		// Call parent constructor.
		parent::__construct();

		// Other settings.
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}


	/**
	 * Dispatch the email
	 *
	 * @since 1.0
	 */
	public function trigger( $order_id ) {
		if ( $order_id ) {
			$this->object = new WC_Order( $order_id );

			$this->placeholders = array_merge(
				array(
					'{order_date}'   => date_i18n(
						wc_date_format(),
						strtotime(
							(
							$this->object->get_date_created()
							? gmdate( 'Y-m-d H:i:s', $this->object->get_date_created()->getOffsetTimestamp() )
							: ''
							)
						)
					),
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
		global $npom_manager;
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'plain_text'    => false,
				'email'         => $this,
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
		global $npom_manager;
		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'plain_text'    => true,
				'sent_to_admin' => true,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}


	/**
	 * Initialise Settings Form Fields
	 *
	 * @since 1.0
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'nextxen-pre-order-manager' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'nextxen-pre-order-manager' ),
				'default' => 'yes',
			),
			'recipient'  => array(
				'title'       => __( 'Recipient(s)', 'nextxen-pre-order-manager' ),
				'type'        => 'text',
				/* translators: %s: admin email address */
				'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to <code>%s</code>.', 'nextxen-pre-order-manager' ), esc_attr( get_option( 'admin_email' ) ) ),
				'placeholder' => '',
				'default'     => '',
			),
			'subject'    => array(
				'title'       => __( 'Subject', 'nextxen-pre-order-manager' ),
				'type'        => 'text',
				/* translators: %s: email subject */
				'description' => sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'nextxen-pre-order-manager' ), $this->subject ),
				'placeholder' => '',
				'default'     => '',
			),
			'heading'    => array(
				'title'       => __( 'Email heading', 'nextxen-pre-order-manager' ),
				'type'        => 'text',
				/* translators: %s: email heading */
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'nextxen-pre-order-manager' ), $this->heading ),
				'placeholder' => '',
				'default'     => '',
			),
			'email_type' => array(
				'title'       => __( 'Email type', 'nextxen-pre-order-manager' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'nextxen-pre-order-manager' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => array(
					'plain'     => __( 'Plain text', 'nextxen-pre-order-manager' ),
					'html'      => __( 'HTML', 'nextxen-pre-order-manager' ),
					'multipart' => __( 'Multipart', 'nextxen-pre-order-manager' ),
				),
			),
		);
	}
}
