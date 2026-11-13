<?php
/**
 * Transactions List Template
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Transaction;

use DDFCMultiPOS\Helper\Error\DDFCPOS_Error_Helper;
use DDFCMultiPOS\Helper\Transaction\DDFCPOS_Transaction_Helper;
use DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper;
use DDFCMultiPOS\Helper\Cashier\DDFCPOS_Cashier_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Transactions_List_Template' ) ) {
	/**
	 * Transactions list class
	 */
	class DDFCPOS_Transactions_List_Template extends \WP_List_table {
		/**
		 * Error Helper Trait
		 */
		use DDFCPOS_Error_Helper;

		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddfcpos_configuration;

		/**
		 * Transactions Helper Variable
		 *
		 * @var object
		 */
		protected $transaction_helper;

		/**
		 * Outlet Helper Variable
		 *
		 * @var object
		 */
		protected $outlet_helper;

		/**
		 * Class constructor
		 */
		public function __construct( $ddfcpos_configuration ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;
			$this->transaction_helper    = new DDFCPOS_Transaction_Helper();
			$this->outlet_helper         = new DDFCPOS_Outlet_Helper();

			parent::__construct(
				array(
					'singular' => esc_html__( 'Transaction List', 'devdiggers-multipos-for-fluentcart' ),
					'plural'   => esc_html__( 'Transactions List', 'devdiggers-multipos-for-fluentcart' ),
					'ajax'     => false,
				)
			);
		}

		/**
		 * Prepare Items
		 *
		 * @return void
		 */
		public function prepare_items() {
			$this->_column_headers = $this->get_column_info();

			$http_host   = ! empty( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$request_uri = ! empty( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$current_url = set_url_scheme( 'http://' . $http_host . $request_uri );

			if ( strpos( $current_url, '_wp_http_referer' ) !== false ) {
				$new_url = remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), stripslashes( $current_url ) );
				wp_safe_redirect( $new_url );
				exit();
			}

			$this->process_bulk_action();
			$this->process_row_action();

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List table search is read-only.
			$search = ! empty( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

			$per_page     = $this->get_items_per_page( 'transactions_per_page', 20 );
			$current_page = $this->get_pagenum();
			$total_items  = $this->transaction_helper->ddfcpos_get_all_transactions_count( $search );

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
				)
			);

			$data = $this->ddfcpos_get_data( $per_page, $current_page, $search );

			usort( $data, array( $this, 'usort_reorder' ) );

			$this->items = $data;
		}

		/**
		 * Usort
		 *
		 * @param int $first First value.
		 * @param int $second Second value.
		 * @return $result
		 */
		public function usort_reorder( $first, $second ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List table sorting is read-only.
			$orderby = ! empty( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'id';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List table sorting is read-only.
			$order  = ! empty( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc';
			$result = strnatcmp( $first[ $orderby ], $second[ $orderby ] );

			return 'asc' === $order ? $result : -$result;
		}

		/**
		 * Fetch data
		 *
		 * @param int    $per_page Per Page.
		 * @param int    $current_page Page.
		 * @param string $search Search.
		 * @return array $data
		 */
		public function ddfcpos_get_data( $per_page, $current_page, $search = '' ) {
            $data = [];

            $offset = ( $current_page - 1 ) * $per_page;

            $transactions = $this->transaction_helper->ddfcpos_get_all_transactions( $per_page, $offset, $search );

            if ( ! empty( $transactions ) ) {
				global $wpdb;

				$date_format = get_option( 'date_format' );
				$time_format = get_option( 'time_format' );
				$order_ids   = array_filter( array_map( 'absint', wp_list_pluck( $transactions, 'order_id' ) ) );
				// Show the order number FluentCart shows ( its invoice number ), not the row ID.
				$numbers     = [];

				if ( $order_ids ) {
					$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- FluentCart table; one %d placeholder per ID.
					$numbers = $wpdb->get_results( $wpdb->prepare( "SELECT id, invoice_no FROM {$wpdb->prefix}fct_orders WHERE id IN ($placeholders)", $order_ids ), OBJECT_K );
				}

                foreach ( $transactions as $transaction ) {
                    $cashier_id     = $transaction[ 'cashier_id' ];
                    $cashier        = get_user_by( 'ID', $cashier_id );
                    $outlet_details = $this->outlet_helper->ddfcpos_get_outlet_details_by_id( $transaction[ 'outlet_id' ] );

					if ( 'opencash' === $transaction[ 'method' ] ) {
						$method = esc_html__( 'Open Cash Drawer Amount', 'devdiggers-multipos-for-fluentcart' );
					} elseif ( 'manual' === $transaction[ 'method' ] ) {
						$method = esc_html__( 'Manual', 'devdiggers-multipos-for-fluentcart' );
					} elseif ( 'split' === $transaction[ 'method' ] ) {
						$method = esc_html__( 'Split', 'devdiggers-multipos-for-fluentcart' );
					} elseif ( 'refund' === $transaction[ 'method' ] ) {
						$method = esc_html__( 'Refund', 'devdiggers-multipos-for-fluentcart' );
					} elseif ( 'refund_noncash' === $transaction[ 'method' ] ) {
						$method = esc_html__( 'Refund (not cash)', 'devdiggers-multipos-for-fluentcart' );
					} else {
						$method = array_filter( $this->ddfcpos_configuration[ 'payment_method' ], function( $payment_method ) use ( $transaction ) {
							return $payment_method[ 'slug' ] === $transaction[ 'method' ];
						} );

						$method = array_values( $method );

						$method = ! empty( $method ) ? $method[ 0 ][ 'name' ] : $transaction[ 'method' ];
					}

                    $data[] = [
                        'id'        => $transaction[ 'id' ],
                        'cashier'   => $cashier ? ( ! empty( $cashier->display_name ) ? $cashier->display_name : $cashier->user_login ) : '-',
                        'cashier_id'   => (int) $cashier_id,
                        'outlet'    => ! empty( $outlet_details['name'] ) ? $outlet_details['name'] : '',
                        'order_id'  => ! empty( $numbers[ $transaction['order_id'] ]->invoice_no ) ? $numbers[ $transaction['order_id'] ]->invoice_no : ( ! empty( $transaction[ 'order_id' ] ) ? $transaction[ 'order_id' ] : '' ),
                        'order_link' => ! empty( $transaction[ 'order_id' ] ) ? DDFCPOS_FluentCart_Helper::order_admin_url( $transaction[ 'order_id' ] ) : '',
                        'amount_in'  => $transaction[ 'amount_in' ],
                        'amount_out' => $transaction[ 'amount_out' ],
                        'method'    => $method,
                        'reference' => ! empty( $transaction[ 'reference' ] ) ? $transaction[ 'reference' ] : '',
                        'date'      => date_i18n( $date_format . ' ' . $time_format, strtotime( $transaction[ 'date' ] ) ),
					];
                }
            }

			return apply_filters( 'ddfcpos/admin/transactions/rows', $data );
		}

		/**
		 * Process bulk actions
		 *
		 * @return void
		 */
		public function process_bulk_action() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is sanitized and verified below.
			if ( ! empty( $_GET['ddfcpos_nonce'] ) && wp_unslash( $_GET['ddfcpos_nonce'] ) ) { // WPCS: CSRF ok. // WPCS: input var ok. // WPCS: sanitization ok.
				$nonce = sanitize_text_field( wp_unslash( $_GET['ddfcpos_nonce'] ) );
				if ( wp_verify_nonce( $nonce, 'ddfcpos_nonce_action' ) ) {
					if ( ! current_user_can( 'ddfcpos_manage' ) ) {
						$this->ddfcpos_print_notification( esc_html__( 'Insufficient permissions.', 'devdiggers-multipos-for-fluentcart' ), 'error' );
						return;
					}

					$action = $this->current_action();

					if ( in_array( $action, array( 'delete' ) ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
						if ( ! empty( $_GET['ddfcpos-id'] ) ) { // WPCS: input var ok.
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
							if ( is_array( $_GET['ddfcpos-id'] ) ) { // WPCS: input var ok.
								// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
								$ids = array_map( 'sanitize_text_field', wp_unslash( $_GET['ddfcpos-id'] ) ); // WPCS: input var ok.

								$success = $error = 0;

								foreach ( $ids as $id ) {
									$response = $this->transaction_helper->ddfcpos_delete_transaction( $id );
									if ( $response ) {
										++$success;
									} else {
										++$error;
									}
								}

								if ( $success ) {
									/* translators: %d: Number of transactions deleted. */
									$message = sprintf( esc_html__( '%d Transaction(s) deleted successfully.', 'devdiggers-multipos-for-fluentcart' ), $success );
									$this->ddfcpos_print_notification( $message );
								}

								if ( $error ) {
									/* translators: %d: Number of transactions not found. */
									$message = sprintf( esc_html__( '%d Transaction(s) not exits.', 'devdiggers-multipos-for-fluentcart' ), $error );
									$this->ddfcpos_print_notification( $message, 'error' );
								}
							}
						} else {
							$message = esc_html__( 'Select transaction(s) to delete.', 'devdiggers-multipos-for-fluentcart' );
							$this->ddfcpos_print_notification( $message, 'error' );
						}
					}
				} else {
					$message = esc_html__( 'Invalid nonce. Security check failed!!!', 'devdiggers-multipos-for-fluentcart' );
					$this->ddfcpos_print_notification( $message, 'error' );
				}
			}
		}

		/**
		 * Process row actions
		 *
		 * @return void
		 */
		public function process_row_action() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is sanitized and verified below.
			if ( ! empty( $_GET['ddfcpos_nonce'] ) && wp_unslash( $_GET['ddfcpos_nonce'] ) ) { // WPCS: CSRF ok. // WPCS: input var ok. // WPCS: sanitization ok.
				$nonce = sanitize_text_field( wp_unslash( $_GET['ddfcpos_nonce'] ) );
				if ( wp_verify_nonce( $nonce, 'ddfcpos_nonce_action' ) ) {
					if ( ! current_user_can( 'ddfcpos_manage' ) ) {
						$this->ddfcpos_print_notification( esc_html__( 'Insufficient permissions.', 'devdiggers-multipos-for-fluentcart' ), 'error' );
						return;
					}

					$action = $this->current_action();

					if ( in_array( $action, array( 'delete' ) ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
						if ( ! empty( $_GET['ddfcpos-id'] ) && ! is_array( $_GET['ddfcpos-id'] ) ) { // WPCS: input var ok.
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
							$id       = intval( wp_unslash( $_GET['ddfcpos-id'] ) );       // WPCS: input var ok.
							$response = $this->transaction_helper->ddfcpos_delete_transaction( $id );

							if ( $response ) {
								$message = esc_html__( 'Transaction is deleted successfully.', 'devdiggers-multipos-for-fluentcart' );
								$this->ddfcpos_print_notification( $message );
							} else {
								$message = esc_html__( 'Transaction not exists.', 'devdiggers-multipos-for-fluentcart' );
								$this->ddfcpos_print_notification( $message, 'error' );
							}
						}
					}
				} else {
					$message = esc_html__( 'Invalid nonce. Security check failed!!!', 'devdiggers-multipos-for-fluentcart' );
					$this->ddfcpos_print_notification( $message, 'error' );
				}
			}
		}

		/**
		 *  No items
		 *
		 * @return void
		 */
		public function no_items() {
			ddfw_print_empty_state(
				array(
					'title'       => esc_html__( 'No transactions available', 'devdiggers-multipos-for-fluentcart' ),
					'description' => esc_html__( 'Cash and payment transactions will be listed here.', 'devdiggers-multipos-for-fluentcart' ),
				)
			);
		}

		/**
		 * Hidden Columns
		 *
		 * @return array
		 */
		public function get_hidden_columns() {
			return array();
		}

		/**
		 *  Associative array of columns
		 *
		 * @return array
		 */
		public function get_columns() {
			return apply_filters(
				'ddfcpos_transactions_list_columns',
				array(
					'cb'             => '<input type="checkbox" />',
					'identification' => esc_html__( 'ID & Date', 'devdiggers-multipos-for-fluentcart' ),
					'attribution'    => esc_html__( 'Staff Attribution', 'devdiggers-multipos-for-fluentcart' ),
					'context'        => esc_html__( 'Transaction Context', 'devdiggers-multipos-for-fluentcart' ),
					'amount'         => esc_html__( 'Financial Totals', 'devdiggers-multipos-for-fluentcart' ),
				)
			);
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
		 * Columns to make sortable.
		 *
		 * @return array
		 */
		public function get_sortable_columns() {
			return apply_filters(
				'ddfcpos_transactions_list_sortable_columns',
				array(
					'identification' => array( 'id', true ),
					'amount'         => array( 'in', true ),
				)
			);
		}

		/**
		 * Render the bulk edit checkbox
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_cb( $item ) {
			return sprintf( '<input type="checkbox" name="ddfcpos-id[]" value="%d" />', esc_attr( $item['id'] ) );
		}

		public function column_identification( $item ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Value is used only to build admin action links.
			$search       = ! empty( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			$current_page = $this->get_pagenum();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Value is used only to build admin action links.
			$page = ! empty( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';

			$actions = array(
				'delete' => sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . $page . '&action=delete&s=' . $search . '&paged=' . $current_page . '&ddfcpos-id=' . $item['id'] ), 'ddfcpos_nonce_action', 'ddfcpos_nonce' ) ), esc_html__( 'Delete', 'devdiggers-multipos-for-fluentcart' ) ),
			);

			return sprintf(
				'<div class="ddfcpos-transaction-id-column">
					<div class="ddfcpos-transaction-id">#%1$d</div>
					<div class="ddfcpos-transaction-date">%2$s</div>
					%3$s
				</div>',
				$item['id'],
				$item['date'],
				$this->row_actions( apply_filters( 'ddfcpos_transactions_list_line_actions', $actions ) )
			);
		}

		/**
		 * Attribution Column
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_attribution( $item ) {
			return DDFCPOS_Cashier_Helper::ddfcpos_get_attribution_html( $item['cashier_id'], '-' !== $item['cashier'] ? $item['cashier'] : '', $item['outlet'] );
		}

		/**
		 * Context Column
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_context( $item ) {
			$order_html = ! empty( $item['order_link'] ) ? sprintf( '<a href="%1$s">#%2$s</a>', esc_url( $item['order_link'] ), esc_html( $item['order_id'] ) ) : '-';

			return sprintf(
				'<div class="ddfcpos-transaction-context-column">
					<div class="ddfcpos-method-tier">
						<mark class="ddfcpos-method-badge method-%1$s">%2$s</mark>
					</div>
					<div class="ddfcpos-order-tier">
						<span class="ddfcpos-label-mini">%3$s</span> %4$s
					</div>
					<div class="ddfcpos-reference-tier">
						<span class="ddfcpos-label-mini">%5$s</span> <em>%6$s</em>
					</div>
				</div>',
				sanitize_title( $item['method'] ),
				esc_html( $item['method'] ),
				esc_html__( 'Order:', 'devdiggers-multipos-for-fluentcart' ),
				$order_html,
				esc_html__( 'Ref:', 'devdiggers-multipos-for-fluentcart' ),
				! empty( $item['reference'] ) ? esc_html( $item['reference'] ) : '-'
			);
		}

		/**
		 * Amount Column
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_amount( $item ) {
			return sprintf(
				'<div class="ddfcpos-transaction-amount-column">
					<div class="ddfcpos-amount-row amount-in">
						<span class="ddfcpos-label-mini">%1$s</span>
						<strong>%2$s</strong>
					</div>
					<div class="ddfcpos-amount-row amount-out">
						<span class="ddfcpos-label-mini">%3$s</span>
						<span>%4$s</span>
					</div>
				</div>',
				esc_html__( 'In:', 'devdiggers-multipos-for-fluentcart' ),
				DDFCPOS_FluentCart_Helper::format_price( $item['amount_in'] ),
				esc_html__( 'Out:', 'devdiggers-multipos-for-fluentcart' ),
				DDFCPOS_FluentCart_Helper::format_price( $item['amount_out'] )
			);
		}

		/**
		 * Bulk actions on list.
		 *
		 * @return array
		 */
		public function get_bulk_actions() {
			return apply_filters(
				'ddfcpos_modify_bulk_actions_in_transactions',
				array(
					'delete' => esc_html__( 'Delete', 'devdiggers-multipos-for-fluentcart' ),
				)
			);
		}

		/**
		 * Transactions List Filters
		 *
		 * @param string $which Position of filter.
		 */
		public function extra_tablenav( $which ) {
			if ( 'top' === $which ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filters are read-only.
				$transaction_from_date = ! empty( $_GET['transaction-from-date'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction-from-date'] ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filters are read-only.
				$transaction_to_date = ! empty( $_GET['transaction-to-date'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction-to-date'] ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filters are read-only.
				$cashier_filter_id = ! empty( $_GET['cashier-id'] ) ? absint( wp_unslash( $_GET['cashier-id'] ) ) : 0;
				?>
				<div class="alignleft actions bulkactions">
					<label for="ddfcpos-transaction-from-date"><?php esc_html_e( 'From:', 'devdiggers-multipos-for-fluentcart' ); ?></label>
					<input type="date" value="<?php echo esc_attr( $transaction_from_date ); ?>" name="transaction-from-date" id="ddfcpos-transaction-from-date" class="ddfcpos-datepicker" placeholder="yyyy-mm-dd" autocomplete="off" />

					<label for="ddfcpos-transaction-to-date"><?php esc_html_e( 'To:', 'devdiggers-multipos-for-fluentcart' ); ?></label>
					<input type="date" value="<?php echo esc_attr( $transaction_to_date ); ?>" name="transaction-to-date" id="ddfcpos-transaction-to-date" class="ddfcpos-datepicker" placeholder="yyyy-mm-dd" autocomplete="off" />

					<select name="cashier-id" data-placeholder="<?php esc_attr_e( 'Select Cashier', 'devdiggers-multipos-for-fluentcart' ); ?>">
						<option value=""><?php esc_html_e( 'Select Cashier', 'devdiggers-multipos-for-fluentcart' ); ?></option>
						<?php
						$query = new \WP_User_Query(
							array(
								'role__in'       => apply_filters( 'ddfcpos/access/allowed_roles', array( 'ddfcpos_cashier', 'administrator' ) ),
								'order'          => 'DESC',
								'orderby'        => 'ID',
								'search_columns' => array( 'user_nicename', 'ID', 'user_login', 'user_email' ),
								'fields'         => array( 'ID', 'user_login', 'user_email' ),
							)
						);

						$cashiers = $query->get_results();

						if ( ! empty( $cashiers ) ) {
							foreach ( $cashiers as $cashier ) {
								$cashier_id           = $cashier->ID;
								$cashier_option_value = "(#{$cashier_id}) {$cashier->user_login} <{$cashier->user_email}>";
								?>
								<option value="<?php echo esc_attr( $cashier_id ); ?>" <?php selected( $cashier_id, $cashier_filter_id ); ?>><?php echo esc_html( $cashier_option_value ); ?></option>
								<?php
							}
						}
						?>
					</select>

					<input type="submit" value="<?php esc_attr_e( 'Filter', 'devdiggers-multipos-for-fluentcart' ); ?>" name="ddfcpos_filter_submit" class="button" />

					<?php
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reset link is shown for read-only filters.
					if ( ! empty( $_GET['ddfcpos_filter_submit'] ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Values are used only to build reset URL.
						$page = ! empty( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Values are used only to build reset URL.
						$menu = ! empty( $_GET['menu'] ) ? sanitize_text_field( wp_unslash( $_GET['menu'] ) ) : '';
						?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page . '&menu=' . $menu ) ); ?>" name="ddfcpos_filter_reset" class="button"><?php esc_html_e( 'Reset', 'devdiggers-multipos-for-fluentcart' ); ?></a>
						<?php
					}
					?>
				</div>
				<?php
			}
		}
	}
}
