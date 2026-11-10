<?php
/**
 * API Get Orders class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\API\Includes\Orders;

use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Order;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Product;
use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Get_Orders' ) ) {
	/**
	 * API Get Orders Class.
	 * 
	 * Handles order retrieval for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Get_Orders extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name.
		 *
		 * @var string the route base
		 */
		public $base = 'get-orders';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [ 'cashier_id', 'outlet_id' ];

		/**
		 * Execute the specific API logic for getting orders.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Order data or error.
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
				return $this->get_orders_count( $request, $outlet_id );
			}

			// Handle orders list request
			return $this->get_orders_list( $request, $outlet_id, $per_page, $current_page );
		}

		/**
		 * Order query arguments for an outlet.
		 *
		 * @param int $outlet_id Outlet ID.
		 * @return array
		 */
		protected function ddfcpos_get_orders_query_args( $outlet_id ) {
			return [ 'outlet_id' => $outlet_id ];
		}

		/**
		 * Get total orders count.
		 *
		 * @param array $request Request data.
		 * @param int $outlet_id Outlet ID.
		 * @return int Total orders count.
		 */
		protected function get_orders_count( $request, $outlet_id ) {
			$args = apply_filters( 'ddfcpos/api/orders/count_query', array_merge( $this->ddfcpos_get_orders_query_args( $outlet_id ), [ 'count' => true ] ), $request );

			return apply_filters( 'ddfcpos/api/orders/total', DDFCPOS_FluentCart_Helper::query_pos_orders( $args ), $request );
		}

		/**
		 * Get orders list with pagination.
		 *
		 * @param array $request Request data.
		 * @param int $outlet_id Outlet ID.
		 * @param int $per_page Items per page.
		 * @param int $current_page Current page.
		 * @return array Orders data.
		 */
		protected function get_orders_list( $request, $outlet_id, $per_page, $current_page ) {
			$data = [];
			$args = array_merge(
				$this->ddfcpos_get_orders_query_args( $outlet_id ),
				[
					'limit'  => $per_page,
					'offset' => ( max( 1, $current_page ) - 1 ) * $per_page,
				]
			);

			$args      = apply_filters( 'ddfcpos/api/orders/query', $args, $request );
			$order_ids = DDFCPOS_FluentCart_Helper::query_pos_orders( $args );

			foreach ( $order_ids as $order_id ) {
				$order          = DDFCPOS_Order::get( $order_id );
				$order_response = $this->ddfcpos_prepare_order_data( $order );

				if ( $order_response ) {
					$data[] = apply_filters( 'ddfcpos/api/order/data', $order_response, $order, $request );
				}
			}

			return apply_filters( 'ddfcpos/api/orders/response', $data, $request );
		}

		/**
		 * Address of an order in the shape the POS receipt reads.
		 *
		 * @param DDFCPOS_Order $order Order.
		 * @param string        $type  billing or shipping.
		 * @return array
		 */
		protected function ddfcpos_get_order_address( $order, $type ) {
			$address = $order->get_address( $type );

			$data = [
				'first_name' => $address['first_name'],
				'last_name'  => $address['last_name'],
				'address_1'  => $address['address_1'],
				'address_2'  => $address['address_2'],
				'city'       => $address['city'],
				'state'      => DDFCPOS_FluentCart_Helper::state_name( $address['country'], $address['state'] ),
				'country'    => $address['country'] ? DDFCPOS_FluentCart_Helper::country_name( $address['country'] ) : '',
				'postcode'   => $address['postcode'],
			];

			if ( 'billing' === $type ) {
				$data['phone'] = $address['phone'];
			}

			return $data;
		}

		/**
		 * Prepare order data function
		 *
		 * @param int|DDFCPOS_Order $order_id Order or ID.
		 * @return array
		 */
		public function ddfcpos_prepare_order_data( $order_id ) {
			$order = DDFCPOS_Order::get( $order_id );

			if ( ! $order ) {
				return [];
			}

			$order_id        = $order->get_id();
			$order_response  = [];
			$tendered        = floatval( $order->get_meta( '_ddfcpos_tendered_amount' ) );
			$table           = $order->get_meta( '_ddfcpos_table' );
			$payment_methods = $order->get_meta( '_ddfcpos_payment_methods' );
			$currency        = $order->get_currency();

			$order_response[ 'order_status' ]          = $order->get_status();
			$order_response[ 'order_status_label' ]    = DDFCPOS_FluentCart_Helper::order_status_label( $order_response[ 'order_status' ] );
			$order_response[ 'payment_status' ]        = $order->get_payment_status();
			$order_response[ 'order_currency' ]        = $currency;
			$order_response[ 'order_currency_symbol' ] = DDFCPOS_FluentCart_Helper::currency_symbol( $currency );
			$order_response[ 'id' ]                    = $order_id;
			$order_response[ 'order_id' ]              = $order->get_order_number();
			$order_response[ 'customer_id' ]           = $order->get_fc_customer_id();

			$custom_index = 0;

			$order_response[ 'products' ] = [];

			foreach ( $order->get_items() as $item_id => $item ) {
				$quantity = max( 1, (int) $item->quantity );
				$meta     = apply_filters( 'ddfcpos/api/order/line_meta', [], $item->toArray() );
				$product  = $item->object_id ? DDFCPOS_Product::from_variation_id( $item->object_id ) : false;

				if ( $product ) {
					$product_id = $product->get_id();
					$type       = $product->get_type();
					$image      = $product->get_image( 'thumbnail' );
				} else {
					$product_id = 'custom_' . $custom_index++;
					$type       = 'custom';
					$image      = null;
				}

				$name = $item->post_title && $item->title && $item->title !== $item->post_title && 0 !== stripos( $item->title, $item->post_title ) ? $item->post_title . ' - ' . $item->title : ( $item->title ? $item->title : $item->post_title );

				// What the customer paid for the line before cart level discounts, as the POS cart showed it.
				$line_total = apply_filters( 'ddfcpos/api/order/line_total', DDFCPOS_FluentCart_Helper::from_cents( $item->subtotal ), $item );
				$unit_tax   = $quantity ? DDFCPOS_FluentCart_Helper::from_cents( $item->tax_amount ) / $quantity : 0;

				$order_response[ 'products' ][] = [
					'item_id'           => (int) $item_id,
					'id'                => $product_id,
					'parent_id'         => $product ? $product->get_parent_id() : 0,
					'product_id'        => $product_id,
					'type'              => $type,
					'name'              => $name,
					'quantity'          => $quantity,
					'image'             => $image,
					'unit_price'                => $line_total / $quantity,
					'line_total'          => $line_total,
					'meta_data'         => $meta,
					'taxes'             => $unit_tax ? [ 'tax' => round( $unit_tax, 4 ) ] : [],
					'returned_quantity' => $order->get_qty_refunded_for_item( $item_id ),
				];
			}

			$order_response[ 'tax_lines' ] = $order->get_tax_lines();
			$order_response[ 'billing' ]   = $this->ddfcpos_get_order_address( $order, 'billing' );
			$order_response[ 'shipping' ]  = $this->ddfcpos_get_order_address( $order, 'shipping' );

			$order_date  = $order->get_date_created();
			$order_total = floatval( $order->get_total() );

			$order_response[ 'order_created' ]        = $order_date->format( 'Y-m-d H:i:s' );
			$order_response[ 'order_notes' ]          = $order->get_order_notes();
			$order_response[ 'tendered' ]             = $tendered;
			$order_response[ 'table' ]                = $table;
			$order_response[ 'change' ]               = ( $tendered - $order_total ) > 0 ? round( $tendered - $order_total, 4 ) : 0;
			$order_response[ 'email' ]                = $order->get_billing_email();
			$order_response[ 'order_date' ]           = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order_date->getTimestamp() );
			$order_response[ 'payment_method' ]       = $order->get_payment_method();
			$order_response[ 'payment_method_title' ] = $order->get_payment_method_title();
			$order_response[ 'payment_methods' ]      = ! empty( $payment_methods ) && is_array( $payment_methods ) ? $payment_methods : [];

			$fees = [];

			foreach ( $order->get_fees() as $fee ) {
				$fees[] = [
					'name'   => $fee->title,
					'amount' => DDFCPOS_FluentCart_Helper::from_cents( $fee->subtotal ),
				];
			}

			$order_response[ 'coupons' ]        = $order->get_coupons();
			$order_response[ 'fees' ]           = $fees;
			$order_response[ 'order_subtotal' ] = $order->get_subtotal();
			// The cashier's cart discount, negative as the receipt prints it.
			$order_response[ 'discount' ]       = $order->get_manual_discount() ? -1 * $order->get_manual_discount() : 0;
			$order_response[ 'order_type' ]     = 'online';
			$order_response[ 'order_total' ]    = $order_total;
			$order_response[ 'order_refunded' ] = $order->get_total_refunded();
			$order_response[ 'fc_order_url' ]   = DDFCPOS_FluentCart_Helper::order_admin_url( $order_id );

			return apply_filters( 'ddfcpos/api/order/response', $order_response, $order );
		}
	}
}
