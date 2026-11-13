<?php
/**
 * Invoices Configuration template class
 *
 * @package MultiPOS for FluentCart
 * @version 2.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Invoices;

use DevDiggers\Framework\Includes\DDFW_Layout;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Invoices_Template' ) ) {
	/**
	 * Free invoice template list.
	 *
	 * The receipt composer is a Pro-only feature; its code is not shipped in the free
	 * build. The "Compose" action routes to an upgrade notice only.
	 */
	class DDFCPOS_Invoices_Template {
		/**
		 * Configuration variable.
		 *
		 * @var array
		 */
		protected $ddfcpos_configuration;

		/**
		 * Construct.
		 *
		 * @param array $ddfcpos_configuration Configuration.
		 */
		public function __construct( $ddfcpos_configuration ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input, no state change.
			$action = ! empty( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

			if ( 'compose' === $action ) {
				$this->ddfcpos_get_invoice_composer_upgrade();
				return;
			}

			$this->ddfcpos_get_invoice_template();
		}

		/**
		 * Render the Pro upgrade notice for the invoice composer.
		 *
		 * @return void
		 */
		public function ddfcpos_get_invoice_composer_upgrade() {
			ddfw_upgrade_to_pro_section(
				array(
					'image_url'     => file_exists( DDFCPOS_PLUGIN_FILE . 'assets/images/pro-pages/customize-invoice.webp' ) ? DDFCPOS_PLUGIN_URL . 'assets/images/pro-pages/customize-invoice.webp' : '',
					'heading'       => esc_html__( 'Design Your Own Receipts', 'devdiggers-multipos-for-fluentcart' ),
					'description'   => esc_html__( 'The free version ships one translatable receipt template. Upgrade to Pro to fully compose and customize receipts and manage multiple templates.', 'devdiggers-multipos-for-fluentcart' ),
					'list_features' => array(
						esc_html__( 'Visual and HTML receipt composer with a live preview', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Add your logo, store details, and custom blocks', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Create and manage multiple receipt templates', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Per-outlet receipt branding', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Show or hide tax, discount, and payment rows', 'devdiggers-multipos-for-fluentcart' ),
					),
					'upgrade_url'   => 'https://devdiggers.com/product/fluentcart-pos/',
				)
			);
		}

		/**
		 * Render invoice template list.
		 *
		 * @return void
		 */
		public function ddfcpos_get_invoice_template() {
			$compose_url  = admin_url( 'admin.php?page=ddfcpos-dashboard&menu=invoices&action=compose' );
			$default_name = esc_html__( 'Default Receipt', 'devdiggers-multipos-for-fluentcart' );
			$outlet       = ( new \DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper() )->ddfcpos_get_outlet_option();

			ob_start();
			?>
			<div class="ddfcpos-invoices-grid-wrapper">
				<div class="ddfcpos-invoices-grid">
					<div class="ddfcpos-invoice-card">
						<div class="ddfcpos-invoice-thumb" data-ddfcpos-invoice-thumb role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Preview', 'devdiggers-multipos-for-fluentcart' ); ?>"></div>

						<div class="ddfcpos-card-body">
							<div class="ddfcpos-invoice-card-title">
								<strong><?php echo esc_html( $default_name ); ?></strong>
								<mark class="ddfcpos-status ddfcpos-status-active"><?php esc_html_e( 'Active', 'devdiggers-multipos-for-fluentcart' ); ?></mark>
							</div>
							<code class="ddfcpos-invoice-slug">default-invoice</code>
							<p class="ddfcpos-invoice-usage">
								<?php
								if ( ! empty( $outlet['name'] ) ) {
									/* translators: %s: outlet name. */
									echo esc_html( sprintf( __( 'Used by %s', 'devdiggers-multipos-for-fluentcart' ), $outlet['name'] ) );
								} else {
									esc_html_e( 'Printed by every POS sale', 'devdiggers-multipos-for-fluentcart' );
								}
								?>
							</p>
						</div>

						<div class="ddfcpos-card-footer">
							<button type="button" class="button button-primary ddfcpos-invoice-fullscreen"><?php esc_html_e( 'Preview', 'devdiggers-multipos-for-fluentcart' ); ?></button>
							<a href="<?php echo esc_url( $compose_url ); ?>" class="button ddfw-upgrade-to-pro-tag-wrapper"><?php esc_html_e( 'Customize', 'devdiggers-multipos-for-fluentcart' ); ?></a>
						</div>
					</div>

					<a href="<?php echo esc_url( $compose_url ); ?>" class="ddfcpos-invoice-card create-new ddfw-upgrade-to-pro-tag-wrapper">
						<div class="ddfcpos-create-content">
							<span class="dashicons dashicons-plus"></span>
							<h3><?php esc_html_e( 'New Template', 'devdiggers-multipos-for-fluentcart' ); ?></h3>
							<p><?php esc_html_e( 'Design your own receipts in Pro.', 'devdiggers-multipos-for-fluentcart' ); ?></p>
						</div>
					</a>
				</div>
			</div>
			<?php
			$custom_html = ob_get_clean();

			$args = array(
				array(
					'header'            => array(
						'heading'     => esc_html__( 'Receipt Templates', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Every POS sale prints with this receipt. Open the preview to see it at real size on 58 mm, 80 mm or A4 paper, and print a test.', 'devdiggers-multipos-for-fluentcart' ),
					),
					'after_header_html' => $custom_html,
					'fields'            => array(),
				),
			);

			$layout = new DDFW_Layout();
			$layout->get_form_section_layout( $args, '', '', 'ddfcpos-invoices-container' );
		}
	}
}
