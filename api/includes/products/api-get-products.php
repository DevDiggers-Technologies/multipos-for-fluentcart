<?php
/**
 * API Get Products class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\API\Includes\Products;

use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;
use DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper;
use DDFCMultiPOS\Helper\Barcode\DDFCPOS_Barcode_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Product;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Get_Products' ) ) {
	/**
	 * API Get Products Class.
	 * 
	 * Handles product retrieval for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Get_Products extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name.
		 *
		 * @var string the route base
		 */
		public $base = 'get-products';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [ 'cashier_id', 'outlet_id' ];

		/**
		 * Outlet Helper Variable
		 *
		 * @var object
		 */
		protected $outlet_helper;

		/**
		 * Barcode Helper Variable
		 *
		 * @var object
		 */
		protected $barcode_helper;

		/**
		 * Constructor.
		 *
		 * @param array $ddfcpos_configuration Configuration array.
		 */
		public function __construct( $ddfcpos_configuration ) {
			parent::__construct( $ddfcpos_configuration );
			$this->outlet_helper  = new DDFCPOS_Outlet_Helper();
			$this->barcode_helper = new DDFCPOS_Barcode_Helper( $ddfcpos_configuration );
		}

		/**
		 * Execute the specific API logic for getting products.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Product data or error.
		 */
		protected function execute_api_logic( $request ) {
			$cashier_id   = intval( $request['cashier_id'] );
			$outlet_id    = intval( $request['outlet_id'] );
			$per_page     = isset( $request['per_page'] ) ? intval( $request['per_page'] ) : 10;
			$current_page = isset( $request['current_page'] ) ? intval( $request['current_page'] ) : 1;

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

			// Get outlet data
			$outlet_data = $this->outlet_helper->ddfcpos_get_outlet_details_by_id( $outlet_id );
			if ( ! $outlet_data ) {
				return $this->error_response(
					esc_html__( 'Outlet data not found', 'multipos-for-fluentcart' ),
					'outlet_data_not_found',
					404
				);
			}

			// Get tax rates for the outlet location
			$tax_rates = $this->get_tax_rates_for_outlet( $outlet_data );

			// Handle count request
			if ( -1 === $per_page ) {
				return $this->get_products_count( $request, $tax_rates );
			}

			// Handle products list request
			return $this->get_products_list( $request, $outlet_data, $tax_rates );
		}

		/**
		 * Tax rates for the outlet address, keyed by rate ID.
		 *
		 * @param array $outlet_data Outlet data.
		 * @return array Tax rates.
		 */
		protected function get_tax_rates_for_outlet( $outlet_data ) {
			return DDFCPOS_FluentCart_Helper::tax_rates_for_address( DDFCPOS_FluentCart_Helper::outlet_tax_address( $outlet_data ) );
		}

		/**
		 * MultiPOS product IDs in catalogue order. The free POS sells simple products.
		 *
		 * @param array $request Request data.
		 * @return array
		 */
		protected function ddfcpos_get_catalogue_ids( $request ) {
			global $wpdb;

			$statuses = apply_filters( 'ddfcpos/api/products/statuses', [ 'publish' ], $request );

			$statuses     = array_values( array_map( 'strval', (array) $statuses ) );
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- FluentCart tables; values are placeholders.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID AS post_id, v.id AS variation_id, d.variation_type,
						( SELECT COUNT(*) FROM {$wpdb->prefix}fct_product_variations cv WHERE cv.post_id = p.ID AND ( cv.item_status IS NULL OR cv.item_status != 'inactive' ) ) AS variation_count
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->prefix}fct_product_variations v ON v.post_id = p.ID
					LEFT JOIN {$wpdb->prefix}fct_product_details d ON d.post_id = p.ID
					WHERE p.post_type = %s
						AND p.post_status IN ($placeholders)
						AND ( v.item_status IS NULL OR v.item_status != 'inactive' )
					ORDER BY p.ID DESC, v.serial_index ASC, v.id ASC",
					array_merge( [ DDFCPOS_FluentCart_Helper::PRODUCT_POST_TYPE ], $statuses )
				),
				ARRAY_A
			);
			// phpcs:enable

			$ids = [];

			foreach ( (array) $rows as $row ) {
				$is_simple = 1 === (int) $row['variation_count'] && ( empty( $row['variation_type'] ) || 'simple' === $row['variation_type'] );

				if ( $is_simple && ! in_array( (int) $row['post_id'], $ids, true ) ) {
					$ids[] = (int) $row['post_id'];
				}
			}

			return apply_filters( 'ddfcpos/api/products/catalogue_ids', $ids, $request );
		}

		/**
		 * Get total products count.
		 *
		 * @param array $request Request data.
		 * @param array $tax_rates Tax rates.
		 * @return array Response with count and tax rates.
		 */
		protected function get_products_count( $request, $tax_rates ) {
			$response = [
				'taxes'          => $tax_rates,
				'total_products' => apply_filters( 'ddfcpos/api/products/total', count( $this->ddfcpos_get_catalogue_ids( $request ) ), $request ),
			];

			return apply_filters( 'ddfcpos/api/products/total_response', $response, $request );
		}

		/**
		 * Get products list with pagination.
		 *
		 * @param array $request Request data.
		 * @param array $outlet_data Outlet data.
		 * @param array $tax_rates Tax rates.
		 * @return array Products list.
		 */
		protected function get_products_list( $request, $outlet_data, $tax_rates ) {
			$outlet_id    = intval( $request['outlet_id'] );
			$per_page     = max( 1, intval( $request['per_page'] ) );
			$current_page = max( 1, intval( $request['current_page'] ) );
			$page_ids     = array_slice( $this->ddfcpos_get_catalogue_ids( $request ), ( $current_page - 1 ) * $per_page, $per_page );
			$products     = [];

			foreach ( $page_ids as $product_id ) {
				// Skip excluded products
				if ( apply_filters( 'ddfcpos/product/exclude', false, $product_id ) ) {
					continue;
				}

				$product_data = $this->ddfcpos_prepare_product_data( $product_id, $outlet_id, $request, $outlet_data );

				if ( $product_data ) {
					$products[] = $product_data;
				}
			}

			return apply_filters( 'ddfcpos/api/products/response', $products, $request );
		}

		/**
		 * Stock figures of a sellable product, from FluentCart stock.
		 *
		 * @param DDFCPOS_Product $product Product.
		 * @return array { stock, stock_quantity, stock_status, backorders, stock_html }
		 */
		protected function ddfcpos_get_stock_data( $product ) {
			$stock_status   = $product->get_stock_status();
			$stock_quantity = $product->get_stock_quantity();
			$backorders     = $product->backorders_allowed();

			if ( $backorders ) {
				$stock = 999999999;
				/* translators: %s for product stock quantity */
				$stock_html = '<mark class="instock">' . ( $stock_quantity > 0 ? sprintf( esc_html__( 'On Backorder(%s)', 'multipos-for-fluentcart' ), $stock_quantity ) : esc_html__( 'On Backorder', 'multipos-for-fluentcart' ) ) . '</mark>';
			} elseif ( 'instock' === $stock_status ) {
				$stock = $stock_quantity > 0 ? $stock_quantity : 999999999;
				/* translators: %s for product stock quantity */
				$stock_html = '<mark class="instock">' . ( $stock_quantity > 0 ? sprintf( esc_html__( 'In Stock(%s)', 'multipos-for-fluentcart' ), $stock_quantity ) : esc_html__( 'In Stock', 'multipos-for-fluentcart' ) ) . '</mark>';
			} else {
				$stock      = 0;
				$stock_html = '<mark class="outofstock">' . esc_html__( 'Out of Stock', 'multipos-for-fluentcart' ) . '</mark>';
			}

			return [
				'stock'          => $stock,
				'stock_quantity' => $stock_quantity,
				'stock_status'   => $backorders ? 'onbackorder' : $stock_status,
				'backorders'     => $backorders,
				'stock_html'     => $stock_html,
			];
		}

		/**
		 * Prices of a sellable product as the POS works with them.
		 *
		 * When catalogue prices include tax the POS sells at those prices and the tax is
		 * worked out of them on the order. When they exclude tax the POS adds the per unit
		 * tax for the outlet address on top.
		 *
		 * @param DDFCPOS_Product $product     Product.
		 * @param array           $outlet_data Outlet data.
		 * @return array { regular_price, sale_price, tax }
		 */
		protected function ddfcpos_get_price_data( $product, $outlet_data ) {
			$regular_price = floatval( $product->get_regular_price() );
			$sale_price    = floatval( $product->get_price() );
			$tax           = 0;

			if ( DDFCPOS_FluentCart_Helper::tax_enabled() && ! DDFCPOS_FluentCart_Helper::prices_include_tax() ) {
				$unit_tax = DDFCPOS_FluentCart_Helper::unit_tax( $product->get_post_id(), $product->get_variation_id(), $product->get_price_cents(), DDFCPOS_FluentCart_Helper::outlet_tax_address( $outlet_data ) );
				$tax      = $unit_tax['inclusive'] ? 0 : $unit_tax['tax'];
			}

			return [
				'regular_price' => $regular_price,
				'sale_price'    => $sale_price,
				'tax'           => $tax,
			];
		}

		/**
		 * Prepare product data function
		 *
		 * @param int|string $product_id  MultiPOS product ID.
		 * @param int        $outlet_id   Outlet ID.
		 * @param array      $request     Request.
		 * @param array      $outlet_data Outlet data.
		 * @return array|false
		 */
		public function ddfcpos_prepare_product_data( $product_id, $outlet_id, $request, $outlet_data = [] ) {
			$product = DDFCPOS_Product::get( $product_id );

			// The free POS sells simple products only.
			if ( ! $product || 'simple' !== $product->get_type() ) {
				return false;
			}

			// FluentCart Pro bundles take stock from their child products, which a till sale
			// does not book, so they stay with FluentCart checkout unless a store opts in.
			if ( $product->is_bundle() && ! apply_filters( 'ddfcpos/product/allow_bundles', false, $product ) ) {
				return false;
			}

			// Subscription plans are billed by FluentCart's checkout, not sold at a till.
			if ( 'subscription' === $product->get_payment_type() && ! apply_filters( 'ddfcpos/product/allow_subscriptions', false, $product ) ) {
				return false;
			}

			if ( empty( $outlet_data ) ) {
				$outlet_data = $this->outlet_helper->ddfcpos_get_outlet_details_by_id( $outlet_id );
			}

			$stock_data = $this->ddfcpos_get_stock_data( $product );

			if ( $stock_data['stock'] <= 0 ) {
				return false;
			}

			$price_data = $this->ddfcpos_get_price_data( $product, $outlet_data );

			return apply_filters(
				'ddfcpos/api/products/item',
				[
					'product_id'     => $product->get_id(),
					'sku'            => $product->get_sku(),
					'slug'           => $product->get_slug(),
					'title'          => $product->get_name(),
					'parent_id'      => $product->get_parent_id(),
					'price_html'     => $product->get_price_html(),
					'price'          => $price_data['sale_price'],
					'regular_price'  => $price_data['regular_price'],
					'on_sale'        => $product->is_on_sale(),
					'stock'          => $stock_data['stock'],
					'stock_html'     => $stock_data['stock_html'],
					'stock_status'   => $stock_data['stock_status'],
					'stock_quantity' => $stock_data['stock_quantity'],
					'image'          => $product->get_image( 'thumbnail' ),
					'categories'     => $product->get_category_ids(),
					'tax'            => $price_data['tax'],
					'type'           => 'simple',
					'barcode'        => $this->barcode_helper->ddfcpos_get_barcode( $product ),
				],
				$request
			);
		}
	}
}
