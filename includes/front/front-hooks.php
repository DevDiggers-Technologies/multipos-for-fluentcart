<?php
/**
 * This file handles all front end action hooks.
 *
 * @author DevDiggers
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Includes\Front;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Front_Hooks' ) ) {
	/**
	 * Front end hooks class
	 */
	class DDFCPOS_Front_Hooks extends DDFCPOS_Front_Functions {
		/**
		 * Construct
		 */
		public function __construct( $ddfcpos_configuration ) {
			parent::__construct( $ddfcpos_configuration );

			add_filter( 'query_vars', [ $this, 'ddfcpos_add_query_vars' ] );
			add_action( 'parse_request', [ $this, 'ddfcpos_parse_request' ] );
			add_action( 'wp_login_failed', [ $this, 'ddfcpos_login_failed' ] );
			add_filter( 'authenticate', [ $this, 'ddfcpos_login_empty_fields' ], 10, 3 );
			add_action( 'wp_enqueue_scripts', [ $this, 'ddfcpos_front_scripts' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'ddfcpos_deregister_front_scripts' ], 99999 );
		}
	}
}
