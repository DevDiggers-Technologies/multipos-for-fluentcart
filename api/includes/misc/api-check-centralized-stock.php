<?php
/**
 * API Check Centralized Stock class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Api\Includes\Misc;

use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Product;

use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Check_Centralized_Stock' ) ) {
	/**
	 * API Check Centralized Stock class
	 * 
	 * Handles stock checking for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Check_Centralized_Stock extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name.
		 *
		 * @var string the route base
		 */
		public $base = 'check-centralized-stock';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [ 'cart_data' ];

		/**
		 * Execute the specific API logic for checking centralized stock.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Stock check result or error.
		 */
		protected function execute_api_logic( $request ) {
			$cart_data = $request['cart_data'];

			// Validate cart data
			$cart_validation = $this->validate_cart_data( $cart_data );
			if ( is_wp_error( $cart_validation ) ) {
				return $cart_validation;
			}

			// Check stock for all products
			return $this->check_stock_availability( $cart_data );
		}

		/**
		 * Validate cart data.
		 *
		 * @param string $cart_data JSON string of cart data.
		 * @return bool|WP_Error True if valid, WP_Error if invalid.
		 */
		protected function validate_cart_data( $cart_data ) {
			if ( empty( $cart_data ) ) {
				return $this->error_response(
					esc_html__( 'Cart data is required', 'devdiggers-multipos-for-fluentcart' ),
					'missing_cart_data',
					400
				);
			}

			$cart_list = json_decode( $cart_data, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return $this->error_response(
					esc_html__( 'Invalid JSON format in cart data', 'devdiggers-multipos-for-fluentcart' ),
					'invalid_json_format',
					400
				);
			}

			if ( ! is_array( $cart_list ) || empty( $cart_list ) ) {
				return $this->error_response(
					esc_html__( 'Cart data must be a non-empty array', 'devdiggers-multipos-for-fluentcart' ),
					'invalid_cart_format',
					400
				);
			}

			return true;
		}

		/**
		 * Check stock availability for cart items.
		 *
		 * @param string $cart_data JSON string of cart data.
		 * @return array Stock check result.
		 */
		protected function check_stock_availability( $cart_data ) {
			try {
				$cart_list = $this->sanitize_value( (array) json_decode( $cart_data, true ) );
				$response = [ 'out_of_stock_products' => [] ];

				if ( ! empty( $cart_list ) ) {
					foreach ( $cart_list as $key => $cart_item ) {
						$stock_check = $this->check_product_stock( $cart_item );
						if ( $stock_check['out_of_stock'] ) {
							$response['out_of_stock_products'][] = $stock_check['product_id'];
						}
					}
				}

				return apply_filters( 'ddfcpos/api/stock/check_response', $response, $cart_list );

			} catch ( \Exception $e ) {
				return $this->error_response(
					$e->getMessage(),
					'stock_check_error',
					500
				);
			}
		}

		/**
		 * Check stock for individual product.
		 *
		 * @param array $cart_item Cart item data.
		 * @return array Stock check result for the product.
		 */
		protected function check_product_stock( $cart_item ) {
			$product_id = sanitize_text_field( (string) ( $cart_item['product_id'] ?? '' ) );
			$quantity   = intval( $cart_item['quantity'] ?? 0 );

			if ( '' === $product_id || 0 === strpos( $product_id, 'custom' ) || $quantity <= 0 ) {
				return [
					'product_id'   => $product_id,
					'out_of_stock' => false,
					'reason'       => 'invalid_data'
				];
			}

			$product = DDFCPOS_Product::get( $product_id );
			if ( ! $product ) {
				return [
					'product_id'   => $product_id,
					'out_of_stock' => true,
					'reason'       => 'product_not_found'
				];
			}

			$product_stock_status = $product->get_stock_status();
			$product_stock        = false;

			if ( $product->get_manage_stock() ) {
				$product_stock = intval( $product->get_stock_quantity() );
			}

			$is_out_of_stock = false;
			$reason = '';

			if ( 'outofstock' === $product_stock_status ) {
				$is_out_of_stock = true;
				$reason = 'product_out_of_stock';
			} elseif ( 'instock' === $product_stock_status && is_numeric( $product_stock ) && $quantity > $product_stock ) {
				if ( ! $product->backorders_allowed() ) {
					$is_out_of_stock = true;
					$reason = 'insufficient_stock';
				}
			}

			return [
				'product_id'         => $product_id,
				'out_of_stock'       => $is_out_of_stock,
				'reason'             => $reason,
				'available_stock'    => $product_stock,
				'requested_quantity' => $quantity
			];
		}
	}
}
