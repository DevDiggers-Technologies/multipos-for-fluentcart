<?php
/**
 * API Get Categories class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\API\Includes\Products;

use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_GET_Product_Categories' ) ) {
	/**
	 * API Get Categories class
	 * 
	 * Handles product categories retrieval for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_GET_Product_Categories extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name.
		 *
		 * @var string the route base
		 */
		public $base = 'get-product-categories';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [ 'cashier_id', 'outlet_id' ];

		/**
		 * Execute the specific API logic for getting product categories.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Categories data or error.
		 */
		protected function execute_api_logic( $request ) {
			$cashier_id = intval( $request['cashier_id'] );
			$outlet_id  = intval( $request['outlet_id'] );

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

			// Check for custom approach filter
			if ( apply_filters( 'ddfcpos/api/categories/custom_query', false, $request ) ) {
				return apply_filters( 'ddfcpos/api/categories/custom_response', [], $request );
			}

			// Get categories
			return $this->get_product_categories( $request );
		}

		/**
		 * Get product categories.
		 *
		 * @param array $request Request data.
		 * @return array|WP_Error Categories data or error.
		 */
		protected function get_product_categories( $request ) {
			try {
				$categories = [];

				$args = [
					'taxonomy'     => \DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper::CATEGORY_TAXONOMY,
					'orderby'      => 'name',
					'show_count'   => 1,
					'pad_counts'   => 1,
					'hierarchical' => 1,
					'title_li'     => '',
					'hide_empty'   => 0,
				];

				$args = apply_filters( 'ddfcpos/api/categories/query', $args, $request );
				$all_categories = get_terms( $args );

				if ( is_wp_error( $all_categories ) ) {
					$all_categories = [];
				}

				foreach ( $all_categories as $cat ) {
					$cat_thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
					$cat_image_url    = wp_get_attachment_url( $cat_thumbnail_id );

					if ( apply_filters( 'ddfcpos/api/categories/item', true, $cat->term_id, $request ) ) {
						$categories[] = [
							'id'     => $cat->term_id,
							// Term names are stored HTML-escaped ( "Dairy &amp; Eggs" ); the POS prints text, not HTML.
							'name'   => html_entity_decode( $cat->name, ENT_QUOTES, 'UTF-8' ),
							'image'  => $cat_image_url,
							'parent' => $cat->parent,
							'count'  => $cat->count,
							'slug'   => $cat->slug,
						];
					}
				}

				return apply_filters( 'ddfcpos/api/categories/response', $categories, $request );

			} catch ( \Exception $e ) {
				return $this->error_response(
					$e->getMessage(),
				'categories_retrieval_error',
					500
				);
			}
		}
	}
}
