<?php
/**
 * Import filters
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2022 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Importer;

use MeprSubscription;
use Pronamic\WordPress\Money\Parser;
use Pronamic\WordPress\Money\TaxedMoney;
use Pronamic\WordPress\Pay\Banks\BankAccountDetails;
use Pronamic\WordPress\Pay\Gateways\Mollie\Client;
use Pronamic\WordPress\Pay\Gateways\Mollie\Customer;
use Pronamic\WordPress\Pay\Gateways\Mollie\CustomerDataStore;
use Pronamic\WordPress\Pay\Gateways\Mollie\Integration;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Import filters.
 *
 * @author  Reüel van der Steege
 * @since   1.0.0
 * @version 1.0.0
 */
class ImportFilters {
	/**
	 * Filter prefix.
	 */
	const FILTER_PREFIX = 'pronamic_pay_import_data_';

	/**
	 * Import filters constructor.
	 */
	public function __construct() {
		$fields = array(
			'amount',
			'config_id',
			'memberpress_subscription_id',
			'mollie_customer_id',
			'subscription_id',
		);

		// Add filters.
		foreach ( $fields as $field ) {
			\add_filter( self::FILTER_PREFIX . $field, array( $this, $field ), 10, 2 );
		}
	}

	/**
	 * Amount.
	 *
	 * @param array  $data   Item data.
	 * @param string $amount Amount.
	 * @return array
	 */
	public function amount( $data, $amount ) {
		$parser = new Parser();

		$amount = $parser->parse( $amount );

		if ( \array_key_exists( 'currency', $data ) ) {
			$money = new TaxedMoney( $amount->get_value(), $data['currency'] );
		} else {
			$money = new TaxedMoney( $amount->get_value() );
		}

		$data['amount']   = $money->get_value();
		$data['currency'] = $money->get_currency()->get_alphabetic_code();

		return $data;
	}

	/**
	 * Config ID.
	 *
	 * @param array  $data      Item data.
	 * @param string $config_id Config ID.
	 * @return array
	 */
	public function config_id( $data, $config_id ) {
		if ( empty( $config_id ) || ! \is_numeric( $config_id ) ) {
			$data['config_id'] = \get_option( 'pronamic_pay_config_id' );
		}

		return $data;
	}

	/**
	 * Subscription ID value.
	 *
	 * @param array  $data            Item data.
	 * @param string $subscription_id Subscription ID.
	 * @return array
	 */
	public function subscription_id( $data, $subscription_id ) {
		if ( empty( $subscription_id ) || ! \is_numeric( $subscription_id ) ) {
			$subscription = null;

			if ( \array_key_exists( 'source_id', $data ) ) {
				$source = \array_key_exists( 'source', $data ) ? $data['source'] : 'import';

				$subscriptions = \get_pronamic_subscriptions_by_source( $source, $data['source_id'] );

				$subscription = \array_shift( $subscriptions );

				if ( null !== $subscription ) {
					$data['subscription_id'] = $subscription->get_id();
				}
			}
		}

		return $data;
	}

	/**
	 * MemberPress Subscription ID.
	 *
	 * @param array  $data            Item data.
	 * @param string $subscription_id Subscription ID.
	 * @return int
	 */
	public function memberpress_subscription_id( $data, $subscription_id ) {
		if ( ! \class_exists( 'MeprSubscription' ) ) {
			\printf(
				'- %s ' . \PHP_EOL,
				esc_html__( 'Could not filter `memberpress_subscription_id` because MemberPress seems not available. ', 'pronamic-pay-importer' )
			);

			return $data;
		}

		$mp_subscription = new MeprSubscription( $subscription_id );

		/* translators: 1: import data user ID, 2: MemberPress subscription user ID, 3: MemberPress subscription ID */
		$format = __( 'Update item `user_id` from `%1$s` to `%2$s` (MemberPress subscription #%3$s)', 'pronamic-pay-importer' );

		if ( empty( $data['user_id'] ) ) {
			/* translators: 2: MemberPress subscription user ID, 3: MemberPress subscription ID */
			$format = __( 'Add item `user_id` with value `%2$s` (from MemberPress subscription #%3$s)', 'pronamic-pay-importer' );
		}

		printf(
			esc_html( '- ' . $format . \PHP_EOL ),
			\esc_html( $data['user_id'] ),
			\esc_html( $mp_subscription->user_id ),
			\esc_html( $subscription_id )
		);

		$data['user_id'] = $mp_subscription->user_id;

		return $data;
	}

	/**
	 * Mollie customer id.
	 *
	 * @param array  $data               Data.
	 * @param string $mollie_customer_id Mollie Customer ID.
	 * @return array
	 */
	public function mollie_customer_id( $data, $mollie_customer_id ) {
		if ( empty( $mollie_customer_id ) ) {
			// Check consumer name and IBAN.
			if ( ! \array_key_exists( 'consumer_name', $data ) || ! \array_key_exists( 'consumer_iban', $data ) ) {
				printf( '- No consumer name and IBAN provided for Mollie customer.' . \PHP_EOL );

				return $data;
			}

			$consumer_bank_details = new BankAccountDetails();

			$consumer_bank_details->set_name( $data['consumer_name'] );
			$consumer_bank_details->set_iban( $data['consumer_iban'] );

			// Config ID.
			$config_id = null;

			if ( \array_key_exists( 'config_id', $data ) ) {
				$config_id = $data['config_id'];
			}

			if ( empty( $config_id ) ) {
				$config_id = \get_option( 'pronamic_pay_config_id' );
			}

			$gateway = Plugin::get_gateway( $config_id );

			if ( null === $gateway ) {
				\printf( '- Invalid gateway provided.' . \PHP_EOL );
			}

			$integration = new Integration();

			$config = $integration->get_config( $config_id );

			$client = new Client( $config->api_key );

			// Create Mollie customer.
			$mollie_customer = new Customer();
			$mollie_customer->set_mode( \get_post_meta( $config_id, '_pronamic_gateway_mode', true ) === 'test' ? 'test' : 'live' );
			$mollie_customer->set_name( $consumer_bank_details->get_name() );
			$mollie_customer->set_email( \array_key_exists( 'email', $data ) ? $data['email'] : null );

			$mollie_customer = $client->create_customer( $mollie_customer );

			$customer_data_store = new CustomerDataStore();

			$customer_data_store->insert_customer( $mollie_customer );

			$customer_id = $mollie_customer->get_id();

			$data['mollie_customer_id'] = $customer_id;

			\printf( '- Create customer `%s`' . \PHP_EOL, \esc_html( $customer_id ) );

			// Create mandate.
			$mandate = $client->create_mandate( $customer_id, $consumer_bank_details );

			if ( ! \property_exists( $mandate, 'id' ) ) {
				printf( '- Missing mandate ID in Mollie response.' . \PHP_EOL );

				return $data;
			}

			$mandate_id = $mandate->id;

			$data['mollie_mandate_id'] = $mandate_id;

			\printf( '- Create mandate `%s`' . \PHP_EOL, \esc_html( $mandate_id ) );
		}

		return $data;
	}
}
