<?php
/**
 * WooCommerce cart bridge for boarding quotes (optional).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Boarding_Commerce
 */
class KennelFlow_Boarding_Boarding_Commerce {

	const CART_DATA_KEY = 'kennelpress_boarding';

	/**
	 * Register WooCommerce filters.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_boarding_cart_prices' ), 20, 1 );
	}

	/**
	 * After booking REST create: optionally add a cart line priced from the server quote.
	 *
	 * @param int                  $booking_post_id  Booking ID.
	 * @param int                  $location_post_id Hub location post ID.
	 * @param array<string, mixed> $quote            From KennelFlow_Boarding_Boarding_Quote::build.
	 * @param array<string, mixed> $settings         Facility settings.
	 * @return array{checkout_url?:string, cart_error?:string}
	 */
	public static function maybe_add_booking_to_cart( $booking_post_id, $location_post_id, array $quote, array $settings ) {
		$mode = isset( $settings['boarding_price_application'] ) ? sanitize_key( (string) $settings['boarding_price_application'] ) : 'quote_only';
		if ( ! in_array( $mode, array( 'woocommerce', 'both' ), true ) ) {
			return array();
		}
		if ( ! function_exists( 'wc_get_checkout_url' ) || ! function_exists( 'WC' ) ) {
			return array(
				'cart_error' => __( 'WooCommerce is not available.', 'kennelflow-boarding' ),
			);
		}
		$cart = WC()->cart;
		if ( ! $cart ) {
			return array(
				'cart_error' => __( 'Cart is not available.', 'kennelflow-boarding' ),
			);
		}

		$product_id = isset( $settings['boarding_wc_product_id'] ) ? absint( $settings['boarding_wc_product_id'] ) : 0;
		if ( $product_id < 1 || 'product' !== get_post_type( $product_id ) ) {
			return array(
				'cart_error' => __( 'Boarding product is not configured for WooCommerce checkout.', 'kennelflow-boarding' ),
			);
		}

		$pet_size = isset( $quote['pet_size'] ) ? sanitize_key( (string) $quote['pet_size'] ) : 'medium';
		$var_key  = 'boarding_wc_variation_' . $pet_size;
		$var_id   = isset( $settings[ $var_key ] ) ? absint( $settings[ $var_key ] ) : 0;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array(
				'cart_error' => __( 'Invalid boarding product.', 'kennelflow-boarding' ),
			);
		}

		$price = isset( $quote['total'] ) ? (float) $quote['total'] : 0;
		if ( $price <= 0 ) {
			return array(
				'cart_error' => __( 'Quoted total must be greater than zero for checkout.', 'kennelflow-boarding' ),
			);
		}

		$cart_item_data = array(
			self::CART_DATA_KEY => array(
				'booking_id'  => absint( $booking_post_id ),
				'location_id' => absint( $location_post_id ),
				'price'       => $price,
				'quote_json'  => wp_json_encode( $quote ),
			),
		);

		if ( $product->is_type( 'variable' ) && $var_id > 0 ) {
			$added = $cart->add_to_cart( $product_id, 1, $var_id, array(), $cart_item_data );
		} else {
			$added = $cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );
		}

		if ( ! $added ) {
			return array(
				'cart_error' => __( 'Could not add boarding deposit to cart.', 'kennelflow-boarding' ),
			);
		}

		return array(
			'checkout_url' => wc_get_checkout_url(),
		);
	}

	/**
	 * Set line item price from cart meta (boarding quote total).
	 *
	 * @param WC_Cart $cart Cart.
	 * @return void
	 */
	public static function apply_boarding_cart_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! $cart || $cart->is_empty() ) {
			return;
		}
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item[ self::CART_DATA_KEY ] ) || ! is_array( $cart_item[ self::CART_DATA_KEY ] ) ) {
				continue;
			}
			$data  = $cart_item[ self::CART_DATA_KEY ];
			$price = isset( $data['price'] ) ? (float) $data['price'] : 0;
			if ( $price <= 0 ) {
				continue;
			}
			if ( isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ) {
				$cart_item['data']->set_price( $price );
			}
		}
	}
}
