<?php
/**
 * File handler
 *
 * @author DevDiggers
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Includes;

use DDFCMultiPOS\Includes\Admin;
use DDFCMultiPOS\Includes\Front;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_File_Handler' ) ) {
	/**
	 * File handler class
	 */
	class DDFCPOS_File_Handler {

		/**
		 * Construct
		 */
		public function __construct() {
			global $ddfcpos_configuration;

			add_rewrite_endpoint( $ddfcpos_configuration['endpoint'], EP_ROOT | EP_PAGES );

			new Common\DDFCPOS_Common_Hooks( $ddfcpos_configuration );

			if ( is_admin() ) {
				new DDFCPOS_Admin_Dashboard( $ddfcpos_configuration );
				new Admin\DDFCPOS_Admin_Hooks( $ddfcpos_configuration );
				new Admin\DDFCPOS_Setup_Wizard();
			} elseif ( ! empty( $ddfcpos_configuration['enabled'] ) ) {
				new Front\DDFCPOS_Front_Hooks( $ddfcpos_configuration );
			}
		}
	}
}
