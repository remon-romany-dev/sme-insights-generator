<?php
/**
 * Plugin Name: SME Insights Generator
 * Plugin URI: https://prortec.com/remon-romany/
 * Description: Generate and publish WordPress posts on a schedule with OpenAI, Google Gemini, and Anthropic Claude. Empty topic fields keep the original SME subject lists.
 * Version: 1.1.0
 * Author: REMON ROMANY
 * Author URI:  https://prortec.com/remon-romany/
 * License: GPL2
 * Text Domain: sme-insights-generator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'SIG_VERSION', '1.1.0' );
define( 'SIG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The main plugin class.
 */
class SME_Insights_Generator {

	/**
	 * Singleton instance.
	 *
	 * @var SME_Insights_Generator
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return SME_Insights_Generator
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define necessary constants.
	 */
	private function define_constants() {
		// Constants are defined at the top of the file.
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Core logic classes.
		require_once SIG_PLUGIN_DIR . 'includes/class-sig-api-handler.php';
		require_once SIG_PLUGIN_DIR . 'includes/class-sig-post-creator.php';
		require_once SIG_PLUGIN_DIR . 'includes/class-sig-cron-manager.php';

		// Admin classes.
		if ( is_admin() ) {
			require_once SIG_PLUGIN_DIR . 'admin/class-sig-settings-page.php';
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Initialize the settings page in the admin area.
		if ( is_admin() ) {
			new SIG_Settings_Page();
		}

		// Initialize the cron manager.
		SIG_Cron_Manager::get_instance();

		// Add SEO meta tags hook (only if no SEO plugin is active).
		if ( ! function_exists( 'yoast_breadcrumb' ) && ! class_exists( 'RankMath' ) && ! function_exists( 'aioseo' ) ) {
			add_action( 'wp_head', array( 'SIG_Post_Creator', 'output_seo_meta_tags' ), 1 );
		}

		// Activation and deactivation hooks.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Plugin activation hook.
	 */
	public function activate() {
		// Schedule the cron job upon activation.
		SIG_Cron_Manager::get_instance()->schedule_cron_job();
	}

	/**
	 * Plugin deactivation hook.
	 */
	public function deactivate() {
		// Clear the cron job upon deactivation.
		SIG_Cron_Manager::get_instance()->clear_cron_job();
	}
}

// Initialize the plugin.
SME_Insights_Generator::get_instance();
