<?php
/**
 * API Get Transactions class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\API\Includes\Transactions;

use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Get_Transactions' ) ) {
	/**
	 * API Get Transactions class
	 * 
	 * Handles transaction retrieval for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Get_Transactions extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name.
		 *
		 * @var string the route base
		 */
		public $base = 'get-transactions';

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
		public function __construct( $ddfcpos_configuration = [] ) {
			parent::__construct( $ddfcpos_configuration );
		}

		/**
		 * Execute the specific API logic for getting transactions.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Transaction data or error.
		 */
		protected function execute_api_logic( $request ) {
			$cashier_id = intval( $request['cashier_id'] );
			$outlet_id  = intval( $request['outlet_id'] );
			$order_id   = ! empty( $request['order_id'] ) ? intval( $request['order_id'] ) : 0;
			$date       = ! empty( $request['date'] ) ? sanitize_text_field( $request['date'] ) : '';

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

			if ( ! empty( $order_id ) ) {
				return $this->get_transactions_for_order( $order_id, $request );
			}

			// Validate date format
			$date_validation = $this->validate_date_format( $date );
			if ( is_wp_error( $date_validation ) ) {
				return $date_validation;
			}

			// Get transactions for the specified date
			return $this->get_transactions_for_date( $outlet_id, $cashier_id, $date, $request );
		}

		/**
		 * Validate date format.
		 *
		 * @param string $date Date string to validate.
		 * @return bool|WP_Error True if valid, WP_Error if invalid.
		 */
		protected function validate_date_format( $date ) {
			if ( empty( $date ) ) {
				return $this->error_response(
					esc_html__( 'Date is required', 'multipos-for-fluentcart' ),
					'missing_date',
					400
				);
			}

			// Validate date format (Y-m-d)
			$date_obj = \DateTime::createFromFormat( 'Y-m-d', $date );
			if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date ) {
				return $this->error_response(
					esc_html__( 'Invalid date format. Expected Y-m-d format', 'multipos-for-fluentcart' ),
					'invalid_date_format',
					400
				);
			}

			return true;
		}

		/**
		 * Get transactions for a specific order.
		 *
		 * @param int $order_id Order ID.
		 * @param array $request Original request data.
		 * @return array Transactions data.
		 */
		protected function get_transactions_for_order( $order_id, $request ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'ddfcpos_transactions';

			// Prepare the query with proper escaping
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin ledger table.
			$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE order_id = %d ORDER BY id DESC", $order_id ), ARRAY_A );

			return $this->prepare_transaction_results( $result );
		}

		/**
		 * Get transactions for a specific date.
		 *
		 * @param int $outlet_id Outlet ID.
		 * @param int $cashier_id Cashier ID.
		 * @param string $date Date in Y-m-d format.
		 * @param array $request Original request data.
		 * @return array Transactions data.
		 */
		protected function get_transactions_for_date( $outlet_id, $cashier_id, $date, $request ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'ddfcpos_transactions';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin ledger table.
			$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE outlet_id = %d AND cashier_id = %d AND DATE(date) = %s ORDER BY id DESC", $outlet_id, $cashier_id, $date ), ARRAY_A );

			return $this->prepare_transaction_results( $result );
		}

		/**
		 * Prepare transaction results by adding cashier name.
		 *
		 * @param array $result Transaction rows.
		 * @return array Processed results.
		 */
		protected function prepare_transaction_results( $result ) {
			if ( ! empty( $result ) ) {
				global $wpdb;

				// The invoice number the POS and receipts show ( INV-7 ), not FluentCart's internal ID.
				$ids     = array_filter( array_map( 'absint', wp_list_pluck( $result, 'order_id' ) ) );
				$numbers = [];

				if ( $ids ) {
					$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- FluentCart table; one %d placeholder per ID.
					$numbers = $wpdb->get_results( $wpdb->prepare( "SELECT id, invoice_no FROM {$wpdb->prefix}fct_orders WHERE id IN ($placeholders)", $ids ), OBJECT_K );
				}

				foreach ( $result as $key => $value ) {
					$user     = get_userdata( $value[ 'cashier_id' ] );
					$order_id = absint( $value['order_id'] );

					$result[ $key ][ 'cashier_name' ] = $user ? $user->display_name : '-';
					$result[ $key ][ 'order_number' ] = $order_id ? ( ! empty( $numbers[ $order_id ]->invoice_no ) ? $numbers[ $order_id ]->invoice_no : (string) $order_id ) : '';
				}
			}

			// Return empty array if no results, otherwise return the data
			return ! empty( $result ) ? $result : [];
		}
	}
}
