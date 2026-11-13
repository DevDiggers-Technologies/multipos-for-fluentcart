<?php
/**
 * Cashiers List Template
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Cashier;

use DDFCMultiPOS\Helper\Cashier\DDFCPOS_Cashier_Helper;
use DDFCMultiPOS\Helper\Error\DDFCPOS_Error_Helper;
use DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper;
use DDFCMultiPOS\Helper\Report\DDFCPOS_Activity_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Cashiers_List_Template' ) ) {
	/**
	 * Cashiers list class
	 */
	class DDFCPOS_Cashiers_List_Template extends \WP_List_table {
		/**
		 * Error Helper Trait
		 */
		use DDFCPOS_Error_Helper;

		/**
		 * Class constructor
		 */
		public function __construct() {
			parent::__construct(
				array(
					'singular' => esc_html__( 'Cashier List', 'devdiggers-multipos-for-fluentcart' ),
					'plural'   => esc_html__( 'Cashiers List', 'devdiggers-multipos-for-fluentcart' ),
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

			$per_page     = $this->get_items_per_page( 'cashiers_per_page', 20 );
			$current_page = $this->get_pagenum();
			$total_items  = $this->ddfcpos_cashiers_count( $search );

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
				)
			);

			$data = $this->ddfcpos_get_cashiers( $per_page, $current_page, $search );

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
		 * Record count
		 *
		 * @param string $search Search.
		 * @return $count
		 */
		public function ddfcpos_cashiers_count( $search = '' ) {
			$user_query = new \WP_User_Query(
				array(
					'role__in'       => apply_filters( 'ddfcpos/access/allowed_roles', array( 'ddfcpos_cashier', 'administrator' ) ),
					'search'         => '*' . esc_attr( $search ) . '*',
					'search_columns' => array( 'user_nicename', 'ID', 'user_login', 'user_email' ),
				)
			);

			wp_reset_postdata();

			return $user_query->get_total();
		}

		/**
		 * Fetch Users
		 *
		 * @param int    $per_page Per Page.
		 * @param int    $current_page Page.
		 * @param string $search Search.
		 * @return array $users
		 */
		public function ddfcpos_get_cashiers( $per_page, $current_page, $search = '' ) {
			$data = array();

			$off = ( $current_page - 1 ) * $per_page;

			$query = new \WP_User_Query(
				array(
					'role__in'       => apply_filters( 'ddfcpos/access/allowed_roles', array( 'ddfcpos_cashier', 'administrator' ) ),
					'number'         => $per_page,
					'offset'         => $off,
					'order'          => 'DESC',
					'orderby'        => 'ID',
					'search'         => '*' . esc_attr( $search ) . '*',
					'search_columns' => array( 'user_nicename', 'ID', 'user_login', 'user_email' ),
					'fields'         => array( 'ID', 'user_login', 'user_email' ),
				)
			);

			wp_reset_postdata();

			$cashiers = $query->get_results();

			if ( ! empty( $cashiers ) ) {
				global $wp_roles;
				$outlet_helper = new DDFCPOS_Outlet_Helper();
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page slug is read-only link context.
				$page = ! empty( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

				$assigned_outlet = $outlet_helper->ddfcpos_get_outlet_option();
				$activity        = new DDFCPOS_Activity_Helper();
				$sales           = $activity->ddfcpos_get_sales_by( 'cashier_id', gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - 29 * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Ledger dates are local time.

				foreach ( $cashiers as $cashier ) {
					$cashier_id = $cashier->ID;
					$user_data  = get_userdata( $cashier_id );

					$roles = array_map(
						function ( $role ) use ( $wp_roles ) {
							return $wp_roles->roles[ $role ]['name'];
						},
						$user_data->roles
					);

					$data[] = array(
						'id'              => $cashier_id,
						'username'        => $cashier->user_login,
						'email'           => $cashier->user_email,
						'role'            => implode( ', ', $roles ),
						'assigned_outlet' => ! empty( $assigned_outlet['name'] ) ? $assigned_outlet['name'] : 'N/A',
						'name'            => ! empty( $user_data->display_name ) ? $user_data->display_name : $cashier->user_login,
						'sales'           => isset( $sales[ $cashier_id ] ) ? $sales[ $cashier_id ] : array(),
					);
				}
			}

			return apply_filters( 'ddfcpos_cashiers_list_data', $data );
		}

		/**
		 * Process bulk actions
		 *
		 * @return void
		 */
		public function process_bulk_action() {
			$nonce = isset( $_GET['ddfcpos_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['ddfcpos_nonce'] ) ) : '';

			if ( wp_verify_nonce( $nonce, 'ddfcpos_nonce_action' ) ) {
				$action = $this->current_action();

				if ( in_array( $action, array( 'delete' ), true ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by the inline wp_verify_nonce() check above.
					if ( ! empty( $_GET['ddfcpos-id'] ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by the inline wp_verify_nonce() check above.
						if ( is_array( $_GET['ddfcpos-id'] ) ) {
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by the inline wp_verify_nonce() check above.
							$ids     = array_map( 'absint', wp_unslash( $_GET['ddfcpos-id'] ) );
							$success = $error = 0;

							foreach ( $ids as $id ) {
								// Never the account doing the deleting, or an administrator from a cashier list.
								if ( ! current_user_can( 'delete_user', $id ) || get_current_user_id() === $id || user_can( $id, 'manage_options' ) ) {
									++$error;
									continue;
								}

								$response = get_current_user_id() !== $id && ! user_can( $id, 'manage_options' ) && current_user_can( is_multisite() ? 'remove_user' : 'delete_user', $id ) ? wp_delete_user( $id ) : false;
								if ( $response ) {
									++$success;
								} else {
									++$error;
								}
							}

							if ( $success ) {
								/* translators: %d: Number of cashiers deleted. */
								$message = sprintf( esc_html__( '%d cashier(s) deleted successfully.', 'devdiggers-multipos-for-fluentcart' ), $success );
								$this->ddfcpos_print_notification( $message );
							}

							if ( $error ) {
								/* translators: %d: number of accounts. */
								$message = sprintf( esc_html__( '%d account(s) could not be deleted. Administrators and your own account are skipped.', 'devdiggers-multipos-for-fluentcart' ), $error );
								$this->ddfcpos_print_notification( $message, 'error' );
							}
						}
					} else {
						$message = esc_html__( 'Select cashier(s) to delete.', 'devdiggers-multipos-for-fluentcart' );
						$this->ddfcpos_print_notification( $message, 'error' );
					}
				}
			}
		}

		/**
		 * Process row actions
		 *
		 * @return void
		 */
		public function process_row_action() {
			$nonce = isset( $_GET['ddfcpos_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['ddfcpos_nonce'] ) ) : '';

			if ( wp_verify_nonce( $nonce, 'ddfcpos_nonce_action' ) ) {
				$action = $this->current_action();

				if ( in_array( $action, array( 'delete' ), true ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by the inline wp_verify_nonce() check above.
					if ( ! empty( $_GET['ddfcpos-id'] ) && ! is_array( $_GET['ddfcpos-id'] ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by the inline wp_verify_nonce() check above.
						$id = absint( wp_unslash( $_GET['ddfcpos-id'] ) );
						if ( ! current_user_can( 'delete_user', $id ) || get_current_user_id() === $id || user_can( $id, 'manage_options' ) ) {
							$this->ddfcpos_print_notification( esc_html__( 'Insufficient permissions.', 'devdiggers-multipos-for-fluentcart' ), 'error' );
							return;
						}

						$response = get_current_user_id() !== $id && ! user_can( $id, 'manage_options' ) && current_user_can( is_multisite() ? 'remove_user' : 'delete_user', $id ) ? wp_delete_user( $id ) : false;

						if ( $response ) {
							$message = esc_html__( 'Cashier is deleted successfully.', 'devdiggers-multipos-for-fluentcart' );
							$this->ddfcpos_print_notification( $message );
						} else {
							$message = esc_html__( 'This account could not be deleted. Administrators and your own account are skipped.', 'devdiggers-multipos-for-fluentcart' );
							$this->ddfcpos_print_notification( $message, 'error' );
						}
					}
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
					'title'       => esc_html__( 'No cashiers available', 'devdiggers-multipos-for-fluentcart' ),
					'description' => esc_html__( 'Cashiers you create will be listed here.', 'devdiggers-multipos-for-fluentcart' ),
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
			$columns = array(
				'cb'              => '<input type="checkbox" />',
				'id'              => esc_html__( 'ID', 'devdiggers-multipos-for-fluentcart' ),
				'username'        => esc_html__( 'Username', 'devdiggers-multipos-for-fluentcart' ),
				'role'            => esc_html__( 'Role', 'devdiggers-multipos-for-fluentcart' ),
				'assigned_outlet' => esc_html__( 'Assigned Outlet', 'devdiggers-multipos-for-fluentcart' ),
				'sales'           => esc_html__( 'Last 30 Days', 'devdiggers-multipos-for-fluentcart' ),
				'last_active'     => esc_html__( 'Last Sale', 'devdiggers-multipos-for-fluentcart' ),
			);

			return apply_filters( 'ddfcpos_cashiers_list_columns', $columns );
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
				'ddfcpos_cashiers_list_sortable_columns',
				array(
					'id'       => array( 'id', true ),
					'username' => array( 'username', true ),
					'role'     => array( 'role', true ),
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

		public function column_username( $item ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Values are used only to build admin action links.
			$page = ! empty( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Values are used only to build admin action links.
			$menu = ! empty( $_GET['menu'] ) ? sanitize_text_field( wp_unslash( $_GET['menu'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Values are used only to build admin action links.
			$search       = ! empty( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			$current_page = $this->get_pagenum();

			$actions = array(
				'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( DDFCPOS_Cashier_Helper::ddfcpos_get_edit_url( $item['id'] ) ), esc_html__( 'Edit', 'devdiggers-multipos-for-fluentcart' ) ),
				'delete' => sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'admin.php?page=' . $page . '&menu=' . $menu . '&action=delete&s=' . $search . '&paged=' . $current_page . '&ddfcpos-id=' . $item['id'] ), 'ddfcpos_nonce_action', 'ddfcpos_nonce' ), esc_html__( 'Delete', 'devdiggers-multipos-for-fluentcart' ) ),
			);

			// Without the right to edit this user the link leads nowhere, so it is not offered.
			if ( ! current_user_can( 'edit_user', (int) $item['id'] ) ) {
				unset( $actions['edit'] );
			}

			// Deleting is WordPress's own right to remove this user, never just access to the POS screens.
			if ( get_current_user_id() === (int) $item['id'] || user_can( (int) $item['id'], 'manage_options' ) || ! current_user_can( is_multisite() ? 'remove_user' : 'delete_user', (int) $item['id'] ) ) {
				unset( $actions['delete'] );
			}

			return sprintf(
				'<div class="ddfcpos-cashier-cell">%1$s<div><strong><a class="row-title" href="%2$s">%3$s</a></strong><small>%4$s</small>%5$s</div></div>',
				get_avatar( $item['id'], 36 ),
				esc_url( DDFCPOS_Cashier_Helper::ddfcpos_get_edit_url( $item['id'] ) ),
				esc_html( $item['name'] ),
				esc_html( $item['username'] === $item['name'] ? $item['email'] : $item['username'] . ' · ' . $item['email'] ),
				$this->row_actions( apply_filters( 'ddfcpos_cashiers_list_line_actions', $actions ) )
			);
		}

		/**
		 * Column Last 30 Days.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_sales( $item ) {
			$sales  = ! empty( $item['sales']['sales'] ) ? $item['sales']['sales'] : 0;
			$orders = ! empty( $item['sales']['orders'] ) ? $item['sales']['orders'] : 0;

			return sprintf(
				'<strong>%1$s</strong><span class="ddfcpos-list-meta">%2$s</span>',
				wp_kses_post( DDFCPOS_FluentCart_Helper::format_price( $sales ) ),
				/* translators: %d: number of orders. */
				esc_html( sprintf( _n( '%d order', '%d orders', $orders, 'devdiggers-multipos-for-fluentcart' ), $orders ) )
			);
		}

		/**
		 * Column Last Sale: spots an account nobody uses any more.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_last_active( $item ) {
			if ( empty( $item['sales']['last_sale'] ) ) {
				return '<span class="ddfcpos-list-meta">' . esc_html__( 'No sales yet', 'devdiggers-multipos-for-fluentcart' ) . '</span>';
			}

			$time = strtotime( $item['sales']['last_sale'] );

			return sprintf(
				'%1$s<span class="ddfcpos-list-meta">%2$s</span>',
				/* translators: %s: time since the last sale, like 2 hours. */
				esc_html( sprintf( __( '%s ago', 'devdiggers-multipos-for-fluentcart' ), human_time_diff( $time, current_time( 'timestamp' ) ) ) ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Ledger dates are local time.
				esc_html( date_i18n( get_option( 'date_format' ), $time ) )
			);
		}

		/**
		 * Column ID
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_id( $item ) {
			return $item['id'];
		}

		/**
		 * Bulk actions on list.
		 *
		 * @return array
		 */
		public function get_bulk_actions() {
			return apply_filters(
				'ddfcpos_modify_bulk_actions_in_cashiers',
				array(
					'delete' => esc_html__( 'Delete', 'devdiggers-multipos-for-fluentcart' ),
				)
			);
		}
	}
}
