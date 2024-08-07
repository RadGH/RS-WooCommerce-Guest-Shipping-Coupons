<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) die();

// Controller
class RS_WC_Guest_Shipping_Coupons_Enqueue {
	
	public function __construct() {
		
		// Public front-end styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ), 20 );
		
		// Admin dashboard styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 20 );
		
		// Add block editor styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_block_editor_assets' ), 20 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_editor_assets' ), 20 );
		
	}
	
	// Get the instance of this class
	public static function get_instance() {
		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new self();
		}
		return $instance;
	}
	
	
	// Utilities
	
	public function url( $asset ) {
		return RS_WC_Guest_Shipping_Coupons_Plugin::$url . $asset;
	}
	
	public function path( $asset ) {
		return RS_WC_Guest_Shipping_Coupons_Plugin::$path . $asset;
	}
	
	public function version( $asset ) {
		// return filemtime($this->path($asset));
		return RS_WC_Guest_Shipping_Coupons_Plugin::$version;
	}
	
	// Hooks
	
	/**
	 * Public front-end styles
	 *
	 * @return void
	 */
	public function enqueue_public_assets() {
		
		if ( is_checkout() ) {
			wp_enqueue_script( 'rs-wc-gsc', $this->url('/assets/public.js'), array( 'jquery' ), $this->version('/assets/public.js') );
			
			// Get coupon in cart from store.php
			$coupon = RS_WC_Guest_Shipping_Coupons_Store::get_instance()->get_guest_shipping_coupon_from_cart();
			$coupon_data = $coupon ? RS_WC_Guest_Shipping_Coupons_Store::get_instance()->get_coupon_js_data( $coupon ) : false;
			
			// Add settings to the script using wp_localize_script
			wp_localize_script( 'rs-wc-gsc', 'rswcgsc_settings', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'rswcgsc' ),
				'coupon_in_cart' => $coupon_data,
			) );
		}
		
	}
	
	/**
	 * Admin dashboard styles
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
	}
	
	/**
	 * Admin block editor styles
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
	}
	
}

// Initialize
RS_WC_Guest_Shipping_Coupons_Enqueue::get_instance();