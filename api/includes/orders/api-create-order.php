<?php
/**
 * API Create Order class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\API\Includes\Orders;

use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;
use DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper;
use DDFCMultiPOS\Helper\Transaction\DDFCPOS_Transaction_Helper;
use DDFCMultiPOS\Helper\Coupon\DDFCPOS_Coupon_Helper;
use DDFCMultiPOS\Helper\Customer\DDFCPOS_Customer_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Order;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Product;
use FluentCart\App\Events\Order\OrderCreated;
use FluentCart\App\Events\Order\OrderStatusUpdated;
use FluentCart\App\Helpers\Status;
use FluentCart\App\Helpers\StatusHelper;
use FluentCart\App\Models\AppliedCoupon;
use FluentCart\App\Models\Coupon;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderAddress;
use FluentCart\App\Models\OrderItem;
use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Modules\Tax\TaxModule;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Create_Order' ) ) {
	/**
	 * API Create Order Class.
	 * 
	 * Handles order creation for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Create_Order extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name.
		 *
		 * @var string the route base
		 */
		public $base = 'create-order';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [ 'cashier_id', 'outlet_id' ];

		/**
		 * Coupons refused while building the current order, for its notes.
		 *
		 * @var string[]
		 */
		protected $coupon_notes = [];

		/**
		 * Coupon uses claimed for the order being built, given back if it is never created.
		 *
		 * @var int[]
		 */
		protected $claimed_coupons = [];

		/**
		 * ID of the order being built, 0 until the FluentCart order row exists.
		 *
		 * @var int
		 */
		protected $current_order_id = 0;

		/**
		 * Execute the specific API logic for creating orders.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Order data or error.
		 */
		protected function execute_api_logic( $request ) {
			$cashier_id = intval( $request['cashier_id'] );
			$outlet_id  = intval( $request['outlet_id'] );

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

			// Handle single order creation
			if ( ! empty( $request['order_data'] ) ) {
				$order_data = json_decode( $request['order_data'], true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					return $this->error_response(
						esc_html__( 'Invalid JSON data in order_data', 'multipos-for-fluentcart' ),
						'invalid_json_data',
						400
					);
				}
				return $this->create_single_order( $order_data, $request );
			}

			return $this->error_response(
				esc_html__( 'No order data provided', 'multipos-for-fluentcart' ),
				'no_order_data',
				400
			);
		}

		/**
		 * Create a single order.
		 *
		 * @param array $order_data Order data.
		 * @param array $request Original request.
		 * @return array|WP_Error Order response or error.
		 */
		protected function create_single_order( $order_data, $request ) {
			try {
				return $this->ddfcpos_create_order_once( $order_data, $request );
			} catch ( \Exception $e ) {
				return $this->error_response(
					$e->getMessage(),
					'order_creation_failed',
					500
				);
			}
		}

		/**
		 * Reject tenders that are not configured payment methods, or that carry a negative amount.
		 *
		 * A slug like "opencash" or "refund", or a negative cash amount, would otherwise be written to
		 * the ledger and quietly change what the register expects in the drawer.
		 *
		 * @param array $payment_methods Tenders sent by the POS.
		 * @return void
		 * @throws \Exception When a tender is not valid.
		 */
		protected function ddfcpos_validate_payment_methods( $payment_methods ) {
			$allowed = wp_list_pluck( (array) $this->ddfcpos_configuration['payment_method'], 'slug' );
			$allowed = array_diff( $allowed, [ 'opencash', 'manual', 'refund', 'refund_noncash', 'split' ] );

			if ( empty( $payment_methods ) || ! is_array( $payment_methods ) ) {
				throw new \Exception( esc_html__( 'The order has no payment.', 'multipos-for-fluentcart' ) );
			}

			// One payment method per sale. Splitting a sale across methods is a MultiPOS Pro feature.
			if ( 1 !== count( $payment_methods ) ) {
				throw new \Exception( esc_html__( 'Take the payment with one payment method.', 'multipos-for-fluentcart' ) );
			}

			foreach ( $payment_methods as $payment ) {
				if ( empty( $payment['slug'] ) || ! in_array( $payment['slug'], $allowed, true ) || ! isset( $payment['amount'] ) || ! is_numeric( $payment['amount'] ) || $payment['amount'] < 0 ) {
					throw new \Exception( esc_html__( 'The order has a payment method or amount that is not allowed.', 'multipos-for-fluentcart' ) );
				}
			}
		}

		/**
		 * Lines that ask for more units than FluentCart has, as "Name (N left)".
		 *
		 * @param array $lines Order lines.
		 * @return string[]
		 */
		protected function ddfcpos_get_oversold_lines( $lines ) {
			$wanted   = [];
			$products = [];

			foreach ( $lines as $line ) {
				if ( empty( $line['pos_product'] ) ) {
					continue;
				}

				$id              = (string) $line['pos_product']->get_id();
				$wanted[ $id ]   = ( isset( $wanted[ $id ] ) ? $wanted[ $id ] : 0 ) + (int) $line['quantity'];
				$products[ $id ] = $line['pos_product'];
			}

			$oversold = [];

			foreach ( $products as $id => $product ) {
				if ( DDFCPOS_FluentCart_Helper::stock_module_active() && $product->managing_stock() && ! $product->backorders_allowed() ) {
					$left = (int) $product->get_stock_quantity();
				} else {
					continue;
				}

				if ( $wanted[ $id ] > $left ) {
					/* translators: 1: product name, 2: units left. */
					$oversold[] = sprintf( esc_html__( '%1$s (%2$d left)', 'multipos-for-fluentcart' ), $product->get_name(), max( 0, $left ) );
				}
			}

			return $oversold;
		}

		/**
		 * The key that makes a sale idempotent: the POS sends one with each sale, so a retried
		 * submit never creates a second order.
		 *
		 * @param array $order_data Order data.
		 * @return array { meta_key, value } or empty when the sale has no key.
		 */
		protected function ddfcpos_get_sale_key( $order_data ) {
			$sale_key = ! empty( $order_data['sale_key'] ) ? substr( sanitize_key( (string) $order_data['sale_key'] ), 0, 64 ) : '';

			return '' !== $sale_key ? [ '_ddfcpos_sale_key', $sale_key ] : [];
		}

		/**
		 * Create the order unless this sale was already saved. The lookup and the insert run under a
		 * MySQL named lock per sale, so two parallel sends of one sale can not both create an order.
		 *
		 * @param array $order_data Order data.
		 * @param array $request    Original request.
		 * @return array Order response.
		 * @throws \Exception When the order can not be created.
		 */
		protected function ddfcpos_create_order_once( $order_data, $request ) {
			global $wpdb;

			$sale_key = $this->ddfcpos_get_sale_key( $order_data );

			if ( ! $sale_key ) {
				return $this->ddfcpos_create_order_safely( $order_data, $request );
			}

			$lock = 'ddfcpos_sale_' . md5( implode( '|', $sale_key ) . '|' . intval( $request['outlet_id'] ) . '|' . intval( $request['cashier_id'] ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Named lock, no table.
			if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 15 )', $lock ) ) ) {
				throw new \Exception( esc_html__( 'This sale is still being saved. Try again in a moment.', 'multipos-for-fluentcart' ) );
			}

			try {
				// A send whose response got lost comes again: hand back the order made the first
				// time instead of charging stock and the drawer twice.
				$existing = $this->ddfcpos_get_existing_sale_order( $order_data, $request );

				return $existing ? $existing : $this->ddfcpos_create_order_safely( $order_data, $request );
			} finally {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Named lock, no table.
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
			}
		}

		/**
		 * Create the order; when it fails before the order row exists, give back the coupon uses
		 * it claimed so the coupon is not used up by a sale that never happened.
		 *
		 * @param array $order_data Order data.
		 * @param array $request    Original request.
		 * @return array Order response.
		 * @throws \Exception When the order can not be created.
		 */
		protected function ddfcpos_create_order_safely( $order_data, $request ) {
			global $wpdb;

			$this->claimed_coupons  = [];
			$this->current_order_id = 0;

			try {
				return $this->ddfcpos_create_order( $order_data, $request );
			} catch ( \Exception $e ) {
				if ( ! $this->current_order_id ) {
					foreach ( $this->claimed_coupons as $coupon_id ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fct_coupons SET use_count = use_count - 1 WHERE id = %d AND use_count > 0", $coupon_id ) );
					}
				}

				throw $e;
			}
		}

		/**
		 * Order already created from this sale, ready to send back to the POS.
		 *
		 * @param array $order_data Order data.
		 * @param array $request    Original request.
		 * @return array Empty when the sale was not saved before.
		 */
		protected function ddfcpos_get_existing_sale_order( $order_data, $request ) {
			$sale_key = $this->ddfcpos_get_sale_key( $order_data );

			if ( ! $sale_key ) {
				return [];
			}

			$order_ids = array_values(
				array_intersect(
					DDFCPOS_FluentCart_Helper::get_order_ids_by_meta( $sale_key[0], $sale_key[1] ),
					DDFCPOS_FluentCart_Helper::get_order_ids_by_meta( DDFCPOS_FluentCart_Helper::OUTLET_META, intval( $request['outlet_id'] ) ),
					DDFCPOS_FluentCart_Helper::get_order_ids_by_meta( DDFCPOS_FluentCart_Helper::CASHIER_META, intval( $request['cashier_id'] ) )
				)
			);

			if ( empty( $order_ids ) ) {
				return [];
			}

			global $wpdb;

			$api_get_orders = new DDFCPOS_API_Get_Orders( $this->ddfcpos_configuration );
			$order_response = $api_get_orders->ddfcpos_prepare_order_data( $order_ids[0] );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin ledger table.
			$order_response['transactions'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ddfcpos_transactions WHERE order_id = %d", $order_ids[0] ), ARRAY_A );

			return $order_response;
		}

		/**
		 * Create Order.
		 *
		 * Builds a FluentCart order from a POS sale: product lines, coupons, tax worked out by
		 * FluentCart's tax engine for the outlet address, a paid transaction for the tender, and
		 * the FluentCart events
		 * (stock, emails, integrations) that a normal paid order goes through.
		 *
		 * @param array $order_data
		 * @param object $request
		 * @return array
		 */
		public function ddfcpos_create_order( $order_data, $request ) {
			$outlet_id          = $request[ 'outlet_id' ];
			$cashier_id         = $request[ 'cashier_id' ];
			$outlet_helper      = new DDFCPOS_Outlet_Helper();
			$transaction_helper = new DDFCPOS_Transaction_Helper();
			$outlet_data        = $outlet_helper->ddfcpos_get_outlet_details_by_id( $outlet_id );
			$customer_id        = apply_filters( 'ddfcpos/order/customer_id', ! empty( $order_data[ 'customer_id' ] ) ? $order_data[ 'customer_id' ] : 0, $request );
			$coupons            = ! empty( $order_data[ 'coupons' ] ) ? $order_data[ 'coupons' ] : [];
			$payment_methods    = $order_data[ 'payment_methods' ];
			$table              = isset( $order_data[ 'table' ] ) ? $order_data[ 'table' ] : '';
			$tax_address        = DDFCPOS_FluentCart_Helper::outlet_tax_address( $outlet_data );
			$this->coupon_notes = [];
			$tendered           = 0;

			// Tenders become rows of the cash ledger, so only real payment methods with real amounts may pass.
			$this->ddfcpos_validate_payment_methods( $payment_methods );

			if ( empty( $order_data[ 'products' ] ) || ! is_array( $order_data[ 'products' ] ) ) {
				throw new \Exception( esc_html__( 'The order has no products.', 'multipos-for-fluentcart' ) );
			}

			$customer = DDFCPOS_Customer_Helper::ddfcpos_get_order_customer( $customer_id );

			if ( ! $customer ) {
				throw new \Exception( esc_html__( 'The customer of this order could not be found.', 'multipos-for-fluentcart' ) );
			}

			foreach ( $payment_methods as $value ) {
				$tendered += floatval( $value[ 'amount' ] );
			}

			// 1. Lines, in cents.
			$lines         = [];
			$has_physical  = false;
			$cart_index    = 0;

			foreach ( $order_data[ 'products' ] as $item ) {
				$quantity = max( 1, intval( $item[ 'quantity' ] ) );
				$product  = ! empty( $item[ 'is_custom' ] ) ? false : DDFCPOS_Product::get( $item[ 'product_id' ] );

				// The free POS sells simple catalogue products.
				if ( ! $product || 'simple' !== $product->get_type() ) {
					/* translators: %s: product name. */
					throw new \Exception( sprintf( esc_html__( '%s is no longer available.', 'multipos-for-fluentcart' ), esc_html( sanitize_text_field( isset( $item[ 'name' ] ) ? $item[ 'name' ] : '' ) ) ) );
				}

				// Sold at the catalogue price: changing prices at the till is a MultiPOS Pro feature.
				$unit_price   = (int) $product->get_price_cents();
				$fulfillment  = $product->get_fulfillment_type();
				$has_physical = $has_physical || 'physical' === $fulfillment;

				$lines[] = [
					'post_id'          => $product->get_post_id(),
					'object_id'        => $product->get_variation_id(),
					'post_title'       => get_post_field( 'post_title', $product->get_post_id(), 'raw' ),
					'title'            => $product->get_name(),
					'fulfillment_type' => $fulfillment,
					'payment_type'     => 'onetime',
					'quantity'         => $quantity,
					'unit_price'       => $unit_price,
					'subtotal'         => $unit_price * $quantity,
					'discount_total'   => 0,
					'cart_index'       => ++$cart_index,
					'other_info'       => [
						'payment_type'   => 'onetime',
						'variation_type' => 'simple',
					],
					'pos_item'         => $item,
					'pos_product'      => $product,
				];
			}

			// 1b. Stock. A sale can not take more than is left (unless the item allows backorders or
			// its stock is not tracked).
			$oversold = $this->ddfcpos_get_oversold_lines( $lines );

			if ( $oversold ) {
				/* translators: %s: products and the units left, like "Mug (1 left)". */
				throw new \Exception( sprintf( esc_html__( 'Not enough stock for: %s', 'multipos-for-fluentcart' ), esc_html( implode( ', ', $oversold ) ) ) );
			}

			// 2. Coupons, spread over the product lines.
			$applied_coupons = $this->ddfcpos_apply_coupons( $coupons, $lines, $request, $customer );
			$coupon_total    = array_sum( wp_list_pluck( $applied_coupons, 'amount' ) );

			// 3. Tax for the outlet address.
			$tax_total    = 0;
			$tax_lines    = [];
			$tax_behavior = 0;
			$calculator   = DDFCPOS_FluentCart_Helper::tax_calculator( $this->ddfcpos_get_tax_line_items( $lines ), $tax_address );

			if ( $calculator ) {
				$taxed_lines  = $calculator->getTaxedLines();
				$tax_total    = (int) $calculator->getTotalTax();
				$tax_lines    = $calculator->getTaxLinesByRates();
				$tax_behavior = DDFCPOS_FluentCart_Helper::prices_include_tax() ? 2 : 1;

				foreach ( $lines as $index => $line ) {
					$lines[ $index ][ 'tax_amount' ] = isset( $taxed_lines[ $index ][ 'tax_amount' ] ) ? (int) $taxed_lines[ $index ][ 'tax_amount' ] : 0;
					$lines[ $index ][ 'line_meta' ]  = isset( $taxed_lines[ $index ][ 'line_meta' ] ) ? $taxed_lines[ $index ][ 'line_meta' ] : [];
				}
			}

			$exclusive_tax = 1 === $tax_behavior ? $tax_total : 0;

			$subtotal = array_sum( wp_list_pluck( $lines, 'subtotal' ) );
			$total    = max( 0, $subtotal - $coupon_total + $exclusive_tax );

			// What the drawer took, change aside. A short payment (a rounding cent, say) is recorded as such.
			$tendered_cents = DDFCPOS_FluentCart_Helper::to_cents( $tendered );
			$paid_cents     = min( $total, max( 0, $tendered_cents ) );

			// 6. The order.
			$store_settings = new \FluentCart\Api\StoreSettings();
			$payment        = $this->ddfcpos_get_payment_labels( $payment_methods );

			$order = Order::query()->create(
				apply_filters(
					'ddfcpos/order/fluentcart_data',
					[
						'status'                => Status::ORDER_ON_HOLD,
						'type'                  => Status::ORDER_TYPE_PAYMENT,
						'fulfillment_type'      => $has_physical ? Status::FULFILLMENT_TYPE_PHYSICAL : Status::FULFILLMENT_TYPE_DIGITAL,
						'mode'                  => $store_settings->get( 'order_mode' ) === 'test' ? 'test' : 'live',
						'shipping_status'       => $has_physical ? Status::SHIPPING_UNSHIPPED : '',
						'customer_id'           => $customer->id,
						'payment_method'        => 'multipos',
						'payment_method_title'  => $payment['title'],
						'payment_status'        => Status::PAYMENT_PENDING,
						'currency'              => DDFCPOS_FluentCart_Helper::currency(),
						'subtotal'              => $subtotal,
						'discount_tax'          => 0,
						'manual_discount_total' => 0,
						'coupon_discount_total' => $coupon_total,
						'shipping_tax'          => 0,
						'shipping_total'        => 0,
						'fee_total'             => 0,
						'tax_total'             => $tax_total,
						'tax_behavior'          => $tax_behavior,
						'total_amount'          => $total,
						'total_paid'            => 0,
						'total_refund'          => 0,
						'rate'                  => 1,
						'note'                  => '',
						'ip_address'            => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
						'config'                => [
							'source'      => 'multipos',
							'pos_outlet'  => (int) $outlet_id,
							'pos_cashier' => (int) $cashier_id,
						],
					],
					$order_data,
					$request
				)
			);

			if ( ! $order ) {
				throw new \Exception( esc_html__( 'The order could not be created.', 'multipos-for-fluentcart' ) );
			}

			$order_id               = (int) $order->id;
			$this->current_order_id = $order_id;

			// First, so a retry after a failure further down finds this order instead of making another.
			$sale_key = $this->ddfcpos_get_sale_key( $order_data );

			if ( $sale_key ) {
				$order->updateMeta( $sale_key[0], $sale_key[1] );
			}

			$order->updateMeta( DDFCPOS_FluentCart_Helper::OUTLET_META, (string) intval( $outlet_id ) );
			$order->updateMeta( DDFCPOS_FluentCart_Helper::CASHIER_META, (string) intval( $cashier_id ) );

			$sold_items = [];

			foreach ( $lines as $line ) {
				$pos_item = isset( $line['pos_item'] ) ? $line['pos_item'] : [];
				unset( $line['pos_item'], $line['pos_product'] );

				$line['order_id']   = $order_id;
				$line['line_total'] = $line['subtotal'] - $line['discount_total'];

				$order_item = OrderItem::query()->create( $line );

				if ( ! empty( $line['object_id'] ) ) {
					$sold_items[ $order_item->id ] = [
						'variation_id' => (int) $line['object_id'],
						'post_id'      => (int) $line['post_id'],
						'quantity'     => (int) $line['quantity'],
					];
				}

				if ( $pos_item ) {
					do_action( 'ddfcpos/order/line_added', $order_item->id, $pos_item, $order, $request );
				}
			}

			$this->ddfcpos_save_order_addresses( $order_id, $customer, $outlet_data );

			foreach ( $applied_coupons as $applied_coupon ) {
				// The use was claimed when the coupon was applied (ddfcpos_apply_coupons).
				AppliedCoupon::query()->create(
					[
						'order_id'  => $order_id,
						'coupon_id' => $applied_coupon['coupon_id'],
						'code'      => $applied_coupon['code'],
						'amount'    => $applied_coupon['amount'],
					]
				);
			}

			if ( $calculator && class_exists( TaxModule::class ) ) {
				TaxModule::persistTaxRates(
					$order_id,
					$tax_lines,
					[
						'tax_country' => $tax_address['country'],
						'source'      => 'multipos',
					],
					0
				);
			}

			// 7. MultiPOS data on the order.
			$pos_order = DDFCPOS_Order::get( $order_id );

			$pos_order->update_meta_data( '_ddfcpos_customer_id', (string) $customer->id );
			$pos_order->update_meta_data( '_ddfcpos_customer_user_id', (string) intval( $customer->user_id ) );
			$pos_order->update_meta_data( '_ddfcpos_tendered_amount', (string) $tendered );
			$pos_order->update_meta_data( '_ddfcpos_table', is_scalar( $table ) ? (string) $table : wp_json_encode( $table ) );
			$pos_order->update_meta_data( '_ddfcpos_payment_methods', array_values( $payment_methods ) );
			$pos_order->update_meta_data( '_ddfcpos_payment_method', $payment['slug'] );
			$pos_order->update_meta_data( '_ddfcpos_payment_method_title', $payment['name'] );

			if ( $this->coupon_notes ) {
				$pos_order->add_order_note( esc_html__( 'Coupons not applied:', 'multipos-for-fluentcart' ) . "\n" . implode( "\n", $this->coupon_notes ) );
			}

			// 8. Stock. The units leave FluentCart's stock now, since the customer takes them at the till.
			DDFCPOS_FluentCart_Helper::commit_sold_stock( $order_id, $sold_items );

			if ( $paid_cents < $total ) {
				/* translators: 1: amount paid, 2: order total. */
				$pos_order->add_order_note( sprintf( esc_html__( 'Short payment: the POS took %1$s for a %2$s sale.', 'multipos-for-fluentcart' ), DDFCPOS_FluentCart_Helper::format_price_text( DDFCPOS_FluentCart_Helper::from_cents( $paid_cents ) ), DDFCPOS_FluentCart_Helper::format_price_text( DDFCPOS_FluentCart_Helper::from_cents( $total ) ) ) );
				$pos_order->update_meta_data( '_ddfcpos_limit_exceeded', 'yes' );
			}

			do_action( 'ddfcpos/order/before_payment_complete', $pos_order, $order_data, $request );

			DDFCPOS_FluentCart_Helper::without_fluentcart_stock(
				function () use ( $order_id, $customer ) {
					( new OrderCreated( Order::query()->with( [ 'order_items' ] )->find( $order_id ), null, $customer ) )->dispatch();
				}
			);

			// The shared walk-in record must never become a WordPress account, whatever FluentCart's
			// "create accounts on purchase" setting says; FluentCart skips emails that already exist.
			$walkin_email = (int) $customer->id === absint( get_option( DDFCPOS_Customer_Helper::DEFAULT_CUSTOMER_OPTION ) ) ? strtolower( $customer->email ) : '';
			$skip_account = function ( $user_id, $email ) use ( $walkin_email ) {
				return $user_id ? $user_id : ( $walkin_email && strtolower( (string) $email ) === $walkin_email ? PHP_INT_MAX : $user_id );
			};

			add_filter( 'email_exists', $skip_account, 10, 2 );

			// 9. Payment: one paid transaction for everything the customer handed over (minus change).
			if ( apply_filters( 'ddfcpos/order/complete_payment', true, $pos_order, $order_data, $request ) ) {
				$transaction = OrderTransaction::query()->create(
					[
						'order_id'            => $order_id,
						'order_type'          => Status::ORDER_TYPE_PAYMENT,
						'transaction_type'    => Status::TRANSACTION_TYPE_CHARGE,
						'payment_method'      => 'multipos',
						'payment_mode'        => $order->mode,
						'payment_method_type' => $payment['slug'],
						'status'              => Status::TRANSACTION_SUCCEEDED,
						'currency'            => $order->currency,
						'total'               => $paid_cents,
						'rate'                => 1,
						'uuid'                => md5( 'ddfcpos' . $order_id . wp_generate_uuid4() ),
						'meta'                => [
							'payment_methods' => array_values( $payment_methods ),
							'outlet_id'       => (int) $outlet_id,
							'cashier_id'      => (int) $cashier_id,
						],
					]
				);

				DDFCPOS_FluentCart_Helper::without_fluentcart_stock(
					function () use ( $order_id, $transaction ) {
						( new StatusHelper( Order::query()->with( [ 'order_items' ] )->find( $order_id ) ) )->syncOrderStatuses( $transaction );
					}
				);

				// FluentCart names the method from its gateways; keep the POS tender name.
				Order::query()->where( 'id', $order_id )->update( [ 'payment_method_title' => $payment['title'] ] );
			}

			remove_filter( 'email_exists', $skip_account, 10 );

			// 10. The customer leaves with the goods: physical items are delivered at the till.
			if ( $has_physical && apply_filters( 'ddfcpos/order/mark_delivered', true, $order_id, $order_data, $request ) ) {
				Order::query()->where( 'id', $order_id )->update( [ 'shipping_status' => Status::SHIPPING_DELIVERED ] );

				DDFCPOS_FluentCart_Helper::without_fluentcart_stock(
					function () use ( $order_id ) {
						// FluentCart logs the event's activity; an empty one leaves a blank row on the order's Activity.
						$activity = [
							'title'   => __( 'Shipping status updated', 'multipos-for-fluentcart' ),
							'content' => __( 'Items handed over at the point of sale, so the order is marked Delivered.', 'multipos-for-fluentcart' ),
						];
						( new OrderStatusUpdated( Order::query()->with( [ 'order_items' ] )->find( $order_id ), Status::SHIPPING_UNSHIPPED, Status::SHIPPING_DELIVERED, true, $activity, 'shipping_status' ) )->dispatch();
					}
				);
			}

			$pos_order = DDFCPOS_Order::get( $order_id );

			$pos_order = apply_filters( 'ddfcpos/order/create_data', $pos_order, $order_data, $request );

			do_action( 'ddfcpos/order/created', $pos_order, $order_data, $request );

			if ( apply_filters( 'ddfcpos/order/update_status', true, $pos_order, $order_data, $request ) ) {
				$this->ddfcpos_update_order_status( $order_id, $this->ddfcpos_configuration[ 'order_status' ] );
			}

			$api_get_orders = new DDFCPOS_API_Get_Orders( $this->ddfcpos_configuration );
			$order_response = $api_get_orders->ddfcpos_prepare_order_data( $order_id );

			$order_response[ 'transactions' ] = [];

			$change_given = false;

			foreach ( $payment_methods as $key => $value ) {
				// The change comes out of the drawer once, against the first cash tender.
				$change       = 'cash' === $value[ 'slug' ] && ! $change_given ? $order_response[ 'change' ] : 0;
				$change_given = $change_given || 'cash' === $value[ 'slug' ];

				$transaction_data = [
					'cashier_id' => $cashier_id,
					'outlet_id'  => $outlet_id,
					'order_id'   => $order_id,
					'amount_in'  => $value[ 'amount' ],
					'amount_out' => $change,
					'method'     => $value[ 'slug' ],
					'reference'  => '',
					'date'       => current_time( 'Y-m-d H:i:s' ),
				];

				$transaction_data[ 'id' ]             = strval( $transaction_helper->ddfcpos_save_transaction( $transaction_data ) );
				$order_response[ 'transactions' ][]   = $transaction_data;
			}

			$order_response = apply_filters( 'ddfcpos/api/order/data', $order_response, $pos_order, $request );

			return apply_filters( 'ddfcpos/api/order/create_response', $order_response, $pos_order, $request );
		}

		/**
		 * Move the order to the configured status.
		 *
		 * @param int    $order_id Order ID.
		 * @param string $status   FluentCart order status.
		 * @return void
		 */
		protected function ddfcpos_update_order_status( $order_id, $status ) {
			$order = Order::query()->find( $order_id );

			if ( ! $order || ! $status || ! array_key_exists( $status, DDFCPOS_FluentCart_Helper::order_statuses() ) || $status === $order->status ) {
				return;
			}

			$old_status = $order->status;

			$order->updateStatus( 'status', $status );

			$activity = [
				'title'   => __( 'Order status updated', 'multipos-for-fluentcart' ),
				/* translators: 1: old order status, 2: new order status */
				'content' => sprintf( __( 'Order status has been updated from %1$s to %2$s', 'multipos-for-fluentcart' ), $old_status, $status ),
			];

			( new OrderStatusUpdated( $order, $old_status, $status, false, $activity, 'order_status' ) )->dispatch();
		}

		/**
		 * Payment method slug, name and the title shown in FluentCart.
		 *
		 * @param array $payment_methods Tenders.
		 * @return array { slug, name, title }
		 */
		protected function ddfcpos_get_payment_labels( $payment_methods ) {
			$slug = sanitize_key( $payment_methods[0][ 'slug' ] );
			$name = sanitize_text_field( $payment_methods[0][ 'name' ] );
			/* translators: %s: payment method name. */
			$title = sprintf( esc_html__( 'POS: %s', 'multipos-for-fluentcart' ), $name );

			return compact( 'slug', 'name', 'title' );
		}

		/**
		 * Line items for FluentCart's tax engine.
		 *
		 * @param array $lines Order lines.
		 * @return array
		 */
		protected function ddfcpos_get_tax_line_items( $lines ) {
			return array_map(
				function ( $line ) {
					return [
						'id'             => $line['object_id'],
						'post_id'        => $line['post_id'],
						'object_id'      => $line['object_id'],
						'quantity'       => $line['quantity'],
						'unit_price'     => $line['unit_price'],
						'subtotal'       => $line['subtotal'],
						'discount_total' => $line['discount_total'],
						'other_info'     => $line['other_info'],
					];
				},
				$lines
			);
		}

		/**
		 * Spread the POS coupons over the product lines (writes each line's discount_total).
		 *
		 * The POS sends the coupons it applied (code, type, amount). The discount is worked out
		 * again here from the FluentCart coupon, so a tampered amount can not lower a price.
		 *
		 * @param array                            $coupons      POS coupons.
		 * @param array                            $lines        Order lines, by reference.
		 * @param array                            $request      Request.
		 * @param \FluentCart\App\Models\Customer $customer     Customer of the sale.
		 * @return array Applied coupons: coupon_id, code, amount (cents), and notes for refused ones.
		 */
		protected function ddfcpos_apply_coupons( $coupons, &$lines, $request, $customer = null ) {
			global $wpdb;

			$applied = [];
			$seen    = [];
			$spend   = array_sum( wp_list_pluck( $lines, 'subtotal' ) );

			foreach ( (array) $coupons as $pos_coupon ) {
				$code = ! empty( $pos_coupon['code'] ) ? strtolower( sanitize_text_field( $pos_coupon['code'] ) ) : '';

				// The same code twice would compound.
				if ( '' === $code || isset( $seen[ $code ] ) ) {
					continue;
				}

				$seen[ $code ] = true;
				$coupon        = Coupon::query()->where( 'code', $code )->first();

				if ( ! $coupon ) {
					$this->coupon_notes[] = sprintf( '%s: %s', $code, esc_html__( 'Invalid coupon code', 'multipos-for-fluentcart' ) );
					continue;
				}

				$error = DDFCPOS_Coupon_Helper::ddfcpos_get_coupon_error( $coupon, $customer ? $customer->id : 0, $customer ? $customer->email : '', $spend );

				// One coupon that does not stack with others is used alone.
				if ( ! $error && ( 'no' === $coupon->stackable || ( $applied && ! empty( $applied[0]['alone'] ) ) ) && $applied ) {
					$error = esc_html__( 'This coupon can not be combined with another coupon.', 'multipos-for-fluentcart' );
				}

				if ( $error ) {
					$this->coupon_notes[] = sprintf( '%s: %s', $coupon->code, $error );
					continue;
				}

				$conditions = is_array( $coupon->conditions ) ? $coupon->conditions : (array) json_decode( (string) $coupon->conditions, true );
				$eligible   = [];
				$base       = 0;

				// A coupon limited to products or categories reaches only those catalog lines.
				foreach ( $lines as $index => $line ) {
					if ( ! DDFCPOS_Coupon_Helper::ddfcpos_line_is_eligible( $conditions, $line['post_id'], $line['object_id'] ) ) {
						continue;
					}

					$remaining = $line['subtotal'] - $line['discount_total'];

					if ( $remaining <= 0 ) {
						continue;
					}

					$eligible[ $index ] = $remaining;
					$base              += $remaining;
				}

				if ( ! $base ) {
					continue;
				}

				if ( 'percentage' === $coupon->type ) {
					$amount = (int) round( $base * floatval( $coupon->amount ) / 100 );
				} else {
					$amount = DDFCPOS_Coupon_Helper::ddfcpos_fixed_amount_cents( $coupon );
				}

				$max_discount = isset( $conditions['max_discount_amount'] ) ? (int) $conditions['max_discount_amount'] : 0;

				if ( $max_discount > 0 ) {
					$amount = min( $amount, $max_discount );
				}

				$amount = (int) min( $amount, $base );

				if ( $amount <= 0 ) {
					continue;
				}

				$max_uses = isset( $conditions['max_uses'] ) ? (int) $conditions['max_uses'] : 0;

				// Claim the use before any discount is given. The guarded update is atomic, so when two
				// tills race for the last use only one gets a row back; the other sale gets no discount.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
				$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fct_coupons SET use_count = use_count + 1 WHERE id = %d AND ( %d = 0 OR use_count < %d )", $coupon->id, $max_uses, $max_uses ) );

				if ( ! $claimed ) {
					$this->coupon_notes[] = sprintf( '%s: %s', $coupon->code, esc_html__( 'Sorry, Coupon usage limit exceeded', 'multipos-for-fluentcart' ) );
					continue;
				}

				$this->claimed_coupons[] = (int) $coupon->id;

				// Proportional split; the last line takes the rounding remainder.
				$given = 0;
				$keys  = array_keys( $eligible );
				$last  = end( $keys );

				foreach ( $eligible as $index => $remaining ) {
					$share = $index === $last ? $amount - $given : (int) floor( $amount * $remaining / $base );
					$share = min( $share, $remaining );

					$lines[ $index ]['discount_total'] += $share;
					$given                             += $share;
				}

				$applied[] = [
					'coupon_id' => (int) $coupon->id,
					'code'      => $coupon->code,
					'amount'    => $given,
					'alone'     => 'no' === $coupon->stackable,
					'max_uses'  => $max_uses,
				];
			}

			$applied = apply_filters( 'ddfcpos/order/applied_coupons', $applied, $coupons, $request );

			// A use claimed for a coupon that a filter then dropped goes back.
			$kept = array_map( 'intval', wp_list_pluck( (array) $applied, 'coupon_id' ) );

			foreach ( $this->claimed_coupons as $index => $coupon_id ) {
				if ( ! in_array( $coupon_id, $kept, true ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fct_coupons SET use_count = use_count - 1 WHERE id = %d AND use_count > 0", $coupon_id ) );
					unset( $this->claimed_coupons[ $index ] );
				}
			}

			return $applied;
		}

		/**
		 * Save the outlet as the order's billing and shipping address, with the customer's name.
		 *
		 * @param int                              $order_id    Order ID.
		 * @param \FluentCart\App\Models\Customer $customer    Customer.
		 * @param array                            $outlet_data Outlet.
		 * @return void
		 */
		protected function ddfcpos_save_order_addresses( $order_id, $customer, $outlet_data ) {
			if ( ! apply_filters( 'ddfcpos/order/address', true, $order_id, $outlet_data ) ) {
				return;
			}

			$country = ! empty( $outlet_data['country'] ) ? strtoupper( $outlet_data['country'] ) : '';
			$name    = trim( $customer->first_name . ' ' . $customer->last_name );

			foreach ( [ 'billing', 'shipping' ] as $type ) {
				OrderAddress::query()->create(
					[
						'order_id'  => $order_id,
						'type'      => $type,
						'name'      => $name ? $name : $customer->email,
						'address_1' => isset( $outlet_data['address1'] ) ? $outlet_data['address1'] : '',
						'address_2' => isset( $outlet_data['address2'] ) ? $outlet_data['address2'] : '',
						'city'      => isset( $outlet_data['city'] ) ? $outlet_data['city'] : '',
						'state'     => DDFCPOS_FluentCart_Helper::state_code( $country, isset( $outlet_data['state'] ) ? $outlet_data['state'] : '' ),
						'postcode'  => isset( $outlet_data['postcode'] ) ? $outlet_data['postcode'] : '',
						'country'   => $country,
						'meta'      => [
							'other_data' => [
								'email' => $customer->email,
								'phone' => (string) $customer->getMeta( 'ddfcpos_phone', '' ),
							],
						],
					]
				);
			}
		}
	}
}
