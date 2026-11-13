<?php
/**
 * Layout Configuration template class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Configuration;

use DevDiggers\Framework\Includes\DDFW_Layout;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Layout_Configuration_Template' ) ) {
	/**
	 * Layout Configuration template class
	 */
	class DDFCPOS_Layout_Configuration_Template {
		/**
		 * Construct
		 *
		 * @param array $ddfcpos_configuration
		 */
		public function __construct( $ddfcpos_configuration ) {
			// Disabled, non-functional Pro preview markers (no register_setting, never saved).
			$pro_field = array(
				'field_class'       => array( 'ddfw-upgrade-to-pro-tag-wrapper' ),
				'custom_attributes' => array( 'disabled' => 'disabled' ),
			);

			$args = array(
				array(
					'header' => array(
						'heading'     => esc_html__( 'Colors', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Choose colors used across POS screens and buttons.', 'devdiggers-multipos-for-fluentcart' ),
					),
					'fields' => array(
						array(
							'type'        => 'field_html',
							'label'       => esc_html__( 'Color Palette', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Pick a ready made palette, then fine tune any color below. Changing a color by hand switches to Custom.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-palette',
							'html'        => $this->get_palette_picker_html(),
						),
						array(
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Primary Brand Color', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Main color for active states and primary buttons.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-primary-color',
							'value'       => $ddfcpos_configuration['layout_primary_color'],
						),
						array(
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Secondary Brand Color', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Secondary color used in gradients and highlights.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-secondary-color',
							'value'       => $ddfcpos_configuration['layout_secondary_color'],
						),
						array(
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Primary Text Color', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Main text color across POS.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-font-color',
							'value'       => $ddfcpos_configuration['layout_font_color'],
						),
						array(
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Surface Color', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Background color for cards, panels, and inputs.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-surface-color',
							'value'       => $ddfcpos_configuration['layout_surface_color'],
						),
						array(
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Muted Background Color 1', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Muted background for smaller secondary elements.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-muted-background-color-1',
							'value'       => $ddfcpos_configuration['layout_muted_background_color_1'],
						),
						array(
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Muted Background Color 2', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Muted background for larger secondary sections.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-muted-background-color-2',
							'value'       => $ddfcpos_configuration['layout_muted_background_color_2'],
						),
						array(
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Button Text Color', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Text and icon color on primary buttons.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-button-font-color',
							'value'       => $ddfcpos_configuration['layout_button_font_color'],
						),
						array(
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Border Color', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Default border color for cards, inputs, and sections.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-border-color',
							'value'       => $ddfcpos_configuration['layout_border_color'],
						),
						array(
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Success Color', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Color used for success states and positive amounts.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-success-color',
							'value'       => $ddfcpos_configuration['layout_success_color'],
						),
					),
				),
				array(
					'header' => array(
						'heading'     => esc_html__( 'Typography', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Set font style and size for POS screens.', 'devdiggers-multipos-for-fluentcart' ),
					),
					'fields' => array(
						array(
							'type'        => 'select',
							'label'       => esc_html__( 'POS Font Family', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose font used in POS only.', 'devdiggers-multipos-for-fluentcart' ),
							'options'     => array(
								'open_sans'  => esc_html__( 'Open Sans', 'devdiggers-multipos-for-fluentcart' ),
								'poppins'    => esc_html__( 'Poppins', 'devdiggers-multipos-for-fluentcart' ),
								'lato'       => esc_html__( 'Lato', 'devdiggers-multipos-for-fluentcart' ),
								'montserrat' => esc_html__( 'Montserrat', 'devdiggers-multipos-for-fluentcart' ),
								'nunito'     => esc_html__( 'Nunito', 'devdiggers-multipos-for-fluentcart' ),
							),
							'id'          => 'ddfcpos-layout-pos-font-family',
							'value'       => $ddfcpos_configuration['layout_pos_font_family'],
							'input_class' => array( 'ddfcpos-select2' ),
						),
						array(
							'type'        => 'number',
							'label'       => esc_html__( 'Base Font Size', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Base font size in pixels.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-font-size',
							'value'       => $ddfcpos_configuration['layout_font_size'],
						),
						array(
							'type'        => 'number',
							'label'       => esc_html__( 'Global Corner Radius', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Corner radius in pixels for buttons, inputs, and cards.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-layout-radius',
							'value'       => $ddfcpos_configuration['layout_radius'],
						),
					),
				),
				array(
					'header' => array(
						'heading'     => esc_html__( 'Product Display', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Choose how products and variations appear to cashiers.', 'devdiggers-multipos-for-fluentcart' ),
					),
					'fields' => array(
						array(
							'type'        => 'select',
							'label'       => esc_html__( 'Product Card Orientation', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose whether product image sits left or top.', 'devdiggers-multipos-for-fluentcart' ),
							'options'     => array(
								'image_left' => esc_html__( 'Horizontal (Image Left, Text Right)', 'devdiggers-multipos-for-fluentcart' ),
								'image_top'  => esc_html__( 'Vertical (Image Top, Text Bottom)', 'devdiggers-multipos-for-fluentcart' ),
							),
							'id'          => 'ddfcpos-product-layout',
							'value'       => $ddfcpos_configuration['product_layout'],
							'input_class' => array( 'ddfcpos-select2' ),
						),
						array_merge(
							array(
								'type'        => 'select',
								'label'       => esc_html__( 'Variation Picker Style', 'devdiggers-multipos-for-fluentcart' ),
								'description' => esc_html__( 'Choose how cashiers pick variation options.', 'devdiggers-multipos-for-fluentcart' ),
								'options'     => array(
									'grid'  => esc_html__( 'Inline Grid (High Efficiency)', 'devdiggers-multipos-for-fluentcart' ),
									'popup' => esc_html__( 'Modal Popup (Clean Design)', 'devdiggers-multipos-for-fluentcart' ),
								),
								'id'          => 'ddfcpos-locked-product-variation-layout',
								'value'       => 'grid',
								'input_class' => array( 'ddfcpos-select2' ),
							),
							$pro_field
						),
					),
				),
				array(
					'header' => array(
						'heading'     => esc_html__( 'Product Details', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Choose which product details stay visible in grid.', 'devdiggers-multipos-for-fluentcart' ),
					),
					'fields' => array(
						array_merge(
							array(
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Individual Variation Display', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Show Variations as Individual Items', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Turn off to group variations under parent product.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-variation-product-enabled',
								'value'          => '',
							),
							$pro_field
						),
						array(
							'type'           => 'checkbox',
							'label'          => esc_html__( 'Real-time Stock Indicators', 'devdiggers-multipos-for-fluentcart' ),
							'checkbox_label' => esc_html__( 'Display Current Stock Count', 'devdiggers-multipos-for-fluentcart' ),
							'description'    => esc_html__( 'Show stock count on each product card.', 'devdiggers-multipos-for-fluentcart' ),
							'id'             => 'ddfcpos-show-product-stock-enabled',
							'value'          => $ddfcpos_configuration['show_product_stock_enabled'],
						),
						array_merge(
							array(
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Quick Keys', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Show a row of pinned best sellers above the products', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Pin a product with the star on its card.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-quick-keys-enabled',
								'value'          => '',
							),
							$pro_field
						),
					),
				),
			);

			$layout = new DDFW_Layout();
			$layout->get_form_section_layout( $args, 'ddfcpos-layout-configuration-fields' );
		}

		/**
		 * Preset POS palettes. Keys match the color picker ids without the "ddfcpos-layout-" prefix.
		 *
		 * @return array
		 */
		public function get_palettes() {
			$neutral = array(
				'surface-color'     => '#ffffff',
				'button-font-color' => '#ffffff',
				'success-color'     => '#16a34a',
			);

			// One brand color per preset: primary and secondary match, so buttons stay flat. Every
			// primary passes WCAG AA (4.5:1) with white button text. Pick two colors by hand for a gradient.
			return apply_filters(
				'ddfcpos_layout_color_palettes',
				array(
					'classic'  => array(
						'label'  => esc_html__( 'Classic Blue', 'devdiggers-multipos-for-fluentcart' ),
						'colors' => array_merge(
							$neutral,
							array(
								'primary-color'            => '#0256ff',
								'secondary-color'          => '#0256ff',
								'font-color'               => '#555555',
								'muted-background-color-1' => '#f1f3f7',
								'muted-background-color-2' => '#f9fafb',
								'border-color'             => '#e1e4e8',
								'success-color'            => '#15b71a',
							)
						),
					),
					'azure'    => array(
						'label'  => esc_html__( 'Azure', 'devdiggers-multipos-for-fluentcart' ),
						'colors' => array_merge(
							$neutral,
							array(
								'primary-color'            => '#0071e3',
								'secondary-color'          => '#0071e3',
								'font-color'               => '#515154',
								'muted-background-color-1' => '#f0f2f5',
								'muted-background-color-2' => '#f8f9fb',
								'border-color'             => '#e0e3e8',
								'success-color'            => '#16a34a',
							)
						),
					),
					'indigo'   => array(
						'label'  => esc_html__( 'Indigo', 'devdiggers-multipos-for-fluentcart' ),
						'colors' => array_merge(
							$neutral,
							array(
								'primary-color'            => '#4f46e5',
								'secondary-color'          => '#4f46e5',
								'font-color'               => '#52525b',
								'muted-background-color-1' => '#f1f1f8',
								'muted-background-color-2' => '#f9f9fc',
								'border-color'             => '#e3e3ee',
								'success-color'            => '#16a34a',
							)
						),
					),
					'ocean'    => array(
						'label'  => esc_html__( 'Ocean', 'devdiggers-multipos-for-fluentcart' ),
						'colors' => array_merge(
							$neutral,
							array(
								'primary-color'            => '#0369a1',
								'secondary-color'          => '#0369a1',
								'font-color'               => '#475569',
								'muted-background-color-1' => '#eef3f7',
								'muted-background-color-2' => '#f7fafc',
								'border-color'             => '#dde5ec',
								'success-color'            => '#16a34a',
							)
						),
					),
					'emerald'  => array(
						'label'  => esc_html__( 'Emerald', 'devdiggers-multipos-for-fluentcart' ),
						'colors' => array_merge(
							$neutral,
							array(
								'primary-color'            => '#047857',
								'secondary-color'          => '#047857',
								'font-color'               => '#4b5563',
								'muted-background-color-1' => '#eef4f1',
								'muted-background-color-2' => '#f7faf9',
								'border-color'             => '#dde7e2',
								'success-color'            => '#16a34a',
							)
						),
					),
					'teal'     => array(
						'label'  => esc_html__( 'Teal', 'devdiggers-multipos-for-fluentcart' ),
						'colors' => array_merge(
							$neutral,
							array(
								'primary-color'            => '#0f766e',
								'secondary-color'          => '#0f766e',
								'font-color'               => '#475569',
								'muted-background-color-1' => '#eef4f3',
								'muted-background-color-2' => '#f7fafa',
								'border-color'             => '#dde7e5',
								'success-color'            => '#16a34a',
							)
						),
					),
					'violet'   => array(
						'label'  => esc_html__( 'Violet', 'devdiggers-multipos-for-fluentcart' ),
						'colors' => array_merge(
							$neutral,
							array(
								'primary-color'            => '#7c3aed',
								'secondary-color'          => '#7c3aed',
								'font-color'               => '#52525b',
								'muted-background-color-1' => '#f3f1f8',
								'muted-background-color-2' => '#faf9fc',
								'border-color'             => '#e6e2ef',
								'success-color'            => '#16a34a',
							)
						),
					),
					'rose'     => array(
						'label'  => esc_html__( 'Rose', 'devdiggers-multipos-for-fluentcart' ),
						'colors' => array_merge(
							$neutral,
							array(
								'primary-color'            => '#e11d48',
								'secondary-color'          => '#e11d48',
								'font-color'               => '#525252',
								'muted-background-color-1' => '#f6f1f2',
								'muted-background-color-2' => '#fbf8f8',
								'border-color'             => '#ebe2e4',
								'success-color'            => '#16a34a',
							)
						),
					),
					'ember'    => array(
						'label'  => esc_html__( 'Ember', 'devdiggers-multipos-for-fluentcart' ),
						'colors' => array_merge(
							$neutral,
							array(
								'primary-color'            => '#c2410c',
								'secondary-color'          => '#c2410c',
								'font-color'               => '#57534e',
								'muted-background-color-1' => '#f5f1ee',
								'muted-background-color-2' => '#fbf9f7',
								'border-color'             => '#e9e2dc',
								'success-color'            => '#16a34a',
							)
						),
					),
					'graphite' => array(
						'label'  => esc_html__( 'Graphite', 'devdiggers-multipos-for-fluentcart' ),
						'colors' => array_merge(
							$neutral,
							array(
								'primary-color'            => '#18181b',
								'secondary-color'          => '#18181b',
								'font-color'               => '#52525b',
								'muted-background-color-1' => '#f1f1f3',
								'muted-background-color-2' => '#f9f9fa',
								'border-color'             => '#e4e4e7',
								'success-color'            => '#16a34a',
							)
						),
					),
				)
			);
		}

		/**
		 * Palette swatches plus a small live preview of POS elements.
		 *
		 * @return string
		 */
		public function get_palette_picker_html() {
			ob_start();
			?>
			<div class="ddfcpos-palette-picker">
				<div class="ddfcpos-palette-options" role="radiogroup" aria-label="<?php esc_attr_e( 'Color Palette', 'devdiggers-multipos-for-fluentcart' ); ?>">
					<?php foreach ( $this->get_palettes() as $key => $palette ) : ?>
						<button type="button" class="ddfcpos-palette-option" role="radio" aria-checked="false" data-palette="<?php echo esc_attr( $key ); ?>" data-colors="<?php echo esc_attr( wp_json_encode( $palette['colors'] ) ); ?>">
							<span class="ddfcpos-palette-swatches" aria-hidden="true">
								<i style="background:<?php echo esc_attr( $palette['colors']['primary-color'] ); ?>"></i>
								<i style="background:<?php echo esc_attr( $palette['colors']['muted-background-color-1'] ); ?>"></i>
								<i style="background:<?php echo esc_attr( $palette['colors']['font-color'] ); ?>"></i>
							</span>
							<span class="ddfcpos-palette-name"><?php echo esc_html( $palette['label'] ); ?></span>
						</button>
					<?php endforeach; ?>
					<span class="ddfcpos-palette-option ddfcpos-palette-custom" data-palette="custom" aria-hidden="true">
						<span class="ddfcpos-palette-swatches"><i></i><i></i><i></i></span>
						<span class="ddfcpos-palette-name"><?php esc_html_e( 'Custom', 'devdiggers-multipos-for-fluentcart' ); ?></span>
					</span>
				</div>

				<div class="ddfcpos-palette-preview" aria-hidden="true">
					<div class="ddfcpos-palette-preview-rail">
						<span class="ddfcpos-palette-preview-logo"></span>
						<span class="ddfcpos-palette-preview-nav ddfcpos-active"></span>
						<span class="ddfcpos-palette-preview-nav"></span>
						<span class="ddfcpos-palette-preview-nav"></span>
					</div>
					<div class="ddfcpos-palette-preview-main">
						<div class="ddfcpos-palette-preview-search"><?php esc_html_e( 'Search products...', 'devdiggers-multipos-for-fluentcart' ); ?></div>
						<div class="ddfcpos-palette-preview-grid">
							<div class="ddfcpos-palette-preview-card"><span></span><strong><?php esc_html_e( 'Coffee', 'devdiggers-multipos-for-fluentcart' ); ?></strong><em><?php echo wp_kses_post( DDFCPOS_FluentCart_Helper::format_price( 4.5 ) ); ?></em></div>
							<div class="ddfcpos-palette-preview-card"><span></span><strong><?php esc_html_e( 'Croissant', 'devdiggers-multipos-for-fluentcart' ); ?></strong><em><?php echo wp_kses_post( DDFCPOS_FluentCart_Helper::format_price( 3.2 ) ); ?></em></div>
						</div>
					</div>
					<div class="ddfcpos-palette-preview-cart">
						<div class="ddfcpos-palette-preview-line"><span><?php esc_html_e( 'Coffee x 2', 'devdiggers-multipos-for-fluentcart' ); ?></span><span><?php echo wp_kses_post( DDFCPOS_FluentCart_Helper::format_price( 9 ) ); ?></span></div>
						<div class="ddfcpos-palette-preview-line ddfcpos-palette-preview-success"><span><?php esc_html_e( 'Discount', 'devdiggers-multipos-for-fluentcart' ); ?></span><span><?php echo wp_kses_post( DDFCPOS_FluentCart_Helper::format_price( -1 ) ); ?></span></div>
						<div class="ddfcpos-palette-preview-pay"><?php /* translators: %s: sample amount to pay. */ echo wp_kses_post( sprintf( __( 'Pay %s', 'devdiggers-multipos-for-fluentcart' ), DDFCPOS_FluentCart_Helper::format_price( 8 ) ) ); ?></div>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}
