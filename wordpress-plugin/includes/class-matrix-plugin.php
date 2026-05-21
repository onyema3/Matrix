<?php
/**
 * Main Plugin Class
 *
 * @package MatrixPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Plugin {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize hooks and actions
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		load_plugin_textdomain( 'matrix-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Matrix Plugin', 'matrix-plugin' ),
			__( 'Matrix', 'matrix-plugin' ),
			'manage_options',
			'matrix-plugin',
			array( $this, 'render_admin_page' ),
			'dashicons-admin-generic',
			20
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		include MATRIX_PLUGIN_DIR . 'admin/admin-page.php';
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts() {
		wp_enqueue_style( 'wp-admin' );
	}
}
