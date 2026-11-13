<?php
/**
 * Manage Outlet template class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Outlet;

use DevDiggers\Framework\Includes\DDFW_Layout;
use DDFCMultiPOS\Helper\Error\DDFCPOS_Error_Helper;
use DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper;
use DDFCMultiPOS\Helper\Report\DDFCPOS_Activity_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'DDFCPOS_Manage_Outlet_Template' ) ) {
	/**
	 * Manage Outlet template class
	 */
	class DDFCPOS_Manage_Outlet_Template {
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
		 * Outlet helper variable
		 *
		 * @var object
		 */
		protected $outlet_helper;

		/**
		 * Outlet data variable
		 *
		 * @var array
		 */
		protected $outlet_data;

		/**
		 * Construct
		 */
		public function __construct( $ddfcpos_configuration ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;
			$this->outlet_helper         = new DDFCPOS_Outlet_Helper();
			$this->ddfcpos_perform_save_outlet();

			$this->outlet_data = array(
				'id'       => '',
				'name'     => '',
				'mode'     => 'grocery',
				'address1' => '',
				'address2' => '',
				'city'     => '',
				'state'    => '',
				'country'  => '',
				'postcode' => '',
				'phone'    => '',
				'email'    => '',
				'payments' => array(),
				'tables'   => array(),
				'status'   => 'enabled',
			);

			$outlet_data = $this->outlet_helper->ddfcpos_get_outlet_option();

			if ( $outlet_data ) {
				$this->outlet_data = wp_parse_args( $outlet_data, $this->outlet_data );
			}

			$this->outlet_data['payments'] = maybe_unserialize( $this->outlet_data['payments'] );
			$this->outlet_data['tables']   = ! empty( $this->outlet_data['tables'] ) ? maybe_unserialize( $this->outlet_data['tables'] ) : maybe_unserialize( array() );

			$this->ddfcpos_get_manage_outlet_template();
		}

		/**
		 * Perform Save Outlet function
		 *
		 * @return void
		 */
		public function ddfcpos_perform_save_outlet() {
			if ( ! empty( $_POST['ddfcpos_save_outlet_submit'] ) && ! empty( $_POST['ddfcpos_save_outlet_submit_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddfcpos_save_outlet_submit_nonce'] ) ), 'ddfcpos_save_outlet_submit_nonce_action' ) ) {
				if ( ! current_user_can( 'ddfcpos_manage' ) ) {
					$this->ddfcpos_print_notification( esc_html__( 'Insufficient permissions.', 'devdiggers-multipos-for-fluentcart' ), 'error' );
					return;
				}

				$id       = ! empty( $_POST['ddfcpos_outlet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_id'] ) ) : '';
				$name     = ! empty( $_POST['ddfcpos_outlet_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_name'] ) ) : '';
				$mode     = ! empty( $_POST['ddfcpos_outlet_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_mode'] ) ) : '';
				$address1 = ! empty( $_POST['ddfcpos_outlet_address1'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_address1'] ) ) : '';
				$address2 = ! empty( $_POST['ddfcpos_outlet_address2'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_address2'] ) ) : '';
				$city     = ! empty( $_POST['ddfcpos_outlet_city'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_city'] ) ) : '';
				$state    = ! empty( $_POST['ddfcpos_outlet_state'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_state'] ) ) : '';
				$country  = ! empty( $_POST['ddfcpos_outlet_country'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_country'] ) ) : '';
				$postcode = ! empty( $_POST['ddfcpos_outlet_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_postcode'] ) ) : '';
				$phone    = ! empty( $_POST['ddfcpos_outlet_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_phone'] ) ) : '';
				$email    = ! empty( $_POST['ddfcpos_outlet_email'] ) ? sanitize_email( wp_unslash( $_POST['ddfcpos_outlet_email'] ) ) : '';
				$payments = ! empty( $_POST['ddfcpos_outlet_payments'] ) ? maybe_serialize( array_map( 'sanitize_text_field', wp_unslash( $_POST['ddfcpos_outlet_payments'] ) ) ) : '';
				$tables   = ! empty( $_POST['ddfcpos_outlet_tables'] ) ? maybe_serialize( array_map( 'sanitize_text_field', wp_unslash( $_POST['ddfcpos_outlet_tables'] ) ) ) : maybe_serialize( array() );
				$status   = ! empty( $_POST['ddfcpos_outlet_status'] ) ? sanitize_text_field( wp_unslash( $_POST['ddfcpos_outlet_status'] ) ) : '';

				$tables = 'restaurant' === $mode ? $tables : maybe_serialize( array() );

				if ( ! empty( $name ) && ! empty( $mode ) && ! empty( $address1 ) && ! empty( $city ) && ! empty( $state ) && ! empty( $country ) && ! empty( $postcode ) && ! empty( $phone ) && ! empty( $email ) && ! empty( $payments ) && ! empty( $status ) ) {
					$data = compact( 'id', 'name', 'mode', 'address1', 'address2', 'city', 'state', 'country', 'postcode', 'phone', 'email', 'payments', 'tables', 'status' );

					$outlet_id = $this->outlet_helper->ddfcpos_save_outlet( $data );

					if ( $outlet_id ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page/menu are read-only routing values.
						$page = ! empty( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page/menu are read-only routing values.
						$menu = ! empty( $_GET['menu'] ) ? sanitize_text_field( wp_unslash( $_GET['menu'] ) ) : '';

						wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&menu=' . $menu . '&success=saved' ) );
						exit();
					}
				} else {
					$this->ddfcpos_print_notification( esc_html__( 'Fields are either empty or invalid.', 'devdiggers-multipos-for-fluentcart' ), 'error' );
				}
			}
		}

		/**
		 * Get Manage Outlet Template function
		 *
		 * @return void
		 */
		public function ddfcpos_get_manage_outlet_template() {
			$outlet_modes = $this->outlet_helper->ddfcpos_get_outlet_modes();

			extract( $this->outlet_data );

			// Single-outlet model: editing the existing outlet vs. first-time setup.
			$heading = ! empty( $id )
				? esc_html__( 'Outlet Settings', 'devdiggers-multipos-for-fluentcart' )
				: esc_html__( 'Set Up Your Outlet', 'devdiggers-multipos-for-fluentcart' );

			$status_label = ( 'enabled' === $status ) ? 'active' : 'inactive';
			$states       = DDFCPOS_FluentCart_Helper::states( $country );

			$payment_options = array();
			foreach ( $this->ddfcpos_configuration['payment_method'] as $value ) {
				if ( 'enabled' === $value['status'] ) {
					$payment_options[ $value['slug'] ] = $value['name'];
				}
			}

			$table_options = array();
			foreach ( $this->ddfcpos_configuration['tables'] as $value ) {
				if ( 'enabled' === $value['status'] ) {
					$table_options[ $value['slug'] ] = $value['name'];
				}
			}

			$args = array(
				array(
					'header' => array(
						'heading'     => esc_html__( 'Outlet Details', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'The name cashiers see on the POS, and the payments they can take here.', 'devdiggers-multipos-for-fluentcart' ),
					),
					'fields' => array(
						array(
							'type'             => 'text',
							'label'            => esc_html__( 'Outlet Name', 'devdiggers-multipos-for-fluentcart' ),
							'description'      => esc_html__( 'A descriptive name to identify this store location (e.g., Downtown Branch).', 'devdiggers-multipos-for-fluentcart' ),
							'value'            => $name,
							'id'               => 'ddfcpos-outlet-name',
							'name'             => 'ddfcpos_outlet_name',
							'required'         => true,
							'after_field_text' => '<input type="hidden" name="ddfcpos_outlet_id" value="' . esc_attr( $id ) . '" />',
							'wrapper'          => 'span',
						),
						array(
							'type'              => 'select',
							'label'             => esc_html__( 'Payment Methods', 'devdiggers-multipos-for-fluentcart' ),
							'description'       => esc_html__( 'Choose the payment options that will be available for customers at this POS outlet.', 'devdiggers-multipos-for-fluentcart' ),
							'value'             => $payments,
							'id'                => 'ddfcpos-outlet-payments',
							'name'              => 'ddfcpos_outlet_payments[]',
							'required'          => true,
							'options'           => $payment_options,
							'custom_attributes' => array(
								'multiple' => 'multiple',
							),
						),
						array(
							'type'              => 'select',
							'label'             => esc_html__( 'Dining Tables', 'devdiggers-multipos-for-fluentcart' ),
							'description'       => esc_html__( 'Select the active tables for this outlet. (Applicable only in "Restaurant/Cafe" mode).', 'devdiggers-multipos-for-fluentcart' ),
							'value'             => $tables,
							'id'                => 'ddfcpos-outlet-tables',
							'name'              => 'ddfcpos_outlet_tables[]',
							'options'           => $table_options,
							'custom_attributes' => array(
								'multiple' => 'multiple',
							),
						),
					),
				),
				array(
					'header' => array(
						'heading'     => esc_html__( 'Address & Contact', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Shown on printed receipts, and used for POS tax.', 'devdiggers-multipos-for-fluentcart' ),
					),
					'fields' => array(
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Address Line 1', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Primary street address. This will appear on printed invoices.', 'devdiggers-multipos-for-fluentcart' ),
							'value'       => $address1,
							'id'          => 'ddfcpos-outlet-address1',
							'name'        => 'ddfcpos_outlet_address1',
							'required'    => true,
						),
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Address Line 2', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Apartment, suite, unit, etc. (optional).', 'devdiggers-multipos-for-fluentcart' ),
							'value'       => $address2,
							'id'          => 'ddfcpos-outlet-address2',
							'name'        => 'ddfcpos_outlet_address2',
						),
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'City', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'City where the outlet is physically located.', 'devdiggers-multipos-for-fluentcart' ),
							'value'       => $city,
							'id'          => 'ddfcpos-outlet-city',
							'name'        => 'ddfcpos_outlet_city',
							'required'    => true,
						),
						[
							'type'        => 'select',
							'label'       => esc_html__( 'Country', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Country for this outlet.', 'devdiggers-multipos-for-fluentcart' ),
							'options'     => [ '' => esc_html__( 'Select a country', 'devdiggers-multipos-for-fluentcart' ) ] + DDFCPOS_FluentCart_Helper::countries(),
							'input_class' => [ 'ddfcpos-select2' ],
							'value'       => $country,
							'id'          => 'ddfcpos-outlet-country',
							'name'        => 'ddfcpos_outlet_country',
							'required'    => true,
						],
						[
							// ponytail: a list when the saved country has states, free text otherwise (save to load a new country's states).
							'type'        => $states ? 'select' : 'text',
							'label'       => esc_html__( 'State/Province', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'State, province, or region.', 'devdiggers-multipos-for-fluentcart' ),
							'options'     => [ '' => esc_html__( 'Select a state', 'devdiggers-multipos-for-fluentcart' ) ] + $states,
							'value'       => $states ? DDFCPOS_FluentCart_Helper::state_code( $country, $state ) : $state,
							'id'          => 'ddfcpos-outlet-state',
							'name'        => 'ddfcpos_outlet_state',
							'required'    => true,
						],
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Postcode/ZIP', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Postal code for the store location.', 'devdiggers-multipos-for-fluentcart' ),
							'value'       => $postcode,
							'id'          => 'ddfcpos-outlet-postcode',
							'name'        => 'ddfcpos_outlet_postcode',
							'required'    => true,
						),
						array(
							'type'        => 'tel',
							'label'       => esc_html__( 'Phone', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Enter the contact phone number for the outlet.', 'devdiggers-multipos-for-fluentcart' ),
							'value'       => $phone,
							'id'          => 'ddfcpos-outlet-phone',
							'name'        => 'ddfcpos_outlet_phone',
							'required'    => true,
						),
						array(
							'type'        => 'email',
							'label'       => esc_html__( 'Email', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Enter the contact email for the outlet.', 'devdiggers-multipos-for-fluentcart' ),
							'value'       => $email,
							'id'          => 'ddfcpos-outlet-email',
							'name'        => 'ddfcpos_outlet_email',
							'required'    => true,
						),
					),
				),
				array(
					'column' => 'side',
					'header' => array(
						'heading' => esc_html__( 'Status', 'devdiggers-multipos-for-fluentcart' ),
					),
					'fields' => array(
						array(
							'type'        => 'select',
							'label'       => esc_html__( 'Outlet Status', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Set to "Inactive" to temporarily prevent POS access for this store.', 'devdiggers-multipos-for-fluentcart' ),
							'value'       => $status,
							'id'          => 'ddfcpos-outlet-status',
							'name'        => 'ddfcpos_outlet_status',
							'required'    => true,
							'options'     => array(
								'enabled'  => esc_html__( 'Active', 'devdiggers-multipos-for-fluentcart' ),
								'disabled' => esc_html__( 'Inactive', 'devdiggers-multipos-for-fluentcart' ),
							),
							'class'       => 'ddfcpos-select2',
						),
						array(
							'type'        => 'select',
							'label'       => esc_html__( 'Operation Mode', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Select how this outlet operates. "Restaurant/Cafe" mode enables additional features like Table management.', 'devdiggers-multipos-for-fluentcart' ),
							'value'       => $mode,
							'id'          => 'ddfcpos-outlet-mode',
							'name'        => 'ddfcpos_outlet_mode',
							'required'    => true,
							'options'     => $outlet_modes,
							'class'       => 'ddfcpos-select2',
						),
					),
				),
			);

			if ( ! empty( $id ) ) {
				// First in the rail, like Publish on the edit post screen: how the outlet is doing before its settings.
				array_splice(
					$args,
					2,
					0,
					array(
						array(
							'column'            => 'side',
							'header'            => array(
								'heading' => esc_html__( 'At a Glance', 'devdiggers-multipos-for-fluentcart' ),
							),
							'after_header_html' => $this->ddfcpos_get_outlet_glance_html( (int) $id, $mode ),
							'fields'            => array(),
						),
					)
				);
			}

			$submit_button = array(
				'name'  => 'ddfcpos_save_outlet_submit',
				'value' => esc_html__( 'Save Outlet', 'devdiggers-multipos-for-fluentcart' ),
			);

			$screen_header = array(
				'heading'     => $heading,
				'description' => esc_html__( 'Your store counter: its address goes on receipts, and its mode and payments shape the POS.', 'devdiggers-multipos-for-fluentcart' ),
			);

			$layout = new DDFW_Layout();
			$layout->get_edit_screen_layout( $args, '', $submit_button, 'ddfcpos-manage-outlet-form', $screen_header );
		}

		/**
		 * At a glance card: today's and recent sales, and the screens an owner jumps to next.
		 *
		 * @param int    $outlet_id Outlet ID.
		 * @param string $mode      Outlet mode.
		 * @return string
		 */
		protected function ddfcpos_get_outlet_glance_html( $outlet_id, $mode ) {
			$activity = new DDFCPOS_Activity_Helper();
			$month    = $activity->ddfcpos_get_sales_by( 'outlet_id', gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - 29 * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Local store day.
			$today    = $activity->ddfcpos_get_sales_by( 'outlet_id', current_time( 'Y-m-d 00:00:00' ) );
			$month    = isset( $month[ $outlet_id ] ) ? $month[ $outlet_id ] : array(
				'sales'  => 0,
				'orders' => 0,
			);
			$today    = isset( $today[ $outlet_id ] ) ? $today[ $outlet_id ] : [ 'sales' => 0, 'orders' => 0 ];
			$base     = admin_url( 'admin.php?page=ddfcpos-dashboard' );

			ob_start();
			?>
			<div class="ddfcpos-glance">
				<div class="ddfcpos-glance-stats">
					<div>
						<span><?php esc_html_e( 'Today', 'devdiggers-multipos-for-fluentcart' ); ?></span>
						<strong><?php echo wp_kses_post( DDFCPOS_FluentCart_Helper::format_price( $today['sales'] ) ); ?></strong>
						<small>
							<?php
							/* translators: %d: number of orders. */
							echo esc_html( sprintf( _n( '%d order', '%d orders', $today['orders'], 'devdiggers-multipos-for-fluentcart' ), $today['orders'] ) );
							?>
						</small>
					</div>
					<div>
						<span><?php esc_html_e( 'Last 30 days', 'devdiggers-multipos-for-fluentcart' ); ?></span>
						<strong><?php echo wp_kses_post( DDFCPOS_FluentCart_Helper::format_price( $month['sales'] ) ); ?></strong>
						<small>
							<?php
							/* translators: %d: number of orders. */
							echo esc_html( sprintf( _n( '%d order', '%d orders', $month['orders'], 'devdiggers-multipos-for-fluentcart' ), $month['orders'] ) );
							?>
						</small>
					</div>
				</div>

				<div class="ddfcpos-glance-links">
					<a href="<?php echo esc_url( site_url( $this->ddfcpos_configuration['endpoint'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Visit POS', 'devdiggers-multipos-for-fluentcart' ); ?></a>
					<a href="<?php echo esc_url( $base . '&menu=orders' ); ?>"><?php esc_html_e( 'Orders', 'devdiggers-multipos-for-fluentcart' ); ?></a>
					<a href="<?php echo esc_url( $base . '&menu=cashiers' ); ?>"><?php esc_html_e( 'Cashiers', 'devdiggers-multipos-for-fluentcart' ); ?></a>
					<?php if ( 'restaurant' === $mode ) : ?>
						<a href="<?php echo esc_url( $base . '&menu=configuration&tab=tables' ); ?>"><?php esc_html_e( 'Tables', 'devdiggers-multipos-for-fluentcart' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}
