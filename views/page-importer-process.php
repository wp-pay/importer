<?php
/**
 * Page importer process.
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2021 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Importer;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

?>

<div class="wrap">
	<h1><?php \esc_html_e( 'Importing', 'pronamic-pay-importer' ); ?></h1>

	<pre><?php

		$this->import_file( $_FILES['pronamic-pay-importer-file']['tmp_name'] );

	?></pre>
</div>
