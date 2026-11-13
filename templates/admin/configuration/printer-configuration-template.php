<?php
/**
 * Printer Configuration template class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Configuration;

use DevDiggers\Framework\Includes\DDFW_Layout;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Printer_Configuration_Template' ) ) {
	/**
	 * Printer Configuration template class
	 */
	class DDFCPOS_Printer_Configuration_Template {
		/**
		 * Construct
		 *
		 * @param array $ddfcpos_configuration
		 */
		public function __construct( $ddfcpos_configuration ) {
			$pro_field = array(
				'field_class'       => array( 'ddfw-upgrade-to-pro-tag-wrapper' ),
				'custom_attributes' => array( 'disabled' => 'disabled' ),
			);

			$args = array(
				array(
					'header' => array(
						'heading'     => esc_html__( 'Barcode Paper', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Set paper size for barcode labels.', 'devdiggers-multipos-for-fluentcart' ),
					),
					'fields' => array(
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Page Width', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Label width in millimeters.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-barcode-printer-width',
							'value'       => $ddfcpos_configuration['barcode_printer_width'],
						),
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Page Height', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Label height in millimeters. Use 0 for continuous rolls.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-barcode-printer-height',
							'value'       => $ddfcpos_configuration['barcode_printer_height'],
						),
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Page Margins', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Label margins in millimeters.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-barcode-printer-margin',
							'value'       => $ddfcpos_configuration['barcode_printer_margin'],
						),
					),
				),
				array(
					'header' => array(
						'heading'     => esc_html__( 'Barcode Layout', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Set barcode size and spacing.', 'devdiggers-multipos-for-fluentcart' ),
					),
					'fields' => array(
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Generated Barcode Height', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Barcode height in millimeters.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-barcode-height',
							'value'       => $ddfcpos_configuration['barcode_height'],
						),
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Unit Spacing Offset', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Space between barcodes in millimeters.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-barcode-margin',
							'value'       => $ddfcpos_configuration['barcode_margin'],
						),
						array(
							'type'        => 'select',
							'label'       => esc_html__( 'Print Output Orientation', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose barcode print direction.', 'devdiggers-multipos-for-fluentcart' ),
							'options'     => array(
								'vertical'   => esc_html__( 'Vertical (Portrait)', 'devdiggers-multipos-for-fluentcart' ),
								'horizontal' => esc_html__( 'Horizontal (Landscape)', 'devdiggers-multipos-for-fluentcart' ),
							),
							'id'          => 'ddfcpos-barcode-orientation',
							'value'       => $ddfcpos_configuration['barcode_orientation'],
							'input_class' => array( 'ddfcpos-select2' ),
						),
					),
				),
				array(
					'header' => array(
						'heading'     => esc_html__( 'Barcode Label Content', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Print the product name, price and more next to the barcode, so the label works on the shelf too. Available in Pro.', 'devdiggers-multipos-for-fluentcart' ),
					),
					// Pro-only previews: disabled, never registered or saved.
					'fields' => array(
						array_merge(
							array(
								'type'        => 'select',
								'label'       => esc_html__( 'Label Details', 'devdiggers-multipos-for-fluentcart' ),
								'description' => esc_html__( 'Store name, product name, barcode number, SKU and price, with a live preview.', 'devdiggers-multipos-for-fluentcart' ),
								'id'          => 'ddfcpos-locked-barcode-label-fields',
								'value'       => '',
								'options'     => array(
									'' => esc_html__( 'Barcode only', 'devdiggers-multipos-for-fluentcart' ),
								),
							),
							$pro_field
						),
						array_merge(
							array(
								'type'        => 'number',
								'label'       => esc_html__( 'Label Text Size', 'devdiggers-multipos-for-fluentcart' ),
								'description' => esc_html__( 'Font size in pixels. The price prints 3px larger.', 'devdiggers-multipos-for-fluentcart' ),
								'id'          => 'ddfcpos-locked-barcode-label-font-size',
								'value'       => '10',
							),
							$pro_field
						),
					),
				),
				array(
					'header' => array(
						'heading'     => esc_html__( 'Receipt Size', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Set default size for printed receipts.', 'devdiggers-multipos-for-fluentcart' ),
					),
					'fields' => array(
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Thermal Roll Width', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Receipt width in millimeters, like 80 or 58.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-printer-width',
							'value'       => $ddfcpos_configuration['printer_width'],
						),
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Thermal Content Height', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Receipt height in millimeters. Leave empty for automatic height.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-printer-height',
							'value'       => $ddfcpos_configuration['printer_height'],
						),
						array(
							'type'        => 'text',
							'label'       => esc_html__( 'Safe Content Margins', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Receipt margins in millimeters to keep text from clipping.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-printer-margin',
							'value'       => $ddfcpos_configuration['printer_margin'],
						),
					),
				),
			);

			$layout = new DDFW_Layout();
			$layout->get_form_section_layout( $args, 'ddfcpos-printer-configuration-fields' );
		}
	}
}
