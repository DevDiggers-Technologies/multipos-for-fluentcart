<?php
/**
 * This file handles all admin dashboard functionalities.
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Includes;

use DevDiggers\Framework\Includes\DDFW_Plugin_Dashboard;
use DevDiggers\Framework\Includes\DDFW_Assets;
use DevDiggers\Framework\Includes\DDFW_SVG;
use DDFCMultiPOS\Templates\Admin;

defined( 'ABSPATH' ) || exit();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin routing/filter GET values here are read-only; destructive list-table actions verify their own nonce.

if ( ! class_exists( 'DDFCPOS_Admin_Dashboard' ) ) {
	/**
	 * Admin Dashboard Class
	 */
	class DDFCPOS_Admin_Dashboard {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddfcpos_configuration;

		/**
		 * Dashboard Variable
		 *
		 * @var DDFW_Plugin_Dashboard
		 */
		protected $dashboard;

		/**
		 * Construct
		 *
		 * @param array $ddfcpos_configuration
		 */
		public function __construct( $ddfcpos_configuration ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;
			$this->ddfcpos_add_dashboard_menu();
			add_action( 'admin_enqueue_scripts', array( $this, 'ddfcpos_enqueue_admin_scripts' ) );
			add_filter( 'admin_footer_text', array( $this, 'ddfcpos_set_admin_footer_text' ), 99 );
		}

		/**
		 * Add Admin menu function
		 *
		 * @return void
		 */
		public function ddfcpos_add_dashboard_menu() {
			ob_start();
			?>
			<svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
				<circle cx="16" cy="16" r="15" fill="var(--ddfw-tab-background-color)"/>
				<path d="M9 21C9 19.8954 9.89543 19 11 19H21C22.1046 19 23 19.8954 23 21V25C23 25.5523 22.5523 26 22 26H10C9.44772 26 9 25.5523 9 25V21Z" fill="var(--ddfw-primary-color)"/>
				<path d="M10 11C10 9.89543 10.8954 9 12 9H20C21.1046 9 22 9.89543 22 11V19H10V11Z" fill="var(--ddfw-primary-color)" fill-opacity="0.7"/>
				<rect x="12" y="11" width="2" height="1.5" rx="0.5" fill="white"/>
				<rect x="15" y="11" width="2" height="1.5" rx="0.5" fill="white"/>
				<rect x="18" y="11" width="2" height="1.5" rx="0.5" fill="white"/>
				<rect x="12" y="14" width="2" height="1.5" rx="0.5" fill="white"/>
				<rect x="15" y="14" width="2" height="1.5" rx="0.5" fill="white"/>
				<rect x="18" y="14" width="2" height="1.5" rx="0.5" fill="white"/>
				<circle cx="16" cy="22.5" r="1.2" fill="white"/>
				<path d="M18 9V4L19 5L20 4L21 5L22 4V9H18Z" fill="var(--ddfw-primary-color)" fill-opacity="0.5"/>
			</svg>
			<?php esc_html_e( 'MultiPOS for FluentCart', 'devdiggers-multipos-for-fluentcart' ); ?>
			<?php
			$plugin_name = ob_get_clean();

			$args = array(
				'page_title'              => esc_html__( 'MultiPOS for FluentCart', 'devdiggers-multipos-for-fluentcart' ),
				'menu_title'              => esc_html__( 'MultiPOS for FluentCart', 'devdiggers-multipos-for-fluentcart' ),
				'slug'                    => 'ddfcpos-dashboard',
				'plugin_name'             => $plugin_name,
				'upgrade_url'             => 'https://devdiggers.com/product/fluentcart-pos/',
				'screen_options_callback' => array( $this, 'add_screen_options' ),
				'menus'                   => array(
					'dashboard'        => array(
						'label'    => esc_html__( 'Dashboard', 'devdiggers-multipos-for-fluentcart' ),
						'callback' => array( $this, 'ddfcpos_get_dashboard_template' ),
						'layout'   => 'full-width',
					),
					'outlets'          => array(
						'label'    => esc_html__( 'Outlet', 'devdiggers-multipos-for-fluentcart' ),
						'callback' => array( $this, 'ddfcpos_get_outlets_template' ),
						'layout'   => 'full-width',
					),
					'cashiers'         => array(
						'label'    => esc_html__( 'Cashiers', 'devdiggers-multipos-for-fluentcart' ),
						'callback' => array( $this, 'ddfcpos_get_cashiers_template' ),
						'layout'   => 'full-width',
					),
					'product-barcodes' => array(
						'label'    => esc_html__( 'Assign Barcodes', 'devdiggers-multipos-for-fluentcart' ),
						'callback' => array( $this, 'ddfcpos_get_product_barcodes_template' ),
						'layout'   => 'full-width',
					),
					'product-stocks'   => array(
						'label'    => esc_html__( 'Assign Stocks', 'devdiggers-multipos-for-fluentcart' ),
						'callback' => array( $this, 'ddfcpos_get_product_stocks_upgrade_template' ),
						'layout'   => 'full-width',
					),
					'orders'           => array(
						'label'    => esc_html__( 'Orders', 'devdiggers-multipos-for-fluentcart' ),
						'callback' => array( $this, 'ddfcpos_get_orders_template' ),
						'layout'   => 'full-width',
					),
					'reports'          => array(
						'label'    => esc_html__( 'Reports', 'devdiggers-multipos-for-fluentcart' ),
						'callback' => array( $this, 'ddfcpos_get_reports_upgrade_template' ),
						'layout'   => 'full-width',
					),
					'transactions'     => array(
						'label'    => esc_html__( 'Transactions', 'devdiggers-multipos-for-fluentcart' ),
						'callback' => array( $this, 'ddfcpos_get_transactions_template' ),
						'layout'   => 'full-width',
					),
					'registers'        => array(
						'label'    => esc_html__( 'Registers', 'devdiggers-multipos-for-fluentcart' ),
						'callback' => array( $this, 'ddfcpos_get_registers_upgrade_template' ),
						'layout'   => 'full-width',
					),
					'invoices'         => array(
						'label'    => esc_html__( 'Invoices', 'devdiggers-multipos-for-fluentcart' ),
						'callback' => array( $this, 'ddfcpos_get_invoices_template' ),
						'layout'   => 'full-width',
					),
					'configuration'    => array(
						'label'  => esc_html__( 'Configuration', 'devdiggers-multipos-for-fluentcart' ),
						'layout' => 'sidebar',
						'tabs'   => array(
							'general'     => array(
								'label'    => esc_html__( 'General', 'devdiggers-multipos-for-fluentcart' ),
								'icon'     => DDFW_SVG::get_svg_icon(
									'general',
									true,
									array( 'size' => 18 )
								),
								'callback' => array( $this, 'ddfcpos_get_general_configuration_template' ),
							),
							'payments'    => array(
								'label'    => esc_html__( 'Payments', 'devdiggers-multipos-for-fluentcart' ),
								'icon'     => DDFW_SVG::get_svg_icon(
									'payments',
									true,
									array( 'size' => 18 )
								),
								'callback' => array( $this, 'ddfcpos_get_payments_configuration_template' ),
							),
							'login'       => array(
								'label'    => esc_html__( 'Login', 'devdiggers-multipos-for-fluentcart' ),
								'icon'     => DDFW_SVG::get_svg_icon(
									'login',
									true,
									array( 'size' => 18 )
								),
								'callback' => array( $this, 'ddfcpos_get_login_configuration_template' ),
							),
							'printer'     => array(
								'label'    => esc_html__( 'Printer', 'devdiggers-multipos-for-fluentcart' ),
								'icon'     => DDFW_SVG::get_svg_icon(
									'printer',
									true,
									array( 'size' => 18 )
								),
								'callback' => array( $this, 'ddfcpos_get_printer_configuration_template' ),
							),
							'permissions' => array(
								'label'    => esc_html__( 'Permissions', 'devdiggers-multipos-for-fluentcart' ),
								'icon'     => DDFW_SVG::get_svg_icon(
									'permissions',
									true,
									array( 'size' => 18 )
								),
								'callback' => array( $this, 'ddfcpos_get_permissions_configuration_template' ),
							),
							'register'    => array(
								'label'    => esc_html__( 'Cash Register', 'devdiggers-multipos-for-fluentcart' ),
								'icon'     => DDFW_SVG::get_svg_icon(
									'register',
									true,
									array( 'size' => 18 )
								),
								'callback' => array( $this, 'ddfcpos_get_register_configuration_template' ),
							),
							'pwa'         => array(
								'label'    => esc_html__( 'PWA', 'devdiggers-multipos-for-fluentcart' ),
								'icon'     => DDFW_SVG::get_svg_icon(
									'pwa',
									true,
									array( 'size' => 18 )
								),
								'callback' => array( $this, 'ddfcpos_get_pwa_configuration_template' ),
							),
							'tables'      => array(
								'label'    => esc_html__( 'Tables', 'devdiggers-multipos-for-fluentcart' ),
								'icon'     => DDFW_SVG::get_svg_icon(
									'tables',
									true,
									array( 'size' => 18 )
								),
								'callback' => array( $this, 'ddfcpos_get_tables_configuration_template' ),
							),
							'layout'      => array(
								'label'    => esc_html__( 'Layout', 'devdiggers-multipos-for-fluentcart' ),
								'icon'     => DDFW_SVG::get_svg_icon(
									'layout',
									true,
									array( 'size' => 18 )
								),
								'callback' => array( $this, 'ddfcpos_get_layout_configuration_template' ),
							),
						),
					),
				),
			);

			$this->dashboard = new DDFW_Plugin_Dashboard( $args );
		}

		/**
		 * Add screen options for the admin dashboard
		 *
		 * @return void
		 */
		public function add_screen_options() {
			global $ddfcpos_list_table;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input, no state change.
			$current_menu = ! empty( $_GET['menu'] ) ? sanitize_title( wp_unslash( $_GET['menu'] ) ) : 'dashboard';

			$args = array(
				'label'    => esc_html__( 'Results Per Page', 'devdiggers-multipos-for-fluentcart' ),
				'default'  => 20,
				'hidden'   => 'id',
				'sanitize' => 'intval',
			);

			// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WP_List_Table instance name is local to this method.
			switch ( $current_menu ) {
				case 'cashiers':
					$args['option']     = 'cashiers_per_page';
					$ddfcpos_list_table = new Admin\Cashier\DDFCPOS_Cashiers_List_Template();
					break;
				case 'product-barcodes':
					$args['option']     = 'barcodes_per_page';
					$ddfcpos_list_table = new Admin\Barcode\DDFCPOS_Product_Barcodes_List_Template( $this->ddfcpos_configuration );
					break;
				case 'transactions':
					$args['option']     = 'transactions_per_page';
					$ddfcpos_list_table = new Admin\Transaction\DDFCPOS_Transactions_List_Template( $this->ddfcpos_configuration );
					break;
				case 'orders':
					$args['option']     = 'orders_per_page';
					$ddfcpos_list_table = new Admin\Order\DDFCPOS_Orders_List_Template();
					break;
			}

			if ( ! empty( $args['option'] ) ) {
				add_screen_option( 'per_page', $args );
			}
		}

		/**
		 * Dashboard Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_dashboard_template() {
			new Admin\Dashboard\DDFCPOS_Dashboard_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Outlets Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_outlets_template() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input, no state change.
			if ( ! empty( $_GET['success'] ) ) {
				ddfw_print_notification( esc_html__( 'Outlet saved successfully.', 'devdiggers-multipos-for-fluentcart' ), 'success' );
			}

			// Free single-outlet model: configure the one outlet directly (no list, no add).
			// The form loads and upserts the single outlet; managing multiple outlets is a Pro feature.
			new Admin\Outlet\DDFCPOS_Manage_Outlet_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Cashiers Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_cashiers_template() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input; the screen verifies its own nonce on save.
			$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

			if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
				new Admin\Cashier\DDFCPOS_Manage_Cashier_Template();
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input, no state change.
			if ( ! empty( $_GET['success'] ) ) {
				ddfw_print_notification( esc_html__( 'Cashier created successfully.', 'devdiggers-multipos-for-fluentcart' ), 'success' );
			}
			$obj = new Admin\Cashier\DDFCPOS_Cashiers_List_Template();

			// Routing values are fixed for this screen; no request input is trusted to build them.
			$page = 'ddfcpos-dashboard';
			$menu = 'cashiers';
			?>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Cashiers', 'devdiggers-multipos-for-fluentcart' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ddfcpos-dashboard&menu=cashiers&action=add' ) ); ?>" class="page-title-action button-primary button">
				<?php
				DDFW_SVG::get_svg_icon(
					'plus',
					false,
					array( 'size' => 15 )
				);
				esc_html_e( 'Add New', 'devdiggers-multipos-for-fluentcart' );
				?>
			</a>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
				<input type="hidden" name="menu" value="<?php echo esc_attr( $menu ); ?>" />
				<?php
				wp_nonce_field( 'ddfcpos_nonce_action', 'ddfcpos_nonce' );
				$obj->prepare_items();
				$obj->search_box( esc_html__( 'Search', 'devdiggers-multipos-for-fluentcart' ), 'search-id' );
				$obj->display();
				?>
			</form>
			<?php
		}

		/**
		 * Product Barcodes Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_product_barcodes_template() {
			$obj = new Admin\Barcode\DDFCPOS_Product_Barcodes_List_Template( $this->ddfcpos_configuration );

			// Routing values are fixed for this screen; no request input is trusted to build them.
			$page = 'ddfcpos-dashboard';
			$menu = 'product-barcodes';
			?>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Barcodes', 'devdiggers-multipos-for-fluentcart' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=fluent-cart#/products' ) ); ?>" class="page-title-action button-primary button">
				<?php
				DDFW_SVG::get_svg_icon(
					'plus',
					false,
					array( 'size' => 15 )
				);
				esc_html_e( 'Add Product', 'devdiggers-multipos-for-fluentcart' );
				?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page . '&menu=configuration&tab=printer' ) ); ?>" class="page-title-action button"><?php esc_html_e( 'Label Settings', 'devdiggers-multipos-for-fluentcart' ); ?></a>
			<hr class="wp-header-end" />
			<p class="description"><?php esc_html_e( 'Tick products and choose Print labels to print them in one go. The quantity box sets how many labels each product gets, one when empty.', 'devdiggers-multipos-for-fluentcart' ); ?></p>
			<form method="get" class="ddfcpos-barcodes-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
				<input type="hidden" name="menu" value="<?php echo esc_attr( $menu ); ?>" />
				<?php
				wp_nonce_field( 'ddfcpos_nonce_action', 'ddfcpos_nonce' );
				$obj->prepare_items();
				$obj->search_box( esc_html__( 'Search', 'devdiggers-multipos-for-fluentcart' ), 'search-id' );
				$obj->display();
				?>
			</form>
			<?php
		}

		/**
		 * Screenshot for a Pro introduction card, or '' until one is added to assets/images/pro-pages.
		 *
		 * @param string $name Image name without extension.
		 * @return string
		 */
		protected function ddfcpos_get_pro_image( $name ) {
			return file_exists( DDFCPOS_PLUGIN_FILE . 'assets/images/pro-pages/' . $name . '.webp' ) ? DDFCPOS_PLUGIN_URL . 'assets/images/pro-pages/' . $name . '.webp' : '';
		}

		/**
		 * Product Stocks Upgrade Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_product_stocks_upgrade_template() {
			ddfw_upgrade_to_pro_section(
				array(
					'image_url'     => $this->ddfcpos_get_pro_image( 'assign-stocks' ),
					'heading'       => esc_html__( 'Assign/Track Stock by Outlet', 'devdiggers-multipos-for-fluentcart' ),
					'description'   => esc_html__( 'Upgrade to Pro to manage inventory separately for each outlet and keep stock levels accurate across all locations.', 'devdiggers-multipos-for-fluentcart' ),
					'list_features' => array(
						esc_html__( 'Set stock levels for each outlet', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Use custom outlet stock or central FluentCart inventory', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Show only available products per location', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Keep inventory accurate across multiple stores', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Bulk stock updates using CSV import', 'devdiggers-multipos-for-fluentcart' ),
					),
					'upgrade_url'   => 'https://devdiggers.com/product/fluentcart-pos/',
				)
			);
		}

		/**
		 * Reports Upgrade Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_reports_upgrade_template() {
			ddfw_upgrade_to_pro_section(
				array(
					'image_url'     => $this->ddfcpos_get_pro_image( 'reports' ),
					'heading'       => esc_html__( 'Advanced POS Reports in One Place', 'devdiggers-multipos-for-fluentcart' ),
					'description'   => esc_html__( 'Basic sales, outlet, and payment summaries are available on the Dashboard. Upgrade to Pro for one Reports page with every POS figure, compared with an earlier period and exportable to CSV.', 'devdiggers-multipos-for-fluentcart' ),
					'list_features' => array(
						esc_html__( 'Net sales, orders, refunds, discounts and taxes against the previous period or last year', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Each cashier\'s sales, refunds and whether their drawers balanced', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Busiest weekday and hour, and how customers pay', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Top products, categories, customers, outlets and coupons', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Filter by date range, outlet and cashier', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'CSV exports for accounting and audits', 'devdiggers-multipos-for-fluentcart' ),
					),
					'upgrade_url'   => 'https://devdiggers.com/product/fluentcart-pos/',
				)
			);
		}

		/**
		 * Registers Upgrade Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_registers_upgrade_template() {
			ddfw_upgrade_to_pro_section(
				array(
					'image_url'     => $this->ddfcpos_get_pro_image( 'registers' ),
					'heading'       => esc_html__( 'Close Every Register with a Z-Report', 'devdiggers-multipos-for-fluentcart' ),
					'description'   => esc_html__( 'Upgrade to Pro to count the cash drawer at the end of each shift, see at once if it is balanced, over or short, and keep every closed register on file.', 'devdiggers-multipos-for-fluentcart' ),
					'list_features' => array(
						esc_html__( 'Close Register on the POS with the counted cash and the expected drawer', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'X-report during the shift and a Z-report at the close, sized for receipt printers', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Blind close that hides the expected amount until the cashier has counted', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Required note when the drawer is off by more than you allow', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Cash in and cash out with reasons, such as bank drop or petty cash', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'End of day Z-report emailed to the owner', 'devdiggers-multipos-for-fluentcart' ),
					),
					'upgrade_url'   => 'https://devdiggers.com/product/fluentcart-pos/',
				)
			);
		}

		/**
		 * Orders Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_orders_template() {
			$obj = new Admin\Order\DDFCPOS_Orders_List_Template();

			// Routing values are fixed for this screen; no request input is trusted to build them.
			$page = 'ddfcpos-dashboard';
			$menu = 'orders';
			?>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Orders', 'devdiggers-multipos-for-fluentcart' ); ?></h1>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
				<input type="hidden" name="menu" value="<?php echo esc_attr( $menu ); ?>" />
				<?php
				wp_nonce_field( 'ddfcpos_nonce_action', 'ddfcpos_nonce' );
				$obj->prepare_items();
				$obj->search_box( esc_html__( 'Search', 'devdiggers-multipos-for-fluentcart' ), 'search-id' );
				$obj->display();
				?>
			</form>
			<?php
		}

		/**
		 * Transactions Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_transactions_template() {
			$obj = new Admin\Transaction\DDFCPOS_Transactions_List_Template( $this->ddfcpos_configuration );

			// Routing values are fixed for this screen; filters are read only when their nonce verifies.
			$page                  = 'ddfcpos-dashboard';
			$menu                  = 'transactions';
			$nonce_ok              = isset( $_GET['ddfcpos_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['ddfcpos_nonce'] ) ), 'ddfcpos_nonce_action' );
			$transaction_from_date = ( $nonce_ok && ! empty( $_GET['transaction-from-date'] ) ) ? sanitize_text_field( wp_unslash( $_GET['transaction-from-date'] ) ) : '';
			$transaction_to_date   = ( $nonce_ok && ! empty( $_GET['transaction-to-date'] ) ) ? sanitize_text_field( wp_unslash( $_GET['transaction-to-date'] ) ) : '';
			$cashier_id            = ( $nonce_ok && ! empty( $_GET['cashier-id'] ) ) ? sanitize_text_field( wp_unslash( $_GET['cashier-id'] ) ) : '';
			?>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Transactions', 'devdiggers-multipos-for-fluentcart' ); ?></h1>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
				<input type="hidden" name="menu" value="<?php echo esc_attr( $menu ); ?>" />
				<input type="hidden" name="transaction-from-date" value="<?php echo esc_attr( $transaction_from_date ); ?>" />
				<input type="hidden" name="transaction-to-date" value="<?php echo esc_attr( $transaction_to_date ); ?>" />
				<input type="hidden" name="cashier-id" value="<?php echo esc_attr( $cashier_id ); ?>" />
				<?php
				wp_nonce_field( 'ddfcpos_nonce_action', 'ddfcpos_nonce' );
				$obj->prepare_items();
				$obj->search_box( esc_html__( 'Search', 'devdiggers-multipos-for-fluentcart' ), 'search-id' );
				$obj->display();
				?>
			</form>
			<?php
		}

		/**
		 * Invoices Template
		 *
		 * @return void
		 */


		/**
		 * General Configuration Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_general_configuration_template() {
			new Admin\Configuration\DDFCPOS_General_Configuration_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Payments Configuration Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_payments_configuration_template() {
			new Admin\Configuration\DDFCPOS_Payments_Configuration_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Login Configuration Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_login_configuration_template() {
			new Admin\Configuration\DDFCPOS_Login_Configuration_Template( $this->ddfcpos_configuration );
		}

		/**
		 * PWA Configuration Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_pwa_configuration_template() {
			new Admin\Configuration\DDFCPOS_PWA_Configuration_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Permissions Configuration Template (introduces the Pro cashier permissions).
		 *
		 * @return void
		 */
		public function ddfcpos_get_permissions_configuration_template() {
			new Admin\Configuration\DDFCPOS_Permissions_Configuration_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Cash Register Configuration Template (introduces the Pro cash control).
		 *
		 * @return void
		 */
		public function ddfcpos_get_register_configuration_template() {
			new Admin\Configuration\DDFCPOS_Register_Configuration_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Printer Configuration Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_printer_configuration_template() {
			new Admin\Configuration\DDFCPOS_Printer_Configuration_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Tables Configuration Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_tables_configuration_template() {
			new Admin\Configuration\DDFCPOS_Tables_Configuration_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Invoices Upgrade Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_invoices_template() {
			new Admin\Invoices\DDFCPOS_Invoices_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Layout Configuration Template
		 *
		 * @return void
		 */
		public function ddfcpos_get_layout_configuration_template() {
			new Admin\Configuration\DDFCPOS_Layout_Configuration_Template( $this->ddfcpos_configuration );
		}

		/**
		 * Enqueue admin scripts function
		 *
		 * @return void
		 */
		public function ddfcpos_enqueue_admin_scripts() {
			if ( $this->dashboard->is_a_plugin_page() ) {
				wp_enqueue_media();

				wp_enqueue_style( 'ddfcpos-admin-style', DDFCPOS_PLUGIN_URL . 'assets/css/admin.css', array( DDFW_Assets::$framework_css_handle ), filemtime( DDFCPOS_PLUGIN_FILE . 'assets/css/admin.css' ) );

				wp_enqueue_script( 'ddfcpos-admin-script', DDFCPOS_PLUGIN_URL . 'assets/js/admin.js', array( DDFW_Assets::$framework_js_handle, 'wp-util' ), filemtime( DDFCPOS_PLUGIN_FILE . 'assets/js/admin.js' ), true );

				wp_localize_script(
					'ddfcpos-admin-script',
					'ddfcposAdminObj',
					array(
						'i18n'                  => array(
							'pleaseEnter'          => esc_html__( 'Please enter', 'devdiggers-multipos-for-fluentcart' ),
							'moreCharacter'        => esc_html__( 'or more character', 'devdiggers-multipos-for-fluentcart' ),
							'noResult'             => esc_html__( 'No result Found', 'devdiggers-multipos-for-fluentcart' ),
							'uploadIcon'           => esc_html__( 'Upload Icon', 'devdiggers-multipos-for-fluentcart' ),
							'barcodeError'         => esc_html__( 'Barcode field is empty.', 'devdiggers-multipos-for-fluentcart' ),
							'barcodeQuantityError' => esc_html__( 'Barcode quantity field is empty.', 'devdiggers-multipos-for-fluentcart' ),
							'barcodeSelectError'   => esc_html__( 'Tick the products to print labels for.', 'devdiggers-multipos-for-fluentcart' ),
							'previewTitle'         => esc_html__( 'Receipt preview', 'devdiggers-multipos-for-fluentcart' ),
							'previewHint'          => esc_html__( 'Sample order at real size.', 'devdiggers-multipos-for-fluentcart' ),
							/* translators: %s: printer paper width, like 80mm. */
							'previewPrinterWidth'  => esc_html__( 'Your printer (%s)', 'devdiggers-multipos-for-fluentcart' ),
							'previewPaper'         => esc_html__( 'Paper', 'devdiggers-multipos-for-fluentcart' ),
							'previewZoom'          => esc_html__( 'Zoom', 'devdiggers-multipos-for-fluentcart' ),
							'previewPrint'         => esc_html__( 'Print test', 'devdiggers-multipos-for-fluentcart' ),
							'previewClose'         => esc_html__( 'Close', 'devdiggers-multipos-for-fluentcart' ),
						),
						'ddfcpos_configuration' => $this->ddfcpos_configuration,
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing value.
						'invoicePreview'        => isset( $_GET['menu'] ) && 'invoices' === sanitize_key( wp_unslash( $_GET['menu'] ) ) ? ( new \DDFCMultiPOS\Helper\Invoice\DDFCPOS_Invoice_Helper( $this->ddfcpos_configuration ) )->ddfcpos_get_invoice_preview_data() : array(),
						'siteReferer'           => ! empty( $_SERVER['HTTP_REFERER'] ) ? false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), 'ddfcpos-cashiers' ) : false,
					)
				);
			}
		}

		/**
		 * Change the admin footer text function.
		 *
		 * @param  string $footer_text text to be rendered in the footer.
		 * @return string
		 */
		public function ddfcpos_set_admin_footer_text( $footer_text ) {
			if ( $this->dashboard->is_a_plugin_page() ) {
				$footer_text = sprintf(
					/* translators: %s for a tag */
					esc_html__( 'If you really like our plugin, please leave us a %s rating, we\'ll really appreciate it.', 'devdiggers-multipos-for-fluentcart' ),
					'<a href="https://wordpress.org/support/plugin/devdiggers-multipos-for-fluentcart/reviews/#new-post" target="_blank" aria-label="' . esc_attr__( 'Review', 'devdiggers-multipos-for-fluentcart' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
				);
			}

			return $footer_text;
		}
	}
}
