<?php
/**
 * Import actions
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2021 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Importer {

	use DateTimeImmutable;
	use MeprProduct;
	use MeprSubscription;
	use Pronamic\WordPress\DateTime\DateTime;
	use Pronamic\WordPress\Money\TaxedMoney;
	use Pronamic\WordPress\Pay\Address;
	use Pronamic\WordPress\Pay\ContactName;
	use Pronamic\WordPress\Pay\Core\PaymentMethods;
	use Pronamic\WordPress\Pay\Core\Util;
	use Pronamic\WordPress\Pay\Customer;
	use Pronamic\WordPress\Pay\Extensions\MemberPress\MemberPress;
	use Pronamic\WordPress\Pay\Extensions\MemberPress\SubscriptionStatuses;
	use Pronamic\WordPress\Pay\Gateways\Mollie\CLI;
	use Pronamic\WordPress\Pay\Subscriptions\Subscription;
	use Pronamic\WordPress\Pay\Subscriptions\SubscriptionHelper;
	use Pronamic\WordPress\Pay\Subscriptions\SubscriptionInterval;
	use Pronamic\WordPress\Pay\Subscriptions\SubscriptionPhase;
	use Pronamic\WordPress\Pay\Subscriptions\SubscriptionStatus;
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
				'subscription_id',
			);

			// Add actions.
			foreach ( $fields as $field ) {
				\add_action( self::ACTION_PREFIX . $field, array( $this, $field ), 10, 2 );
			}

			\add_action( self::ACTION_PREFIX . 'start', array( $this, 'import_start' ), 10, 1 );
		}

		/**
		 * Import start.
		 *
		 * @param array $items Items to import.
		 * @retun void
		 */
		public function import_start( $items ) {
			// Sync Mollie customers <> users.
			$mollie_cli = new CLI();

			\printf(
				'- %s' . \PHP_EOL,
				\esc_html__( 'Synchronize Mollie customers', 'pronamic-pay-importer' )
			);

			try {
				$mollie_cli->wp_cli_customers_synchronize( array(), array() );
			} catch ( \Exception $e ) {
				echo \esc_html( $e->getMessage() );
			}

			echo \PHP_EOL;

			\printf(
				'- %s' . \PHP_EOL,
				\esc_html__( 'Connect Mollie customers to WordPress users', 'pronamic-pay-importer' )
			);

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
		 * @return void
		 */
		public function memberpress_subscription_id( $subscription_id, $data ) {
			if ( empty( $subscription_id ) ) {
				return;
			}

			if ( ! \class_exists( 'MeprSubscription' ) ) {
				\printf(
					'- %s' . \PHP_EOL,
					\esc_html__( 'Could not execute action for `memberpress_subscription_id` because MemberPress seems not available. ', 'pronamic-pay-importer' )
				);

				return;
			}

			$mp_subscription = new MeprSubscription( $subscription_id );

			if ( ! MeprSubscription::exists( $subscription_id ) ) {
				\printf(
					/* translators: 1: MemberPress subscription ID */
					\esc_html__( 'MemberPress subscription `%1$s` does not exist.', 'pronamic-pay-importer' ) . \PHP_EOL,
					\esc_html( $subscription_id )
				);

				\esc_html_e( 'Skipping...', 'pronamic-pay-importer' );

				return;
			}

			$mp_product = new MeprProduct( $mp_subscription->product_id );

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

				/* translators: 1: subscription ID */
				$log = '+ ' . __( 'Create Pronamic Pay subscription #%1$s', 'pronamic-pay-importer' );
			} else {
				/* translators: 1: subscription ID */
				$log = '- ' . __( 'Update Pronamic Pay subscription #%1$s', 'pronamic-pay-importer' );
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
					$mp_subscription->tax_rate
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

			printf(
				'%s' . \PHP_EOL,
				esc_html( sprintf( $log, $subscription->get_id() ) )
			);

			$subscription = \get_pronamic_subscription( $subscription->get_id() );

			echo wp_json_encode( $subscription->get_json(), \JSON_PRETTY_PRINT ) . \PHP_EOL;

			// Add Mollie customer ID.
			if ( isset( $data['mollie_customer_id'] ) && ! empty( $data['mollie_customer_id'] ) ) {
				$mollie_customer_id = $data['mollie_customer_id'];

				$subscription->set_meta( 'mollie_customer_id', $mollie_customer_id );

				\printf(
					/* translators: 1: Mollie customer ID, 2: subscription ID */
					'- ' . \esc_html__( 'Add Mollie Customer ID `%1$s` to subscription `%2$s`', 'pronamic-pay-importer' ) . \PHP_EOL,
					\esc_html( $mollie_customer_id ),
					\esc_html( $subscription->get_id() )
				);
			}

			$data['subscription_id'] = $subscription->get_id();
		}

		/**
		 * Subscription.
		 *
		 * @param string $subscription_id Subscription ID.
		 * @param array  $data            Item data.
		 * @return void
		 */
		public function subscription_id( $subscription_id, $data ) {
			$subscription = null;

			if ( ! empty( $subscription_id ) ) {
				$subscription = \get_pronamic_subscription( $subscription_id );
			}

			$source = \array_key_exists( 'source', $data ) ? $data['source'] : 'import';
			$source_id = null;

			if ( null === $subscription && \array_key_exists( 'source_id', $data ) ) {
				$source_id = $data['source_id'];

				$subscriptions = \get_pronamic_subscriptions_by_source( $source, $source_id );

				if ( ! empty( $subscriptions ) ) {
					$subscription = \array_shift( $subscriptions );
				}
			}

			if ( null === $subscription ) {
				$subscription = new Subscription();

				/* translators: 1: subscription ID */
				$log = '+ ' . __( 'Create Pronamic Pay subscription #%1$s', 'pronamic-pay-importer' );
			} else {
				/* translators: 1: subscription ID */
				$log = '- ' . __( 'Update Pronamic Pay subscription #%1$s', 'pronamic-pay-importer' );
			}

			// Subscription info.
			$description = __( 'Subscription', 'pronamic_ideal' );

			if ( \array_key_exists( 'description', $data ) ) {
				$description = $data['description'];
			}

			$subscription->description = $description;

			$subscription->set_status( SubscriptionStatus::ACTIVE );
			$subscription->set_source( $source );
			$subscription->set_source_id( $source_id );

			// Payment method.
			$payment_method = \array_key_exists( 'payment_method', $data ) ? $data['payment_method'] : PaymentMethods::DIRECT_DEBIT;

			$subscription->payment_method = $payment_method;

			// Config  ID.
			$config_id = \array_key_exists( 'config_id', $data ) ? $data['config_id'] : null;

			if ( empty( $config_id ) || ! \is_numeric( $config_id ) ) {
				$config_id = \get_option( 'pronamic_pay_config_id' );

				$data['config_id'] = $config_id;
			}

			$subscription->config_id = $config_id;

			// Customer.
			$customer = new Customer();
			$customer->set_email( \array_key_exists( 'email', $data ) ? $data['email'] : null );
			$customer->set_user_id( 0 );

			$contact_name = new ContactName();

			if ( \array_key_exists( 'user_id', $data ) ) {
				$user = new WP_User( $data['user_id'] );

				$customer->set_user_id( $data['user_id'] );
				$customer->set_email( $user->user_email );

				$contact_name->set_first_name( $user->first_name );
				$contact_name->set_last_name( $user->last_name );
			}

			$customer->set_name( $contact_name );

			$subscription->set_customer( $customer );

			// Billing address.
			$customer_email = $customer->get_email();

			if ( ! empty( $customer_email ) ) {
				$billing_address = new Address();
				$billing_address->set_email( $customer_email );

				$subscription->set_billing_address( $billing_address );
			}

			// Amount.
			$amount = new TaxedMoney(
				$data['amount'],
				$data['currency']
			);

			// Phase.
			$start_date = new \DateTimeImmutable();

			$subscription_start_date = $subscription->get_start_date();

			if ( null !== $subscription_start_date ) {
				$start_date = DateTimeImmutable::createFromMutable( $subscription_start_date );
			}

			$new_phase = new SubscriptionPhase(
				$subscription,
				$start_date,
				new SubscriptionInterval( $data['interval'] ),
				$amount
			);

			if ( \array_key_exists( 'frequency', $data ) && \is_numeric( $data['frequency'] ) ) {
				$new_phase->set_total_periods( $data['frequency'] );
			}

			$phases = $subscription->get_phases();

			$first_phase = \array_shift( $phases );

			if ( null !== $first_phase ) {
				$first_phase->set_sequence_number( null );
			}

			if ( wp_json_encode( $first_phase ) !== \wp_json_encode( $new_phase ) ) {
				foreach ( $subscription->get_phases() as $phase ) {
					$phase->set_canceled_at( new DateTimeImmutable() );
				}

				$subscription->add_phase( $new_phase );
			}

			// Complement subscription.
			SubscriptionHelper::complement_subscription( $subscription );
			SubscriptionHelper::complement_subscription_dates( $subscription );

			$subscription->expiry_date = $start_date;

			// Save.
			$subscription->save();

			printf(
				'%s' . \PHP_EOL,
				esc_html( sprintf( $log, $subscription->get_id() ) )
			);

			$subscription = \get_pronamic_subscription( $subscription->get_id() );

			$data['subscription_id'] = $subscription->get_id();

			echo wp_json_encode( $subscription->get_json(), \JSON_PRETTY_PRINT ) . \PHP_EOL;

			// Add Mollie customer ID.
			if ( isset( $data['mollie_customer_id'] ) && ! empty( $data['mollie_customer_id'] ) ) {
				$mollie_customer_id = $data['mollie_customer_id'];

				$subscription->set_meta( 'mollie_customer_id', $mollie_customer_id );

				\printf(
					/* translators: 1: Mollie customer ID, 2: subscription ID */
					'- ' . \esc_html__( 'Add Mollie Customer ID `%1$s` to subscription `%2$s`', 'pronamic-pay-importer' ) . \PHP_EOL,
					\esc_html( $mollie_customer_id ),
					\esc_html( $subscription->get_id() )
				);
			}

			// Add Mollie mandate ID.
			if ( isset( $data['mollie_mandate_id'] ) && ! empty( $data['mollie_mandate_id'] ) ) {
				$mollie_mandate_id = $data['mollie_mandate_id'];

				$subscription->set_meta( 'mollie_mandate_id', $mollie_mandate_id );

				\printf(
					/* translators: 1: Mollie customer ID, 2: subscription ID */
					'- ' . \esc_html__( 'Add Mollie mandate ID `%1$s` to subscription `%2$s`', 'pronamic-pay-importer' ) . \PHP_EOL,
					\esc_html( $mollie_mandate_id ),
					\esc_html( $subscription->get_id() )
				);
			}
		}
	}
}

namespace {
	class WP_CLI {
		public static function add_command() {
			// Ignore.
		}

		public static function log( $log ) {
			echo esc_html( $log ) . \PHP_EOL;
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
