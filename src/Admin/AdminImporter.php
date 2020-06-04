<?php
/**
 * Admin Importer
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2020 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Importer\Admin;


use Pronamic\WordPress\Pay\Importer\Addon;
use Pronamic\WordPress\Pay\Importer\ImportActions;
use Pronamic\WordPress\Pay\Importer\ImportData;
use Pronamic\WordPress\Pay\Importer\ImportFilters;

/**
 * Admin importer.
 *
 * @author  Reüel van der Steege
 * @since   1.0.0
 * @version 1.0.0
 */
class AdminImporter {
	/**
	 * Addon.
	 *
	 * @var Addon
	 */
	private $addon;

	/**
	 * Importer constructor.
	 *
	 * @param Addon $addon Addon.
	 */
	public function __construct( Addon $addon ) {
		$this->addon = $addon;

		new ImportFilters();
		new ImportActions();
	}

	/**
	 * Page importer.
	 */
	public function page_importer() {
		if (
			\wp_verify_nonce( \filter_input( \INPUT_POST, 'pronamic-pay-importer-nonce', \FILTER_SANITIZE_STRING ), 'pronamic-pay-importer-import' )
				&&
			isset( $_FILES['pronamic-pay-importer-file'] )
		) {
			if ( 'text/csv' !== $_FILES['pronamic-pay-importer-file']['type'] ) {
				\wp_die( __( 'Uploaded file is not a CSV file.', 'pronamic-pay-importer' ) );
			}

			require __DIR__ . '/../../views/page-importer-process.php';

			return;
		}

		require __DIR__ . '/../../views/page-importer.php';
	}

	/**
	 * Maybe import.
	 *
	 * @return array
	 */
	private function import_file( $path ) {
		if ( ! is_readable( $path ) ) {
			\wp_die( __( 'Import file could not be read.', 'pronamic-pay-importer' ) );
		}

		$file = \file( $path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES );

		/*
		 * Remove UTF-8 byte order mark.
		 *
		 * @link https://en.wikipedia.org/wiki/Byte_order_mark
		 */
		$file[0] = \preg_replace( '/^\xEF\xBB\xBF/', '', $file[0] );

		// Parse the CSV file into an array.
		$delimiter = ( false === strpos( $file[0], ',' ) ? ';' : ',' );

		$delimiters = array_fill( 0, count( $file ), $delimiter );

		$data = array_map( 'str_getcsv', $file, $delimiters );

		$data = new ImportData( $data );

		$result = $data->process();

		return $result;
	}
}
