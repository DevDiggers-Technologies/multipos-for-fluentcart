<?php
/**
 * Transaction Helper class
 *
 * @package MultiPOS for FluentCart
 */

namespace DDFCMultiPOS\Helper\Transaction;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Transaction_Helper' ) ) {
	/**
	 * Transaction Helper class
	 */
	class DDFCPOS_Transaction_Helper {
		/**
		 * Database Object
		 *
		 * @var object
		 */
		protected $wpdb;

		/**
		 * Transaction table variable
		 *
		 * @var string
		 */
		protected $transactions_table;

		/**
		 * Users table Variable
		 *
		 * @var string
		 */
		protected $users_table;

		/**
		 * Construct
		 */
		public function __construct() {
			global $wpdb;
			$this->wpdb = $wpdb;

			$this->transactions_table = $this->wpdb->prefix . 'ddfcpos_transactions';
			$this->users_table        = $this->wpdb->users;
		}

		/**
		 * Save transaction to DB function
		 *
		 * @param array $data
		 * @return int
		 */
		public function ddfcpos_save_transaction( $data ) {
			$default_data = [
				'cashier_id' => 0,
				'outlet_id'  => 0,
				'order_id'   => NULL,
				'amount_in'  => 0,
				'amount_out' => 0,
				'method'     => '',
				'reference'  => '',
				'date'       => current_time( 'Y-m-d H:i:s' ),
			];

			$data = wp_parse_args( $data, $default_data );
			$this->wpdb->insert(
				$this->transactions_table,
				$data,
				[ '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
			);

			do_action( 'ddfcpos/transaction/saved', $this->wpdb->insert_id, $data );

			return $this->wpdb->insert_id;
		}

		/**
		 * SQL conditions for the list filters. The filter values are read only when the list
		 * form's nonce verifies; search is passed in by the list table.
		 *
		 * @param string $search Search term.
		 * @return string
		 */
		protected function ddfcpos_get_list_conditions( $search ) {
			$conditions     = '';
			$nonce_verified = isset( $_GET['ddfcpos_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['ddfcpos_nonce'] ) ), 'ddfcpos_nonce_action' );

			if ( $nonce_verified && ( ! empty( $_GET['transaction-from-date'] ) || ! empty( $_GET['transaction-to-date'] ) ) ) {
				$from_date   = ! empty( $_GET['transaction-from-date'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction-from-date'] ) ) : current_time( 'Y-m-d' );
				$end_date    = ! empty( $_GET['transaction-to-date'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction-to-date'] ) ) : current_time( 'Y-m-d' );
				$conditions .= $this->wpdb->prepare( ' AND DATE(transactions.date) BETWEEN %s AND %s', $from_date, $end_date );
			}

			if ( $nonce_verified && ! empty( $_GET['cashier-id'] ) ) {
				$conditions .= $this->wpdb->prepare( ' AND transactions.cashier_id=%d', absint( wp_unslash( $_GET['cashier-id'] ) ) );
			}

			if ( ! empty( $search ) ) {
				$conditions .= $this->wpdb->prepare( ' AND transactions.id LIKE %s', '%' . $this->wpdb->esc_like( $search ) . '%' );
			}

			return $conditions;
		}

		/**
		 * Get all transactions function
		 *
		 * @param int    $per_page Rows per page.
		 * @param int    $offset   Offset.
		 * @param string $search   Search term.
		 * @return array
		 */
		public function ddfcpos_get_all_transactions( $per_page, $offset, $search ) {
			$conditions = $this->ddfcpos_get_list_conditions( $search );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin table; filter values are prepared in $conditions.
			$data = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT DISTINCT transactions.* FROM $this->transactions_table as transactions LEFT JOIN $this->users_table as users ON transactions.cashier_id=users.ID WHERE 1=1 $conditions ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );

			return apply_filters( 'ddfcpos/transaction/list', $data, $per_page, $offset, $search );
		}

		/**
		 * Get all transactions count function
		 *
		 * @param string $search Search term.
		 * @return int
		 */
		public function ddfcpos_get_all_transactions_count( $search ) {
			$conditions = $this->ddfcpos_get_list_conditions( $search );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin table; filter values are prepared in $conditions.
			$data = $this->wpdb->get_var( "SELECT count(DISTINCT transactions.id) FROM $this->transactions_table as transactions LEFT JOIN $this->users_table as users ON transactions.cashier_id=users.ID WHERE 1=1 $conditions" );

			return (int) apply_filters( 'ddfcpos/transaction/count', $data, $search );
		}

		/**
		 * Delete transaction function
		 *
		 * @param int $id Transaction ID.
		 * @return int|bool
		 */
		public function ddfcpos_delete_transaction( $id ) {
			return $this->wpdb->delete(
				$this->transactions_table,
				[
					'id' => $id,
				],
				[ '%d' ]
			);
		}
	}
}
