<?php
/**
 * FluentCart bridge.
 *
 * One place that knows how FluentCart stores money, tax, statuses, countries and
 * customers, so the rest of MultiPOS can stay store agnostic.
 *
 * FluentCart keeps every amount as an integer in cents. MultiPOS (and its POS app)
 * works with decimals, so values are converted at this boundary only.
 *
 * @package MultiPOS for FluentCart
 */

namespace DDFCMultiPOS\Helper\FluentCart;

use FluentCart\Api\CurrencySettings;
use FluentCart\App\Helpers\CurrenciesHelper;
use FluentCart\App\Helpers\Helper;
use FluentCart\App\Helpers\Status;
use FluentCart\App\Models\TaxRate;
use FluentCart\App\Modules\Tax\TaxCalculator;
use FluentCart\App\Modules\Tax\TaxModule;
use FluentCart\App\Services\Localization\LocalizationManager;
use FluentCart\App\Services\Money;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_FluentCart_Helper' ) ) {
	/**
	 * FluentCart bridge class.
	 */
	class DDFCPOS_FluentCart_Helper {
		/**
		 * Product category taxonomy registered by FluentCart.
		 */
		const CATEGORY_TAXONOMY = 'product-categories';

		/**
		 * Product post type registered by FluentCart.
		 */
		const PRODUCT_POST_TYPE = 'fluent-products';

		/**
		 * Order meta keys MultiPOS stores on a FluentCart order.
		 */
		const OUTLET_META  = '_ddfcpos_outlet_id';
		const CASHIER_META = '_ddfcpos_cashier_id';

		/*
		|--------------------------------------------------------------------------
		| Money
		|--------------------------------------------------------------------------
		*/

		/**
		 * Store currency code.
		 *
		 * @return string
		 */
		public static function currency() {
			return (string) CurrencySettings::get( 'currency' );
		}

		/**
		 * Currency symbol, decoded.
		 *
		 * @param string $currency Currency code, store currency when empty.
		 * @return string
		 */
		public static function currency_symbol( $currency = '' ) {
			return html_entity_decode( CurrenciesHelper::getCurrencySign( $currency ? $currency : self::currency() ) );
		}

		/**
		 * Decimal places of a currency.
		 *
		 * @param string $currency Currency code.
		 * @return int
		 */
		public static function decimals( $currency = '' ) {
			return (int) Money::getDecimals( $currency ? $currency : self::currency() );
		}

		/**
		 * Decimal separator.
		 *
		 * @return string
		 */
		public static function decimal_separator() {
			return 'comma' === CurrencySettings::get( 'decimal_separator' ) ? ',' : '.';
		}

		/**
		 * Thousand separator. FluentCart derives it from the decimal separator.
		 *
		 * @return string
		 */
		public static function thousand_separator() {
			return ',' === self::decimal_separator() ? '.' : ',';
		}

		/**
		 * Price format for accounting.js style formatters (%s symbol, %v value).
		 *
		 * @return string
		 */
		public static function price_format() {
			switch ( CurrencySettings::get( 'currency_position' ) ) {
				case 'after':
					return '%v%s';
				case 'iso_after':
				case 'symbool_after_iso':
					return '%v %s';
				case 'iso_before':
				case 'symbool_and_iso':
					return '%s %v';
				default:
					return '%s%v';
			}
		}

		/**
		 * Store currency for the framework dashboard charts ( the `ddfw_dashboard_currency` filter ).
		 *
		 * @return array
		 */
		public static function dashboard_currency() {
			$positions = [
				'%s%v'  => 'left',
				'%v%s'  => 'right',
				'%s %v' => 'left_space',
				'%v %s' => 'right_space',
			];

			return [
				'symbol'            => self::currency_symbol(),
				'position'          => $positions[ self::price_format() ],
				'decimals'          => self::decimals(),
				'decimalSeparator'  => self::decimal_separator(),
				'thousandSeparator' => self::thousand_separator(),
			];
		}

		/**
		 * Decimal amount to FluentCart cents.
		 *
		 * @param float|string $amount Decimal amount.
		 * @return int
		 */
		public static function to_cents( $amount ) {
			return (int) round( floatval( $amount ) * 100 );
		}

		/**
		 * FluentCart cents to a decimal amount.
		 *
		 * @param int|string $cents Amount in cents.
		 * @return float
		 */
		public static function from_cents( $cents ) {
			return round( floatval( $cents ) / 100, 4 );
		}

		/**
		 * Format a decimal amount the way the store shows prices. Stands in for wc_price().
		 *
		 * @param float  $amount   Decimal amount.
		 * @param string $currency Currency code.
		 * @return string HTML.
		 */
		public static function format_price( $amount, $currency = '' ) {
			$negative = floatval( $amount ) < 0;
			$price    = Helper::toDecimal( self::to_cents( abs( floatval( $amount ) ) ), true, $currency ? $currency : null );

			return '<span class="ddfcpos-amount amount">' . ( $negative ? '-' : '' ) . $price . '</span>';
		}

		/**
		 * Plain-text formatted price.
		 *
		 * @param float  $amount   Decimal amount.
		 * @param string $currency Currency code.
		 * @return string
		 */
		public static function format_price_text( $amount, $currency = '' ) {
			return html_entity_decode( wp_strip_all_tags( self::format_price( $amount, $currency ) ) );
		}

		/**
		 * Round to the store currency precision.
		 *
		 * @param float $amount Amount.
		 * @return float
		 */
		public static function round( $amount ) {
			return round( floatval( $amount ), self::decimals() );
		}

		/*
		|--------------------------------------------------------------------------
		| Tax
		|--------------------------------------------------------------------------
		*/

		/**
		 * FluentCart tax settings.
		 *
		 * @return array
		 */
		public static function tax_settings() {
			static $settings = null;

			if ( null === $settings ) {
				$settings = class_exists( TaxModule::class ) ? ( new TaxModule() )->getSettings() : [];
			}

			return $settings;
		}

		/**
		 * Whether tax is on.
		 *
		 * @return bool
		 */
		public static function tax_enabled() {
			return class_exists( TaxModule::class ) && TaxModule::isTaxEnabled();
		}

		/**
		 * Whether catalog prices already include tax.
		 *
		 * @return bool
		 */
		public static function prices_include_tax() {
			$settings = self::tax_settings();

			return isset( $settings['tax_inclusion'] ) ? 'included' === $settings['tax_inclusion'] : true;
		}

		/**
		 * Tax label shown next to prices.
		 *
		 * @return string
		 */
		public static function tax_label() {
			$settings = self::tax_settings();

			return ! empty( $settings['tax_display_label'] ) ? $settings['tax_display_label'] : esc_html__( 'Tax', 'multipos-for-fluentcart' );
		}

		/**
		 * Address array FluentCart's tax engine understands, built from an outlet.
		 *
		 * @param array $outlet Outlet row.
		 * @return array
		 */
		public static function outlet_tax_address( $outlet ) {
			$country = ! empty( $outlet['country'] ) ? strtoupper( $outlet['country'] ) : '';

			return [
				'country'  => $country,
				'state'    => self::state_code( $country, ! empty( $outlet['state'] ) ? $outlet['state'] : '' ),
				'city'     => ! empty( $outlet['city'] ) ? $outlet['city'] : '',
				'postcode' => ! empty( $outlet['postcode'] ) ? $outlet['postcode'] : '',
			];
		}

		/**
		 * Run FluentCart's tax engine on line items at an address.
		 *
		 * @param array $line_items Items with post_id, object_id, quantity, subtotal and discount_total in cents.
		 * @param array $address    Country, state, city, postcode.
		 * @return TaxCalculator|null
		 */
		public static function tax_calculator( $line_items, $address ) {
			if ( ! self::tax_enabled() || empty( $address['country'] ) || ! class_exists( TaxCalculator::class ) ) {
				return null;
			}

			$settings = self::tax_settings();

			return new TaxCalculator(
				$line_items,
				array_merge(
					$address,
					[
						'inclusive'    => self::prices_include_tax(),
						'tax_rounding' => ! empty( $settings['tax_rounding'] ) ? $settings['tax_rounding'] : 'item',
					]
				)
			);
		}

		/**
		 * Tax for one unit of a variation at an address.
		 *
		 * @param int   $post_id      Product ID.
		 * @param int   $variation_id Variation ID.
		 * @param int   $price_cents  Unit price in cents.
		 * @param array $address      Tax address.
		 * @return array { tax: float, rates: array, inclusive: bool }
		 */
		public static function unit_tax( $post_id, $variation_id, $price_cents, $address ) {
			$calculator = self::tax_calculator(
				[
					[
						'id'             => $variation_id,
						'post_id'        => $post_id,
						'object_id'      => $variation_id,
						'quantity'       => 1,
						'unit_price'     => $price_cents,
						'subtotal'       => $price_cents,
						'discount_total' => 0,
					],
				],
				$address
			);

			if ( ! $calculator ) {
				return [
					'tax'       => 0,
					'rates'     => [],
					'inclusive' => self::prices_include_tax(),
				];
			}

			$lines     = $calculator->getTaxedLines();
			$line      = ! empty( $lines[0] ) ? $lines[0] : [];
			$rates     = isset( $line['line_meta']['tax_config']['rates'] ) ? $line['line_meta']['tax_config']['rates'] : [];
			$inclusive = isset( $line['line_meta']['tax_config']['inclusive'] ) ? (bool) $line['line_meta']['tax_config']['inclusive'] : self::prices_include_tax();

			// The unrounded unit tax, from the rates FluentCart matched. The POS multiplies it by the
			// quantity and rounds per line, as FluentCart does, so both arrive at the same cent.
			$exact = 0;

			foreach ( $rates as $rate ) {
				$base   = empty( $rate['is_compound'] ) ? $price_cents : $price_cents + $exact;
				$exact += $inclusive ? $base * floatval( $rate['rate'] ) / ( 100 + floatval( $rate['rate'] ) ) : $base * floatval( $rate['rate'] ) / 100;
			}

			return [
				'tax'       => $rates ? round( $exact / 100, 6 ) : self::from_cents( isset( $line['tax_amount'] ) ? $line['tax_amount'] : 0 ),
				'rates'     => $rates,
				'inclusive' => $inclusive,
			];
		}

		/**
		 * Standard rates for an address, in the shape the POS app expects
		 * (rate, label, compound yes/no, shipping yes/no).
		 *
		 * @param array $address Tax address.
		 * @return array
		 */
		public static function tax_rates_for_address( $address ) {
			if ( ! self::tax_enabled() || empty( $address['country'] ) ) {
				return [];
			}

			$rates = TaxRate::query()
				->where( 'country', $address['country'] )
				->orderBy( 'priority', 'asc' )
				->get();

			$matched = [];

			foreach ( $rates as $rate ) {
				if ( $rate->state && $rate->state !== $address['state'] ) {
					continue;
				}

				if ( $rate->city && strtolower( $rate->city ) !== strtolower( $address['city'] ) ) {
					continue;
				}

				if ( $rate->postcode && ! in_array( strtoupper( str_replace( ' ', '', $address['postcode'] ) ), array_map( 'trim', explode( ',', strtoupper( str_replace( ' ', '', $rate->postcode ) ) ) ), true ) ) {
					continue;
				}

				// Only the standard class drives cart-level tax, per product rates come with each product.
				if ( (int) $rate->class_id !== self::standard_tax_class_id() ) {
					continue;
				}

				$matched[ $rate->id ] = [
					'id'       => (int) $rate->id,
					'rate'     => floatval( $rate->rate ),
					'label'    => $rate->name ? $rate->name : self::tax_label(),
					'shipping' => floatval( $rate->for_shipping ) ? 'yes' : 'no',
					'compound' => intval( $rate->is_compound ) ? 'yes' : 'no',
				];
			}

			return $matched;
		}

		/**
		 * ID of the standard tax class.
		 *
		 * @return int
		 */
		public static function standard_tax_class_id() {
			static $id = null;

			if ( null === $id ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
				$id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}fct_tax_classes WHERE slug = 'standard' ORDER BY id ASC LIMIT 1" );
			}

			return $id;
		}

		/*
		|--------------------------------------------------------------------------
		| Countries
		|--------------------------------------------------------------------------
		*/

		/**
		 * Country list (code => name).
		 *
		 * @return array
		 */
		public static function countries() {
			return LocalizationManager::getInstance()->countries();
		}

		/**
		 * States of a country (code => name).
		 *
		 * @param string $country Country code.
		 * @return array
		 */
		public static function states( $country ) {
			return $country ? (array) LocalizationManager::getInstance()->states( strtoupper( $country ) ) : [];
		}

		/**
		 * Country name by code.
		 *
		 * @param string $country Country code.
		 * @return string
		 */
		public static function country_name( $country ) {
			$countries = self::countries();

			return isset( $countries[ $country ] ) ? $countries[ $country ] : (string) $country;
		}

		/**
		 * State code from a state code or name.
		 *
		 * @param string $country Country code.
		 * @param string $state   State code or name.
		 * @return string
		 */
		public static function state_code( $country, $state ) {
			$states = self::states( $country );

			if ( isset( $states[ $state ] ) ) {
				return $state;
			}

			$code = array_search( $state, $states, true );

			return false !== $code ? $code : (string) $state;
		}

		/**
		 * State name from a code.
		 *
		 * @param string $country Country code.
		 * @param string $state   State code.
		 * @return string
		 */
		public static function state_name( $country, $state ) {
			$states = self::states( $country );

			return isset( $states[ $state ] ) ? $states[ $state ] : (string) $state;
		}

		/**
		 * Store base address, from the FluentCart store settings.
		 *
		 * @return array
		 */
		public static function store_address() {
			$settings = (array) get_option( 'fluent_cart_store_settings', [] );

			return [
				'address1' => isset( $settings['store_address1'] ) ? $settings['store_address1'] : '',
				'address2' => isset( $settings['store_address2'] ) ? $settings['store_address2'] : '',
				'city'     => isset( $settings['store_city'] ) ? $settings['store_city'] : '',
				'state'    => isset( $settings['store_state'] ) ? $settings['store_state'] : '',
				'postcode' => isset( $settings['store_postcode'] ) ? $settings['store_postcode'] : '',
				'country'  => isset( $settings['store_country'] ) ? $settings['store_country'] : '',
			];
		}

		/*
		|--------------------------------------------------------------------------
		| Orders
		|--------------------------------------------------------------------------
		*/

		/**
		 * Order statuses (slug => label).
		 *
		 * @return array
		 */
		public static function order_statuses() {
			return Status::getOrderStatuses();
		}

		/**
		 * Label of an order status.
		 *
		 * @param string $status Status slug.
		 * @return string
		 */
		public static function order_status_label( $status ) {
			$statuses = self::order_statuses();

			return isset( $statuses[ $status ] ) ? $statuses[ $status ] : ucwords( str_replace( [ '-', '_' ], ' ', (string) $status ) );
		}

		/**
		 * Admin URL of an order in FluentCart.
		 *
		 * @param int $order_id Order ID.
		 * @return string
		 */
		public static function order_admin_url( $order_id ) {
			return admin_url( 'admin.php?page=fluent-cart#/orders/' . absint( $order_id ) . '/view' );
		}

		/**
		 * Admin URL of a product in FluentCart.
		 *
		 * @param int $product_id Product ID.
		 * @return string
		 */
		public static function product_admin_url( $product_id ) {
			return admin_url( 'admin.php?page=fluent-cart#/products/' . absint( $product_id ) );
		}

		/**
		 * IDs of POS orders, newest first.
		 *
		 * @param array $args outlet_id, cashier_id, after (Y-m-d H:i:s, site time), before, limit, offset, count.
		 * @return int[]|int IDs, or the total when count is set.
		 */
		public static function query_pos_orders( $args = [] ) {
			global $wpdb;

			$args = wp_parse_args(
				$args,
				[
					'outlet_id'  => '',
					'cashier_id' => '',
					'after'      => '',
					'before'     => '',
					'limit'      => -1,
					'offset'     => 0,
					'count'      => false,
					'status'     => [],
				]
			);

			$joins  = "INNER JOIN {$wpdb->prefix}fct_order_meta om ON om.order_id = o.id AND om.meta_key = '" . self::OUTLET_META . "'";
			$wheres = [ "o.type IN ('payment','subscription')" ];

			if ( '' !== $args['outlet_id'] && null !== $args['outlet_id'] ) {
				$wheres[] = $wpdb->prepare( 'om.meta_value = %s', (string) $args['outlet_id'] );
			}

			if ( '' !== $args['cashier_id'] && null !== $args['cashier_id'] ) {
				$joins   .= " INNER JOIN {$wpdb->prefix}fct_order_meta cm ON cm.order_id = o.id AND cm.meta_key = '" . self::CASHIER_META . "'";
				$wheres[] = $wpdb->prepare( 'cm.meta_value = %s', (string) $args['cashier_id'] );
			}

			if ( $args['after'] ) {
				$wheres[] = $wpdb->prepare( 'o.created_at >= %s', get_gmt_from_date( $args['after'] ) );
			}

			if ( $args['before'] ) {
				$wheres[] = $wpdb->prepare( 'o.created_at <= %s', get_gmt_from_date( $args['before'] ) );
			}

			if ( ! empty( $args['status'] ) ) {
				$wheres[] = "o.status IN ('" . implode( "','", array_map( 'esc_sql', (array) $args['status'] ) ) . "')";
			}

			$where = implode( ' AND ', $wheres );

			if ( $args['count'] ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Parts are prepared above.
				return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT o.id) FROM {$wpdb->prefix}fct_orders o {$joins} WHERE {$where}" );
			}

			$limit = (int) $args['limit'] > 0 ? $wpdb->prepare( 'LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] ) : '';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Parts are prepared above.
			return array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT o.id FROM {$wpdb->prefix}fct_orders o {$joins} WHERE {$where} ORDER BY o.id DESC {$limit}" ) );
		}

		/**
		 * Order IDs that carry a meta value.
		 *
		 * @param string $key   Meta key.
		 * @param string $value Meta value.
		 * @return int[]
		 */
		public static function get_order_ids_by_meta( $key, $value ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
			return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}fct_order_meta WHERE meta_key = %s AND meta_value = %s", $key, (string) $value ) ) );
		}

		/*
		|--------------------------------------------------------------------------
		| Stock
		|--------------------------------------------------------------------------
		*/

		/**
		 * Whether FluentCart tracks stock at all (its Stock Management module).
		 *
		 * @return bool
		 */
		public static function stock_module_active() {
			return class_exists( '\FluentCart\Api\ModuleSettings' ) && \FluentCart\Api\ModuleSettings::isActive( 'stock_management' );
		}

		/**
		 * Run a callback with FluentCart's own stock listeners detached.
		 *
		 * A till sale is handed over on the spot, so MultiPOS books its stock itself
		 * (see commit_sold_stock). FluentCart's listeners would move it again on the order
		 * events, and they count a variation sold on two lines once. Every other listener
		 * (licenses, CRM, emails, integrations) still runs.
		 *
		 * @param callable $callback Callback.
		 * @return mixed What the callback returns.
		 */
		public static function without_fluentcart_stock( $callback ) {
			global $wp_filter;

			$detached = [];

			foreach ( [ 'fluent_cart/order_created', 'fluent_cart/order_paid', 'fluent_cart/shipping_status_changed' ] as $hook ) {
				if ( empty( $wp_filter[ $hook ] ) ) {
					continue;
				}

				foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $entry ) {
						$function = $entry['function'];

						if ( is_array( $function ) && is_object( $function[0] ) && $function[0] instanceof \FluentCart\App\Modules\StockManagement\StockManagement ) {
							remove_action( $hook, $function, $priority );
							$detached[] = [ $hook, $function, $priority, $entry['accepted_args'] ];
						}
					}
				}
			}

			try {
				return $callback();
			} finally {
				foreach ( $detached as $listener ) {
					add_action( $listener[0], $listener[1], $listener[2], $listener[3] );
				}
			}
		}

		/**
		 * Take sold units out of FluentCart stock and record the movement on the order,
		 * in the stock_movement meta FluentCart reads when an order is canceled or refunded.
		 *
		 * @param int   $order_id Order ID.
		 * @param array $items    Order item ID => [ variation_id, quantity ].
		 * @return void
		 */
		public static function commit_sold_stock( $order_id, $items ) {
			global $wpdb;

			if ( ! self::stock_module_active() ) {
				return;
			}

			$table    = $wpdb->prefix . 'fct_product_variations';
			$movement = [];
			$per_unit = [];

			foreach ( $items as $item_id => $item ) {
				$per_unit[ (int) $item['variation_id'] ] = ( isset( $per_unit[ (int) $item['variation_id'] ] ) ? $per_unit[ (int) $item['variation_id'] ] : 0 ) + (int) $item['quantity'];
			}

			$tracked = [];

			if ( $per_unit ) {
				$variation_ids = array_map( 'intval', array_keys( $per_unit ) );
				$placeholders  = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table; one %d placeholder per ID.
				$tracked = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE manage_stock = 1 AND id IN ($placeholders)", $variation_ids ) ) );
			}

			foreach ( $tracked as $variation_id ) {
				// One statement per variation, so two tills selling at once both count.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET available = GREATEST( available - %d, 0 ), committed = committed + %d, stock_status = IF( available > 0, 'in-stock', 'out-of-stock' ) WHERE id = %d", $per_unit[ $variation_id ], $per_unit[ $variation_id ], $variation_id ) );
			}

			$post_ids = [];

			foreach ( $items as $item_id => $item ) {
				if ( in_array( (int) $item['variation_id'], $tracked, true ) ) {
					$movement[ $item_id ] = [
						'committed' => (int) $item['quantity'],
						'on_hold'   => 0,
					];

					$post_ids[ (int) $item['post_id'] ] = true;
				}
			}

			if ( $movement ) {
				\FluentCart\App\Models\Order::query()->find( $order_id )->updateMeta( 'stock_movement', $movement );
			}

			foreach ( array_keys( $post_ids ) as $post_id ) {
				DDFCPOS_Product::sync_product_stock_availability( $post_id );
			}

			DDFCPOS_Product::flush_cache();
		}

		/*
		|--------------------------------------------------------------------------
		| Customers
		|--------------------------------------------------------------------------
		*/

		/**
		 * Grant the "ddfcpos_manage" capability (store manager in MultiPOS) to
		 * administrators and FluentCart shop managers. Hooked to user_has_cap.
		 *
		 * @param array $allcaps All capabilities of the user.
		 * @param array $caps    Required primitive capabilities.
		 * @param array $args    Arguments; [1] is the user ID.
		 * @return array
		 */
		public static function grant_manage_capability( $allcaps, $caps, $args ) {
			if ( ! in_array( 'ddfcpos_manage', (array) $caps, true ) || ! empty( $allcaps['ddfcpos_manage'] ) ) {
				return $allcaps;
			}

			$is_manager = ! empty( $allcaps['manage_options'] );

			if ( ! $is_manager && ! empty( $args[1] ) ) {
				$role       = get_user_meta( (int) $args[1], '_fluent_cart_admin_role', true );
				$is_manager = in_array( $role, [ 'super_admin', 'manager' ], true );
			}

			$allcaps['ddfcpos_manage'] = (bool) apply_filters( 'ddfcpos/access/can_manage', $is_manager, $args );

			return $allcaps;
		}

		/**
		 * Placeholder product image URL.
		 *
		 * @return string
		 */
		public static function placeholder_image_src() {
			return function_exists( 'ddfw_get_placeholder_image_src' ) ? ddfw_get_placeholder_image_src() : includes_url( 'images/media/default.svg' );
		}
	}
}
