<?php
/**
 * Common hooks class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Includes\Common;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Common_Hooks' ) ) {
	/**
	 * Common hooks class
	 */
	class DDFCPOS_Common_Hooks extends DDFCPOS_Common_Functions {
		/**
		 * Construct
		 */
		public function __construct( $ddfcpos_configuration ) {
			parent::__construct( $ddfcpos_configuration );

			add_filter( 'user_has_cap', [ '\\DDFCMultiPOS\\Helper\\FluentCart\\DDFCPOS_FluentCart_Helper', 'grant_manage_capability' ], 10, 3 );

			add_filter( 'fluent_cart/should_send_email_notification', [ $this, 'ddfcpos_filter_fluentcart_emails' ], 10, 2 );

			add_filter( 'ddfw_modify_svg_icons', [ $this, 'ddfcpos_add_svg_icons' ], 10, 2 );
		}
	}
}
