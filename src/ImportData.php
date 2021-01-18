<?php
/**
 * Import data
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2021 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Importer;

/**
 *  Import data.
 *
 * @author  Reüel van der Steege
 * @since   1.0.0
 * @version 1.0.0
 */
class ImportData implements \IteratorAggregate {
	/**
	 * Fields.
	 *
	 * @var array
	 */
	private $fields;

	/**
	 * Data.
	 *
	 * @var ImportItem[]
	 */
	private $items;

	/**
	 * Construct import data.
	 *
	 * @param array $data Data.
	 */
	public function __construct( $data = array() ) {
		$this->fields = array_shift( $data );

		$this->items = \array_map(
			function( $item ) {
				if ( \is_array( $item ) ) {
					$item = \array_combine( $this->get_fields(), $item );
				}

				return new ImportItem( $item );
			},
			$data
		);
	}

	/**
	 * Get iterator.
	 *
	 * @return \ArrayIterator<int, ImportItem>
	 */
	public function getIterator() {
		return new \ArrayIterator( $this->items );
	}

	/**
	 * Add item.
	 *
	 * @param ImportItem $item The item to add.
	 * @return void
	 */
	public function add_item( ImportItem $item ) {
		$this->items[] = $item;
	}

	/**
	 * Get fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Get items.
	 *
	 * @return array
	 */
	public function get_items() {
		return $this->items;
	}

	/**
	 * Process.
	 *
	 * @return array
	 */
	public function process() {
		\do_action( 'pronamic_pay_import_start', $this->items );

		$i = 1;

		foreach ( $this->items as $item ) {
			\printf( '<strong>' . \esc_html__( 'Processing item #%d...', 'pronamic-pay-importer' ) . '</strong>' . \PHP_EOL, $i );

			$item->process();

			echo \PHP_EOL;

			$i++;
		}
	}
}
