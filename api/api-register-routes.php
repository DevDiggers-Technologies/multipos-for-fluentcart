<?php
/**
 * API Register Route class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\API;

use DDFCMultiPOS\API\Includes;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Register_Routes' ) ) {
	/**
	 * Register API Routes Class.
	 * 
	 * Handles registration of all REST API routes for the MultiPOS system.
	 * Provides organized route management with proper validation and error handling.
	 */
	class DDFCPOS_API_Register_Routes {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddfcpos_configuration;

		/**
		 * API Namespace
		 *
		 * @var string
		 */
		const API_NAMESPACE = 'ddfcpos/v1';

		/**
		 * Constructor.
		 * 
		 * @param array $ddfcpos_configuration Plugin configuration array.
		 */
		public function __construct( $ddfcpos_configuration ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;

			$this->ddfcpos_init_api();
		}

		/**
		 * Initialize API functionality.
		 *
		 * @return void
		 */
		public function ddfcpos_init_api() {
			if ( is_user_logged_in() ) {
				add_action( 'rest_api_init', [ $this, 'ddfcpos_register_api_routes' ] );
			}
		}

		/**
		 * Register main API routes.
		 * 
		 * Registers the POS API routes: products, customers, coupons, stock, orders and the day's transactions.
		 *
		 * @return void
		 */
		public function ddfcpos_register_api_routes() {
			do_action( 'ddfcpos/api/before_routes' );

			if ( ! is_admin() ) {
				$this->ddfcpos_register_pos_routes();
			}

			do_action( 'ddfcpos/api/routes_registered' );
		}

		/**
		 * Register POS-specific routes.
		 * 
		 * Registers all POS functionality routes (products, customers, orders, etc.).
		 *
		 * @return void
		 */
		protected function ddfcpos_register_pos_routes() {
			$pos_routes = $this->ddfcpos_get_pos_routes();

			foreach ( $pos_routes as $route_key => $route_instance ) {
				$this->ddfcpos_register_single_route( $route_instance, 'POST' );
			}
		}

		/**
		 * Register a single API route.
		 * 
		 * @param object $route_instance Route instance object.
		 * @param string $method HTTP method.
		 * @return void
		 */
		protected function ddfcpos_register_single_route( $route_instance, $method ) {
			register_rest_route(
				self::API_NAMESPACE,
				$route_instance->base,
				[
					'methods'             => $method,
					'permission_callback' => [ $this, 'ddfcpos_permission_check' ],
					'callback'            => [ $route_instance, 'ddfcpos_get_data' ],
				]
			);
		}

		/**
		 * Check whether the current user can access POS REST routes.
		 *
		 * @return bool
		 */
		public function ddfcpos_permission_check( $request = null ) {
			if ( ! is_user_logged_in() ) {
				return false;
			}

			$user  = wp_get_current_user();
			$roles = ! empty( $user->roles ) ? (array) $user->roles : [];

			return current_user_can( 'ddfcpos_manage' ) || in_array( 'ddfcpos_cashier', $roles, true );
		}

		/**
		 * Get POS routes configuration.
		 * 
		 * @return array Array of POS route instances.
		 */
		protected function ddfcpos_get_pos_routes() {
			return [
				'get-products'            => new Includes\Products\DDFCPOS_API_Get_Products( $this->ddfcpos_configuration ),
				'get-product-categories'  => new Includes\Products\DDFCPOS_API_GET_Product_Categories( $this->ddfcpos_configuration ),
				'get-customers'           => new Includes\Customers\DDFCPOS_API_Get_Customers( $this->ddfcpos_configuration ),
				'get-countries-states'    => new Includes\Misc\DDFCPOS_API_Get_Countries_States( $this->ddfcpos_configuration ),
				'check-coupon'            => new Includes\Misc\DDFCPOS_API_Coupon_Check( $this->ddfcpos_configuration ),
				'check-centralized-stock' => new Includes\Misc\DDFCPOS_API_Check_Centralized_Stock( $this->ddfcpos_configuration ),
				'manage-customer'         => new Includes\Customers\DDFCPOS_API_Manage_Customer( $this->ddfcpos_configuration ),
				'delete-customer'         => new Includes\Customers\DDFCPOS_API_Delete_Customer( $this->ddfcpos_configuration ),
				'create-order'            => new Includes\Orders\DDFCPOS_API_Create_Order( $this->ddfcpos_configuration ),
				'get-orders'              => new Includes\Orders\DDFCPOS_API_Get_Orders( $this->ddfcpos_configuration ),
				'save-cashier'            => new Includes\Misc\DDFCPOS_API_Save_Cashier( $this->ddfcpos_configuration ),
				'get-transactions'        => new Includes\Transactions\DDFCPOS_API_Get_Transactions( $this->ddfcpos_configuration ),
			];
		}
	}
}
