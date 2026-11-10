<?php
/**
 * API Coupon Check class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\API\Includes\Misc;

use DDFCMultiPOS\Helper\Coupon\DDFCPOS_Coupon_Helper;
use DDFCMultiPOS\Helper\Customer\DDFCPOS_Customer_Helper;
use FluentCart\App\Models\Coupon;
use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Coupon_Check' ) ) {
	/**
	 * API Coupon Check class
	 *
	 * Handles coupon validation for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Coupon_Check extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name
		 *
		 * @var string $base the route base
		 */
		public $base = 'check-coupon';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [ 'coupon_code', 'customer' ];

		/**
		 * Constructor.
		 *
		 * @param array $ddfcpos_configuration Configuration array.
		 */
		public function __construct( $ddfcpos_configuration = [] ) {
			parent::__construct( $ddfcpos_configuration );
		}

		/**
		 * Execute the specific API logic for checking coupons.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Coupon validation result or error.
		 */
		protected function execute_api_logic( $request ) {
			$coupon_code = sanitize_text_field( $request['coupon_code'] );
			$customer_data = $request['customer'];

			// Validate customer data
			if ( empty( $customer_data['id'] ) || ! is_numeric( $customer_data['id'] ) ) {
				return $this->error_response(
					esc_html__( 'Invalid customer data', 'multipos-for-fluentcart' ),
					'invalid_customer_data',
					400
				);
			}

			$customer_id = intval( $customer_data['id'] );

			// Validate coupon code
			if ( empty( $coupon_code ) ) {
				return $this->error_response(
					esc_html__( 'Coupon code is required', 'multipos-for-fluentcart' ),
					'missing_coupon_code',
					400
				);
			}

			// Check if coupon exists
			$coupon = Coupon::query()->where( 'code', sanitize_text_field( $coupon_code ) )->first();

			if ( ! $coupon ) {
				return $this->error_response(
					esc_html__( 'Invalid coupon code', 'multipos-for-fluentcart' ),
					'invalid_coupon_code',
					404
				);
			}

			$customer = DDFCPOS_Customer_Helper::ddfcpos_get_order_customer( $customer_id );
			$error    = DDFCPOS_Coupon_Helper::ddfcpos_get_coupon_error( $coupon, $customer ? $customer->id : 0, $customer ? $customer->email : '' );

			if ( $error ) {
				return $this->error_response( $error, 'coupon_not_valid', 400 );
			}

			return apply_filters( 'ddfcpos/api/coupon/response', [
				'success' => true,
				'coupon'  => DDFCPOS_Coupon_Helper::ddfcpos_prepare_pos_coupon( $coupon ),
				'message' => esc_html__( 'Coupon Applied Successfully', 'multipos-for-fluentcart' ),
			], $coupon_code );
		}
	}
}
