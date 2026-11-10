<?php
/**
 * Product adapter.
 *
 * A FluentCart product is a `fluent-products` post with one or more rows in
 * fct_product_variations. The sellable unit is always a variation (a simple product
 * has exactly one). MultiPOS works with three kinds of "product":
 *
 * - simple:    the post ID; stock, price and barcode come from its only variation.
 * - variable:  the post ID of a product with several variations (not sellable itself).
 * - variation: "v{variation_id}", one variation of a variable product.
 *
 * The adapter exposes the small WC_Product-like surface MultiPOS needs.
 *
 * @package MultiPOS for FluentCart
 */

namespace DDFCMultiPOS\Helper\FluentCart;

use FluentCart\App\Models\ProductVariation;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Product' ) ) {
	/**
	 * Product adapter class.
	 */
	class DDFCPOS_Product {
		/**
		 * Product post.
		 *
		 * @var \WP_Post
		 */
		protected $post;

		/**
		 * Variation row (null for a variable parent).
		 *
		 * @var ProductVariation|null
		 */
		protected $variation;

		/**
		 * All variations of the post.
		 *
		 * @var ProductVariation[]
		 */
		protected $variations = [];

		/**
		 * Product details row.
		 *
		 * @var object|null
		 */
		protected $detail;

		/**
		 * simple, variable or variation.
		 *
		 * @var string
		 */
		protected $type;

		/**
		 * Per request cache.
		 *
		 * @var array
		 */
		protected static $cache = [];

		/**
		 * Get a product by its MultiPOS ID (post ID, or "v{variation_id}").
		 *
		 * @param int|string $id MultiPOS product ID.
		 * @return static|false
		 */
		public static function get( $id ) {
			if ( $id instanceof self ) {
				return $id;
			}

			$id = (string) $id;

			if ( '' === $id ) {
				return false;
			}

			if ( isset( self::$cache[ $id ] ) ) {
				return self::$cache[ $id ];
			}

			if ( 'v' === $id[0] ) {
				$variation = ProductVariation::query()->find( absint( substr( $id, 1 ) ) );
				$product   = $variation ? self::from_variation( $variation ) : false;
			} else {
				$product = self::from_post( absint( $id ) );
			}

			self::$cache[ $id ] = $product;

			return $product;
		}

		/**
		 * Product from a post ID.
		 *
		 * @param int $post_id Post ID.
		 * @return static|false
		 */
		public static function from_post( $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || DDFCPOS_FluentCart_Helper::PRODUCT_POST_TYPE !== $post->post_type ) {
				return false;
			}

			$variations = self::load_variations( $post_id );

			if ( empty( $variations ) ) {
				return false;
			}

			$product             = new static();
			$product->post       = $post;
			$product->variations = $variations;
			$product->detail     = self::load_detail( $post_id );

			if ( 1 === count( $variations ) && ( ! $product->detail || 'simple' === $product->detail->variation_type ) ) {
				$product->type      = 'simple';
				$product->variation = reset( $variations );
			} else {
				$product->type = 'variable';
			}

			return $product;
		}

		/**
		 * Product (simple or variation) from a variation row.
		 *
		 * @param ProductVariation $variation Variation.
		 * @return static|false
		 */
		public static function from_variation( $variation ) {
			$parent = self::from_post( $variation->post_id );

			if ( ! $parent ) {
				return false;
			}

			if ( 'simple' === $parent->type ) {
				return $parent;
			}

			$product             = new static();
			$product->post       = $parent->post;
			$product->variations = $parent->variations;
			$product->detail     = $parent->detail;
			$product->variation  = $variation;
			$product->type       = 'variation';

			return $product;
		}

		/**
		 * Product that sells a FluentCart variation (what an order item points at).
		 *
		 * @param int $variation_id Variation ID.
		 * @return static|false
		 */
		public static function from_variation_id( $variation_id ) {
			$variation = $variation_id ? ProductVariation::query()->find( absint( $variation_id ) ) : null;

			return $variation ? self::from_variation( $variation ) : false;
		}

		/**
		 * Variations of a post, active only, in their sort order.
		 *
		 * @param int $post_id Post ID.
		 * @return ProductVariation[]
		 */
		protected static function load_variations( $post_id ) {
			return ProductVariation::query()
				->where( 'post_id', $post_id )
				->where( function ( $query ) {
					$query->whereNull( 'item_status' )->orWhere( 'item_status', '!=', 'inactive' );
				} )
				->orderBy( 'serial_index', 'ASC' )
				->orderBy( 'id', 'ASC' )
				->get()
				->all();
		}

		/**
		 * Product details row.
		 *
		 * @param int $post_id Post ID.
		 * @return object|null
		 */
		protected static function load_detail( $post_id ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fct_product_details WHERE post_id = %d", $post_id ) );
		}

		/**
		 * Forget cached products, after a stock change.
		 *
		 * @return void
		 */
		public static function flush_cache() {
			self::$cache = [];
		}

		/*
		|--------------------------------------------------------------------------
		| Identity
		|--------------------------------------------------------------------------
		*/

		/**
		 * MultiPOS ID.
		 *
		 * @return int|string
		 */
		public function get_id() {
			return 'variation' === $this->type ? 'v' . $this->variation->id : (int) $this->post->ID;
		}

		/**
		 * FluentCart variation ID (0 for a variable parent).
		 *
		 * @return int
		 */
		public function get_variation_id() {
			return $this->variation ? (int) $this->variation->id : 0;
		}

		/**
		 * FluentCart product (post) ID.
		 *
		 * @return int
		 */
		public function get_post_id() {
			return (int) $this->post->ID;
		}

		/**
		 * Parent post ID for a variation, 0 otherwise.
		 *
		 * @return int
		 */
		public function get_parent_id() {
			return 'variation' === $this->type ? (int) $this->post->ID : 0;
		}

		/**
		 * Product type.
		 *
		 * @return string
		 */
		public function get_type() {
			return $this->type;
		}

		/**
		 * Display name. Variations get "Product - Variation".
		 *
		 * @return string
		 */
		public function get_name() {
			if ( 'variation' !== $this->type ) {
				return $this->post->post_title;
			}

			$label = $this->get_variation_label();

			if ( ! $label || $label === $this->post->post_title ) {
				return $this->post->post_title;
			}

			// Store owners often repeat the product name in the variation title ("iPhone 15 Pink").
			return 0 === stripos( $label, $this->post->post_title ) ? $label : $this->post->post_title . ' - ' . $label;
		}

		/**
		 * Variation label, e.g. "Pink 128GB".
		 *
		 * @return string
		 */
		public function get_variation_label() {
			if ( ! $this->variation ) {
				return '';
			}

			$label = method_exists( $this->variation, 'getVariationLabel' ) ? $this->variation->getVariationLabel() : '';

			return $label ? $label : (string) $this->variation->variation_title;
		}

		/**
		 * Slug.
		 *
		 * @return string
		 */
		public function get_slug() {
			return 'variation' === $this->type ? $this->post->post_name . '-' . $this->variation->id : $this->post->post_name;
		}

		/**
		 * SKU of the variation.
		 *
		 * @return string
		 */
		public function get_sku() {
			return $this->variation ? (string) $this->variation->sku : '';
		}

		/**
		 * Fulfillment type: physical or digital.
		 *
		 * @return string
		 */
		public function get_fulfillment_type() {
			if ( $this->variation && $this->variation->fulfillment_type ) {
				return $this->variation->fulfillment_type;
			}

			return $this->detail && $this->detail->fulfillment_type ? $this->detail->fulfillment_type : 'physical';
		}

		/**
		 * Whether this is a FluentCart Pro bundle (a product made of other products).
		 *
		 * @return bool
		 */
		public function is_bundle() {
			$info = $this->detail && $this->detail->other_info ? json_decode( $this->detail->other_info, true ) : [];

			return is_array( $info ) && isset( $info['is_bundle_product'] ) && 'yes' === $info['is_bundle_product'];
		}

		/**
		 * Payment type of the variation: onetime or subscription.
		 *
		 * @return string
		 */
		public function get_payment_type() {
			return $this->variation && $this->variation->payment_type ? $this->variation->payment_type : 'onetime';
		}

		/*
		|--------------------------------------------------------------------------
		| Children
		|--------------------------------------------------------------------------
		*/

		/**
		 * Child products of a variable product.
		 *
		 * @return static[]
		 */
		public function get_children_products() {
			if ( 'variable' !== $this->type ) {
				return [];
			}

			$children = [];

			foreach ( $this->variations as $variation ) {
				$child             = new static();
				$child->post       = $this->post;
				$child->variations = $this->variations;
				$child->detail     = $this->detail;
				$child->variation  = $variation;
				$child->type       = 'variation';

				self::$cache[ 'v' . $variation->id ] = $child;

				$children[] = $child;
			}

			return $children;
		}

		/*
		|--------------------------------------------------------------------------
		| Price (decimals)
		|--------------------------------------------------------------------------
		*/

		/**
		 * Selling price.
		 *
		 * @return float|string
		 */
		public function get_price() {
			if ( ! $this->variation ) {
				return $this->detail ? DDFCPOS_FluentCart_Helper::from_cents( $this->detail->min_price ) : '';
			}

			return DDFCPOS_FluentCart_Helper::from_cents( $this->variation->item_price );
		}

		/**
		 * Selling price in cents.
		 *
		 * @return int
		 */
		public function get_price_cents() {
			return $this->variation ? (int) $this->variation->item_price : 0;
		}

		/**
		 * Regular (compare at) price.
		 *
		 * @return float|string
		 */
		public function get_regular_price() {
			if ( ! $this->variation ) {
				return $this->get_price();
			}

			return $this->is_on_sale() ? DDFCPOS_FluentCart_Helper::from_cents( $this->variation->compare_price ) : $this->get_price();
		}

		/**
		 * On sale when a compare price above the price is set.
		 *
		 * @return bool
		 */
		public function is_on_sale() {
			return $this->variation && (int) $this->variation->compare_price > (int) $this->variation->item_price;
		}

		/**
		 * Price HTML.
		 *
		 * @return string
		 */
		public function get_price_html() {
			if ( 'variable' === $this->type ) {
				$min = $this->detail ? DDFCPOS_FluentCart_Helper::from_cents( $this->detail->min_price ) : 0;
				$max = $this->detail ? DDFCPOS_FluentCart_Helper::from_cents( $this->detail->max_price ) : 0;

				return $min === $max ? DDFCPOS_FluentCart_Helper::format_price( $min ) : DDFCPOS_FluentCart_Helper::format_price( $min ) . ' &ndash; ' . DDFCPOS_FluentCart_Helper::format_price( $max );
			}

			if ( $this->is_on_sale() ) {
				return '<del>' . DDFCPOS_FluentCart_Helper::format_price( $this->get_regular_price() ) . '</del> <ins>' . DDFCPOS_FluentCart_Helper::format_price( $this->get_price() ) . '</ins>';
			}

			return DDFCPOS_FluentCart_Helper::format_price( $this->get_price() );
		}

		/*
		|--------------------------------------------------------------------------
		| Stock
		|--------------------------------------------------------------------------
		*/

		/**
		 * Whether FluentCart tracks stock for the variation.
		 *
		 * With FluentCart's stock module off, FluentCart sells without limits and the till
		 * must too, so the variation's leftover stock numbers are ignored.
		 *
		 * @return bool
		 */
		public function managing_stock() {
			if ( ! DDFCPOS_FluentCart_Helper::stock_module_active() ) {
				return false;
			}

			if ( 'variable' === $this->type ) {
				return $this->detail && (int) $this->detail->manage_stock;
			}

			return $this->variation && (int) $this->variation->manage_stock;
		}

		/**
		 * Alias.
		 *
		 * @return bool
		 */
		public function get_manage_stock() {
			return $this->managing_stock();
		}

		/**
		 * Quantity available to sell, null when stock is not tracked.
		 *
		 * @return int|null
		 */
		public function get_stock_quantity() {
			if ( 'variable' === $this->type ) {
				if ( ! $this->managing_stock() ) {
					return null;
				}

				$total = 0;

				foreach ( $this->get_children_products() as $child ) {
					$total += max( 0, (int) $child->get_stock_quantity() );
				}

				return $total;
			}

			return $this->managing_stock() ? (int) $this->variation->available : null;
		}

		/**
		 * Stock status in MultiPOS terms: instock, outofstock or onbackorder.
		 *
		 * @return string
		 */
		public function get_stock_status() {
			if ( 'variable' === $this->type ) {
				foreach ( $this->get_children_products() as $child ) {
					if ( 'outofstock' !== $child->get_stock_status() ) {
						return 'instock';
					}
				}

				return 'outofstock';
			}

			if ( ! $this->managing_stock() ) {
				return 'out-of-stock' === $this->variation->stock_status ? 'outofstock' : 'instock';
			}

			if ( (int) $this->variation->available > 0 ) {
				return 'instock';
			}

			return $this->backorders_allowed() ? 'onbackorder' : 'outofstock';
		}

		/**
		 * Whether the variation sells past zero stock.
		 *
		 * @return bool
		 */
		public function backorders_allowed() {
			return $this->variation && $this->managing_stock() && (int) $this->variation->backorders > 0;
		}

		/**
		 * Update the product level "stock_availability" flag from its variations.
		 *
		 * @param int $post_id Product ID.
		 * @return void
		 */
		public static function sync_product_stock_availability( $post_id ) {
			$has_stock = ProductVariation::query()->where( 'post_id', $post_id )->where( 'stock_status', 'in-stock' )->exists();

			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
			$wpdb->update( $wpdb->prefix . 'fct_product_details', [ 'stock_availability' => $has_stock ? 'in-stock' : 'out-of-stock' ], [ 'post_id' => $post_id ] );
		}

		/*
		|--------------------------------------------------------------------------
		| Images and taxonomy
		|--------------------------------------------------------------------------
		*/

		/**
		 * Image attachment ID: the variation image, then the product image.
		 *
		 * @return int
		 */
		public function get_image_id() {
			if ( $this->variation && 'variation' === $this->type ) {
				if ( $this->variation->media_id ) {
					return (int) $this->variation->media_id;
				}

				$thumbnail = $this->get_variation_thumbnail();

				if ( ! empty( $thumbnail['id'] ) ) {
					return (int) $thumbnail['id'];
				}
			}

			$image_id = (int) get_post_thumbnail_id( $this->post );

			if ( $image_id ) {
				return $image_id;
			}

			// No featured image: the first gallery image, then the first variation that has one.
			$gallery = get_post_meta( $this->post->ID, 'fluent-products-gallery-image', true );

			if ( ! empty( $gallery[0]['id'] ) ) {
				return (int) $gallery[0]['id'];
			}

			foreach ( $this->variations as $variation ) {
				if ( $variation->media_id ) {
					return (int) $variation->media_id;
				}

				$sibling            = clone $this;
				$sibling->variation = $variation;
				$thumbnail          = $sibling->get_variation_thumbnail();

				if ( ! empty( $thumbnail['id'] ) ) {
					return (int) $thumbnail['id'];
				}
			}

			return 0;
		}

		/**
		 * Variation thumbnail entry from FluentCart's product meta.
		 *
		 * @return array
		 */
		protected function get_variation_thumbnail() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FluentCart table.
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}fct_product_meta WHERE object_id = %d AND meta_key = 'product_thumbnail' ORDER BY id DESC LIMIT 1", $this->variation->id ) );
			$value = $value ? json_decode( $value, true ) : [];

			return ! empty( $value[0] ) && is_array( $value[0] ) ? $value[0] : [];
		}

		/**
		 * Image URL.
		 *
		 * @param string $size Image size.
		 * @return string
		 */
		public function get_image_url( $size = 'thumbnail' ) {
			$image_id = $this->get_image_id();
			$url      = $image_id ? wp_get_attachment_image_url( $image_id, $size ) : '';

			if ( ! $url && $this->variation ) {
				$thumbnail = $this->get_variation_thumbnail();
				$url       = ! empty( $thumbnail['url'] ) ? $thumbnail['url'] : '';
			}

			return $url ? $url : DDFCPOS_FluentCart_Helper::placeholder_image_src();
		}

		/**
		 * Image HTML.
		 *
		 * @param string $size Image size.
		 * @return string
		 */
		public function get_image( $size = 'thumbnail' ) {
			$image_id = $this->get_image_id();

			if ( $image_id ) {
				$html = wp_get_attachment_image( $image_id, $size, false, [ 'alt' => $this->get_name() ] );

				if ( $html ) {
					return $html;
				}
			}

			return sprintf( '<img width="150" height="150" src="%1$s" class="attachment-thumbnail size-thumbnail" alt="%2$s" loading="lazy" />', esc_url( $this->get_image_url( $size ) ), esc_attr( $this->get_name() ) );
		}

		/**
		 * Category term IDs, with their ancestors.
		 *
		 * @return int[]
		 */
		public function get_category_ids() {
			$terms = get_the_terms( $this->post->ID, DDFCPOS_FluentCart_Helper::CATEGORY_TAXONOMY );
			$ids   = [];

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$ids[] = (int) $term->term_id;
					$ids   = array_merge( $ids, array_map( 'intval', get_ancestors( $term->term_id, DDFCPOS_FluentCart_Helper::CATEGORY_TAXONOMY, 'taxonomy' ) ) );
				}
			}

			return array_values( array_unique( $ids ) );
		}

		/**
		 * Sellable simple products for admin lists.
		 *
		 * @param array $args search, category, limit, offset, count, status.
		 * @return array|int Rows (post_id, variation_id, variation_type) or a count.
		 */
		public static function query_sellable( $args = [] ) {
			global $wpdb;

			$args = wp_parse_args(
				$args,
				[
					'search'   => '',
					'category' => 0,
					'limit'    => 20,
					'offset'   => 0,
					'count'    => false,
					'status'   => [ 'publish' ],
				]
			);

			$where = [ "p.post_type = '" . DDFCPOS_FluentCart_Helper::PRODUCT_POST_TYPE . "'" ];
			$where[] = "p.post_status IN ('" . implode( "','", array_map( 'esc_sql', (array) $args['status'] ) ) . "')";
			$where[] = "(v.item_status IS NULL OR v.item_status != 'inactive')";
			// The free POS sells simple products: one variation and no variation type.
			$where[] = "(d.variation_type IS NULL OR d.variation_type = '' OR d.variation_type = 'simple')";
			$where[] = "( SELECT COUNT(*) FROM {$wpdb->prefix}fct_product_variations sv WHERE sv.post_id = p.ID AND ( sv.item_status IS NULL OR sv.item_status != 'inactive' ) ) = 1";
			$join    = '';

			if ( $args['search'] ) {
				$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
				$where[] = $wpdb->prepare( '(p.post_title LIKE %s OR v.variation_title LIKE %s OR v.sku LIKE %s OR p.ID = %d OR v.id = %d)', $like, $like, $like, absint( $args['search'] ), absint( ltrim( $args['search'], 'vV' ) ) );
			}

			if ( $args['category'] ) {
				$join   .= " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id";
				$where[] = $wpdb->prepare( 'tt.term_id = %d', $args['category'] );
			}

			$where = implode( ' AND ', $where );
			$from  = "{$wpdb->posts} p INNER JOIN {$wpdb->prefix}fct_product_variations v ON v.post_id = p.ID LEFT JOIN {$wpdb->prefix}fct_product_details d ON d.post_id = p.ID {$join}";

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Parts are prepared above.
			if ( $args['count'] ) {
				return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT v.id) FROM {$from} WHERE {$where}" );
			}

			$limit = (int) $args['limit'] > 0 ? $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] ) : '';

			return $wpdb->get_results( "SELECT DISTINCT p.ID AS post_id, v.id AS variation_id, d.variation_type FROM {$from} WHERE {$where} ORDER BY p.ID DESC, v.serial_index ASC, v.id ASC {$limit}", ARRAY_A );
			// phpcs:enable
		}

		/**
		 * Product adapter for a row of query_sellable().
		 *
		 * @param array $row Row.
		 * @return static|false
		 */
		public static function from_sellable_row( $row ) {
			$product = self::from_post( $row['post_id'] );

			if ( ! $product || 'simple' === $product->get_type() ) {
				return $product;
			}

			return self::get( 'v' . $row['variation_id'] );
		}
	}
}
