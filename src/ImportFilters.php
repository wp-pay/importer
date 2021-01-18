<?php
/**
 * Import filters
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2021 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Importer;

use MeprSubscription;
use Pronamic\WordPress\Pay\Subscriptions\Subscription;

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
			'memberpress_subscription_id',
			'subscription_id',
		);

		// Add filters.
		foreach ( $fields as $field ) {
			\add_filter( self::FILTER_PREFIX . $field, array( $this, $field ), 10, 2 );
		}
	}

	/**
	 * Subscription ID value.
	 *
	 * @param array  $data            Item data.
	 * @param string $subscription_id Subscription ID.
	 *
	 * @return int
	 */
	public function subscription_id( $data, $subscription_id ) {
		if ( empty( $subscription_id ) ) {
			$subscription = new Subscription();

			$subscription->save();
		}

		return $data;
	}

	/**
	 * MemberPress Subscription ID.
	 *
	 * @param array  $data            Item data.
	 * @param string $subscription_id Subscription ID.
	 *
	 * @return int
	 */
	public function memberpress_subscription_id( $data, $subscription_id ) {
		if ( ! \class_exists( 'MeprSubscription' ) ) {
			\printf( '-' . __( 'Could not filter `memberpress_subscription_id` because MemberPress seems not available. ', 'pronamic-pay-importer' ) . \PHP_EOL );

			return $data;
		}

		$mp_subscription = new MeprSubscription( $subscription_id );

		$format = __( 'Update item `user_id` from `%1$s` to `%2$s` (MemberPress subscription #%3$s)', 'pronamic-pay-importer' );

		if ( empty( $data['user_id'] ) ) {
			$format = __( 'Add item `user_id` with value `%2$s` (from MemberPress subscription #%3$s)', 'pronamic-pay-importer' );
		}

		printf(
			'- ' . $format . \PHP_EOL,
			\esc_html( $data['user_id'] ),
			\esc_html( $mp_subscription->user_id ),
			\esc_html( $subscription_id ),
		);

		$data['user_id'] = $mp_subscription->user_id;

		return $data;
	}
}
