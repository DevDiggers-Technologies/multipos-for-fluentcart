<?php
/**
 * Orders List Template
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Order;

use DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper;
use DDFCMultiPOS\Helper\Cashier\DDFCPOS_Cashier_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Order;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Orders_List_Template' ) ) {

    /**
     * Orders List class
     */
    class DDFCPOS_Orders_List_Template extends \WP_List_Table {

        /**
		 * Outlet Helper Variable
		 *
		 * @var object
		 */
		protected $outlet_helper;

        /**
         * Construct
         */
        function __construct() {
            $this->outlet_helper = new DDFCPOS_Outlet_Helper();

            parent::__construct( [
                'singular' => esc_html__( 'Order', 'multipos-for-fluentcart' ),
                'plural'   => esc_html__( 'Orders', 'multipos-for-fluentcart' ),
                'ajax'     => false
            ] );
        }

        /**
         * Prepare the items for the table to process
         */
        public function prepare_items() {
			$this->_column_headers = $this->get_column_info();

			// The referer and nonce fields of a search are stripped on admin_init ( DDFCPOS_Admin_Hooks ).

			$search       = ! empty( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list search.
			$per_page     = $this->get_items_per_page( 'orders_per_page', 20 );
			$current_page = $this->get_pagenum();
			$total_items  = $this->ddfcpos_get_orders_count( $search );

			$this->set_pagination_args( [
				'total_items' => $total_items,
				'per_page'    => $per_page,
			] );

			$data = $this->ddfcpos_get_orders( $per_page, $current_page, $search );

			usort( $data, [ $this, 'usort_reorder' ] );

			$this->items = $data;
		}

		/**
		 * Query arguments shared by the count and the page: POS orders only, narrowed by the
		 * cashier and date filters above the table.
		 *
		 * @param string $search Order number.
		 * @return array
		 */
		protected function ddfcpos_get_order_query_args( $search ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
			$cashier_id = ! empty( $_GET['cashier-id'] ) ? absint( $_GET['cashier-id'] ) : 0;
			$from       = ! empty( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
			$to         = ! empty( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
			// phpcs:enable

			// Each end checked on its own; a missing or broken one means open ended.
			$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ? $from : '';
			$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ? $to : '';

			$args = [
				'cashier_id' => $cashier_id ? $cashier_id : '',
				'after'      => $from ? $from . ' 00:00:00' : '',
				'before'     => $to ? $to . ' 23:59:59' : '',
			];

			// An order number search narrows the list to that order.
			$args['include'] = '' !== $search ? [ absint( $search ) ] : null;

			return $args;
		}

		/**
		 * POS order IDs for the filters, newest first.
		 *
		 * @param array $args   Query arguments.
		 * @param int   $limit  Limit, -1 for all.
		 * @param int   $offset Offset.
		 * @return int[]
		 */
		protected function ddfcpos_query_order_ids( $args, $limit = -1, $offset = 0 ) {
			$include = $args['include'];
			unset( $args['include'] );

			if ( null === $include ) {
				return DDFCPOS_FluentCart_Helper::query_pos_orders( array_merge( $args, [ 'limit' => $limit, 'offset' => $offset ] ) );
			}

			// An order number search: keep only POS orders.
			$ids = array_values( array_intersect( DDFCPOS_FluentCart_Helper::query_pos_orders( $args ), array_map( 'intval', $include ) ) );

			return $limit > 0 ? array_slice( $ids, $offset, $limit ) : $ids;
		}

		/**
		 * Get Orders Count function
		 *
		 * @param string $search
		 * @return int
		 */
		public function ddfcpos_get_orders_count( $search ) {
			$args = $this->ddfcpos_get_order_query_args( $search );

			if ( null === $args['include'] ) {
				unset( $args['include'] );

				return DDFCPOS_FluentCart_Helper::query_pos_orders( array_merge( $args, [ 'count' => true ] ) );
			}

			return count( $this->ddfcpos_query_order_ids( $args ) );
		}

		/**
		 * Fetch Orders Data Function
		 *
		 * @param int $per_page Per Page.
		 * @param int $current_page Page.
		 * @param string $search
		 * @return array $data
		 */
		public function ddfcpos_get_orders( $per_page, $current_page, $search ) {
			$data      = [];
			$order_ids = $this->ddfcpos_query_order_ids( $this->ddfcpos_get_order_query_args( $search ), $per_page, ( $current_page - 1 ) * $per_page );

			foreach ( $order_ids as $order_id ) {
				$order = DDFCPOS_Order::get( $order_id );

				if ( ! $order ) {
					continue;
				}

				$buyer = $order->get_formatted_billing_full_name();
				$buyer = '' !== trim( $buyer ) ? $buyer : $order->get_billing_email();

				/**
				 * Filter buyer name in list table orders.
				 *
				 * @param string        $buyer Buyer name.
				 * @param DDFCPOS_Order $order Order data.
				 */
				$buyer = apply_filters( 'ddfcpos/admin/orders/buyer_name', $buyer, $order );

				$order_html = '<a href="' . esc_url( DDFCPOS_FluentCart_Helper::order_admin_url( $order->get_id() ) ) . '" class="order-view"><strong>#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ) . '</strong></a>';

				$tooltip     = '';
				$notes       = $order->get_order_notes();
				$notes_count = count( $notes );

				if ( 1 === $notes_count ) {
					$tooltip = $notes[0];
				} elseif ( $notes_count > 1 ) {
					/* translators: %d: notes count */
					$tooltip = $notes[0] . '<br/><small style="display:block">' . sprintf( _n( 'Plus %d other note', 'Plus %d other notes', ( $notes_count - 1 ), 'multipos-for-fluentcart' ), $notes_count - 1 ) . '</small>';
				}

				$status_label = DDFCPOS_FluentCart_Helper::order_status_label( $order->get_status() );

				if ( $tooltip ) {
					$status = sprintf( '<mark class="order-status %s tips" data-tip="%s"><span>%s</span></mark>', esc_attr( sanitize_html_class( 'status-' . $order->get_status() ) ), esc_attr( wp_kses_post( $tooltip ) ), esc_html( $status_label ) );
				} else {
					$status = sprintf( '<mark class="order-status %s"><span>%s</span></mark>', esc_attr( sanitize_html_class( 'status-' . $order->get_status() ) ), esc_html( $status_label ) );
				}

				$order_timestamp = $order->get_date_created()->getTimestamp();

				// Check if the order was created within the last 24 hours, and not in the future.
				if ( $order_timestamp > strtotime( '-1 day', time() ) && $order_timestamp <= time() ) {
					$show_date = sprintf(
						/* translators: %s: human-readable time difference */
						_x( '%s ago', '%s = human-readable time difference', 'multipos-for-fluentcart' ),
						human_time_diff( $order_timestamp, time() )
					);
				} else {
					$show_date = wp_date( __( 'M j, Y', 'multipos-for-fluentcart' ), $order_timestamp );
				}

				$date = sprintf(
					'<time datetime="%1$s" title="%2$s">%3$s</time>',
					esc_attr( $order->get_date_created()->format( 'c' ) ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order_timestamp ) ),
					esc_html( $show_date )
				);

				if ( $order->get_payment_method_title() ) {
					/* translators: %s: method */
					$total = '<span class="tips" data-tip="' . esc_attr( sprintf( __( 'via %s', 'multipos-for-fluentcart' ), $order->get_payment_method_title() ) ) . '">' . wp_kses_post( $order->get_formatted_order_total() ) . '</span>';
				} else {
					$total = wp_kses_post( $order->get_formatted_order_total() );
				}

				$outlet      = $this->outlet_helper->ddfcpos_get_outlet_details_by_id( $order->get_meta( '_ddfcpos_outlet_id', true ) );
				$outlet      = $outlet ? $outlet : [ 'name' => '' ];

				$cashier_id  = $order->get_meta( '_ddfcpos_cashier_id', true );
				$cashier_obj = get_user_by( 'ID', $cashier_id );
				$cashier     = $cashier_obj ? ( ! empty( $cashier_obj->display_name ) ? $cashier_obj->display_name : $cashier_obj->user_login ) : '';

				$data[] = [
					'id'               => $order_id,
					'items'            => $order->get_item_count(),
					'refunded'         => (float) $order->get_total_refunded(),
					'order'            => $order_html,
					'date'             => $date,
					'status'           => $status,
					'total'            => $total,
					'outlet'           => $outlet[ 'name' ],
					'cashier'          => $cashier,
					'cashier_id'       => (int) $cashier_id,
					'payment_method'   => $order->get_payment_method_title(),
				];
			}

			return apply_filters( 'ddfcpos/admin/orders/rows', $data );
		}

        /**
		 * Associative array of columns
		 *
		 * @return array
		 */
		public function get_columns() {
			return apply_filters( 'ddfcpos/admin/orders/columns', [
				'order'       => esc_html__( 'Order', 'multipos-for-fluentcart' ),
				'status_date' => esc_html__( 'Status & Date', 'multipos-for-fluentcart' ),
				'revenue'     => esc_html__( 'Revenue', 'multipos-for-fluentcart' ),
				'source'      => esc_html__( 'Staff Attribution', 'multipos-for-fluentcart' ),
			] );
		}

        /**
		 * No items
		 *
		 * @return void
		 */
		public function no_items() {
			ddfw_print_empty_state( [
				'title'       => esc_html__( 'No orders available', 'multipos-for-fluentcart' ),
				'description' => esc_html__( 'POS orders will be listed here once a sale is made.', 'multipos-for-fluentcart' ),
			] );
		}

		/**
		 * Hidden Columns
		 *
		 * @return array
		 */
		public function get_hidden_columns() {
			return [];
		}

        /**
		 * Render a column when no column specific method exists.
		 *
		 * @param array  $item Items.
		 * @param string $column_name Name.
		 *
		 * @return mixed
		 */
		public function column_default( $item, $column_name ) {
			if ( array_key_exists( $column_name, $item ) ) {
				return $item[ $column_name ];
			}

			return '';
		}

        /**
		 * Usort
		 *
		 * @param int $first First value.
		 * @param int $second Second value.
		 * @return $result
		 */
		public function usort_reorder( $first, $second ) {
			$orderby = ! empty( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list sorting.
			$order   = ! empty( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list sorting.

			// An unknown column sorts nothing instead of raising an undefined index warning.
			$result = strnatcmp( (string) ( $first[ $orderby ] ?? '' ), (string) ( $second[ $orderby ] ?? '' ) );
			return $order === 'asc' ? $result : -$result;
        }

        /**
		 * Columns to make sortable.
		 *
		 * @return array
		 */
		public function get_sortable_columns() {
			$sortable_columns = [
				'order'       => [ 'order', true ],
				'status_date' => [ 'status', true ],
				'revenue'     => [ 'total', true ],
			];

			return apply_filters( 'ddfcpos/admin/orders/sortable_columns', $sortable_columns );
		}

		/**
		 * Order Column
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_order( $item ) {
			return sprintf(
				'<div class="ddfcpos-order-id-column">
					<div class="ddfcpos-order-main">
						%1$s
					</div>
					<div class="ddfcpos-offline-id-label">
						%2$s
					</div>
				</div>',
				$item[ 'order' ],
				/* translators: %d: number of items in the order. */
				esc_html( sprintf( _n( '%d item', '%d items', $item['items'], 'multipos-for-fluentcart' ), $item['items'] ) )
			);
		}

		/**
		 * Status & Date Column
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_status_date( $item ) {
			return sprintf(
				'<div class="ddfcpos-status-timeline-column">
					<div class="ddfcpos-status-tier">
						%1$s
					</div>
					<div class="ddfcpos-date-tier">
						%2$s
					</div>
				</div>',
				$item[ 'status' ],
				$item[ 'date' ]
			);
		}

		/**
		 * Revenue Column
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_revenue( $item ) {
			return sprintf(
				'<div class="ddfcpos-revenue-column">
					<div class="ddfcpos-total-amount">
						%1$s
					</div>
					<div class="ddfcpos-payment-method">
						%2$s
					</div>
				</div>',
				$item[ 'total' ],
				/* translators: %s: payment method name. */
				( ! empty( $item[ 'payment_method' ] ) ? esc_html( sprintf( __( 'via %s', 'multipos-for-fluentcart' ), $item[ 'payment_method' ] ) ) : '' )
				/* translators: %s: refunded amount. */
				. ( $item['refunded'] > 0 ? '<span class="ddfcpos-order-refunded">' . wp_kses_post( sprintf( __( 'Refunded %s', 'multipos-for-fluentcart' ), DDFCPOS_FluentCart_Helper::format_price( $item['refunded'] ) ) ) . '</span>' : '' )
			);
		}

		/**
		 * Source Column
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_source( $item ) {
			return DDFCPOS_Cashier_Helper::ddfcpos_get_attribution_html( $item['cashier_id'], $item['cashier'], $item['outlet'] );
		}

		/**
		 * Product Stocks List Filters
		 *
		 * @param string $which Position of filter.
		 */
		public function extra_tablenav( $which ) {
			if ( 'top' !== $which ) {
				return;
			}

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
			$cashier_id = ! empty( $_GET['cashier-id'] ) ? absint( $_GET['cashier-id'] ) : 0;
			$from       = ! empty( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
			$to         = ! empty( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
			$page       = ! empty( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'ddfcpos-dashboard';
			// phpcs:enable

			$cashiers = get_users(
				[
					'role__in' => apply_filters( 'ddfcpos/access/allowed_roles', [ 'ddfcpos_cashier', 'administrator' ] ),
					'fields'   => [ 'ID', 'display_name' ],
					'orderby'  => 'display_name',
				]
			);
			?>
			<div class="alignleft actions ddfcpos-orders-filters">
				<select name="cashier-id" data-placeholder="<?php esc_attr_e( 'All cashiers', 'multipos-for-fluentcart' ); ?>">
					<option value=""><?php esc_html_e( 'All cashiers', 'multipos-for-fluentcart' ); ?></option>
					<?php foreach ( $cashiers as $cashier ) : ?>
						<option value="<?php echo esc_attr( $cashier->ID ); ?>" <?php selected( $cashier_id, (int) $cashier->ID ); ?>><?php echo esc_html( $cashier->display_name ); ?></option>
					<?php endforeach; ?>
				</select>

				<label for="ddfcpos-order-from-date"><?php esc_html_e( 'From:', 'multipos-for-fluentcart' ); ?></label>
				<input type="date" name="from" id="ddfcpos-order-from-date" class="ddfcpos-datepicker" value="<?php echo esc_attr( $from ); ?>" />
				<label for="ddfcpos-order-to-date"><?php esc_html_e( 'To:', 'multipos-for-fluentcart' ); ?></label>
				<input type="date" name="to" id="ddfcpos-order-to-date" class="ddfcpos-datepicker" value="<?php echo esc_attr( $to ); ?>" />

				<input type="submit" value="<?php esc_attr_e( 'Filter', 'multipos-for-fluentcart' ); ?>" name="ddfcpos_filter_submit" class="button" />

				<?php if ( $cashier_id || $from || $to ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page . '&menu=orders' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'multipos-for-fluentcart' ); ?></a>
				<?php endif; ?>
			</div>
			<?php
		}
    }
}
