<?php
/**
 * Page importer.
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
	<h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>

	<p>
		<?php \esc_html_e( 'Select a CSV file to import.', 'pronamic-pay-import' ); ?><br>
	</p>

	<form method="post" action="" enctype="multipart/form-data">
		<?php wp_nonce_field( 'pronamic-pay-importer-import', 'pronamic-pay-importer-nonce' ); ?>

		<fieldset>
			<input type="file" name="pronamic-pay-importer-file" />

			<input type="submit" value="<?php \esc_html_e( 'Import', 'pronamic-pay-importer' ); ?>"/>
		</fieldset>

	</form>
</div>
