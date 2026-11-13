<?php
/**
 * API Manage Customer class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Api\Includes\Customers;

use DDFCMultiPOS\Helper\Customer\DDFCPOS_Customer_Helper;
use FluentCart\App\Models\Customer;

use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;
use DDFCMultiPOS\Helper\Permission\DDFCPOS_Permission_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Manage_Customer' ) ) {
	/**
	 * API Manage Customer class
	 * 
	 * Handles customer creation and updates for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Manage_Customer extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name
		 *
		 * @var string $base the route base
		 */
		public $base = 'manage-customer';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [ 'cashier_id', 'customer_data' ];

		/**
		 * Execute the specific API logic for managing customers.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Customer data or error.
		 */
		protected function execute_api_logic( $request ) {
			$cashier_id = intval( $request['cashier_id'] );
			$customer_data = is_array( $request['customer_data'] ) ? $this->sanitize_value( $request['customer_data'] ) : [];

			// Validate user permissions
			$user_validation = $this->validate_user_permissions( $cashier_id );
			if ( is_wp_error( $user_validation ) ) {
				return $user_validation;
			}

			// Validate customer data
			$data_validation = $this->validate_customer_data( $customer_data, $request );
			if ( is_wp_error( $data_validation ) ) {
				return $data_validation;
			}

			// Handle customer creation or update
			if ( ! empty( $customer_data['id'] ) ) {
				if ( ! DDFCPOS_Permission_Helper::ddfcpos_can_manage_customer_account( intval( $customer_data['id'] ) ) ) {
					return $this->error_response(
						esc_html__( 'Store staff accounts cannot be edited from the POS.', 'devdiggers-multipos-for-fluentcart' ),
						'protected_account',
						403
					);
				}


				return $this->update_customer( $customer_data, $request );
			} else {
				return $this->create_customer( $customer_data, $request );
			}
		}

		/**
		 * Validate customer data.
		 *
		 * @param array $customer_data Customer data to validate.
		 * @param array $request       Original request.
		 * @return bool|WP_Error True if valid, WP_Error if invalid.
		 */
		protected function validate_customer_data( $customer_data, $request = [] ) {
			if ( apply_filters( 'ddfcpos/customer/validate', false, $customer_data, $request ) ) {
				return $this->error_response(
					apply_filters(
						'ddfcpos/customer/validation_message',
						esc_html__( 'Manage customer custom check error message.', 'devdiggers-multipos-for-fluentcart' ),
						$customer_data,
						$request
					),
					'manage_customer_custom_check_failed',
					400
				);
			}

			// Validate required fields
			if ( empty( $customer_data['email'] ) ) {
				return $this->error_response(
					esc_html__( 'Email is required', 'devdiggers-multipos-for-fluentcart' ),
					'missing_email',
					400
				);
			}

			// Validate email format
			if ( ! is_email( $customer_data['email'] ) ) {
				return $this->error_response(
					esc_html__( 'Invalid email format', 'devdiggers-multipos-for-fluentcart' ),
					'invalid_email',
					400
				);
			}

			// Check if email already exists (for new customers)
			$existing = Customer::query()->where( 'email', sanitize_email( $customer_data['email'] ) )->first();

			if ( $existing && ( empty( $customer_data['id'] ) || (int) $existing->id !== intval( $customer_data['id'] ) ) ) {
				return $this->error_response(
					esc_html__( 'Email already exists', 'devdiggers-multipos-for-fluentcart' ),
					'email_exists',
					400
				);
			}

			return true;
		}

		/**
		 * Update existing customer.
		 *
		 * @param array $customer_data Customer data.
		 * @param array $request Original request.
		 * @return array|WP_Error Updated customer data or error.
		 */
		protected function update_customer( $customer_data, $request ) {
			try {
				$customer = Customer::query()->find( intval( $customer_data['id'] ) );

				if ( ! $customer ) {
					return $this->error_response(
						esc_html__( 'Customer not found', 'devdiggers-multipos-for-fluentcart' ),
						'customer_not_found',
						404
					);
				}

				DDFCPOS_Customer_Helper::ddfcpos_save_details( $customer, $customer_data );

				// Keep a linked WordPress account's name in step, never its login email (that is the account owner's call).
				if ( $customer->user_id && get_userdata( $customer->user_id ) ) {
					wp_update_user(
						[
							'ID'         => $customer->user_id,
							'first_name' => $customer->first_name,
							'last_name'  => $customer->last_name,
						]
					);
				}

				do_action( 'ddfcpos/customer/saved', $customer->id, $customer_data, $request );

				return $this->success_response(
					$this->get_customer_data( $customer->id ),
					esc_html__( 'Customer updated successfully', 'devdiggers-multipos-for-fluentcart' )
				);
			} catch ( \Exception $e ) {
				return $this->error_response(
					$e->getMessage(),
					'update_error',
					500
				);
			}
		}

		/**
		 * Create new customer.
		 *
		 * A FluentCart customer record; a WordPress account is linked when one already uses the email.
		 *
		 * @param array $customer_data Customer data.
		 * @param array $request Original request.
		 * @return array|WP_Error Created customer data or error.
		 */
		protected function create_customer( $customer_data, $request ) {
			try {
				$user = get_user_by( 'email', sanitize_email( $customer_data['email'] ) );

				// Never hang till sales on a store staff account that happens to share the email.
				if ( $user && ( user_can( $user, 'edit_posts' ) || user_can( $user, 'ddfcpos_manage' ) || in_array( 'ddfcpos_cashier', (array) $user->roles, true ) ) ) {
					$user = false;
				}

				$customer = Customer::query()->create(
					[
						'email'   => sanitize_email( $customer_data['email'] ),
						'user_id' => $user ? $user->ID : null,
						'status'  => 'active',
					]
				);

				if ( ! $customer ) {
					return $this->error_response(
						esc_html__( 'The customer could not be created.', 'devdiggers-multipos-for-fluentcart' ),
						'user_creation_failed',
						500
					);
				}

				DDFCPOS_Customer_Helper::ddfcpos_save_details( $customer, $customer_data );

				do_action( 'ddfcpos/customer/saved', $customer->id, $customer_data, $request );

				return $this->success_response(
					$this->get_customer_data( $customer->id ),
					esc_html__( 'Customer created successfully', 'devdiggers-multipos-for-fluentcart' )
				);
			} catch ( \Exception $e ) {
				return $this->error_response(
					$e->getMessage(),
					'creation_error',
					500
				);
			}
		}

		/**
		 * Get customer data using the get-customers API.
		 * 
		 * Reuses the customer data preparation logic from api-get-customers.php
		 * to ensure consistency and avoid code duplication.
		 *
		 * @param int $customer_id Customer ID.
		 * @return array|null Customer data or null if invalid.
		 */
		protected function get_customer_data( $customer_id ) {
			$get_customers_api = new DDFCPOS_API_Get_Customers( $this->ddfcpos_configuration );
			return $get_customers_api->prepare_customer_data( $customer_id );
		}
	}
}
