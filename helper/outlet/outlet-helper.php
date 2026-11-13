<?php
/**
 * Outlet Helper class
 *
 * The free plugin runs one outlet, stored as the `_ddfcpos_outlet` option. MultiPOS Pro
 * moves this option into its outlets table on activation, so the outlet keeps id 1 there.
 *
 * @package MultiPOS for FluentCart
 */

namespace DDFCMultiPOS\Helper\Outlet;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Outlet_Helper' ) ) {
	/**
	 * Outlet Helper class
	 */
	class DDFCPOS_Outlet_Helper {
		/**
		 * Option that holds the outlet.
		 *
		 * @var string
		 */
		const DDFCPOS_OUTLET_OPTION = '_ddfcpos_outlet';

		/**
		 * The outlet id, matching the first row Pro creates from this option.
		 *
		 * @var int
		 */
		const DDFCPOS_OUTLET_ID = 1;

		/**
		 * Default outlet fields.
		 *
		 * @return array
		 */
		protected function ddfcpos_get_default_outlet() {
			return [
				'name'     => '',
				'mode'     => '',
				'address1' => '',
				'address2' => '',
				'city'     => '',
				'state'    => '',
				'country'  => '',
				'postcode' => '',
				'phone'    => '',
				'email'    => '',
				'payments' => '',
				'tables'   => '',
				'status'   => '',
				'created'  => '',
				'updated'  => '',
			];
		}

		/**
		 * Get the saved outlet, or null when it has not been set up yet.
		 *
		 * @return array|null
		 */
		public function ddfcpos_get_outlet_option() {
			$outlet = get_option( self::DDFCPOS_OUTLET_OPTION, [] );

			if ( empty( $outlet ) || ! is_array( $outlet ) || empty( $outlet['name'] ) ) {
				return null;
			}

			$outlet                   = wp_parse_args( $outlet, $this->ddfcpos_get_default_outlet() );
			$outlet['id']             = self::DDFCPOS_OUTLET_ID;
			$outlet['inventory_type'] = 'centralized';

			return $outlet;
		}

		/**
		 * Save the outlet.
		 *
		 * @param array $data Outlet fields.
		 * @return int Outlet id.
		 */
		public function ddfcpos_save_outlet( $data ) {
			$existing = get_option( self::DDFCPOS_OUTLET_OPTION, [] );
			$existing = is_array( $existing ) ? $existing : [];

			$data = wp_parse_args( array_intersect_key( (array) $data, $this->ddfcpos_get_default_outlet() ), $existing );
			$data = wp_parse_args( $data, $this->ddfcpos_get_default_outlet() );

			$data['mode']    = in_array( $data['mode'], [ 'grocery', 'restaurant' ], true ) ? $data['mode'] : 'grocery';
			$data['status']  = 'disabled' === $data['status'] ? 'disabled' : 'enabled';
			$data['tables']  = 'restaurant' === $data['mode'] ? $data['tables'] : maybe_serialize( [] );
			$data['created'] = ! empty( $existing['created'] ) ? $existing['created'] : current_time( 'Y-m-d H:i:s' );
			$data['updated'] = current_time( 'Y-m-d H:i:s' );

			update_option( self::DDFCPOS_OUTLET_OPTION, $data );

			return self::DDFCPOS_OUTLET_ID;
		}

		/**
		 * Get the outlet by id.
		 *
		 * @param int $id Outlet id.
		 * @return array|null
		 */
		public function ddfcpos_get_outlet_details_by_id( $id ) {
			$outlet = $this->ddfcpos_get_outlet_option();

			return ( $outlet && self::DDFCPOS_OUTLET_ID === (int) $id ) ? $outlet : null;
		}

		/**
		 * Get outlet modes function
		 *
		 * @return array
		 */
		public function ddfcpos_get_outlet_modes() {
			return [
				'grocery'    => esc_html__( 'Grocery/Retail', 'devdiggers-multipos-for-fluentcart' ),
				'restaurant' => esc_html__( 'Restaurant/Cafe', 'devdiggers-multipos-for-fluentcart' ),
			];
		}
	}
}
