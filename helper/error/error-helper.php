<?php
/**
 * Error Helper trait
 *
 * @package MultiPOS for FluentCart
 */

namespace DDFCMultiPOS\Helper\Error;

defined( 'ABSPATH' ) || exit();

if ( ! trait_exists( 'DDFCPOS_Error_Helper' ) ) {
	/**
	 *Error Helper trait
	*/
	trait DDFCPOS_Error_Helper {
		/**
		 * Print Notification function
		 *
		 * @param string $message
		 * @param string $error_type
		 * @return void
		 */
		public function ddfcpos_print_notification( $message, $type = 'success', $dismissible = true ) {
			if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
				?>
				<div class="notice notice-<?php echo esc_attr( $type . ' ' . ( $dismissible ? 'is-dismissible' : '' ) ); ?>">
					<p><?php echo wp_kses_post( $message ); ?></p>
				</div>
				<?php
			} else {
				printf( '<div class="ddfcpos-notice ddfcpos-notice-%1$s" role="alert">%2$s</div>', esc_attr( $type ), wp_kses_post( $message ) );
			}
		}
	}
}
