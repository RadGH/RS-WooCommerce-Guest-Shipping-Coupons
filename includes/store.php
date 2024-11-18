<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) die();

// Controller
class RS_WC_Guest_Shipping_Coupons_Store {
	
	public function __construct() {
		
		// Add the "Guest Shipping" field to the coupon edit screen
		add_action( 'woocommerce_coupon_options', array( $this, 'add_coupon_field' ), 20, 2 );
		
		// Save the "Guest Shipping" field
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save_coupon_field' ) );
		
		// Add the "Guest Shipping" field to the checkout page fields
		add_action( 'woocommerce_checkout_fields', array( $this, 'add_checkout_field' ) );
		
		// Display the "Guest Shipping" fields on the checkout form
		add_action( 'woocommerce_checkout_billing', array( $this, 'display_checkout_field' ) );
		// add_action( 'woocommerce_before_order_notes', array( $this, 'display_checkout_field' ) );
		
		// Only validate the "Guest Shipping" fields if a guest shipping coupon is in the cart, otherwise ignore validation
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'validate_checkout_field' ) );
		
		// Handle an ajax request which checks if the coupon is in the coupon array or 0
		add_action( 'wp_ajax_rswc_gsc_in_cart', array( $this, 'ajax_is_coupon_in_cart' ) );
		add_action( 'wp_ajax_nopriv_rswc_gsc_in_cart', array( $this, 'ajax_is_coupon_in_cart' ) );
		
		// When processing an order, save the guest checkout address fields to the order metadata
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_guest_checkout_fields' ) );
		
		// Display the guest address fields on the order edit screen
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_order_guest_shipping_fields' ) );
		
		// Display the guest address on the receipt, emails, and other areas, below the product title
		// 1. Website
		add_action( 'woocommerce_order_details_before_order_table', array( $this, 'display_order_guest_shipping_fields_on_product_receipt' ), 20 );
		// 2. Email
		add_action( 'woocommerce_email_before_order_table', array( $this, 'display_order_guest_shipping_fields_on_product_receipt' ), 20 );
		
		
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
	/**
	 * Get the guest shipping address fields
	 * @return null[]
	 */
	public function get_shipping_field_keys() {
		return array(
			'first_name',
			'last_name',
			'company',
			'country',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
		);
	}
	
	/**
	 * Get the guest shipping coupon from an order
	 *
	 * @param WC_Order|int $order
	 *
	 * @return WC_Coupon|false
	 */
	public function get_guest_shipping_coupon_from_order( $order ) {
		
		// Get the order
		$order = wc_get_order( $order );
		
		// Check if the order has a coupon
		if ( ! $order->get_coupon_codes() ) return false;
		
		// Get the applied coupons
		$coupons = $order->get_coupon_codes();
		
		// Loop through the applied coupons
		if ( $coupons ) foreach ( $coupons as $coupon_code ) {
			
			// Get the coupon
			$coupon = new WC_Coupon( $coupon_code );
			
			// Check if the coupon has guest shipping enabled
			if ( $coupon->get_meta( '_guest_shipping' ) ) {
				return $coupon;
			}
		}
		
		return false;
	}
	
	/**
	 * Get the guest shipping coupon from the cart
	 *
	 * @return WC_Coupon|false
	 */
	public function get_guest_shipping_coupon_from_cart() {
		
		// Get the cart
		$cart = WC()->cart;
		
		// Check if the cart has a coupon
		if ( ! $cart->has_discount() ) return false;
		
		// Get the applied coupons
		$coupons = $cart->get_applied_coupons();
		
		// Loop through the applied coupons
		if ( $coupons ) foreach ( $coupons as $coupon_code ) {
			
			// Get the coupon
			$coupon = new WC_Coupon( $coupon_code );
			
			// Check if the coupon has guest shipping enabled
			if ( $coupon->get_meta( '_guest_shipping' ) ) {
				return $coupon;
			}
		}
		
		return false;
	}
	
	/**
	 * Get the guest shipping address title to use on the checkout page, from a coupon
	 *
	 * @param WC_Coupon $coupon
	 *
	 * @return string
	 */
	public function get_guest_shipping_title( $coupon = null ) {
		if ( $coupon === null ) {
			// Get coupon from the cart, if possible
			$coupon = $this->get_guest_shipping_coupon_from_cart();
		}
		
		if ( $coupon instanceof WC_Coupon ) {
			if ( $coupon->get_meta( '_guest_shipping' ) ) {
				return $coupon->get_meta( '_guest_shipping_title' );
			}
		}
		
		return false;
	}
	
	/**
	 * Get the guest shipping address title to use on the checkout page, from a coupon
	 *
	 * @param WC_Coupon $coupon
	 *
	 * @return string
	 */
	public function get_guest_shipping_address_description( $coupon = null ) {
		if ( $coupon === null ) {
			// Get coupon from the cart, if possible
			$coupon = $this->get_guest_shipping_coupon_from_cart();
		}
		
		if ( $coupon instanceof WC_Coupon ) {
			if ( $coupon->get_meta( '_guest_shipping' ) ) {
				return $coupon->get_meta( '_guest_shipping_description' );
			}
		}
		
		return false;
	}
	
	/**
	 * Get data for a coupon to send to JS
	 *
	 * @param WC_Coupon|false $coupon
	 *
	 * @return array
	 */
	public function get_coupon_js_data( $coupon ) {
		$data = array(
			'coupon_id' => false,
			'coupon_code' => false,
			'guest_shipping' => false,
			'guest_shipping_title' => false,
			'guest_shipping_description' => false,
		);
		
		if ( $coupon instanceof WC_Coupon ) {
			$data['coupon_id'] = $coupon->get_id();
			$data['coupon_code'] = $coupon->get_code();
			$data['guest_shipping'] = $coupon->get_meta( '_guest_shipping' ) ?: false;
			$data['guest_shipping_title'] = $coupon->get_meta( '_guest_shipping_title' ) ?: 'Guest Shipping Address';
			$data['guest_shipping_description'] = $coupon->get_meta( '_guest_shipping_description' ) ?: false;
		}
		
		return $data;
	}
	
	/**
	 * Gets the guest shipping address fields from an order that was submitted.
	 * Returns false if not applicable to the order (coupon not applied)
	 *
	 * @param WC_Order|int $order
	 *
	 * @return array|false
	 */
	public function get_guest_shipping_address_from_order( $order ) {
		// Get the order
		if ( is_numeric($order) ) $order = wc_get_order( $order );
		if ( ! $order ) return false;
		
		// Get the guest shipping coupon
		$coupon = $this->get_guest_shipping_coupon_from_order( $order );
		if ( ! $coupon ) return false;
		
		// Get the guest address fields
		$address = array();
		
		// Loop through the fields to get values
		foreach ( $this->get_shipping_field_keys() as $key ) {
			$address[$key] = get_post_meta( $order->get_id(), '_guest_address_' . $key, true );
		}
		
		return $address;
	}
	
	
	
	/**
	 * Displays the shipping address in a simplified format
	 *
	 * @param $order
	 * @param $address
	 *
	 * @return false|void
	 */
	public function display_shipping_address_simplified( $order, $address = null ) {
		if ( $address === null ) {
			$address = $this->get_guest_shipping_address_from_order( $order );
		}
		
		if ( ! $address ) return false;
		
		if ( $address['first_name'] || $address['last_name'] ) {
			echo '<p class="guest-shipping--name">';
			echo '<strong>Name:</strong> <br>';
			echo esc_html( trim( $address['first_name'] . ' ' . $address['last_name'] ) );
			echo '</p>';
		}
		
		if ( $address['company'] ) {
			echo '<p class="guest-shipping--company">';
			echo '<strong>Company:</strong> <br>';
			echo esc_html( $address['company'] );
			echo '</p>';
		}
		
		if ( $address['address_1'] || $address['address_2'] || $address['state'] || $address['city'] || $address['postcode'] ) {
			$rows = array();
			if ( $address['address_1'] ) {
				$rows[] = $address['address_1'];
			}
			if ( $address['address_2'] ) {
				$rows[] = $address['address_2'];
			}
			if ( $address['city'] || $address['state'] || $address['postcode'] ) {
				$city_state_postcode = array();
				if ( $address['city'] ) {
					$city_state_postcode[] = $address['city'];
				}
				if ( $address['state'] ) {
					$city_state_postcode[] = $address['state'];
				}
				if ( $address['postcode'] ) {
					$city_state_postcode[] = $address['postcode'];
				}
				$rows[] = implode( ', ', $city_state_postcode );
			}
			
			echo '<p class="guest-shipping--address">';
			echo '<strong>Address:</strong> <br>';
			echo implode( '<br>', $rows );
			echo '</p>';
		}
	}
	
	// Hooks
	
	/**
	 * Add the "Guest Shipping" field to the coupon edit screen
	 *
	 * @param int       $coupon_id
	 * @param WC_Coupon $coupon
	 * @return void
	 */
	public function add_coupon_field( $coupon_id, $coupon = null ) {
		
		// Get the current value
		$guest_shipping = get_post_meta( $coupon_id, '_guest_shipping', true );
		
		// Output the field
		woocommerce_wp_checkbox( array(
			'id'            => '_guest_shipping',
			'label'         => __( 'Guest Shipping', 'rs-wc-guest-shipping-coupons' ),
			'description'   => __( 'Enable guest shipping for this coupon.', 'rs-wc-guest-shipping-coupons' ),
			'value'         => $guest_shipping ? 'yes' : 'no',
		) );
		
		// Output a label field
		woocommerce_wp_text_input( array(
			'id'            => '_guest_shipping_title',
			'label'         => __( 'Guest Shipping Title', 'rs-wc-guest-shipping-coupons' ),
			'description'   => __( 'The label to use for the guest shipping address on the checkout page.', 'rs-wc-guest-shipping-coupons' ),
			'desc_tip'      => true,
			'value'         => get_post_meta( $coupon_id, '_guest_shipping_title', true ),
			'placeholder'   => __( 'Guest Shipping Address', 'rs-wc-guest-shipping-coupons' ),
		) );
		
		// Output a description field
		woocommerce_wp_textarea_input( array(
			'id'            => '_guest_shipping_description',
			'label'         => __( 'Guest Shipping Description', 'rs-wc-guest-shipping-coupons' ),
			'description'   => __( 'A description of the guest shipping address to display on the checkout page.', 'rs-wc-guest-shipping-coupons' ),
			'desc_tip'      => true,
			'value'         => get_post_meta( $coupon_id, '_guest_shipping_description', true ),
		) );
		
	}
	
	/**
	 * Save the "Guest Shipping" field
	 *
	 * @param int $coupon_id
	 * @return void
	 */
	public function save_coupon_field( $coupon_id ) {
		
		// Save checkbox
		$guest_shipping = !empty( $_POST['_guest_shipping'] ) ? '1' : '';
		update_post_meta( $coupon_id, '_guest_shipping', $guest_shipping );
		
		// Save title
		$guest_shipping_label = isset( $_POST['_guest_shipping_title'] ) ? sanitize_text_field( $_POST['_guest_shipping_title'] ) : '';
		update_post_meta( $coupon_id, '_guest_shipping_title', $guest_shipping_label );
		
		// Save description
		$guest_shipping_label = isset( $_POST['_guest_shipping_description'] ) ? sanitize_text_field( $_POST['_guest_shipping_description'] ) : '';
		update_post_meta( $coupon_id, '_guest_shipping_description', $guest_shipping_label );
		
	}
	
	/**
	 * Add the "Guest Shipping" field to the checkout page, if coupon is in cart
	 *
	 * @param array[] $fields
	 *
	 * @return array[]
	 */
	public function add_checkout_field( $fields ) {
		
		// 	if ( ! $this->get_guest_shipping_coupon_from_cart() ) return;
		
		$checkout = WC()->checkout;
		
		// Output the field
		/*
		woocommerce_form_field( 'guest_shipping', array(
			'type'          => 'checkbox',
			'label'         => __( 'Ship to a different address?', 'rs-wc-guest-shipping-coupons' ),
			'required'      => false,
		), $checkout->get_value( 'guest_shipping' ) );
		*/
		
		// Get the guest country based on the billing country
		$billing_country   = $checkout->get_value( 'billing_country' );
		$billing_country   = empty( $billing_country ) ? WC()->countries->get_base_country() : $billing_country;
		$allowed_countries = WC()->countries->get_allowed_countries();
		
		if ( ! array_key_exists( $billing_country, $allowed_countries ) ) {
			$billing_country = current( array_keys( $allowed_countries ) );
		}
		
		$fields['guest_address'] = WC()->countries->get_address_fields(
			$billing_country,
			'guest_address_'
		);
		
		$is_saving = isset($_POST['guest_address_first_name']);
		
		// Make each field optional by default
		foreach ( $fields['guest_address'] as $key => $field ) {
			// Do not require the field when saving - we'll use custom validation instead
			$fields['guest_address'][ $key ]['default_required'] = $fields['guest_address'][ $key ]['required'];
			
			if ( $is_saving ) {
				$fields['guest_address'][ $key ]['required'] = false;
			}
		}
		
		return $fields;
		
	}
	
	/**
	 * Display the "Guest Shipping" fields on the checkout form
	 *
	 * @return void
	 */
	public function display_checkout_field() {
		$checkout = WC_Checkout::instance();
		
		$label = $this->get_guest_shipping_title();
		if ( ! $label ) $label = __( 'Guest Shipping Address', 'rs-wc-guest-shipping-coupons' );
		
		$description = $this->get_guest_shipping_address_description();
		if ( ! $description ) $description = false;
		
		?>
		<div class="guest-shipping-container" style="display: none;">
			
			<h3 class="guest-shipping--title"><?php echo esc_html( $label ); ?></h3>
			
			<div class="guest-shipping--description">
				<?php
				if ( $description ) {
					echo wpautop(wp_kses_post( $description ));
				}
				?>
			</div>
			
			<div class="woocommerce-billing-fields__field-wrapper guest-shipping--fields">
				<?php
				$fields = $checkout->get_checkout_fields( 'guest_address' );
				
				foreach ( $fields as $key => $field ) {
					woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
				}
				?>
			</div>
		
		</div>
		<?php
	}
	
	/**
	 * Only validate the "Guest Shipping" fields if a guest shipping coupon is in the cart, otherwise ignore validation
	 *
	 * @param array $posted_data
	 *
	 * @return array
	 */
	public function validate_checkout_field( $posted_data ) {
		
		// Get the guest shipping coupon
		$coupon = $this->get_guest_shipping_coupon_from_cart();
		
		// If no guest shipping coupon is in the cart, ignore validation
		if ( ! $coupon ) return $posted_data;
		
		$group_label = $this->get_guest_shipping_title( $coupon );
		
		// Get the guest address fields
		$fields = WC()->checkout->get_checkout_fields( 'guest_address' );
		
		// Loop through the fields
		foreach ( $fields as $key => $field ) {
			
			// Check if the field is required
			if ( ! empty( $field['default_required'] ) && empty( $posted_data[ $key ] ) ) {
				$label = $field['label'];
				if ( $group_label ) $label = $group_label . ' - ' . $label;
				$notice = sprintf( __('<strong>%s</strong> is a required field.', 'rs-wc-guest-shipping-coupons' ), $label );
				wc_add_notice( $notice, 'error' );
			}
			
		}
		
		return $posted_data;
	}
	
	/**
	 * Handle an ajax request which checks if the coupon is in the cart and returns the coupon array or 0
	 *
	 * @return void
	 */
	public function ajax_is_coupon_in_cart() {
		$coupon = $this->get_guest_shipping_coupon_from_cart();
		
		if ( $coupon ) {
			$coupon_data = $this->get_coupon_js_data( $coupon );
			wp_send_json_success( json_encode( $coupon_data ) );
		} else {
			wp_send_json_success( 0 );
		}
	}
	
	/**
	 * When processing an order, save the guest checkout address fields to the order metadata
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function save_guest_checkout_fields( $order_id ) {
		
		// Get the guest shipping coupon
		$coupon = $this->get_guest_shipping_coupon_from_cart();
		
		// If no guest shipping coupon is in the cart, ignore validation
		if ( ! $coupon ) return;
		
		// Get the guest address fields
		$fields = WC()->checkout->get_checkout_fields( 'guest_address' );
		
		// Loop through the fields
		foreach ( $fields as $key => $field ) {
			
			// Save the field to the order metadata
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $order_id, '_' . $key, sanitize_text_field( $_POST[ $key ] ) );
			}
			
		}
		
	}
	
	/**
	 * Display the guest address fields on the order edit screen
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function display_order_guest_shipping_fields( $order ) {
		
		// Get the guest shipping coupon
		$coupon = $this->get_guest_shipping_coupon_from_order( $order );
		
		// If no guest shipping coupon is in the cart, ignore validation
		if ( ! $coupon ) return;
		
		// Get the guest address fields
		$address = $this->get_guest_shipping_address_from_order( $order );
		
		$coupon_url = get_edit_post_link( $coupon->get_id() );
		
		echo '<div class="guest-address">';
		echo '<h3>' . esc_html( $this->get_guest_shipping_title( $coupon ) ) . ' <span style="font-weight: 400;">(Coupon: <a href="'. esc_attr($coupon_url) .'">' . esc_html( $coupon->get_code() ) . '</a>)</span></h3>';
		
		$this->display_shipping_address_simplified( $order, $address );
		
		echo '</div>';
		
	}
	
	public function display_order_guest_shipping_fields_on_product_receipt( $order ) {
		// Get the guest shipping coupon
		$coupon = $this->get_guest_shipping_coupon_from_order( $order );
		if ( ! $coupon ) return;
		
		// Get the address
		$address = $this->get_guest_shipping_address_from_order( $order );
		if ( ! $address ) return;
		
		echo '<div class="guest-address">';
		echo '<h2>' . esc_html( $this->get_guest_shipping_title( $coupon ) ) . '</h2>';
		
		$this->display_shipping_address_simplified( $order, $address );
		
		echo '</div>';
	}
	
}

// Initialize
RS_WC_Guest_Shipping_Coupons_Store::get_instance();