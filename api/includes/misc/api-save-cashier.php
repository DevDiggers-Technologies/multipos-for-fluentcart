<?php
/**
 * Save Cashier class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Api\Includes\Misc;

use DDFCMultiPOS\API\Includes\Common\DDFCPOS_API_Base_Controller;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_API_Save_Cashier' ) ) {
	/**
	 * Save Cashier class
	 * 
	 * Handles cashier profile updates with proper validation,
	 * error handling, and standardized responses.
	 */
	class DDFCPOS_API_Save_Cashier extends DDFCPOS_API_Base_Controller {
		/**
		 * Base Name.
		 *
		 * @var string the route base
		 */
		public $base = 'save-cashier';

		/**
		 * Required parameters for validation
		 *
		 * @var array
		 */
		protected $required_params = [ 'cashier_id', 'cashier_data' ];

		/**
		 * Execute the specific API logic for saving cashier data.
		 * 
		 * @param array $request Sanitized request data.
		 * @return array|WP_Error Cashier data or error.
		 */
		protected function execute_api_logic( $request ) {
			$cashier_id = intval( $request['cashier_id'] );
			$cashier_data = is_array( $request['cashier_data'] ) ? $request['cashier_data'] : [];

			// Passwords are trimmed like the WordPress profile screen and never sanitized, which would change them.
			// They only reach wp_check_password() and wp_set_password(), never a hook, the database or a response.
			$passwords = [
				'current_password' => isset( $cashier_data['current_password'] ) ? trim( (string) $cashier_data['current_password'] ) : '',
				'new_password'     => isset( $cashier_data['new_password'] ) ? trim( (string) $cashier_data['new_password'] ) : '',
			];
			$cashier_data = array_merge( $this->sanitize_value( array_diff_key( $cashier_data, $passwords ) ), $passwords );

			// Validate user permissions
			$user_validation = $this->validate_user_permissions( $cashier_id );
			if ( is_wp_error( $user_validation ) ) {
				return $user_validation;
			}

			// The POS settings tab edits the signed-in user's own profile, nobody else's.
			if ( $cashier_id !== get_current_user_id() || empty( $cashier_data['id'] ) || (int) $cashier_data['id'] !== $cashier_id ) {
				return $this->error_response(
					esc_html__( 'You can only edit your own profile.', 'devdiggers-multipos-for-fluentcart' ),
					'invalid_cashier_id',
					403
				);
			}

			// Validate cashier data
			$data_validation = $this->validate_cashier_data( $cashier_data );
			if ( is_wp_error( $data_validation ) ) {
				return $data_validation;
			}

			// Update cashier profile
			return $this->update_cashier_profile( $cashier_id, $cashier_data );
		}

		/**
		 * Validate cashier data.
		 *
		 * @param array $cashier_data Cashier data to validate.
		 * @return bool|WP_Error True if valid, WP_Error if invalid.
		 */
		protected function validate_cashier_data( $cashier_data ) {
			if ( empty( $cashier_data['id'] ) || ! is_numeric( $cashier_data['id'] ) ) {
				return $this->error_response(
					esc_html__( 'Invalid cashier ID', 'devdiggers-multipos-for-fluentcart' ),
					'invalid_cashier_id',
					400
				);
			}

			if ( empty( $cashier_data['first_name'] ) || empty( $cashier_data['last_name'] ) ) {
				return $this->error_response(
					esc_html__( 'First name and last name are required', 'devdiggers-multipos-for-fluentcart' ),
					'missing_required_fields',
					400
				);
			}

			// Validate password change if provided
			if ( ! empty( $cashier_data['current_password'] ) || ! empty( $cashier_data['new_password'] ) ) {
				if ( empty( $cashier_data['current_password'] ) || empty( $cashier_data['new_password'] ) ) {
					return $this->error_response(
						esc_html__( 'Both current and new passwords are required for password change', 'devdiggers-multipos-for-fluentcart' ),
						'incomplete_password_data',
						400
					);
				}

				// Same rule as the WordPress profile screen.
				if ( false !== strpos( $cashier_data['new_password'], '\\' ) ) {
					return $this->error_response(
						esc_html__( 'Passwords may not contain the character "\\".', 'devdiggers-multipos-for-fluentcart' ),
						'invalid_password',
						400
					);
				}

				if ( strlen( $cashier_data['new_password'] ) < 6 ) {
					return $this->error_response(
						esc_html__( 'New password must be at least 6 characters long', 'devdiggers-multipos-for-fluentcart' ),
						'weak_password',
						400
					);
				}
			}

			return true;
		}

		/**
		 * Update cashier profile.
		 *
		 * @param int $cashier_id Cashier ID.
		 * @param array $cashier_data Cashier data.
		 * @return array|WP_Error Update result or error.
		 */
		protected function update_cashier_profile( $cashier_id, $cashier_data ) {
			try {
				// Update basic profile information
				$this->update_basic_profile( $cashier_id, $cashier_data );

				// Handle password change if provided
				if ( ! empty( $cashier_data['current_password'] ) && ! empty( $cashier_data['new_password'] ) ) {
					$password_result = $this->update_cashier_password( $cashier_id, $cashier_data );
					if ( is_wp_error( $password_result ) ) {
						return $password_result;
					}
				}

				return $this->success_response(
					null,
					esc_html__( 'Your account details are saved successfully.', 'devdiggers-multipos-for-fluentcart' )
				);

			} catch ( \Exception $e ) {
				return $this->error_response(
					$e->getMessage(),
					'profile_update_failed',
					500
				);
			}
		}

		/**
		 * Update basic profile information.
		 *
		 * @param int $cashier_id Cashier ID.
		 * @param array $cashier_data Cashier data.
		 */
		protected function update_basic_profile( $cashier_id, $cashier_data ) {
			update_user_meta( $cashier_id, 'first_name', sanitize_text_field( $cashier_data['first_name'] ) );
			update_user_meta( $cashier_id, 'last_name', sanitize_text_field( $cashier_data['last_name'] ) );
		}

		/**
		 * Update cashier password.
		 *
		 * @param int $cashier_id Cashier ID.
		 * @param array $cashier_data Cashier data.
		 * @return bool|WP_Error True if successful, WP_Error if failed.
		 */
		protected function update_cashier_password( $cashier_id, $cashier_data ) {
			$cashier_user = get_userdata( $cashier_id );
			
			if ( ! $cashier_user ) {
				return $this->error_response(
					esc_html__( 'Cashier not found', 'devdiggers-multipos-for-fluentcart' ),
					'cashier_not_found',
					404
				);
			}

			// Verify current password
			if ( ! wp_check_password( $cashier_data['current_password'], $cashier_user->user_pass, $cashier_id ) ) {
				return $this->error_response(
					esc_html__( 'Your current password does not match, please enter the correct password.', 'devdiggers-multipos-for-fluentcart' ),
					'incorrect_current_password',
					400
				);
			}

			// Update password
			wp_set_password( $cashier_data['new_password'], $cashier_id );

			// Re-authenticate user
			wp_set_auth_cookie( $cashier_id );
			wp_set_current_user( $cashier_id );
			do_action( 'wp_login', $cashier_user->user_login, $cashier_user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, fired as after a normal sign-in.

			return true;
		}
	}
}
