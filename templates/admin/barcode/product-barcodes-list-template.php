<?php
/**
 * Product Barcode List Template
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Barcode;

use DDFCMultiPOS\Helper\Barcode\DDFCPOS_Barcode_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Product;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Product_Barcodes_List_Template' ) ) {
	/**
	 * Product Barcode list class
	 */
	class DDFCPOS_Product_Barcodes_List_Template extends \WP_List_table {
		/**
		 * Barcode Helper variable
		 *
		 * @var object
		 */
		protected $barcode_helper;

		/**
         * Configuration Variable
         *
         * @var array
         */
        protected $ddfcpos_configuration;

		/**
		 * Class constructor
		 * 
		 * @param array $ddfcpos_configuration
		 */
		public function __construct( $ddfcpos_configuration ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;
			$this->barcode_helper        = new DDFCPOS_Barcode_Helper( $this->ddfcpos_configuration );
			parent::__construct( [
				'singular' => esc_html__( 'Product List', 'multipos-for-fluentcart' ),
				'plural'   => esc_html__( 'Products List', 'multipos-for-fluentcart' ),
				'ajax'     => false,
			] );
		}

		/**
		 * Prepare Items
		 *
		 * @return void
		 */
		public function prepare_items() {
			$this->_column_headers = $this->get_column_info();

			// The referer and nonce fields of a search are stripped on admin_init ( DDFCPOS_Admin_Hooks ).

			$search       = ! empty( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list search.
			$per_page     = $this->get_items_per_page( 'barcodes_per_page', 20 );
			$current_page = $this->get_pagenum();
			$total_items  = $this->ddfcpos_get_products_count( $search );

			$this->set_pagination_args( [
				'total_items' => $total_items,
				'per_page'    => $per_page,
			] );

			$data = $this->ddfcpos_get_products( $per_page, $current_page, $search );

			usort( $data, [ $this, 'usort_reorder' ] );

			$this->items = $data;
		}

		/**
		 * Get Products Count function
		 *
		 * @param string $search
		 * @return int
		 */
		public function ddfcpos_get_products_count( $search ) {
			return DDFCPOS_Product::query_sellable( [
				'search' => $search,
				'count'  => true,
			] );
		}

		/**
		 * Usort function
		 *
		 * @param int $first First value.
		 * @param int $second Second value.
		 * @return $result
		 */
		public function usort_reorder( $first, $second ) {
			$orderby = ! empty( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list sorting.
			$order   = ! empty( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list sorting.

			// An unknown column sorts nothing instead of raising an undefined index warning.
			$result = strnatcmp( (string) ( $first[ $orderby ] ?? '' ), (string) ( $second[ $orderby ] ?? '' ) );
			return $order === 'asc' ? $result : -$result;
		}

		/**
		 * Fetch Products
		 *
		 * @param int $per_page Per Page.
		 * @param int $current_page Page.
		 * @param string $search
		 * @return array $data
		 */
		public function ddfcpos_get_products( $per_page, $current_page, $search ) {
            $data   = [];
            $off    = ( $current_page - 1 ) * $per_page;

			$args = [
				'search' => $search,
				'limit'  => $per_page,
				'offset' => $off,
			];

			$args = apply_filters( 'ddfcpos/admin/barcodes/query', $args );

			foreach ( DDFCPOS_Product::query_sellable( $args ) as $row ) {
				$product = DDFCPOS_Product::from_sellable_row( $row );

				if ( ! $product ) {
					continue;
				}

				$product_id   = $product->get_id();
				$product_type = $product->get_type();

				// The free POS sells simple products, so only they get labels here.
				if ( 'simple' === $product_type ) {
					$product_type    = esc_html__( 'Simple', 'multipos-for-fluentcart' );
					$main_product_id = $product->get_post_id();

					$barcode = $this->barcode_helper->ddfcpos_get_barcode( $product );

					ob_start();
					?>
					<span class="ddfcpos-barcode ddfcpos-width-60"><?php echo esc_html( $barcode ); ?></span>
					<?php
					$barcode_html = ob_get_clean();

					$barcode_image_url       = $this->barcode_helper->ddfcpos_get_barcode_image_url( $barcode );
					$barcode_image_print_url = $barcode_image_url;

					ob_start();
					?>
					<img class="ddfcpos-barcode-image" <?php echo '' !== (string) $barcode ? 'src="' . esc_url( $barcode_image_url ) . '"' : 'style="display:none;"'; ?> alt="<?php esc_attr_e( 'Barcode', 'multipos-for-fluentcart' ); ?>" />
					<?php
					$barcode_image = ob_get_clean();

					$barcode_print_content = $this->barcode_helper->ddfcpos_get_label_html( $product, $barcode );

					ob_start();
					?>
					<div class="ddfcpos-barcode-print-content">
						<?php echo wp_kses_post( $barcode_print_content ); ?>
					</div>

					<input type="number" min="1" class="ddfcpos-barcode-quantity ddfcpos-width-60" placeholder="<?php esc_attr_e( 'Quantity', 'multipos-for-fluentcart' ); ?>" />
					<button class="button ddfcpos-product-action" data-action="print_barcode" data-product-id="<?php echo esc_attr( $product_id ); ?>"><?php esc_html_e( 'Print', 'multipos-for-fluentcart' ); ?></button>
					<?php
					$barcode_print = ob_get_clean();

					$data[] = apply_filters(
						'ddfcpos/admin/barcodes/row',
						[
							'id'              => $product_id,
							'main_product_id' => $main_product_id,
							'thumb'           => $product->get_image( 'thumbnail' ),
							'pro_name'        => $product->get_name(),
							'pro_type'        => $product_type,
							'price'           => $product->get_price_html(),
							'barcode'         => $barcode_html,
							'barcode_image'   => $barcode_image,
							'barcode_print'   => $barcode_print,
						]
					);
				}
			}

			return apply_filters( 'ddfcpos/admin/barcodes/rows', $data );
		}

		/**
		 * No items
		 *
		 * @return void
		 */
		public function no_items() {
			ddfw_print_empty_state( [
				'title'       => esc_html__( 'No products available', 'multipos-for-fluentcart' ),
				'description' => esc_html__( 'Products you add to your store will be listed here so you can assign barcodes.', 'multipos-for-fluentcart' ),
			] );
		}

		/**
		 * Hidden Columns
		 *
		 * @return array
		 */
		public function get_hidden_columns() {
			return [];
		}

		/**
		 *  Associative array of columns
		 *
		 * @return array
		 */
		public function get_columns() {
			$columns = [
				'cb'                 => '<input type="checkbox" />',
				'product'            => esc_html__( 'Product', 'multipos-for-fluentcart' ),
				'barcode_management' => esc_html__( 'Barcode Management', 'multipos-for-fluentcart' ),
			];

			return apply_filters( 'ddfcpos/admin/barcodes/columns', $columns );
		}

		/**
		 * Render a column when no column specific method exists.
		 *
		 * @param array  $item Items.
		 * @param string $column_name Name.
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
			return apply_filters( 'ddfcpos/admin/barcodes/sortable_columns', [
				'product' => [ 'pro_name', true ],
			] );
		}

		/**
		 * Checkbox for printing several labels at once.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_cb( $item ) {
			return sprintf( '<input type="checkbox" name="ddfcpos-id[]" value="%s" />', esc_attr( $item['id'] ) );
		}

		/**
		 * Bulk actions. Printing happens in the browser, so nothing is posted back.
		 *
		 * @return array
		 */
		public function get_bulk_actions() {
			return apply_filters(
				'ddfcpos/admin/barcodes/bulk_actions',
				[
					'ddfcpos_print_labels' => esc_html__( 'Print labels', 'multipos-for-fluentcart' ),
				]
			);
		}

		public function column_product( $item ) {
			$actions = [
				'edit' => sprintf( '<a href="%s">%s</a>', esc_url( DDFCPOS_FluentCart_Helper::product_admin_url( $item[ 'main_product_id' ] ) ), esc_html__( 'Edit', 'multipos-for-fluentcart' ) ),
				'view' => sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( get_permalink( $item[ 'main_product_id' ] ) ), esc_html__( 'View', 'multipos-for-fluentcart' ) ),
			];

			return sprintf(
				'<div class="ddfcpos-product-list-item">
					<div class="ddfcpos-product-thumb">%1$s</div>
					<div class="ddfcpos-product-details">
						<strong>%2$s</strong><br />
						<small>%3$s - %4$s</small>
						%5$s
					</div>
				</div>',
				$item[ 'thumb' ],
				$item[ 'pro_name' ],
				$item[ 'pro_type' ],
				$item[ 'price' ],
				$this->row_actions( apply_filters( 'ddfcpos/admin/barcodes/row_actions', $actions ) )
			);
		}

		public function column_barcode_management( $item ) {
			return sprintf(
				'<div class="ddfcpos-barcode-management-item">
					<div class="ddfcpos-action-tier">
						<span class="ddfcpos-tier-label">%1$s</span>
						<div class="ddfcpos-field-group">
							%2$s
							<div class="ddfcpos-barcode-preview">
								%3$s
							</div>
						</div>
					</div>
					<div class="ddfcpos-action-tier">
						<span class="ddfcpos-tier-label">%4$s</span>
						<div class="ddfcpos-field-group">
							%5$s
						</div>
					</div>
				</div>',
				esc_html__( 'Barcode Number', 'multipos-for-fluentcart' ),
				$item[ 'barcode' ],
				$item[ 'barcode_image' ],
				esc_html__( 'Print Label', 'multipos-for-fluentcart' ),
				$item[ 'barcode_print' ]
			);
		}
	}
}
