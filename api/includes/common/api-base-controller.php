<?php
/**
 * API Base Controller class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\API\Includes\Common;

use DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Base_Controller' ) ) {
	/**
	 * API Base Controller Class.
	 * 
	 * Provides common functionality for all API controllers including
	 * standardized error handling, input validation, and response formatting.
	 */
	abstract class DDFCPOS_API_Base_Controller {
		/**
		 * Base Name.
		 *
		 * @var string the route base
		 */
		public $base = '';

		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddfcpos_configuration;

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [];

		/**
		 * Constructor.
		 *
		 * @param array $ddfcpos_configuration Configuration array.
		 */
		public function __construct( $ddfcpos_configuration = [] ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;
		}

		/**
		 * Main API callback method.
		 * 
		 * This method handles common functionality like validation,
		 * error handling, and response formatting.
		 *
		 * @param array $request Request data.
		 * @return array|WP_Error Standardized response.
		 */
		public function ddfcpos_get_data( $request ) {
			try {
				// The POS always acts as the signed in user. Never trust an ID sent by the client,
				// or any cashier could act, and record sales, as somebody else.
				$request['cashier_id'] = get_current_user_id();

				// Validate required parameters
				$validation_result = $this->validate_required_params( $request );
				if ( is_wp_error( $validation_result ) ) {
					return $validation_result;
				}

				// Sanitize input data
				// $sanitized_request = $this->sanitize_request_data( $request );
				$sanitized_request = $request;

				// Execute the specific API logic
				$result = $this->execute_api_logic( $sanitized_request );

				// Check if result is an error
				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return $result;

				// Format and return response
				// return $this->format_response( $result, $sanitized_request );

			} catch ( \Exception $e ) {
				return $this->handle_exception( $e );
			}
		}

		/**
		 * Execute the specific API logic.
		 * 
		 * This method should be implemented by each API controller
		 * to handle their specific business logic.
		 *
		 * @param array $request Sanitized request data.
		 * @return mixed API-specific result.
		 */
		abstract protected function execute_api_logic( $request );

		/**
		 * Validate required parameters.
		 *
		 * @param array $request Request data.
		 * @return bool|WP_Error True if valid, WP_Error if invalid.
		 */
		protected function validate_required_params( $request ) {
			foreach ( $this->required_params as $param ) {
				if ( empty( $request[ $param ] ) ) {
					return new \WP_Error(
						'rest_missing_param',
						sprintf(
							/* translators: %s: parameter name */
							esc_html__( 'Missing required parameter: %s', 'devdiggers-multipos-for-fluentcart' ),
							$param
						),
						[ 'status' => 400, 'param' => $param ]
					);
				}
			}

			return true;
		}

		/**
		 * Sanitize request data.
		 *
		 * @param array $request Raw request data.
		 * @return array Sanitized request data.
		 */
		protected function sanitize_request_data( $request ) {
			$sanitized = [];

			foreach ( $request as $key => $value ) {
				$sanitized[ $key ] = $this->sanitize_value( $value );
			}

			return $sanitized;
		}

		/**
		 * Sanitize individual value based on its type.
		 *
		 * @param mixed $value Value to sanitize.
		 * @return mixed Sanitized value.
		 */
		protected function sanitize_value( $value ) {
			if ( is_array( $value ) ) {
				return array_map( [ $this, 'sanitize_value' ], $value );
			}

			if ( is_string( $value ) ) {
				return sanitize_text_field( $value );
			}

			if ( is_numeric( $value ) ) {
				return is_float( $value ) ? floatval( $value ) : intval( $value );
			}

			return $value;
		}

		/**
		 * Format API response.
		 *
		 * @param mixed $result API result.
		 * @param array $request Original request data.
		 * @return array Formatted response.
		 */
		protected function format_response( $result, $request ) {
			$response = [
				'success' => true,
				'data'    => $result,
				'message' => esc_html__( 'Request processed successfully', 'devdiggers-multipos-for-fluentcart' ),
			];

			// Apply filters for customization
			return apply_filters( 'ddfcpos/api/response', $response, $result, $request, $this->base );
		}

		/**
		 * Handle exceptions with standardized error format.
		 *
		 * @param \Exception $exception The exception to handle.
		 * @return WP_Error Formatted error response.
		 */
		protected function handle_exception( \Exception $exception ) {
			return new \WP_Error(
				'ddfcpos_api_error',
				esc_html__( 'An error occurred while processing your request', 'devdiggers-multipos-for-fluentcart' ),
				[
					'status'   => 500,
					'details'  => $exception->getMessage(),
					'endpoint' => $this->base,
				]
			);
		}

		/**
		 * Create success response.
		 *
		 * @param mixed $data Response data.
		 * @param string $message Success message.
		 * @return array Success response.
		 */
		protected function success_response( $data = null, $message = '' ) {
			return [
				'success' => true,
				'data'    => $data,
				'message' => $message ?: esc_html__( 'Operation completed successfully', 'devdiggers-multipos-for-fluentcart' ),
			];
		}

		/**
		 * Create error response.
		 *
		 * @param string $message Error message.
		 * @param string $code Error code.
		 * @param int $status HTTP status code.
		 * @return WP_Error Error response.
		 */
		protected function error_response( $message, $code = 'ddfcpos_error', $status = 400 ) {
			return new \WP_Error( $code, $message, [ 'status' => $status ] );
		}

		/**
		 * Validate user permissions.
		 *
		 * @param int $user_id User ID to validate.
		 * @param string $capability Required capability.
		 * @return bool|WP_Error True if valid, WP_Error if invalid.
		 */
		protected function validate_user_permissions( $user_id, $capability = 'read' ) {
			if ( ! $user_id || ! is_numeric( $user_id ) ) {
				return $this->error_response(
					esc_html__( 'Invalid user ID', 'devdiggers-multipos-for-fluentcart' ),
					'invalid_user_id',
					400
				);
			}

			// The user is always the signed-in one ( ddfcpos_get_data() sets it ).
			$user = get_user_by( 'id', $user_id );

			if ( ! $user ) {
				return $this->error_response(
					esc_html__( 'Invalid user ID', 'devdiggers-multipos-for-fluentcart' ),
					'invalid_user_id',
					400
				);
			}

			// Only check capabilities if user exists and capability checking is enabled
			if ( apply_filters( 'ddfcpos/api/check_capability', false, $user_id, $capability ) ) {
				if ( ! user_can( $user, $capability ) ) {
					return $this->error_response(
						esc_html__( 'Insufficient permissions', 'devdiggers-multipos-for-fluentcart' ),
						'insufficient_permissions',
						403
					);
				}
			}

			return true;
		}

		/**
		 * Validate outlet access.
		 *
		 * @param int $outlet_id Outlet ID to validate.
		 * @param int $user_id User ID for permission check.
		 * @return bool|WP_Error True if valid, WP_Error if invalid.
		 */
		protected function validate_outlet_access( $outlet_id, $user_id ) {
			if ( ! $outlet_id || ! is_numeric( $outlet_id ) ) {
				return $this->error_response(
					esc_html__( 'Invalid outlet ID', 'devdiggers-multipos-for-fluentcart' ),
					'invalid_outlet_id',
					400
				);
			}

			// The free plugin runs one outlet; any other id is not an outlet here.
			$outlet_helper = new DDFCPOS_Outlet_Helper();
			$outlet_data   = $outlet_helper->ddfcpos_get_outlet_details_by_id( $outlet_id );

			if ( empty( $outlet_data ) ) {
				return $this->error_response(
					esc_html__( 'This outlet is not available.', 'devdiggers-multipos-for-fluentcart' ),
					'outlet_not_available',
					403
				);
			}

			if ( 'enabled' !== $outlet_data['status'] ) {
				return $this->error_response(
					esc_html__( 'Outlet is disabled', 'devdiggers-multipos-for-fluentcart' ),
					'outlet_disabled',
					403
				);
			}

			return true;
		}

		/**
		 * Get configuration value with fallback.
		 *
		 * @param string $key Configuration key.
		 * @param mixed $default Default value if key not found.
		 * @return mixed Configuration value or default.
		 */
		protected function get_config( $key, $default = null ) {
			return isset( $this->ddfcpos_configuration[ $key ] ) 
				? $this->ddfcpos_configuration[ $key ] 
				: $default;
		}
	}
}
