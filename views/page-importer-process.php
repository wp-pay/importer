<?php
/**
 * Page importer process.
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2022 Pronamic
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

	<?php

	echo '<pre>';

	if ( isset( $_FILES['pronamic-pay-importer-file']['tmp_name'] ) ) :

		$this->import_file( sanitize_text_field( \wp_unslash( $_FILES['pronamic-pay-importer-file']['tmp_name'] ) ) );

	endif;

	echo '</pre>';

	?>
</div>
