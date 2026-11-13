<?php
/**
 * API Delete Customer class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Api\Includes\Customers;

use FluentCart\App\Models\Customer;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\CustomerAddresses;

use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;
use DDFCMultiPOS\Helper\Permission\DDFCPOS_Permission_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Delete_Customer' ) ) {
	/**
	 * API Delete Customer class
	 * 
	 * Handles customer deletion for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Delete_Customer extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name
		 *
		 * @var string $base the route base
		 */
		public $base = 'delete-customer';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [ 'cashier_id', 'customer_id' ];

		/**
		 * Execute the specific API logic for deleting customers.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Deletion result or error.
		 */
		protected function execute_api_logic( $request ) {
			$cashier_id = intval( $request['cashier_id'] );
			$customer_id = intval( $request['customer_id'] );

			// Validate user permissions
			$user_validation = $this->validate_user_permissions( $cashier_id );
			if ( is_wp_error( $user_validation ) ) {
				return $user_validation;
			}

			// Validate customer exists
			$customer_validation = $this->validate_customer_exists( $customer_id );
			if ( is_wp_error( $customer_validation ) ) {
				return $customer_validation;
			}

			if ( ! DDFCPOS_Permission_Helper::ddfcpos_can_manage_customer_account( $customer_id ) ) {
				return $this->error_response(
					esc_html__( 'Store staff accounts cannot be deleted from the POS.', 'devdiggers-multipos-for-fluentcart' ),
					'protected_account',
					403
				);
			}


			// Check if customer has orders
			$orders_validation = $this->validate_customer_orders( $customer_id );
			if ( is_wp_error( $orders_validation ) ) {
				return $orders_validation;
			}

			// Delete customer
			return $this->delete_customer( $customer_id, $request );
		}

		/**
		 * Validate customer exists.
		 *
		 * @param int $customer_id Customer ID to validate.
		 * @return bool|WP_Error True if valid, WP_Error if invalid.
		 */
		protected function validate_customer_exists( $customer_id ) {
			$customer = Customer::query()->find( $customer_id );

			if ( ! $customer ) {
				return $this->error_response(
					esc_html__( 'Customer not found', 'devdiggers-multipos-for-fluentcart' ),
					'customer_not_found',
					404
				);
			}

			return true;
		}

		/**
		 * Validate customer has no orders.
		 *
		 * @param int $customer_id Customer ID to validate.
		 * @return bool|WP_Error True if valid, WP_Error if invalid.
		 */
		protected function validate_customer_orders( $customer_id ) {
			$orders = Order::query()->where( 'customer_id', $customer_id )->exists();

			if ( ! empty( $orders ) ) {
				return $this->error_response(
					esc_html__( 'Cannot delete customer with existing orders', 'devdiggers-multipos-for-fluentcart' ),
					'customer_has_orders',
					400
				);
			}

			return true;
		}

		/**
		 * Delete customer.
		 *
		 * @param int $customer_id Customer ID to delete.
		 * @param array $request Original request.
		 * @return array|WP_Error Deletion result or error.
		 */
		protected function delete_customer( $customer_id, $request ) {
			try {
				// The walk-in account takes every guest sale, so it stays.
				if ( (int) $customer_id === absint( get_option( '_ddfcpos_default_customer' ) ) ) {
					return $this->error_response(
						esc_html__( 'The walk-in customer can not be deleted.', 'devdiggers-multipos-for-fluentcart' ),
						'protected_account',
						403
					);
				}

				// Only the FluentCart customer record goes; a linked WordPress account is left alone.
				CustomerAddresses::query()->where( 'customer_id', $customer_id )->delete();
				$result = Customer::query()->where( 'id', $customer_id )->delete();

				if ( ! $result ) {
					return $this->error_response(
						esc_html__( 'Failed to delete customer', 'devdiggers-multipos-for-fluentcart' ),
						'deletion_failed',
						500
					);
				}

				return apply_filters( 'ddfcpos/api/customers/delete_response', $customer_id, $request );
			} catch ( \Exception $e ) {
				return $this->error_response(
					$e->getMessage(),
					'deletion_error',
					500
				);
			}
		}
	}
}
