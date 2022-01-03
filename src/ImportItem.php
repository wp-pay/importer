<?php
/**
 * Import item
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2022 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Importer;

/**
 * Import item.
 *
 * @author  Reüel van der Steege
 * @since   1.0.0
 * @version 1.0.0
 */
class ImportItem {
	/**
	 * Item data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Construct import item.
	 *
	 * @param array $data Import item data.
	 */
	public function __construct( $data ) {
		$this->data = $data;
	}

	/**
	 * Get data.
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Process.
	 *
	 * @return void
	 */
	public function process() {
		$data = $this->data;

		// Filter data.
		foreach ( $data as $key => $value ) {
			$data = \apply_filters( 'pronamic_pay_import_data_' . $key, $data, $value );
		}

		// Filter import item.
		$data = \apply_filters( 'pronamic_pay_import_item', $data );

		echo wp_json_encode( $data, \JSON_PRETTY_PRINT ) . \PHP_EOL;

		// Do actions.
		foreach ( $data as $key => $value ) {
			\do_action( 'pronamic_pay_import_' . $key, $value, $data );
		}
	}
}
