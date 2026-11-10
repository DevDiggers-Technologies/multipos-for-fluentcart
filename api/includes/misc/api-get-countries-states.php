<?php
/**
 * API Get Countries & states class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Api\Includes\Misc;

use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;

use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Get_Countries_States' ) ) {
	/**
	 * API Get Countries & states class
	 * 
	 * Handles countries and states retrieval for POS system with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Get_Countries_States extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name
		 *
		 * @var string $base the route base
		 */
		public $base = 'get-countries-states';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [];

		/**
		 * Execute the specific API logic for getting countries and states.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Countries and states data or error.
		 */
		protected function execute_api_logic( $request ) {
			try {
				$countries = DDFCPOS_FluentCart_Helper::countries();
				$states    = [];

				foreach ( array_keys( $countries ) as $country_code ) {
					$country_states = DDFCPOS_FluentCart_Helper::states( $country_code );

					// Same shape as before: false for countries without a state list.
					$states[ $country_code ] = ! empty( $country_states ) ? $country_states : false;
				}

				$store = DDFCPOS_FluentCart_Helper::store_address();

				$response = [
					'countries'    => $countries,
					'base_country' => $store['country'],
					'states'       => $states,
				];

				return apply_filters( 'ddfcpos/api/countries/response', $response, $request );
			} catch ( \Exception $e ) {
				return $this->error_response(
					$e->getMessage(),
					'countries_data_error',
					500
				);
			}
		}
	}
}
