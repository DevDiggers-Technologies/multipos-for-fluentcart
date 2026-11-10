<?php
/**
 * API Get Customers class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\API\Includes\Customers;

use DDFCMultiPOS\Helper\Customer\DDFCPOS_Customer_Helper;
use DDFCMultiPOS\Helper\Permission\DDFCPOS_Permission_Helper;
use FluentCart\App\Models\Customer;
use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Get_Customers' ) ) {
	/**
	 * API Get Customers class
	 * 
	 * Handles customer retrieval for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Get_Customers extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name.
		 *
		 * @var string the route base
		 */
		public $base = 'get-customers';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [ 'cashier_id', 'outlet_id' ];

		/**
		 * Constructor.
		 * 
		 * @param array $ddfcpos_configuration Configuration array.
		 */
		public function __construct( $ddfcpos_configuration ) {
			parent::__construct( $ddfcpos_configuration );
		}

		/**
		 * Execute the specific API logic for getting customers.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Customer data or error.
		 */
		protected function execute_api_logic( $request ) {
			$cashier_id   = intval( $request['cashier_id'] );
			$outlet_id    = intval( $request['outlet_id'] );
			$per_page     = isset( $request['per_page'] ) ? intval( $request['per_page'] ) : 10;
			$current_page = isset( $request['current_page'] ) ? intval( $request['current_page'] ) : 1;

			// Validate user permissions
			$user_validation = $this->validate_user_permissions( $cashier_id );
			if ( is_wp_error( $user_validation ) ) {
				return $user_validation;
			}

			// Validate outlet access
			$outlet_validation = $this->validate_outlet_access( $outlet_id, $cashier_id );
			if ( is_wp_error( $outlet_validation ) ) {
				return $outlet_validation;
			}

			// Handle count request
			if ( -1 === $per_page ) {
				return $this->get_customers_count( $request );
			}

			// Handle customers list request
			return $this->get_customers_list( $request );
		}

		/**
		 * Customers query, newest first.
		 *
		 * @param array $request Request data.
		 * @return \FluentCart\Framework\Database\Orm\Builder
		 */
		protected function ddfcpos_customers_query( $request ) {
			$query = Customer::query()->where( 'status', 'active' );

			return apply_filters( 'ddfcpos/api/customers/query', $query, $request );
		}

		/**
		 * Get total customers count.
		 *
		 * @param array $request Request data.
		 * @return int Total customers count.
		 */
		protected function get_customers_count( $request ) {
			DDFCPOS_Customer_Helper::ddfcpos_get_walkin_customer();

			return apply_filters( 'ddfcpos/api/customers/total', $this->ddfcpos_customers_query( $request )->count(), $request );
		}

		/**
		 * Get customers list with pagination.
		 *
		 * @param array $request Request data.
		 * @return array Customers list.
		 */
		protected function get_customers_list( $request ) {
			$per_page     = max( 1, intval( $request['per_page'] ) );
			$current_page = max( 1, intval( $request['current_page'] ) );
			$customers    = [];
			$default_id   = absint( $this->get_config( 'default_customer' ) );

			$results = $this->ddfcpos_customers_query( $request )
				->orderBy( 'id', 'DESC' )
				->offset( ( $current_page - 1 ) * $per_page )
				->limit( $per_page )
				->get();

			foreach ( $results as $customer ) {
				// Store staff never show up as customers at the till.
				if ( ! DDFCPOS_Permission_Helper::ddfcpos_can_manage_customer_account( $customer->id ) && (int) $customer->id !== $default_id ) {
					continue;
				}

				$customer_data = DDFCPOS_Customer_Helper::ddfcpos_prepare_pos_customer( $customer, $default_id );

				if ( $customer_data ) {
					$customers[] = $customer_data;
				}
			}

			return apply_filters( 'ddfcpos/api/customers/response', $customers, $request );
		}

		/**
		 * Prepare customer data for API response.
		 *
		 * @param int $customer_id FluentCart customer ID.
		 * @return array|null Customer data or null if invalid.
		 */
		public function prepare_customer_data( $customer_id ) {
			return DDFCPOS_Customer_Helper::ddfcpos_prepare_pos_customer( $customer_id, absint( $this->get_config( 'default_customer' ) ) );
		}
	}
}
