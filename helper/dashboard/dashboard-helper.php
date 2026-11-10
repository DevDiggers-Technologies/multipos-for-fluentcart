<?php
/**
 * Dashboard Helper
 *
 * Data for the "today" overview, worked out from the POS orders of today and yesterday.
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Helper\Dashboard;

use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Order;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Dashboard_Helper' ) ) {
	/**
	 * Dashboard Helper Class
	 */
	class DDFCPOS_Dashboard_Helper {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddfcpos_configuration;

		/**
		 * Construct
		 *
		 * @param array $ddfcpos_configuration Plugin configuration.
		 */
		public function __construct( $ddfcpos_configuration ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;
		}

		/**
		 * Today's figures, yesterday's for comparison, and the latest orders.
		 *
		 * @return array
		 */
		public function get_dashboard_data() {
			$today     = current_time( 'Y-m-d' );
			$yesterday = gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) );

			$report = $this->ddfcpos_get_report( $today );
			$before = $this->ddfcpos_get_report( $yesterday );

			return [
				'today'         => $today,
				'totals'        => $report['totals'],
				'yesterday'     => $before['totals'],
				'hours'         => $this->ddfcpos_get_hours( $report['hours'], $before['hours'] ),
				'products'      => array_slice( $report['products'], 0, 5 ),
				'payments'      => $report['payment_methods'],
				'cashiers'      => $report['cashiers'],
				'recent_orders' => $this->get_recent_orders(),
				'cashier_count' => count( get_users( [ 'role' => 'ddfcpos_cashier', 'fields' => 'ID' ] ) ),
			];
		}

		/**
		 * One day of POS sales. Net sales are what was paid less what was refunded.
		 *
		 * @param string $day Y-m-d, store time.
		 * @return array { totals, hours, products, payment_methods, cashiers }
		 */
		protected function ddfcpos_get_report( $day ) {
			// ponytail: loads the day's orders one by one; fine for a counter, switch to grouped SQL if a store rings up thousands a day.
			$order_ids = DDFCPOS_FluentCart_Helper::query_pos_orders(
				[
					'after'  => $day . ' 00:00:00',
					'before' => $day . ' 23:59:59',
				]
			);

			$totals   = [
				'net_sales'       => 0,
				'orders'          => 0,
				'avg_order_value' => 0,
				'items_sold'      => 0,
				'refunds'         => 0,
			];
			$hours    = [];
			$products = [];
			$payments = [];
			$cashiers = [];

			foreach ( $order_ids as $order_id ) {
				$order = DDFCPOS_Order::get( $order_id );

				if ( ! $order || in_array( $order->get_status(), [ 'canceled', 'failed' ], true ) ) {
					continue;
				}

				$refunded = $order->get_total_refunded();
				$net      = $order->get_total() - $refunded;
				$hour     = (int) $order->get_date_created()->format( 'G' );

				$totals['net_sales']  += $net;
				$totals['refunds']    += $refunded;
				$totals['items_sold'] += $order->get_item_count();
				++$totals['orders'];

				$hours[ $hour ]['net_sales'] = ( $hours[ $hour ]['net_sales'] ?? 0 ) + $net;

				$method              = $order->get_payment_method();
				$payments[ $method ] = [
					'name'   => $order->get_payment_method_title(),
					'orders' => ( $payments[ $method ]['orders'] ?? 0 ) + 1,
					'amount' => ( $payments[ $method ]['amount'] ?? 0 ) + $net,
				];

				$cashier_id              = (int) $order->get_meta( '_ddfcpos_cashier_id' );
				$cashier                 = $cashier_id ? get_userdata( $cashier_id ) : false;
				$cashiers[ $cashier_id ] = [
					'id'        => $cashier_id,
					'name'      => $cashier ? $cashier->display_name : __( 'Unknown', 'multipos-for-fluentcart' ),
					'orders'    => ( $cashiers[ $cashier_id ]['orders'] ?? 0 ) + 1,
					'net_sales' => ( $cashiers[ $cashier_id ]['net_sales'] ?? 0 ) + $net,
				];

				foreach ( $order->get_items() as $item ) {
					$post_id              = (int) $item->post_id;
					$products[ $post_id ] = [
						'name'       => $item->post_title ? $item->post_title : $item->title,
						'url'        => $post_id ? DDFCPOS_FluentCart_Helper::product_admin_url( $post_id ) : '',
						'image'      => $post_id ? get_the_post_thumbnail_url( $post_id, 'thumbnail' ) : '',
						'items_sold' => ( $products[ $post_id ]['items_sold'] ?? 0 ) + (int) $item->quantity,
						'net_sales'  => ( $products[ $post_id ]['net_sales'] ?? 0 ) + DDFCPOS_FluentCart_Helper::from_cents( $item->line_total ),
					];
				}
			}

			$totals['avg_order_value'] = $totals['orders'] ? $totals['net_sales'] / $totals['orders'] : 0;

			$by_sales = function ( $first, $second ) {
				return ( $second['net_sales'] ?? $second['amount'] ) <=> ( $first['net_sales'] ?? $first['amount'] );
			};

			usort( $products, $by_sales );
			usort( $payments, $by_sales );
			usort( $cashiers, $by_sales );

			return [
				'totals'          => $totals,
				'hours'           => $hours,
				'products'        => $products,
				'payment_methods' => $payments,
				'cashiers'        => $cashiers,
			];
		}

		/**
		 * Net sales per hour, today against yesterday. Hours still to come today are null, so the
		 * line stops at now instead of dropping to zero.
		 *
		 * @param array $today     Today's hour rows.
		 * @param array $yesterday Yesterday's hour rows.
		 * @return array
		 */
		protected function ddfcpos_get_hours( $today, $yesterday ) {
			$now  = (int) current_time( 'G' );
			$rows = [];

			for ( $hour = 0; $hour < 24; $hour++ ) {
				$rows[] = [
					'hour'      => sprintf( '%02d:00', $hour ),
					'today'     => $hour > $now ? null : (float) ( $today[ $hour ]['net_sales'] ?? 0 ),
					'yesterday' => (float) ( $yesterday[ $hour ]['net_sales'] ?? 0 ),
				];
			}

			return $rows;
		}

		/**
		 * Get recent POS orders
		 *
		 * @param int $limit Orders.
		 * @return array
		 */
		protected function get_recent_orders( $limit = 5 ) {
			$orders = array_filter( array_map( [ DDFCPOS_Order::class, 'get' ], DDFCPOS_FluentCart_Helper::query_pos_orders( [ 'limit' => $limit ] ) ) );
			$data   = [];

			foreach ( $orders as $order ) {
				$data[] = [
					'id'       => $order->get_id(),
					'number'   => $order->get_order_number(),
					'total'    => $order->get_formatted_order_total(),
					'status'   => DDFCPOS_FluentCart_Helper::order_status_label( $order->get_status() ),
					'state'    => $order->get_status(),
					'date'     => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order->get_date_created()->getTimestamp() ),
					'customer' => $order->get_formatted_billing_full_name() ?: __( 'Guest', 'multipos-for-fluentcart' ),
				];
			}

			return $data;
		}
	}
}
