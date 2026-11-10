<?php
/**
 * Order adapter.
 *
 * Wraps a FluentCart order with the small, read-mostly surface MultiPOS needs
 * (decimals instead of cents, site-time dates, MultiPOS meta and notes).
 *
 * @package MultiPOS for FluentCart
 */

namespace DDFCMultiPOS\Helper\FluentCart;

use FluentCart\App\Models\Activity;
use FluentCart\App\Models\Order;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Order' ) ) {
	/**
	 * Order adapter class.
	 */
	class DDFCPOS_Order {
		/**
		 * Title used for notes MultiPOS adds to the FluentCart activity log.
		 */
		const NOTE_TITLE = 'MultiPOS';

		/**
		 * FluentCart order.
		 *
		 * @var Order
		 */
		protected $order;

		/**
		 * Meta cache.
		 *
		 * @var array|null
		 */
		protected $meta;

		/**
		 * Constructor.
		 *
		 * @param Order $order FluentCart order.
		 */
		public function __construct( Order $order ) {
			$this->order = $order;
		}

		/**
		 * Load an order.
		 *
		 * @param int|Order $order Order or ID.
		 * @return static|false
		 */
		public static function get( $order ) {
			if ( $order instanceof self ) {
				return $order;
			}

			if ( ! $order instanceof Order ) {
				$order = $order ? Order::query()->with( [ 'order_items', 'billing_address', 'shipping_address', 'customer' ] )->find( absint( $order ) ) : null;
			}

			return $order ? new static( $order ) : false;
		}

		/**
		 * Order ID.
		 *
		 * @return int
		 */
		public function get_id() {
			return (int) $this->order->id;
		}

		/**
		 * Number shown to people: the invoice number once paid, the ID before.
		 *
		 * @return string
		 */
		public function get_order_number() {
			return $this->order->invoice_no ? (string) $this->order->invoice_no : (string) $this->order->id;
		}

		/**
		 * Order status.
		 *
		 * @return string
		 */
		public function get_status() {
			return (string) $this->order->status;
		}

		/**
		 * Payment status.
		 *
		 * @return string
		 */
		public function get_payment_status() {
			return (string) $this->order->payment_status;
		}

		/**
		 * Currency.
		 *
		 * @return string
		 */
		public function get_currency() {
			return $this->order->currency ? (string) $this->order->currency : DDFCPOS_FluentCart_Helper::currency();
		}

		/**
		 * FluentCart customer ID.
		 *
		 * @return int
		 */
		public function get_fc_customer_id() {
			return (int) $this->order->customer_id;
		}

		/**
		 * Created date in the site timezone.
		 *
		 * @return \DateTime
		 */
		public function get_date_created() {
			return self::to_site_date( $this->order->created_at );
		}

		/**
		 * GMT MySQL date to a DateTime in the site timezone.
		 *
		 * @param mixed $date Date.
		 * @return \DateTime
		 */
		public static function to_site_date( $date ) {
			$date = is_object( $date ) && method_exists( $date, 'format' ) ? $date->format( 'Y-m-d H:i:s' ) : (string) $date;
			$time = new \DateTime( $date ? $date : 'now', new \DateTimeZone( 'UTC' ) );
			$time->setTimezone( wp_timezone() );

			return $time;
		}

		/**
		 * Payment method slug.
		 *
		 * @return string
		 */
		public function get_payment_method() {
			$method = $this->get_meta( '_ddfcpos_payment_method' );

			return '' !== $method ? $method : (string) $this->order->payment_method;
		}

		/**
		 * Payment method title.
		 *
		 * @return string
		 */
		public function get_payment_method_title() {
			$title = $this->get_meta( '_ddfcpos_payment_method_title' );

			return '' !== $title ? $title : (string) $this->order->payment_method_title;
		}

		/*
		|--------------------------------------------------------------------------
		| Totals (decimals)
		|--------------------------------------------------------------------------
		*/

		/**
		 * Grand total.
		 *
		 * @return float
		 */
		public function get_total() {
			return DDFCPOS_FluentCart_Helper::from_cents( $this->order->total_amount );
		}

		/**
		 * Items subtotal, before discounts.
		 *
		 * @return float
		 */
		public function get_subtotal() {
			return DDFCPOS_FluentCart_Helper::from_cents( $this->order->subtotal );
		}

		/**
		 * Tax total.
		 *
		 * @return float
		 */
		public function get_total_tax() {
			return DDFCPOS_FluentCart_Helper::from_cents( $this->order->tax_total );
		}

		/**
		 * Manual (cashier) discount.
		 *
		 * @return float
		 */
		public function get_manual_discount() {
			return DDFCPOS_FluentCart_Helper::from_cents( $this->order->manual_discount_total );
		}

		/**
		 * Refunded amount.
		 *
		 * @return float
		 */
		public function get_total_refunded() {
			return DDFCPOS_FluentCart_Helper::from_cents( $this->order->total_refund );
		}

		/**
		 * Formatted total.
		 *
		 * @return string
		 */
		public function get_formatted_order_total() {
			return DDFCPOS_FluentCart_Helper::format_price( $this->get_total(), $this->get_currency() );
		}

		/*
		|--------------------------------------------------------------------------
		| Items
		|--------------------------------------------------------------------------
		*/

		/**
		 * Product and custom lines (fees excluded).
		 *
		 * @return \FluentCart\App\Models\OrderItem[]
		 */
		public function get_items() {
			$items = [];

			foreach ( $this->order->order_items as $item ) {
				if ( 'fee' === $item->payment_type ) {
					continue;
				}

				$items[ $item->id ] = $item;
			}

			return $items;
		}

		/**
		 * Fee lines.
		 *
		 * @return \FluentCart\App\Models\OrderItem[]
		 */
		public function get_fees() {
			$fees = [];

			foreach ( $this->order->order_items as $item ) {
				if ( 'fee' === $item->payment_type ) {
					$fees[ $item->id ] = $item;
				}
			}

			return $fees;
		}

		/**
		 * Number of units sold.
		 *
		 * @return int
		 */
		public function get_item_count() {
			$count = 0;

			foreach ( $this->get_items() as $item ) {
				$count += (int) $item->quantity;
			}

			return $count;
		}

		/**
		 * Applied coupons: code and amount (decimal).
		 *
		 * @return array
		 */
		public function get_coupons() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT code, amount FROM {$wpdb->prefix}fct_applied_coupons WHERE order_id = %d", $this->get_id() ) );

			return array_map(
				function ( $row ) {
					return [
						'code'   => $row->code,
						'amount' => DDFCPOS_FluentCart_Helper::from_cents( $row->amount ),
					];
				},
				(array) $rows
			);
		}

		/**
		 * Quantity of an order item already returned at the POS.
		 *
		 * @param int $item_id Order item ID.
		 * @return int
		 */
		public function get_qty_refunded_for_item( $item_id ) {
			$returned = (array) $this->get_meta( '_ddfcpos_returned_items' );

			return isset( $returned[ $item_id ] ) ? (int) $returned[ $item_id ] : 0;
		}

		/**
		 * Tax lines from fct_order_tax_rate, decimals.
		 *
		 * @return array
		 */
		public function get_tax_lines() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart tables.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT otr.id, otr.tax_rate_id, otr.order_tax, otr.total_tax, tr.name, tr.rate, tr.is_compound FROM {$wpdb->prefix}fct_order_tax_rate otr LEFT JOIN {$wpdb->prefix}fct_tax_rates tr ON tr.id = otr.tax_rate_id WHERE otr.order_id = %d AND otr.tax_rate_id > 0", $this->get_id() ) );
			$lines = [];

			foreach ( (array) $rows as $row ) {
				$lines[] = [
					'id'       => (int) $row->id,
					'rate_id'  => (int) $row->tax_rate_id,
					'code'     => sanitize_title( $row->name ? $row->name : 'tax' ) . '-' . $row->tax_rate_id,
					'label'    => $row->name ? $row->name : DDFCPOS_FluentCart_Helper::tax_label(),
					'rate'     => floatval( $row->rate ),
					'total'    => DDFCPOS_FluentCart_Helper::from_cents( $row->total_tax ),
					'compound' => (bool) $row->is_compound,
				];
			}

			if ( empty( $lines ) && (int) $this->order->tax_total > 0 ) {
				$lines[] = [
					'id'       => 0,
					'rate_id'  => 0,
					'code'     => 'tax',
					'label'    => DDFCPOS_FluentCart_Helper::tax_label(),
					'rate'     => 0,
					'total'    => $this->get_total_tax(),
					'compound' => false,
				];
			}

			return $lines;
		}

		/*
		|--------------------------------------------------------------------------
		| Addresses
		|--------------------------------------------------------------------------
		*/

		/**
		 * Address with the field names the POS, invoices and reports use.
		 *
		 * @param string $type billing or shipping.
		 * @return array
		 */
		public function get_address( $type = 'billing' ) {
			$address  = 'shipping' === $type ? $this->order->shipping_address : $this->order->billing_address;
			$customer = $this->order->customer;
			$name     = $address && $address->name ? $address->name : ( $customer ? trim( $customer->first_name . ' ' . $customer->last_name ) : '' );
			$parts    = explode( ' ', trim( (string) $name ), 2 );
			$meta     = $address && is_array( $address->meta ) ? $address->meta : [];

			return [
				'first_name' => isset( $parts[0] ) ? $parts[0] : '',
				'last_name'  => isset( $parts[1] ) ? $parts[1] : '',
				'company'    => isset( $meta['other_data']['company_name'] ) ? $meta['other_data']['company_name'] : '',
				'address_1'  => $address ? (string) $address->address_1 : '',
				'address_2'  => $address ? (string) $address->address_2 : '',
				'city'       => $address ? (string) $address->city : '',
				'state'      => $address ? (string) $address->state : '',
				'postcode'   => $address ? (string) $address->postcode : '',
				'country'    => $address ? (string) $address->country : '',
				'email'      => $customer ? (string) $customer->email : '',
				'phone'      => isset( $meta['other_data']['phone'] ) ? $meta['other_data']['phone'] : (string) $this->get_meta( '_ddfcpos_billing_phone' ),
			];
		}

		/**
		 * Billing email.
		 *
		 * @return string
		 */
		public function get_billing_email() {
			return $this->get_address()['email'];
		}

		/**
		 * Customer full name.
		 *
		 * @return string
		 */
		public function get_formatted_billing_full_name() {
			$address = $this->get_address();

			return trim( $address['first_name'] . ' ' . $address['last_name'] );
		}

		/*
		|--------------------------------------------------------------------------
		| Meta and notes
		|--------------------------------------------------------------------------
		*/

		/**
		 * All order meta.
		 *
		 * @return array
		 */
		protected function load_meta() {
			if ( null === $this->meta ) {
				global $wpdb;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
				$rows       = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->prefix}fct_order_meta WHERE order_id = %d ORDER BY id ASC", $this->get_id() ) );
				$this->meta = [];

				foreach ( (array) $rows as $row ) {
					$this->meta[ $row->meta_key ] = $row->meta_value;
				}
			}

			return $this->meta;
		}

		/**
		 * Read meta. Arrays saved by MultiPOS come back as arrays.
		 *
		 * @param string $key    Meta key.
		 * @param bool   $single Kept for WC_Order compatibility.
		 * @return mixed '' when missing.
		 */
		public function get_meta( $key, $single = true ) {
			$meta = $this->load_meta();

			if ( ! isset( $meta[ $key ] ) ) {
				return '';
			}

			$value = $meta[ $key ];

			if ( is_string( $value ) && '' !== $value && ( '[' === $value[0] || '{' === $value[0] ) ) {
				$decoded = json_decode( $value, true );

				if ( JSON_ERROR_NONE === json_last_error() ) {
					return $decoded;
				}
			}

			return $value;
		}

		/**
		 * Write meta, saved right away. Arrays are stored as JSON, as FluentCart does.
		 *
		 * @param string $key   Meta key.
		 * @param mixed  $value Value.
		 * @return void
		 */
		public function update_meta_data( $key, $value ) {
			$this->order->updateMeta( $key, is_array( $value ) || is_object( $value ) ? $value : (string) $value );
			$this->meta = null;
		}

		/**
		 * Add a note to the FluentCart order activity.
		 *
		 * @param string $note Note.
		 * @return void
		 */
		public function add_order_note( $note ) {
			$user = wp_get_current_user();

			$this->order->addLog( self::NOTE_TITLE, nl2br( esc_html( $note ) ), 'info', $user->exists() ? $user->display_name : 'MultiPOS' );
		}

		/**
		 * Notes MultiPOS added, newest first.
		 *
		 * @return string[]
		 */
		public function get_order_notes() {
			$notes = Activity::query()
				->where( 'module_id', $this->get_id() )
				->where( 'module_name', 'Order' )
				->where( 'title', self::NOTE_TITLE )
				->orderBy( 'id', 'DESC' )
				->get();

			$list = [];

			foreach ( $notes as $note ) {
				$list[] = wp_strip_all_tags( str_replace( [ '<br />', '<br>' ], "\n", (string) $note->content ) );
			}

			return $list;
		}
	}
}
