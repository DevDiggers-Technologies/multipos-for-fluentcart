<?php
/**
 * Activity Helper
 *
 * Small at-a-glance numbers for the Outlet and Cashiers screens: recent sales and the last
 * sale. One grouped query per number, so a list of twenty rows costs the same as a list of one.
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Helper\Report;

use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Activity_Helper' ) ) {
	/**
	 * Activity Helper class
	 */
	class DDFCPOS_Activity_Helper {
		/**
		 * Sales, orders and the last sale per outlet or per cashier since a date.
		 *
		 * @param string $column    'outlet_id' or 'cashier_id'.
		 * @param string $since     MySQL datetime.
		 * @param bool   $last_sale Also look up the last sale ever (an unbounded query).
		 * @return array [ id => [ sales, orders, last_sale ] ]
		 */
		public function ddfcpos_get_sales_by( $column, $since, $last_sale = true ) {
			global $wpdb;

			$column       = 'cashier_id' === $column ? 'cashier_id' : 'outlet_id';
			$transactions = $wpdb->prefix . 'ddfcpos_transactions';
			$non_tender   = [ 'opencash', 'manual', 'refund', 'refund_noncash' ]; // Ledger rows that are not sales.
			$placeholders = implode( ',', array_fill( 0, count( $non_tender ), '%s' ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Plugin table; $column is whitelisted above.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$column} AS id,
						SUM(amount_in - amount_out) AS sales,
						COUNT(DISTINCT order_id) AS orders
					FROM {$transactions}
					WHERE date >= %s AND method NOT IN ({$placeholders})
					GROUP BY {$column}",
					array_merge( [ $since ], $non_tender )
				),
				ARRAY_A
			);

			// Last sale ever, not only inside the window: "last active" must not go blank after 30 quiet days.
			$last = ! $last_sale ? [] : $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$column} AS id, MAX(date) AS last_sale FROM {$transactions} WHERE method NOT IN ({$placeholders}) GROUP BY {$column}",
					$non_tender
				),
				OBJECT_K
			);
			// phpcs:enable

			$data = [];

			foreach ( (array) $rows as $row ) {
				$data[ (int) $row['id'] ] = [
					'sales'  => round( (float) $row['sales'], DDFCPOS_FluentCart_Helper::decimals() ),
					'orders' => (int) $row['orders'],
				];
			}

			foreach ( (array) $last as $id => $row ) {
				$data[ (int) $id ]              = isset( $data[ (int) $id ] ) ? $data[ (int) $id ] : [ 'sales' => 0, 'orders' => 0 ];
				$data[ (int) $id ]['last_sale'] = $row->last_sale;
			}

			return $data;
		}
	}
}
