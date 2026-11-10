<?php
/**
 * This file handles all admin end action hooks.
 *
 * @author DevDiggers
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Includes\Admin;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Admin_Hooks' ) ) {
	/**
	 * Admin end hook handler class
	 */
	class DDFCPOS_Admin_Hooks extends DDFCPOS_Admin_Functions {
		/**
		 * Construct
		 */
		public function __construct( $ddfcpos_configuration ) {
			parent::__construct( $ddfcpos_configuration );

			add_action( 'admin_init', [ $this, 'ddfcpos_register_settings' ] );

			// Search and Filter on the list screens submit WordPress's referer and nonce fields in the URL. The lists
			// drop them with a redirect, which has to happen before the page prints anything or the screen goes blank.
			add_action( 'admin_init', static function () {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only strips WordPress's own referer and nonce fields from the URL; nothing is changed.
				if ( empty( $_GET['_wp_http_referer'] ) || ! isset( $_GET['ddfcpos_nonce'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
					return;
				}

				wp_safe_redirect( remove_query_arg( [ '_wp_http_referer', '_wpnonce' ], esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
				exit();
			} );

			add_action( 'admin_bar_menu', [ $this, 'ddfcpos_admin_bar_menu' ] );
		}
	}
}
