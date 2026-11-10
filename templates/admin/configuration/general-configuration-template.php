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
						'heading'     => esc_html__( 'General', 'multipos-for-fluentcart' ),
						'description' => esc_html__( 'Turn POS features on, choose how stock works, and set core behavior for your registers.', 'multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'           => 'checkbox',
							'label'          => esc_html__( 'Plugin Status', 'multipos-for-fluentcart' ),
							'checkbox_label' => esc_html__( 'Enable Point of Sale System', 'multipos-for-fluentcart' ),
							'description'    => esc_html__( 'Turn POS on or off for this site.', 'multipos-for-fluentcart' ),
							'id'             => 'ddfcpos-enabled',
							'value'          => $ddfcpos_configuration['enabled'],
						],
						array_merge(
							[
								'type'        => 'select',
								'label'       => esc_html__( 'Inventory Management', 'multipos-for-fluentcart' ),
								'description' => esc_html__( 'Use Centralized to sync with FluentCart stock, or Custom to manage POS stock separately.', 'multipos-for-fluentcart' ),
								'options'     => [
									'centralized' => esc_html__( 'Centralized (Sync with FluentCart)', 'multipos-for-fluentcart' ),
									'custom'      => esc_html__( 'Custom (Manual POS Stock)', 'multipos-for-fluentcart' ),
								],
								'id'          => 'ddfcpos-locked-inventory-type',
								'value'       => 'centralized',
								'input_class' => [ 'ddfcpos-select2' ],
							],
							$pro_field
						),
						[
							'type'        => 'select',
							'label'       => esc_html__( 'Initial Order Status', 'multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose default status for new POS orders.', 'multipos-for-fluentcart' ),
							'options'     => DDFCPOS_FluentCart_Helper::order_statuses(),
							'id'          => 'ddfcpos-order-status',
							'value'       => $ddfcpos_configuration['order_status'],
							'input_class' => [ 'ddfcpos-select2' ],
						],
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Order Status Visibility', 'multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Show Current Order Status in Orders', 'multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Display each order status badge in the POS Orders list.', 'multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-show-order-status-enabled',
								'value'          => '',
							],
							$pro_field
						),
						[
							'type'        => 'select',
							'label'       => esc_html__( 'Barcode Synchronization', 'multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose whether barcodes use product ID or SKU.', 'multipos-for-fluentcart' ),
							'options'     => [
								'id'  => esc_html__( 'Use Product ID', 'multipos-for-fluentcart' ),
								'sku' => esc_html__( 'Use Product SKU', 'multipos-for-fluentcart' ),
							],
							'id'          => 'ddfcpos-default-barcode',
							'value'       => $ddfcpos_configuration['default_barcode'],
							'input_class' => [ 'ddfcpos-select2' ],
						],
					],
				],
				[
					'header' => [
						'heading'     => esc_html__( 'Checkout Features', 'multipos-for-fluentcart' ),
						'description' => esc_html__( 'Control checkout options your cashiers can use in POS.', 'multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'           => 'checkbox',
							'label'          => esc_html__( 'Order Notifications', 'multipos-for-fluentcart' ),
							'checkbox_label' => esc_html__( 'Enable Automated Order Mails', 'multipos-for-fluentcart' ),
							'description'    => esc_html__( 'Send FluentCart order emails after POS checkout.', 'multipos-for-fluentcart' ),
							'id'             => 'ddfcpos-order-mails-enabled',
							'value'          => $ddfcpos_configuration['order_mails_enabled'],
						],
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Multi-Payment Support', 'multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Enable Split/Multiple Payments', 'multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Let cashiers split payment across multiple methods.', 'multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-multiple-payments-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Customer & Order Notes', 'multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Allow Order Notes', 'multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Let cashiers add notes during checkout.', 'multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-order-note-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Offline Order Resilience', 'multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Enable Fast Offline-Online Sync', 'multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Let POS save orders offline and sync them when connection comes back.', 'multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-offline-orders-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Custom Product Entries', 'multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Allow Adding Custom Products', 'multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Let cashiers add custom items not saved in product catalog.', 'multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-custom-product-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Cash Management', 'multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Enable Cash Drawer Popup', 'multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Show prompt when cash drawer opens so staff can record it.', 'multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-opencash-drawer-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Calculated Unit Pricing', 'multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Enable Weight-Based Pricing', 'multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Update price by weight while adding weighted items.', 'multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-unit-price-enabled',
								'value'          => '',
							],
							$pro_field
						),
					],
				],
				[
					'header'            => [
						'heading'     => esc_html__( 'Kitchen', 'multipos-for-fluentcart' ),
						'description' => esc_html__( 'Restaurant and kitchen options for food service workflows.', 'multipos-for-fluentcart' ),
					],
					'after_header_html' => ddfw_get_upgrade_to_pro_section(
						[
							'heading'       => esc_html__( 'Send orders to a kitchen screen, in Pro', 'multipos-for-fluentcart' ),
							'description'   => esc_html__( 'MultiPOS Pro adds a kitchen display that runs in any browser and shows each order as it is sent from the till.', 'multipos-for-fluentcart' ),
							'list_features' => [
								esc_html__( 'Kitchen display at its own URL, such as /kitchen', 'multipos-for-fluentcart' ),
								esc_html__( 'Send held orders to the kitchen automatically or with one tap', 'multipos-for-fluentcart' ),
								esc_html__( 'Mark dishes as preparing or ready', 'multipos-for-fluentcart' ),
							],
							'upgrade_url'   => 'https://devdiggers.com/product/fluentcart-pos/',
						]
					),
				],
				[
					'header' => [
						'heading'     => esc_html__( 'Branding', 'multipos-for-fluentcart' ),
						'description' => esc_html__( 'Set logo used across POS admin and receipts.', 'multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'          => 'image',
							'label'         => esc_html__( 'POS Terminal Branding Logo', 'multipos-for-fluentcart' ),
							'description'   => esc_html__( 'Shown on receipts and POS admin screens. Recommended size: 200x60 pixels.', 'multipos-for-fluentcart' ),
							'id'            => 'ddfcpos-logo',
							'value'         => $ddfcpos_configuration['logo'],
							'default_image' => DDFCPOS_PLUGIN_URL . 'assets/images/logo.png',
						],
					],
				],
				[
					'header' => [
						'heading'     => esc_html__( 'Customers', 'multipos-for-fluentcart' ),
						'description' => esc_html__( 'Choose how POS handles guest and saved customers.', 'multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'        => 'select',
							'label'       => esc_html__( 'Default Guest Account', 'multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose the FluentCart customer used for walk-in (guest) orders.', 'multipos-for-fluentcart' ),
							'options'     => DDFCPOS_Admin_Functions::ddfcpos_get_customer_options( $ddfcpos_configuration['default_customer'] ),
							'id'          => 'ddfcpos-default-customer',
							'value'       => $ddfcpos_configuration['default_customer'],
							'input_class' => [ 'ddfcpos-select2' ],
						],
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Restricted Customer Logic', 'multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Strict Guest-Only Mode', 'multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Use guest account for every order and hide customer search.', 'multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-load-only-guest-customer-enabled',
								'value'          => '',
							],
							$pro_field
						),
						array_merge(
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Post-Checkout Cart Behavior', 'multipos-for-fluentcart' ),
								'checkbox_label' => esc_html__( 'Reset Customer After Checkout', 'multipos-for-fluentcart' ),
								'description'    => esc_html__( 'Clear selected customer after checkout completes.', 'multipos-for-fluentcart' ),
								'id'             => 'ddfcpos-locked-reset-customer-enabled',
								'value'          => '',
							],
							$pro_field
						),
					],
				],
				[
					'header' => [
						'heading'     => esc_html__( 'Advanced', 'multipos-for-fluentcart' ),
						'description' => esc_html__( 'Set the POS terminal URL path.', 'multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'        => 'text',
							'label'       => esc_html__( 'POS Terminal URL Path', 'multipos-for-fluentcart' ),
							'description' => esc_html__( 'Choose URL path for POS screen, like /pos.', 'multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-endpoint',
							'value'       => $ddfcpos_configuration['endpoint'],
						],
						array_merge(
							[
								'type'        => 'number',
								'label'       => esc_html__( 'Order Sync Threshold', 'multipos-for-fluentcart' ),
								'description' => esc_html__( 'Load only recent orders to keep POS fast.', 'multipos-for-fluentcart' ),
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
