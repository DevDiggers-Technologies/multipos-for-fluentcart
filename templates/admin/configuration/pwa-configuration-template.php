<?php
/**
 * PWA Configuration template class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Configuration;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_PWA_Configuration_Template' ) ) {
	/**
	 * PWA Configuration template class
	 */
	class DDFCPOS_PWA_Configuration_Template {
		/**
		 * Construct
		 *
		 * @param array $ddfcpos_configuration
		 */
		public function __construct( $ddfcpos_configuration ) {
			ddfw_upgrade_to_pro_section(
				[
					'image_url'     => file_exists( DDFCPOS_PLUGIN_FILE . 'assets/images/pro-pages/pwa.webp' ) ? DDFCPOS_PLUGIN_URL . 'assets/images/pro-pages/pwa.webp' : '',
					'heading'       => esc_html__( 'Installable POS App (PWA)', 'devdiggers-multipos-for-fluentcart' ),
					'description'   => esc_html__( 'Turn the POS into an installable Progressive Web App with its own name, colors, and icons. Available in the Pro version.', 'devdiggers-multipos-for-fluentcart' ),
					'list_features' => [
						esc_html__( 'Custom app name, short name, and description', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Branded theme and splash background colors', 'devdiggers-multipos-for-fluentcart' ),
						esc_html__( 'Upload app icons for every screen size', 'devdiggers-multipos-for-fluentcart' ),
					],
					'upgrade_url'   => 'https://devdiggers.com/product/fluentcart-pos/',
				]
			);
		}
	}
}
