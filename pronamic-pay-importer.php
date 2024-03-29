<?php
/**
 * Plugin Name: Pronamic Pay Importer Add-On
 * Plugin URI: https://www.pronamic.eu/plugins/pronamic-pay-importer/
 * Description: Extend the Pronamic Pay plugin with import functionality.
 *
 * Version: 1.0.0
 * Requires at least: 4.7
 *
 * Author: Pronamic
 * Author URI: https://www.pronamic.eu/
 *
 * Text Domain: pronamic-pay-importer
 * Domain Path: /languages/
 *
 * License: GPL-3.0-or-later
 *
 * Depends: wp-pay/core
 *
 * GitHub URI: https://github.com/wp-pay/importer
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2022 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Importer
 */

/**
 * Autoload.
 */
require __DIR__ . '/vendor/autoload.php';

/**
 * Bootstrap.
 */
\Pronamic\WordPress\Pay\Importer\Addon::instance(
	array(
		'file' => __FILE__,
	)
);
