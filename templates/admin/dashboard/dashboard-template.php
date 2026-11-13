<?php
/**
 * Dashboard Template
 *
 * A "today" overview: how the day is going against yesterday, what is selling, who is selling
 * it and the latest orders. Rendered through the shared devdiggers-framework dashboard builder
 * ( DDFW_Dashboard ); the plugin only supplies data + config.
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Dashboard;

use DDFCMultiPOS\Helper\Dashboard\DDFCPOS_Dashboard_Helper;
use DevDiggers\Framework\Includes\DDFW_Dashboard;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Dashboard_Template' ) ) {
	/**
	 * Dashboard template class
	 */
	class DDFCPOS_Dashboard_Template {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddfcpos_configuration;

		/**
		 * Dashboard Data Variable
		 *
		 * @var array
		 */
		protected $dashboard_data;

		/**
		 * Construct
		 *
		 * @param array $ddfcpos_configuration Plugin configuration.
		 */
		public function __construct( $ddfcpos_configuration ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;

			if ( ! class_exists( '\\DevDiggers\\Framework\\Includes\\DDFW_Dashboard' ) ) {
				return;
			}

			$this->dashboard_data = ( new DDFCPOS_Dashboard_Helper( $ddfcpos_configuration ) )->get_dashboard_data();

			$this->render();
		}

		/**
		 * Render dashboard via the shared framework builder.
		 *
		 * @return void
		 */
		protected function render() {
			$data = $this->dashboard_data;

			// Charts write money in FluentCart's currency, not WooCommerce's.
			add_filter( 'ddfw_dashboard_currency', [ DDFCPOS_FluentCart_Helper::class, 'dashboard_currency' ] );

			new DDFW_Dashboard(
				[
					'columns'       => 5,
					'header'        => [
						/* translators: %s: current user display name. */
						'welcome'          => esc_html__( 'Welcome back, %s! 👋🏻', 'devdiggers-multipos-for-fluentcart' ),
						/* translators: %s: today's date, e.g. Sunday, 25 October. */
						'subtitle'         => sprintf( esc_html__( 'Here\'s how your POS is doing today, %s, compared with yesterday.', 'devdiggers-multipos-for-fluentcart' ), wp_date( 'l, j F' ) ),
						'show_date_filter' => false,
						'actions'          => [
							[
								'label'   => esc_html__( 'View orders', 'devdiggers-multipos-for-fluentcart' ),
								'url'     => admin_url( 'admin.php?page=ddfcpos-dashboard&menu=orders' ),
							],
							[
								'label'   => esc_html__( 'Open POS', 'devdiggers-multipos-for-fluentcart' ),
								'url'     => site_url( $this->ddfcpos_configuration['endpoint'] ),
								'new_tab' => true,
								'primary' => true,
							],
						],
					],
					'summary_cards' => [
						$this->ddfcpos_card( esc_html__( 'Sales today', 'devdiggers-multipos-for-fluentcart' ), 'net_sales', true, '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' ),
						$this->ddfcpos_card( esc_html__( 'Orders', 'devdiggers-multipos-for-fluentcart' ), 'orders', false, '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' ),
						$this->ddfcpos_card( esc_html__( 'Average order', 'devdiggers-multipos-for-fluentcart' ), 'avg_order_value', true, '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 3v18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="m7 15 4-4 3 3 5-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' ),
						$this->ddfcpos_card( esc_html__( 'Items sold', 'devdiggers-multipos-for-fluentcart' ), 'items_sold', false, '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m3 8 9 5 9-5M12 13v8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' ),
						$this->ddfcpos_card( esc_html__( 'Refunds', 'devdiggers-multipos-for-fluentcart' ), 'refunds', true, '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 14 4 9l5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>', true ),
					],
					'charts'        => [
						[
							'id'           => 'today-by-hour',
							'title'        => esc_html__( 'Sales by hour', 'devdiggers-multipos-for-fluentcart' ),
							'date_label'   => esc_html__( 'Today vs yesterday', 'devdiggers-multipos-for-fluentcart' ),
							'full_width'   => true,
							'type'         => 'line',
							'value_format' => 'currency',
							'data'         => $data['hours'],
							'x_key'        => 'hour',
							'series'       => [
								[ 'key' => 'today', 'label' => esc_html__( 'Today', 'devdiggers-multipos-for-fluentcart' ) ],
								[ 'key' => 'yesterday', 'label' => esc_html__( 'Yesterday', 'devdiggers-multipos-for-fluentcart' ), 'style' => 'previous' ],
							],
							'empty'        => [
								'title' => esc_html__( 'No sales today or yesterday', 'devdiggers-multipos-for-fluentcart' ),
								'desc'  => esc_html__( 'Sales rung up at the POS show here hour by hour.', 'devdiggers-multipos-for-fluentcart' ),
							],
						],
					],
					'widgets'       => [
						[
							'title'  => esc_html__( 'Selling today', 'devdiggers-multipos-for-fluentcart' ),
							'render' => [ $this, 'render_products' ],
						],
						[
							'title'  => esc_html__( 'Recent POS orders', 'devdiggers-multipos-for-fluentcart' ),
							'render' => [ $this, 'render_recent_orders' ],
						],
						[
							'title'  => esc_html__( 'Payments', 'devdiggers-multipos-for-fluentcart' ),
							'width'  => 'half',
							'render' => [ $this, 'render_payments' ],
						],
						[
							'title'  => esc_html__( 'Cashiers', 'devdiggers-multipos-for-fluentcart' ),
							'width'  => 'half',
							'render' => [ $this, 'render_cashiers' ],
						],
					],
				]
			);
		}

		/**
		 * A summary card for one of today's totals, compared with yesterday.
		 *
		 * @param string $title   Card title.
		 * @param string $key     Totals key.
		 * @param bool   $money   Whether the value is an amount.
		 * @param string $icon    SVG icon.
		 * @param bool   $inverse A rise is bad news ( refunds ).
		 * @return array
		 */
		protected function ddfcpos_card( $title, $key, $money, $icon, $inverse = false ) {
			$current  = (float) ( $this->dashboard_data['totals'][ $key ] ?? 0 );
			$previous = (float) ( $this->dashboard_data['yesterday'][ $key ] ?? 0 );
			$format   = function ( $value ) use ( $money ) {
				return $money ? DDFCPOS_FluentCart_Helper::format_price( $value ) : number_format_i18n( $value );
			};

			// Nothing yesterday: say so instead of a meaningless percentage.
			$change = $previous ? round( ( ( $current - $previous ) / abs( $previous ) ) * 100, 1 ) : ( $current ? null : 0 );

			return [
				'title'             => $title,
				'value'             => $format( $current ),
				'value_type'        => 'html',
				'change'            => $change,
				'is_positive'       => $inverse ? $change <= 0 : $change >= 0,
				'icon'              => $icon,
				'no_previous_label' => esc_html__( 'Nothing yesterday', 'devdiggers-multipos-for-fluentcart' ),
				'previous'          => $format( $previous ),
				'note'              => 0.0 === (float) $change ? esc_html__( 'Same as yesterday', 'devdiggers-multipos-for-fluentcart' ) : '',
			];
		}

		/**
		 * Empty state for a widget with nothing to show yet.
		 *
		 * @param string $title       Title.
		 * @param string $description Description.
		 * @param string $icon        SVG icon.
		 * @return void
		 */
		protected function ddfcpos_empty( $title, $description, $icon = '' ) {
			ddfw_print_empty_state(
				[
					'title'       => $title,
					'description' => $description,
					'icon'        => $icon,
				]
			);
		}

		/**
		 * Orders count as "3 orders".
		 *
		 * @param int $count Orders.
		 * @return string
		 */
		protected function ddfcpos_orders_label( $count ) {
			/* translators: %s: number of orders. */
			return sprintf( _n( '%s order', '%s orders', $count, 'devdiggers-multipos-for-fluentcart' ), number_format_i18n( $count ) );
		}

		/**
		 * Today's best sellers.
		 *
		 * @return void
		 */
		public function render_products() {
			if ( empty( $this->dashboard_data['products'] ) ) {
				$this->ddfcpos_empty( esc_html__( 'Nothing sold yet today', 'devdiggers-multipos-for-fluentcart' ), esc_html__( 'Today\'s best sellers show up here.', 'devdiggers-multipos-for-fluentcart' ), '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>' );
				return;
			}

			DDFW_Dashboard::render_list(
				array_map(
					function ( $product ) {
						return [
							'title' => $product['name'],
							'url'   => $product['url'],
							'image' => $product['image'],
							/* translators: %s: number of items sold. */
							'meta'  => sprintf( _n( '%s sold', '%s sold', $product['items_sold'], 'devdiggers-multipos-for-fluentcart' ), number_format_i18n( $product['items_sold'] ) ),
							'value' => DDFCPOS_FluentCart_Helper::format_price( $product['net_sales'] ),
						];
					},
					$this->dashboard_data['products']
				)
			);
		}

		/**
		 * Today's tenders.
		 *
		 * @return void
		 */
		public function render_payments() {
			if ( empty( $this->dashboard_data['payments'] ) ) {
				$this->ddfcpos_empty( esc_html__( 'No payments yet', 'devdiggers-multipos-for-fluentcart' ), esc_html__( 'Cash, card and every other tender taken today.', 'devdiggers-multipos-for-fluentcart' ), '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 10h20M6 15h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>' );
				return;
			}

			DDFW_Dashboard::render_list(
				array_map(
					function ( $payment ) {
						return [
							'title' => $payment['name'],
							'meta'  => $this->ddfcpos_orders_label( $payment['orders'] ),
							'value' => DDFCPOS_FluentCart_Helper::format_price( $payment['amount'] ),
						];
					},
					$this->dashboard_data['payments']
				),
				'',
				false
			);
		}

		/**
		 * Today's sales per cashier.
		 *
		 * @return void
		 */
		public function render_cashiers() {
			if ( empty( $this->dashboard_data['cashiers'] ) ) {
				$this->ddfcpos_empty( esc_html__( 'No cashier sales yet', 'devdiggers-multipos-for-fluentcart' ), esc_html__( 'Each cashier\'s sales today show here.', 'devdiggers-multipos-for-fluentcart' ), '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="9" cy="8" r="4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1M16 4a4 4 0 0 1 0 8M22 21v-1a6 6 0 0 0-4-5.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>' );
				return;
			}

			DDFW_Dashboard::render_list(
				array_map(
					function ( $cashier ) {
						return [
							'title' => $cashier['name'],
							'image' => $cashier['id'] && get_option( 'show_avatars' ) ? get_avatar_url( $cashier['id'], [ 'size' => 72 ] ) : '',
							'shape' => 'round',
							'meta'  => $this->ddfcpos_orders_label( $cashier['orders'] ),
							'value' => DDFCPOS_FluentCart_Helper::format_price( $cashier['net_sales'] ),
						];
					},
					$this->dashboard_data['cashiers']
				)
			);

			$this->ddfcpos_count_note(
				/* translators: 1: cashiers who sold today, 2: all cashiers. */
				_n( '%1$s of %2$s cashier selling today', '%1$s of %2$s cashiers selling today', $this->dashboard_data['cashier_count'], 'devdiggers-multipos-for-fluentcart' ),
				count( $this->dashboard_data['cashiers'] ),
				$this->dashboard_data['cashier_count']
			);
		}

		/**
		 * "1 of 3 cashiers selling today" under a panel's list.
		 *
		 * @param string $format Text with the two counts.
		 * @param int    $active How many sold today.
		 * @param int    $all    How many there are.
		 * @return void
		 */
		protected function ddfcpos_count_note( $format, $active, $all ) {
			if ( $all ) {
				?>
				<p class="ddfw-dash-list-footnote"><?php echo esc_html( sprintf( $format, number_format_i18n( min( $active, $all ) ), number_format_i18n( $all ) ) ); ?></p>
				<?php
			}
		}

		/**
		 * Render the recent POS orders widget body.
		 *
		 * @return void
		 */
		public function render_recent_orders() {
			$orders = $this->dashboard_data['recent_orders'];

			if ( empty( $orders ) ) {
				$this->ddfcpos_empty( esc_html__( 'No POS orders yet', 'devdiggers-multipos-for-fluentcart' ), esc_html__( 'The latest sales from your registers show up here.', 'devdiggers-multipos-for-fluentcart' ), '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 2h14v20l-3-2-2 2-2-2-2 2-2-2-3 2V2Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>' );
				return;
			}
			?>
			<div class="ddfw-dash-list">
				<?php foreach ( $orders as $order ) : ?>
					<div class="ddfw-dash-list-item">
						<div class="ddfw-dash-list-info">
							<div class="ddfw-dash-list-title">
								<a href="<?php echo esc_url( DDFCPOS_FluentCart_Helper::order_admin_url( $order['id'] ) ); ?>"><strong>#<?php echo esc_html( $order['number'] ); ?></strong></a>
								<span class="ddfw-dash-status-badge ddfw-dash-status-<?php echo esc_attr( sanitize_html_class( $order['state'] ) ); ?>"><?php echo esc_html( $order['status'] ); ?></span>
							</div>
							<div class="ddfw-dash-list-meta"><?php echo esc_html( $order['customer'] . ' • ' . $order['date'] ); ?></div>
						</div>
						<div class="ddfw-dash-list-value"><?php echo wp_kses_post( $order['total'] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}
	}
}
