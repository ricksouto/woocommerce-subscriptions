<?php
/**
 * Subscriptions Email Preview Class
 */
class WC_Subscriptions_Email_Preview {

	/**
	 * The email being previewed
	 *
	 * @var string
	 */
	private $email_type;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_prepare_email_for_preview', [ $this, 'prepare_email_for_preview' ] );
	}

	/**
	 * Prepare subscription email dummy data for preview.
	 *
	 * @param WC_Email $email The email object.
	 *
	 * @return WC_Email
	 */
	public function prepare_email_for_preview( $email ) {
		$this->email_type = get_class( $email );

		if ( ! $this->is_subscription_email() ) {
			return $email;
		}

		$this->set_up_filters();

		switch ( $this->email_type ) {
			case 'WCS_Email_New_Switch_Order':
			case 'WCS_Email_Completed_Switch_Order':
				$email->subscriptions = [ $this->get_dummy_subscription() ];
				break;
			case 'WCS_Email_Cancelled_Subscription':
			case 'WCS_Email_Expired_Subscription':
			case 'WCS_Email_On_Hold_Subscription':
			case 'WCS_Email_Customer_Notification_Auto_Trial_Expiration':
			case 'WCS_Email_Customer_Notification_Manual_Trial_Expiration':
			case 'WCS_Email_Customer_Notification_Subscription_Expiration':
			case 'WCS_Email_Customer_Notification_Manual_Renewal':
			case 'WCS_Email_Customer_Notification_Auto_Renewal':
				$email->set_object( $this->get_dummy_subscription() );
				break;
		}

		add_filter( 'woocommerce_mail_content', [ $this, 'clean_up_filters' ] );

		return $email;
	}

	/**
	 * Get a dummy subscription for use in preview emails.
	 *
	 * @return WC_Subscription
	 */
	private function get_dummy_subscription() {
		$subscription = new WC_Subscription();
		$product      = $this->get_dummy_product();

		$subscription->add_product( $product, 2 );
		$subscription->set_id( 12346 );
		$subscription->set_customer_id( 1 );
		$subscription->set_date_created( gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) ) );
		$subscription->set_currency( 'USD' );
		$subscription->set_total( 100 );
		$subscription->set_billing_period( 'month' );
		$subscription->set_billing_interval( 1 );
		$subscription->set_start_date( gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) ) );
		$subscription->set_trial_end_date( gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ) );
		$subscription->set_next_payment_date( gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ) );
		$subscription->set_end_date( gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ) );

		$address = self::get_dummy_address();

		$subscription->set_billing_address( $address );
		$subscription->set_shipping_address( $address );

		/**
		 * Filter the dummy subscription object used in email previews.
		 *
		 * @param WC_Subscription $subscription The dummy subscription object.
		 * @param string          $email_type   The email type being previewed.
		 */
		return apply_filters( 'woocommerce_subscriptions_email_preview_dummy_subscription', $subscription, $this->email_type );
	}

	/**
	 * Get a dummy product for use when previewing subscription emails.
	 *
	 * @return WC_Product
	 */
	private function get_dummy_product() {
		$product = new WC_Product();
		$product->set_name( 'Dummy Subscription' );
		$product->set_price( 25 );

		/**
		 * Filter the dummy subscription product object used in email previews.
		 *
		 * @param WC_Product $product The dummy product object.
		 * @param string     $email_type The email type being previewed.
		 */
		return apply_filters( 'woocommerce_subscriptions_email_preview_dummy_product', $product, $this->email_type );
	}

	/**
	 * Get a dummy address used when previewing subscription emails.
	 *
	 * @return array
	 */
	private function get_dummy_address() {
		$address = [
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'company'    => 'Company',
			'email'      => 'john@company.com',
			'phone'      => '555-555-5555',
			'address_1'  => '123 Fake Street',
			'city'       => 'Faketown',
			'postcode'   => '12345',
			'country'    => 'US',
			'state'      => 'CA',
		];

		/**
		 * Filter the dummy address used in email previews.
		 *
		 * @param array  $address    The dummy address.
		 * @param string $email_type The email type being previewed.
		 */
		return apply_filters( 'woocommerce_subscriptions_email_preview_dummy_address', $address, $this->email_type );
	}

	/**
	 * Check if the email being previewed is a subscription email.
	 *
	 * @return bool
	 */
	private function is_subscription_email() {
		return isset( WC_Subscriptions_Email::$email_classes[ $this->email_type ] ) || isset( WC_Subscriptions_Email_Notifications::$email_classes[ $this->email_type ] );
	}

	/**
	 * Set up filters for previewing emails.
	 */
	private function set_up_filters() {
		// Filter the last order date created for a subscription to be displayed in the Cancelled Subscription email.
		add_filter( 'woocommerce_subscription_get_last_order_date_created_date', [ $this, 'mock_last_order_date_created' ], 10, 2 );
		// For the purpose of previewing an email, force the subscription to be early renewable if the feature is enabled.
		add_filter( 'woocommerce_subscriptions_can_user_renew_early', [ $this, 'allow_early_renewals_during_preview' ], 10, 3 );
	}

	/**
	 * Clean up filters at the end of previewing emails.
	 *
	 * @param string $preview_content The email content.
	 *
	 * @return string
	 */
	public function clean_up_filters( $preview_content ) {
		remove_filter( 'woocommerce_subscription_get_last_order_date_created_date', [ $this, 'mock_last_order_date_created' ] );
		remove_filter( 'woocommerce_subscriptions_can_user_renew_early', [ $this, 'allow_early_renewals_during_preview' ] );

		return $preview_content;

	}

	/**
	 * Mock the last order date created for a subscription to the current date.
	 *
	 * @param string $date The date.
	 *
	 * @return string
	 */
	public function mock_last_order_date_created( $date, $subscription ) {
		if ( is_a( $subscription, 'WC_Subscription' ) && 12346 === $subscription->get_id() && 1 === $subscription->get_customer_id() ) {
			return gmdate( 'Y-m-d H:i:s' );
		}

		return $date;
	}

	/**
	 * Allow early renewals for previewing emails.
	 *
	 * @param bool            $can_renew_early Whether the subscription can be renewed early.
	 * @param WC_Subscription $subscription    The subscription.
	 * @param int             $user_id         The user ID.
	 *
	 * @return bool
	 */
	public function allow_early_renewals_during_preview( $can_renew_early, $subscription, $user_id ) {
		if ( 1 === $user_id ) {
			return true;
		}

		return $can_renew_early;
	}
}
