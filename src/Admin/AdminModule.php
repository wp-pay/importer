<?php
/**
 * Admin Module
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2022 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Admin
 */

namespace Pronamic\WordPress\Pay\Importer\Admin;

use Pronamic\WordPress\Pay\Importer\Addon;

/**
 * WordPress Pay admin
 *
 * @author  Reüel van der Steege
 * @version 1.0.0
 * @since   1.0.0
 */
class AdminModule {
	/**
	 * Plugin.
	 *
	 * @var Addon
	 */
	private $addon;

	/**
	 * Admin settings page.
	 *
	 * @var AdminSettings
	 */
	public $settings;

	/**
	 * Admin importer.
	 *
	 * @var AdminImporter
	 */
	public $importer;

	/**
	 * Construct and initialize an admin object.
	 *
	 * @param Addon $addon Plugin.
	 */
	public function __construct( Addon $addon ) {
		$this->addon = $addon;

		// Actions.
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Admin initialize.
	 *
	 * @return void
	 */
	public function admin_init() {
	}

	/**
	 * Check if scripts should be enqueued based on the hook and current screen.
	 *
	 * @link https://developer.wordpress.org/reference/functions/get_current_screen/
	 * @link https://developer.wordpress.org/reference/classes/wp_screen/
	 *
	 * @param string $hook Hook.
	 * @return bool True if scripts should be enqueued, false otherwise.
	 */
	private function should_enqueue_scripts( $hook ) {
		// Check if the hook contains the value 'pronamic_pay'.
		if ( false !== strpos( $hook, 'pronamic_pay' ) ) {
			return true;
		}

		// Check if the hook contains the value 'pronamic_ideal'.
		if ( false !== strpos( $hook, 'pronamic_ideal' ) ) {
			return true;
		}

		// Check current screen for some values related to Pronamic Pay.
		$screen = get_current_screen();

		if ( null === $screen ) {
			return false;
		}

		// Current screen is dashboard.
		if ( 'dashboard' === $screen->id ) {
			return true;
		}

		// Gravity Forms.
		if ( 'toplevel_page_gf_edit_forms' === $screen->id ) {
			return true;
		}

		// CHeck if current screen post type is related to Pronamic Pay.
		if ( in_array(
			$screen->post_type,
			array(
				'pronamic_gateway',
				'pronamic_payment',
				'pronamic_pay_form',
				'pronamic_pay_gf',
				'pronamic_pay_subscr',
			),
			true
		) ) {
			return true;
		}

		// Other.
		return false;
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( ! $this->should_enqueue_scripts( $hook ) ) {
			return;
		}

		$min = SCRIPT_DEBUG ? '' : '.min';

		wp_register_style(
			'pronamic-pay-importer-admin',
			plugins_url( '../../css/admin' . $min . '.css', __FILE__ ),
			array(),
			$this->addon->version
		);

		wp_register_script(
			'pronamic-pay-importer-admin',
			plugins_url( '../../js/dist/admin' . $min . '.js', __FILE__ ),
			array( 'jquery' ),
			$this->addon->version,
			true
		);

		// Enqueue.
		wp_enqueue_style( 'pronamic-pay-importer-admin' );
		wp_enqueue_script( 'pronamic-pay-importer-admin' );
	}

	/**
	 * Create the admin menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		// Submenu pages.
		$submenu_pages = array(
			array(
				'page_title' => __( 'Import', 'pronamic_ideal' ),
				'menu_title' => __( 'Import', 'pronamic_ideal' ),
				'capability' => 'manage_options',
				'menu_slug'  => 'pronamic_pay_importer',
				'function'   => function () {
					// Importer.
					$this->importer = new AdminImporter( $this->addon );

					$this->importer->page_importer();
				},
			),
		);

		// Add submenu pages.
		foreach ( $submenu_pages as $page ) {
			/**
			 * To keep PHPStan happy we use an if/else statement for
			 * the 6th $function parameter which should be a callable
			 * function. Unfortunately this is not documented
			 * correctly in WordPress.
			 *
			 * @link https://github.com/WordPress/WordPress/blob/5.2/wp-admin/includes/plugin.php#L1296-L1377
			 */
			if ( array_key_exists( 'function', $page ) ) {
				add_submenu_page( 'pronamic_ideal', $page['page_title'], $page['menu_title'], $page['capability'], $page['menu_slug'], $page['function'] );
			} else {
				add_submenu_page( 'pronamic_ideal', $page['page_title'], $page['menu_title'], $page['capability'], $page['menu_slug'] );
			}
		}
	}
}
