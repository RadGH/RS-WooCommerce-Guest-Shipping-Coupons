<?php
/*
Plugin Name: RS WooCommerce Guest Shipping Coupons
Description: Extends WooCommerce coupons to support "Guest Shipping" address at checkout. Useful for promotions that require a separate shipping address.
Version: 1.0.0
Author: Radley Sustaire
Author URI: https://radleysustaire.com
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) die();

// Plugin controller
class RS_WC_Guest_Shipping_Coupons_Plugin {
	
	public static string $name;
	public static string $version;
	public static string $path;
	public static string $url;
	
	public function __construct() {
		
		self::$name = 'RS WooCommerce Guest Shipping Coupons';
		self::$version = '1.0.0';
		self::$path = __DIR__;
		self::$url = untrailingslashit(plugin_dir_url(__FILE__));
		
		// Initialize the plugin after other plugins have loaded
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		
	}
	
	// Get the instance of this class
	public static function get_instance() {
		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new self();
		}
		return $instance;
	}
	
	/**
	 * Initialize the plugin after other plugins have loaded
	 *
	 * @return void
	 */
	public function init() {
		// Check for required plugins
		$missing_plugins = array();
		
		if ( ! class_exists('ACF') ) {
			$missing_plugins[] = 'Advanced Custom Fields Pro';
		}
		
		if ( $missing_plugins ) {
			self::add_admin_notice( '<strong>'. esc_html(self::$name) .':</strong> The following plugins are required: '. implode(', ', $missing_plugins) . '.', 'error' );
			return;
		}
		
		// Include plugin files
		require_once( __DIR__ . '/includes/enqueue.php' );
		require_once( __DIR__ . '/includes/store.php' );
	}
	
	/**
	 * Adds an admin notice to the dashboard's "admin_notices" hook.
	 *
	 * @param string $message The message to display
	 * @param string $type    The type of notice: info, error, warning, or success. Default is "info"
	 * @param bool $format    Whether to format the message with wpautop()
	 *
	 * @return void
	 */
	public static function add_admin_notice( $message, $type = 'info', $format = true ) {
		add_action( 'admin_notices', function() use ( $message, $type, $format ) {
			?>
			<div class="notice notice-<?php echo $type; ?>">
				<?php echo $format ? wpautop($message) : $message; ?>
			</div>
			<?php
		});
	}
	
}

// Initialize the plugin
RS_WC_Guest_Shipping_Coupons_Plugin::get_instance();