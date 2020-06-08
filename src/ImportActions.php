<?php
/**
 * Import actions
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2020 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Importer {

	use MeprProduct;
	use MeprSubscription;
	use Pronamic\WordPress\DateTime\DateTime;
	use Pronamic\WordPress\Money\TaxedMoney;
	use Pronamic\WordPress\Pay\Address;
	use Pronamic\WordPress\Pay\ContactName;
	use Pronamic\WordPress\Pay\Core\Util;
	use Pronamic\WordPress\Pay\Customer;
	use Pronamic\WordPress\Pay\Extensions\MemberPress\MemberPress;
	use Pronamic\WordPress\Pay\Extensions\MemberPress\SubscriptionStatuses;
	use Pronamic\WordPress\Pay\Gateways\Mollie\CLI;
	use Pronamic\WordPress\Pay\Subscriptions\Subscription;
	use ReflectionClass;
	use WP_User;

	/**
	 * Import actions.
	 *
	 * @author  Reüel van der Steege
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	class ImportActions {
		/**
		 * Action prefix.
		 */
		const ACTION_PREFIX = 'pronamic_pay_import_';

		/**
		 * Import actions constructor.
		 */
		public function __construct() {
			$fields = array(
				'memberpress_subscription_id',
			);

			// Add actions.
			foreach ( $fields as $field ) {
				\add_action( self::ACTION_PREFIX . $field, array( $this, $field ), 10, 2 );
			}

			\add_action( self::ACTION_PREFIX . 'start', array( $this, 'import_start' ), 10, 1 );
		}

		public function import_start( $items ) {
			// Sync Mollie customers <> users.
			$mollie_cli = new CLI();

			echo '- ' . __( 'Synchroinze Mollie customers', 'pronamic-pay-importer' ) . \PHP_EOL;

			try {
				$mollie_cli->wp_cli_customers_synchronize( array(), array() );
			} catch ( \Exception $e ) {
				echo \esc_html( $e->getMessage() );
			}

			echo \PHP_EOL;

			echo '- ' . __( 'Connect Mollie customers to WordPress users', 'pronamic-pay-importer' ) . \PHP_EOL;

			try {
				$mollie_cli->wp_cli_customers_connect_wp_users( array(), array() );
			} catch ( \Exception $e ) {
				echo \esc_html( $e->getMessage() );
			}

			echo \PHP_EOL;
		}

		/**
		 * MemberPress subscription.
		 *
		 * @param string $subscription_id Subscription ID.
		 * @param array  $data            Item data.
		 *
		 * @return void
		 */
		public function memberpress_subscription_id( $subscription_id, $data ) {
			if ( empty( $subscription_id ) ) {
				return;
			}

			if ( ! \class_exists( 'MeprSubscription' ) ) {
				\printf( '-' . __( 'Could not execute action for `memberpress_subscription_id` because MemberPress seems not available. ', 'pronamic-pay-importer' ) . \PHP_EOL );

				return $data;
			}

			$mp_subscription = new MeprSubscription( $subscription_id );

			if ( ! MeprSubscription::exists( $subscription_id ) ) {
				\printf(
					__( 'MemberPress subscription `%1$s` does not exist.', 'pronamic-pay-importer' ) . \PHP_EOL,
					$subscription_id
				);

				\esc_html_e( 'Skipping...', 'pronamic-pay-importer' );

				return;
			}

			$mp_product      = new MeprProduct( $mp_subscription->product_id );

			if ( $mp_product->is_one_time_payment() ) {
				\esc_html_e( 'MemberPress subscription product is a one-time payment.', 'pronamic-pay-importer' );
				\esc_html_e( 'Skipping...', 'pronamic-pay-importer' );

				return;
			}

			$subscription = \get_pronamic_subscription( $data['subscription_id'] );

			if ( null === $subscription ) {
				$subscription = \get_pronamic_subscription_by_meta( '_pronamic_subscription_source_id', $subscription_id );
			}

			if ( null === $subscription ) {
				$subscription = new Subscription();

				$log = '+ ' . __( 'Create Pronamic Pay subscription #%1$s', 'pronamic-pay-importer' ) . \PHP_EOL;
			} else {
				$log = '- ' . __( 'Update Pronamic Pay subscription #%1$s', 'pronamic-pay-importer' ) . \PHP_EOL;
			}

			// Subscription info.
			$subscription->description = $mp_product->post_title;

			$subscription->set_status( SubscriptionStatuses::transform( $mp_subscription->status ) );
			$subscription->set_source( 'memberpress' );
			$subscription->set_source_id( $subscription_id );

			// Payment method.
			$payment_method = null;

			$mp_options = \MeprOptions::fetch();

			$mp_gateway = $mp_options->payment_method( $mp_subscription->gateway );

			$gateway_reflection = new ReflectionClass( $mp_gateway );

			try {
				$payment_method = $gateway_reflection->getProperty( 'payment_method' );
				$payment_method->setAccessible( true );
				$payment_method = $payment_method->getValue( $mp_gateway );
			} catch ( \Exception $e ) {
				\esc_html_e( 'Could not retrieve payment method from MemberPress subscription.', 'pronamic-pay-importer' );
				\esc_html_e( 'Skipping...', 'pronamic-pay-importer' );

				return;
			}

			$subscription->payment_method = $payment_method;
			$subscription->config_id      = $mp_gateway->settings->config_id;

			// Customer.
			$user = new WP_User( $data['user_id'] );

			$customer = new Customer();
			$customer->set_user_id( $data['user_id'] );
			$customer->set_email( $user->user_email );

			$contact_name = new ContactName();
			$contact_name->set_first_name( $user->first_name );
			$contact_name->set_last_name( $user->last_name );

			$customer->set_name( $contact_name );

			$subscription->set_customer( $customer );

			// Billing address.
			if ( ! empty( $user->user_email ) ) {
				$billing_address = new Address();
				$billing_address->set_email( $customer->get_email() );

				$subscription->set_billing_address( $billing_address );
			}

			// Amount.
			$subscription->set_total_amount(
				new TaxedMoney(
					$mp_subscription->total,
					MemberPress::get_currency(),
					$mp_subscription->tax_amount,
					$mp_subscription->tax_rate,
				)
			);

			// Interval.
			$subscription->interval        = $mp_subscription->period;
			$subscription->interval_period = Util::to_period( $mp_subscription->period_type );

			// Frequency.
			if ( ! empty( $mp_subscription->limit_cycles ) && intval( $mp_subscription->limit_cycles_num ) > 0 ) {
				$subscription->frequency = $mp_subscription->limit_cycles_num;

				$period = new \DatePeriod( $subscription->start_date, $subscription->get_date_interval(), $subscription->frequency );

				$dates = iterator_to_array( $period );

				$subscription->end_date = end( $dates );
			}

			// Start, end and expiry dates.
			$subscription->start_date = new DateTime( $mp_subscription->created_at );

			$subscription->expiry_date = clone $subscription->start_date;

			// Add one period to expiry date.
			$subscription->expiry_date->add( $subscription->get_date_interval() );

			$subscription->save();

			// Set next payment (delivery) date.
			$subscription = \get_pronamic_subscription( $subscription->get_id() );

			$subscription->next_payment_date = $subscription->expiry_date;

			$subscription->next_payment_delivery_date = $subscription->next_payment_date;

			$subscription->save();

			printf( $log, \esc_html( $subscription->get_id() ) );

			$subscription = \get_pronamic_subscription( $subscription->get_id() );

			echo wp_json_encode( $subscription->get_json(), \JSON_PRETTY_PRINT ) . \PHP_EOL;

			// Add Mollie customer ID.
			if ( isset( $data['mollie_customer_id'] ) && ! empty( $data['mollie_customer_id'] ) ) {
				$mollie_customer_id = $data['mollie_customer_id'];

				$subscription->set_meta( 'mollie_customer_id', $mollie_customer_id );

				\printf(
					'- ' . __( 'Add Mollie Customer ID `%1$s` to subscription `%2$s`', 'pronamic-pay-importer' ) . \PHP_EOL,
					\esc_html( $mollie_customer_id ),
					\esc_html( $subscription->get_id() )
				);
			}

			$data['subscription_id'] = $subscription->get_id();
		}
	}
}

namespace {
	class WP_CLI {
		public static function add_command() {
			// Ignore.
		}

		public static function log( $log ) {
			echo $log . \PHP_EOL;
		}

		public static function error( $error ) {
			self::log( $error );
		}
	}
}

namespace WP_CLI\Utils {
	function format_items( $format = null, $items = null, $columns = null ) {
	}
}
