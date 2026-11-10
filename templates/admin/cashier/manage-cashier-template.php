<?php
/**
 * Manage Cashier Template
 *
 * Add or edit a cashier on one screen, with their recent sales beside it. The cashier stays an
 * ordinary WordPress user, so the full profile is one click away.
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Cashier;

use DevDiggers\Framework\Includes\DDFW_Layout;
use DDFCMultiPOS\Helper\Cashier\DDFCPOS_Cashier_Helper;
use DDFCMultiPOS\Helper\Error\DDFCPOS_Error_Helper;
use DDFCMultiPOS\Helper\Report\DDFCPOS_Activity_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Manage_Cashier_Template' ) ) {
	/**
	 * Manage Cashier Template class
	 */
	class DDFCPOS_Manage_Cashier_Template {
		/**
		 * Error Helper Trait
		 */
		use DDFCPOS_Error_Helper;

		/**
		 * Cashier being edited, null when adding.
		 *
		 * @var \WP_User|null
		 */
		protected $cashier;

		/**
		 * Values posted with a failed save, so the form keeps what was typed.
		 *
		 * @var array
		 */
		protected $posted = array();

		/**
		 * Construct
		 */
		public function __construct() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only.
			$id = ! empty( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

			if ( $id ) {
				if ( ! DDFCPOS_Cashier_Helper::ddfcpos_is_cashier( $id ) || ! current_user_can( 'edit_user', $id ) ) {
					ddfw_print_empty_state(
						array(
							'title'       => esc_html__( 'Cashier not found', 'multipos-for-fluentcart' ),
							'description' => esc_html__( 'This user does not exist or is not a MultiPOS cashier. Administrators and shop managers are edited from the WordPress Users screen.', 'multipos-for-fluentcart' ),
							'button_url'  => admin_url( 'admin.php?page=ddfcpos-dashboard&menu=cashiers' ),
							'button_text' => esc_html__( 'Back to cashiers', 'multipos-for-fluentcart' ),
						)
					);
					return;
				}

				$this->cashier = get_userdata( $id );
			}

			$this->ddfcpos_save_cashier();
			$this->ddfcpos_print_cashier_template();
		}

		/**
		 * Save the posted cashier.
		 *
		 * @return void
		 */
		protected function ddfcpos_save_cashier() {
			if ( empty( $_POST['ddfcpos_save_cashier_submit'] ) ) {
				return;
			}

			if ( empty( $_POST['ddfcpos_save_cashier_submit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddfcpos_save_cashier_submit_nonce'] ) ), 'ddfcpos_save_cashier_submit_nonce_action' ) ) {
				$this->ddfcpos_print_notification( esc_html__( 'Security check failed or the session expired. Please reload the page and try again.', 'multipos-for-fluentcart' ), 'error' );
				return;
			}

			$id = $this->cashier ? $this->cashier->ID : 0;

			// Same rule as the WordPress Users screen: you may only hand out a role you are allowed to edit.
			$editable = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();

			if ( $id && ! current_user_can( 'edit_user', $id ) ) {
				$this->ddfcpos_print_notification( esc_html__( 'You are not allowed to manage cashiers.', 'multipos-for-fluentcart' ), 'error' );
				return;
			}

			if ( ! $id && ( ! current_user_can( 'create_users' ) || ! isset( $editable[ DDFCPOS_Cashier_Helper::ROLE ] ) ) ) {
				$this->ddfcpos_print_notification( esc_html__( 'You are not allowed to manage cashiers.', 'multipos-for-fluentcart' ), 'error' );
				return;
			}

			$this->posted = array(
				'id'         => $id,
				'username'   => isset( $_POST['ddfcpos_cashier_username'] ) ? sanitize_user( wp_unslash( $_POST['ddfcpos_cashier_username'] ), true ) : '',
				'email'      => isset( $_POST['ddfcpos_cashier_email'] ) ? sanitize_email( wp_unslash( $_POST['ddfcpos_cashier_email'] ) ) : '',
				'first_name' => isset( $_POST['ddfcpos_cashier_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_cashier_first_name'] ) ) : '',
				'last_name'  => isset( $_POST['ddfcpos_cashier_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_cashier_last_name'] ) ) : '',
				// Passwords are taken as typed; WordPress hashes them and sanitizing would change them.
				'password'   => isset( $_POST['ddfcpos_cashier_password'] ) ? (string) wp_unslash( $_POST['ddfcpos_cashier_password'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'send_link'  => ! empty( $_POST['ddfcpos_cashier_send_link'] ),
			);

			$user_id = ( new DDFCPOS_Cashier_Helper() )->ddfcpos_save_cashier( $this->posted );

			if ( is_wp_error( $user_id ) ) {
				$this->ddfcpos_print_notification( implode( '<br />', array_map( 'esc_html', $user_id->get_error_messages() ) ), 'error' );
				return;
			}

			wp_safe_redirect( admin_url( 'admin.php?page=ddfcpos-dashboard&menu=cashiers&action=edit&id=' . $user_id . '&saved=' . ( $id ? 'updated' : 'created' ) ) );
			exit();
		}

		/**
		 * A posted value after a failed save, otherwise the stored one.
		 *
		 * @param string $key           Posted key.
		 * @param mixed  $default_value Stored value.
		 * @return mixed
		 */
		protected function ddfcpos_value( $key, $default_value ) {
			return array_key_exists( $key, $this->posted ) ? $this->posted[ $key ] : $default_value;
		}

		/**
		 * Print the screen.
		 *
		 * @return void
		 */
		protected function ddfcpos_print_cashier_template() {
			$cashier = $this->cashier;
			$id      = $cashier ? $cashier->ID : 0;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			$saved = ! empty( $_GET['saved'] ) ? sanitize_key( $_GET['saved'] ) : '';

			if ( 'created' === $saved ) {
				$this->ddfcpos_print_notification( esc_html__( 'Cashier created. They can log in to the POS with their username or email.', 'multipos-for-fluentcart' ) );
			} elseif ( 'updated' === $saved ) {
				$this->ddfcpos_print_notification( esc_html__( 'Cashier updated.', 'multipos-for-fluentcart' ) );
			}

			$account_fields = array(
				array(
					'type'        => 'text',
					'label'       => esc_html__( 'First Name', 'multipos-for-fluentcart' ),
					'description' => esc_html__( 'Shown on receipts, reports and the POS header.', 'multipos-for-fluentcart' ),
					'value'       => $this->ddfcpos_value( 'first_name', $id ? $cashier->first_name : '' ),
					'id'          => 'ddfcpos-cashier-first-name',
					'name'        => 'ddfcpos_cashier_first_name',
				),
				array(
					'type'        => 'text',
					'label'       => esc_html__( 'Last Name', 'multipos-for-fluentcart' ),
					'description' => esc_html__( 'Leave both names empty to show the username instead.', 'multipos-for-fluentcart' ),
					'value'       => $this->ddfcpos_value( 'last_name', $id ? $cashier->last_name : '' ),
					'id'          => 'ddfcpos-cashier-last-name',
					'name'        => 'ddfcpos_cashier_last_name',
				),
				$id ? array(
					'type'        => 'field_html',
					'label'       => esc_html__( 'Username', 'multipos-for-fluentcart' ),
					'description' => esc_html__( 'Usernames cannot be changed.', 'multipos-for-fluentcart' ),
					'html'        => '<code class="ddfcpos-cashier-username">' . esc_html( $cashier->user_login ) . '</code>',
				) : array(
					'type'         => 'text',
					'label'        => esc_html__( 'Username', 'multipos-for-fluentcart' ),
					'description'  => esc_html__( 'What the cashier types to log in to the POS. Cannot be changed later.', 'multipos-for-fluentcart' ),
					'value'        => $this->ddfcpos_value( 'username', '' ),
					'id'           => 'ddfcpos-cashier-username',
					'name'         => 'ddfcpos_cashier_username',
					'required'     => true,
					'autocomplete' => 'off',
				),
				array(
					'type'         => 'email',
					'label'        => esc_html__( 'Email', 'multipos-for-fluentcart' ),
					'description'  => esc_html__( 'Used for password resets, and also works as a login.', 'multipos-for-fluentcart' ),
					'value'        => $this->ddfcpos_value( 'email', $id ? $cashier->user_email : '' ),
					'id'           => 'ddfcpos-cashier-email',
					'name'         => 'ddfcpos_cashier_email',
					'required'     => true,
					'autocomplete' => 'off',
				),
				array(
					'type'         => 'password',
					'label'        => $id ? esc_html__( 'New Password', 'multipos-for-fluentcart' ) : esc_html__( 'Password', 'multipos-for-fluentcart' ),
					'description'  => $id ? esc_html__( 'Leave empty to keep the current password. A new one signs the cashier out of every device.', 'multipos-for-fluentcart' ) : esc_html__( 'Leave empty and the cashier gets an email with a link to set their own.', 'multipos-for-fluentcart' ),
					'id'           => 'ddfcpos-cashier-password',
					'name'         => 'ddfcpos_cashier_password',
					'autocomplete' => 'new-password',
				),
			);

			if ( ! $id ) {
				$account_fields[] = array(
					'type'           => 'checkbox',
					'label'          => esc_html__( 'Welcome Email', 'multipos-for-fluentcart' ),
					'checkbox_label' => esc_html__( 'Email the cashier their username and a link to set their password', 'multipos-for-fluentcart' ),
					'description'    => esc_html__( 'Always sent when the password is left empty, otherwise the cashier could not log in.', 'multipos-for-fluentcart' ),
					'value'          => $this->ddfcpos_value( 'send_link', true ) ? 'yes' : '',
					'id'             => 'ddfcpos-cashier-send-link',
					'name'           => 'ddfcpos_cashier_send_link',
				);
			}

			$args = array(
				array(
					'header' => array(
						'heading'     => esc_html__( 'Account', 'multipos-for-fluentcart' ),
						'description' => esc_html__( 'How the cashier logs in. They are a normal WordPress user with the POS Cashier role.', 'multipos-for-fluentcart' ),
					),
					'fields' => $account_fields,
				),
				array(
					'header'            => array(
						'heading'     => esc_html__( 'POS Access', 'multipos-for-fluentcart' ),
						'description' => esc_html__( 'Every cashier can open the POS at your outlet.', 'multipos-for-fluentcart' ),
					),
					'after_header_html' => ddfw_get_upgrade_to_pro_section(
						array(
							'heading'       => esc_html__( 'Outlets, supervisors and a Manager PIN, in Pro', 'multipos-for-fluentcart' ),
							'description'   => esc_html__( 'MultiPOS Pro decides where each cashier works and what they can do without a manager.', 'multipos-for-fluentcart' ),
							'list_features' => array(
								esc_html__( 'Assign each cashier to one or more outlets', 'multipos-for-fluentcart' ),
								esc_html__( 'Make a cashier a shift supervisor', 'multipos-for-fluentcart' ),
								esc_html__( 'Manager PIN to approve refunds, price changes and big discounts at the till', 'multipos-for-fluentcart' ),
							),
							'upgrade_url'   => 'https://devdiggers.com/product/fluentcart-pos/',
						)
					),
				),
			);

			if ( $id ) {
				$args[] = array(
					'column'            => 'side',
					'header'            => array(
						'heading' => esc_html__( 'At a Glance', 'multipos-for-fluentcart' ),
					),
					'after_header_html' => $this->ddfcpos_get_cashier_glance_html( $id ),
					'fields'            => array(),
				);
			}

			$screen_header = array(
				'heading'           => $id ? sprintf(
					/* translators: %s: cashier name. */
					esc_html__( 'Edit Cashier: %s', 'multipos-for-fluentcart' ),
					$cashier->display_name
				) : esc_html__( 'Add Cashier', 'multipos-for-fluentcart' ),
				'description'       => esc_html__( 'A person who rings up sales at your outlet. Their sales are tracked under their name.', 'multipos-for-fluentcart' ),
				'back_button_url'   => admin_url( 'admin.php?page=ddfcpos-dashboard&menu=cashiers' ),
				'back_button_label' => esc_html__( 'Back to cashiers', 'multipos-for-fluentcart' ),
				'header_buttons'    => $id ? array(
					array(
						'label' => esc_html__( 'Edit in WordPress', 'multipos-for-fluentcart' ),
						'url'   => admin_url( 'user-edit.php?user_id=' . $id ),
					),
				) : array(),
			);

			$submit_button = array(
				'name'  => 'ddfcpos_save_cashier_submit',
				'value' => $id ? esc_html__( 'Update Cashier', 'multipos-for-fluentcart' ) : esc_html__( 'Create Cashier', 'multipos-for-fluentcart' ),
			);

			( new DDFW_Layout() )->get_edit_screen_layout( $args, '', $submit_button, 'ddfcpos-manage-cashier-form', $screen_header );
		}

		/**
		 * Today, the last 30 days, the last sale and the screens filtered to this cashier.
		 *
		 * @param int $id Cashier ID.
		 * @return string
		 */
		protected function ddfcpos_get_cashier_glance_html( $id ) {
			$activity = new DDFCPOS_Activity_Helper();
			$month    = $activity->ddfcpos_get_sales_by( 'cashier_id', gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - 29 * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Local store day.
			$today    = $activity->ddfcpos_get_sales_by( 'cashier_id', current_time( 'Y-m-d 00:00:00' ) );
			$month    = isset( $month[ $id ] ) ? $month[ $id ] : array(
				'sales'  => 0,
				'orders' => 0,
			);
			$today    = isset( $today[ $id ] ) ? $today[ $id ] : [ 'sales' => 0, 'orders' => 0 ];
			$base     = admin_url( 'admin.php?page=ddfcpos-dashboard' );

			ob_start();
			?>
			<div class="ddfcpos-glance">
				<div class="ddfcpos-glance-stats">
					<div>
						<span><?php esc_html_e( 'Today', 'multipos-for-fluentcart' ); ?></span>
						<strong><?php echo wp_kses_post( DDFCPOS_FluentCart_Helper::format_price( $today['sales'] ) ); ?></strong>
						<small>
							<?php
							/* translators: %d: number of orders. */
							echo esc_html( sprintf( _n( '%d order', '%d orders', $today['orders'], 'multipos-for-fluentcart' ), $today['orders'] ) );
							?>
						</small>
					</div>
					<div>
						<span><?php esc_html_e( 'Last 30 days', 'multipos-for-fluentcart' ); ?></span>
						<strong><?php echo wp_kses_post( DDFCPOS_FluentCart_Helper::format_price( $month['sales'] ) ); ?></strong>
						<small>
							<?php
							/* translators: %d: number of orders. */
							echo esc_html( sprintf( _n( '%d order', '%d orders', $month['orders'], 'multipos-for-fluentcart' ), $month['orders'] ) );
							?>
						</small>
					</div>
				</div>

				<dl>
					<dt><?php esc_html_e( 'Last sale', 'multipos-for-fluentcart' ); ?></dt>
					<dd>
						<?php
						if ( ! empty( $month['last_sale'] ) ) {
							/* translators: %s: time since the last sale, like 2 hours. */
							echo esc_html( sprintf( __( '%s ago', 'multipos-for-fluentcart' ), human_time_diff( strtotime( $month['last_sale'] ), current_time( 'timestamp' ) ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Ledger dates are local time.
						} else {
							esc_html_e( 'No sales yet', 'multipos-for-fluentcart' );
						}
						?>
					</dd>
				</dl>

				<div class="ddfcpos-glance-links">
					<a href="<?php echo esc_url( $base . '&menu=orders&cashier-id=' . $id ); ?>"><?php esc_html_e( 'Orders', 'multipos-for-fluentcart' ); ?></a>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}
