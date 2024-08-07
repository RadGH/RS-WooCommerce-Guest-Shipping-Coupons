jQuery(function($) {

	let o = {};

	// Get global settings provided by enqueue.php
	let s = window.rswcgsc_settings;

	// Get general settings
	let settings = {
		// enqueue.php
		ajax_url:    s.ajax_url || '/wp-admin/admin-ajax.php',
		nonce:       s.nonce || '',
		
		// enqueue.php + ajax
		coupon_data: s.coupon_data || false,

		// internal
		is_visible: false,
	};
	
	let elements = {
		container:   document.querySelector('.guest-shipping-container'),
		title:       document.querySelector('.guest-shipping--title'),
		description: document.querySelector('.guest-shipping--description'),
	};

	o.init = function() {
		if ( ! elements.container ) {
			console.warn('[GSC] No guest shipping address found');
			return;
		}

		console.log('[GSC] Initializing guest shipping fields', settings);

		// When WooCommerce updates the checkout, we need to re-check if a guest shipping coupon is in the cart
		$(document.body).on('updated_checkout', function() {
			o.refresh_state();
		});
	};

	// Returns true if we have a guest shipping coupon in the cart (based on the last time the state was updated)
	o.has_guest_shipping_coupon = function() {
		return !! settings.coupon_data;
	};

	// Get coupon data by key. Properties: coupon_id, coupon_code, guest_shipping, guest_shipping_title, guest_shipping_description
	o.get_coupon_data = function( key ) {
		if ( !! settings.coupon_data ) {
			return settings.coupon_data[key];
		}
		return false;
	}

	// Refresh the state, indicating whether we have a guest shipping coupon in the cart
	o.refresh_state = function() {
		console.log('[GSC] Refreshing state');

		// Perform a wp-ajax using the action "rswc_gsc_in_cart"
		$.ajax({
			url: settings.ajax_url,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'rswc_gsc_in_cart',
				nonce: settings.nonce
			},
			success: function( response ) {
				if ( response.success ) {
					// Convert response.data json to object and update state
					settings.coupon_data = JSON.parse(response.data);

					// Update UI
					o.update_ui();
				}
			}
		});
	};

	// After the state has been updated, update the UI to hide the guest address unless the coupon is in the cart
	o.update_ui = function() {
		console.log('[GSC] Updating UI', o.settings);

		if ( o.has_guest_shipping_coupon() ) {

			// Make guest shipping fields visible
			o.is_visible = true;
			elements.container.style.display = '';
			
			// Update the title
			let title = o.get_coupon_data('guest_shipping_title');
			elements.title.innerHTML = title;
			elements.title.style.display = title ? '' : 'none';

			// Update the description
			let description = o.get_coupon_data('guest_shipping_description');
			elements.description.innerHTML = description;
			elements.description.style.display = description ? '' : 'none';

			// Make <select> into select2 if available
			if ( typeof $.fn.select2 === 'function' ) {
				$('.guest-shipping-container select').select2();
			}

		} else {

			// Hide guest shipping fields
			o.is_visible = false;
			elements.container.style.display = 'none';

		}
	};

	// Add settings to the object
	o.settings = settings;

	// Make the object available globally
	window.rswc_guest_shipping_coupons = o;

	// Update the UI initially
	o.update_ui();

	// Initialize
	o.init();

});