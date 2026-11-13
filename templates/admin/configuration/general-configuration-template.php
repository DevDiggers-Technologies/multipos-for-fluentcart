<?php
/**
 * General Configuration template class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Configuration;

use DevDiggers\Framework\Includes\DDFW_Layout;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;
use DDFCMultiPOS\Includes\Admin\DDFCPOS_Admin_Functions;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_General_Configuration_Template' ) ) {
	/**
	 * General Configuration template class
	 *
	 * Fields tagged with the ddfw-upgrade-to-pro-tag-wrapper class and a disabled
	 * attribute are previews of Pro-only features. They have no backend: they are
	 * not registered via register_setting(), never saved, and no code reads them.
	 * They exist only so users can see what the Pro version adds.
	 */
	class DDFCPOS_General_Configuration_Template {
		/**
		 * Construct
		 *
		 * @param array $ddfcpos_configuration
		 */
		public function __construct( $ddfcpos_configuration ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core settings-updated flag is read-only.
			if ( ! empty( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) {
				flush_rewrite_rules();
			}

			// Disabled, non-functional Pro preview markers (no register_setting, never saved).
			$pro_field = [
				'field_class'       => [ 'ddfw-upgrade-to-pro-tag-wrapper' ],
				'custom_attributes' => [ 'disabled' => 'disabled' ],
			];

			$args = [
				[
					'header' => [
						'heading'     => esc_html__( 'General', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Turn POS features on, choose how stock works, and set core behavior for your registers.', 'devdiggers-multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'           => 'checkbox',
							'label'          => esc_html__( 'Plugin Status', 'devdiggers-multipos-for-fluentcart' ),
							'checkbox_label' => esc_html__( 'Enable Point of Sale System', 'devdiggers-multipos-for-fluentcart' ),
							'description'    => esc_html__( 'Turn POS on or off for this site.', 'devdiggers-multipos-for-fluentcart' ),
							'id'             => 'ddfcpos-enabled',
							'value'          => $ddfcpos_configuration['enabled'],
						],
						array_merge(
							[
								'type'        => 'select',
								'label'       => esc_html__( 'Inventory Management', 'devdiggers-multipos-for-fluentcart' ),
								'description' => esc_html__( 'Use Centralized to sync with FluentCart stock, or Custom to manage POS stock separately.', 'devdiggers-multipos-for-fluentcart' ),
								'options'     => [
									'centralized' => esc_html__( 'Centralized (Sync with FluentCart)', 'devdiggers-multipos-for-fluentcart' ),
									'custom'      => esc_html__( 'Custom (Manual POS Stock)', 'devdiggers-multipos-for-fluentcart' ),
								],
								'id'          => 'ddfcpos-locked-inventory-type',
								'value'       => 'centralized',
								'input_class' => [ 'ddfcpos-select2' ],
							],
							$pro_field
						),
						[
							'type'        => 'select',
							'label'       => esc_html__( 'Initial Order Status', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose default status for new POS orders.', 'devdiggers-multipos-for-fluentcart' ),
							'options'     => DDFCPOS_FluentCart_Helper::order_statuses(),
							'id'          => 'ddfcpos-order-status',
							'value'       => $ddfcpos_configuration['order_status'],
							'input_class' => [ 'ddfcpos-select2' ],
						],
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Order Status Visibility', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Show Current Order Status in Orders', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Display each order status badge in the POS Orders list.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-show-order-status-enabled',
								'value'          => '',
							],
							$pro_field
						),
						[
							'type'        => 'select',
							'label'       => esc_html__( 'Barcode Synchronization', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose whether barcodes use product ID or SKU.', 'devdiggers-multipos-for-fluentcart' ),
							'options'     => [
								'id'  => esc_html__( 'Use Product ID', 'devdiggers-multipos-for-fluentcart' ),
								'sku' => esc_html__( 'Use Product SKU', 'devdiggers-multipos-for-fluentcart' ),
							],
							'id'          => 'ddfcpos-default-barcode',
							'value'       => $ddfcpos_configuration['default_barcode'],
							'input_class' => [ 'ddfcpos-select2' ],
						],
					],
				],
				[
					'header' => [
						'heading'     => esc_html__( 'Checkout Features', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Control checkout options your cashiers can use in POS.', 'devdiggers-multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'           => 'checkbox',
							'label'          => esc_html__( 'Order Notifications', 'devdiggers-multipos-for-fluentcart' ),
							'checkbox_label' => esc_html__( 'Enable Automated Order Mails', 'devdiggers-multipos-for-fluentcart' ),
							'description'    => esc_html__( 'Send FluentCart order emails after POS checkout.', 'devdiggers-multipos-for-fluentcart' ),
							'id'             => 'ddfcpos-order-mails-enabled',
							'value'          => $ddfcpos_configuration['order_mails_enabled'],
						],
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Multi-Payment Support', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Enable Split/Multiple Payments', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Let cashiers split payment across multiple methods.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-multiple-payments-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Customer & Order Notes', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Allow Order Notes', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Let cashiers add notes during checkout.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-order-note-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Offline Order Resilience', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Enable Fast Offline-Online Sync', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Let POS save orders offline and sync them when connection comes back.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-offline-orders-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Custom Product Entries', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Allow Adding Custom Products', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Let cashiers add custom items not saved in product catalog.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-custom-product-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Cash Management', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Enable Cash Drawer Popup', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Show prompt when cash drawer opens so staff can record it.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-opencash-drawer-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Calculated Unit Pricing', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Enable Weight-Based Pricing', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Update price by weight while adding weighted items.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-unit-price-enabled',
								'value'          => '',
							],
							$pro_field
						),
					],
				],
				[
					'header'            => [
						'heading'     => esc_html__( 'Kitchen', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Restaurant and kitchen options for food service workflows.', 'devdiggers-multipos-for-fluentcart' ),
					],
					'after_header_html' => ddfw_get_upgrade_to_pro_section(
						[
							'heading'       => esc_html__( 'Send orders to a kitchen screen, in Pro', 'devdiggers-multipos-for-fluentcart' ),
							'description'   => esc_html__( 'MultiPOS Pro adds a kitchen display that runs in any browser and shows each order as it is sent from the till.', 'devdiggers-multipos-for-fluentcart' ),
							'list_features' => [
								esc_html__( 'Kitchen display at its own URL, such as /kitchen', 'devdiggers-multipos-for-fluentcart' ),
								esc_html__( 'Send held orders to the kitchen automatically or with one tap', 'devdiggers-multipos-for-fluentcart' ),
								esc_html__( 'Mark dishes as preparing or ready', 'devdiggers-multipos-for-fluentcart' ),
							],
							'upgrade_url'   => 'https://devdiggers.com/product/fluentcart-pos/',
						]
					),
				],
				[
					'header' => [
						'heading'     => esc_html__( 'Branding', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Set logo used across POS admin and receipts.', 'devdiggers-multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'          => 'image',
							'label'         => esc_html__( 'POS Terminal Branding Logo', 'devdiggers-multipos-for-fluentcart' ),
							'description'   => esc_html__( 'Shown on receipts and POS admin screens. Recommended size: 200x60 pixels.', 'devdiggers-multipos-for-fluentcart' ),
							'id'            => 'ddfcpos-logo',
							'value'         => $ddfcpos_configuration['logo'],
							'default_image' => DDFCPOS_PLUGIN_URL . 'assets/images/logo.png',
						],
					],
				],
				[
					'header' => [
						'heading'     => esc_html__( 'Customers', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Choose how POS handles guest and saved customers.', 'devdiggers-multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'        => 'select',
							'label'       => esc_html__( 'Default Guest Account', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose the FluentCart customer used for walk-in (guest) orders.', 'devdiggers-multipos-for-fluentcart' ),
							'options'     => DDFCPOS_Admin_Functions::ddfcpos_get_customer_options( $ddfcpos_configuration['default_customer'] ),
							'id'          => 'ddfcpos-default-customer',
							'value'       => $ddfcpos_configuration['default_customer'],
							'input_class' => [ 'ddfcpos-select2' ],
						],
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Restricted Customer Logic', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Strict Guest-Only Mode', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Use guest account for every order and hide customer search.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-load-only-guest-customer-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Post-Checkout Cart Behavior', 'devdiggers-multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Reset Customer After Checkout', 'devdiggers-multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Clear selected customer after checkout completes.', 'devdiggers-multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-reset-customer-enabled',
								'value'          => '',
							],
							$pro_field
						),
					],
				],
				[
					'header' => [
						'heading'     => esc_html__( 'Advanced', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Set the POS terminal URL path.', 'devdiggers-multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'        => 'text',
							'label'       => esc_html__( 'POS Terminal URL Path', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose URL path for POS screen, like /pos.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-endpoint',
							'value'       => $ddfcpos_configuration['endpoint'],
						],
						array_merge(
							[
								'type'        => 'number',
								'label'       => esc_html__( 'Order Sync Threshold', 'devdiggers-multipos-for-fluentcart' ),
								'description' => esc_html__( 'Load only recent orders to keep POS fast.', 'devdiggers-multipos-for-fluentcart' ),
								'id'          => 'ddfcpos-locked-load-orders-only-days-old',
								'value'       => '',
							],
							$pro_field
						),
					],
				],
			];

			$layout = new DDFW_Layout();
			$layout->get_form_section_layout( $args, 'ddfcpos-general-configuration-fields' );
		}
	}
}
