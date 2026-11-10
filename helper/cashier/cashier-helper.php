<?php
/**
 * Cashier Helper
 *
 * Cashiers are ordinary WordPress users with the ddfcpos_cashier role. This helper creates and
 * updates them from the MultiPOS Cashier screen, so login, password reset and every other
 * plugin keep working on the same user.
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Helper\Cashier;


defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Cashier_Helper' ) ) {
	/**
	 * Cashier Helper class
	 */
	class DDFCPOS_Cashier_Helper {
		/**
		 * Cashier role.
		 */
		const ROLE = 'ddfcpos_cashier';

		/**
		 * Whether a user is a MultiPOS cashier.
		 *
		 * @param int|\WP_User $user User or ID.
		 * @return bool
		 */
		public static function ddfcpos_is_cashier( $user ) {
			$user = $user instanceof \WP_User ? $user : get_userdata( (int) $user );

			return $user && in_array( self::ROLE, (array) $user->roles, true );
		}

		/**
		 * The MultiPOS edit screen for a cashier, or the WordPress profile for anyone else
		 * (an administrator or shop manager who rang up a sale). Empty when the viewer may not
		 * edit that user, for example a shop manager looking at an administrator's sale.
		 *
		 * @param int $user_id User ID.
		 * @return string
		 */
		public static function ddfcpos_get_edit_url( $user_id ) {
			if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
				return '';
			}

			if ( ! self::ddfcpos_is_cashier( $user_id ) ) {
				return admin_url( 'user-edit.php?user_id=' . absint( $user_id ) );
			}

			return admin_url( 'admin.php?page=ddfcpos-dashboard&menu=cashiers&action=edit&id=' . absint( $user_id ) );
		}

		/**
		 * The cashier's name, linked to their edit screen when the viewer may edit them.
		 *
		 * @param int    $user_id   User ID.
		 * @param string $name_html Escaped name.
		 * @return string
		 */
		public static function ddfcpos_get_name_html( $user_id, $name_html ) {
			$url = get_userdata( $user_id ) ? self::ddfcpos_get_edit_url( $user_id ) : '';

			return $url ? '<a href="' . esc_url( $url ) . '">' . $name_html . '</a>' : $name_html;
		}

		/**
		 * Who rang it up and where: avatar, cashier name, outlet underneath. Used by the
		 * orders, transactions and registers lists.
		 *
		 * @param int    $cashier_id   Cashier user ID.
		 * @param string $cashier_name Cashier name.
		 * @param string $outlet_name  Outlet name.
		 * @return string
		 */
		public static function ddfcpos_get_attribution_html( $cashier_id, $cashier_name, $outlet_name ) {
			$cashier_id = absint( $cashier_id );
			$name       = '' !== (string) $cashier_name ? esc_html( $cashier_name ) : esc_html__( 'Unknown cashier', 'multipos-for-fluentcart' );

			$name = self::ddfcpos_get_name_html( $cashier_id, $name );

			return sprintf(
				'<div class="ddfcpos-attribution-path">%1$s<div class="ddfcpos-attribution-text"><span class="ddfcpos-path-cashier">%2$s</span><span class="ddfcpos-path-outlet"><span class="dashicons dashicons-store" aria-hidden="true"></span>%3$s</span></div></div>',
				get_avatar( $cashier_id, 28, '', '', [ 'class' => 'ddfcpos-attribution-avatar' ] ),
				$name,
				'' !== (string) $outlet_name ? esc_html( $outlet_name ) : esc_html__( 'No outlet', 'multipos-for-fluentcart' )
			);
		}

		/**
		 * Create or update a cashier.
		 *
		 * @param array $data {
		 *     Sanitized cashier data.
		 *
		 *     @type int    $id          User ID, 0 to create.
		 *     @type string $username    Login name, only used when creating.
		 *     @type string $email       Email.
		 *     @type string $first_name  First name.
		 *     @type string $last_name   Last name.
		 *     @type string $password    New password, empty to keep (or to generate one on create).
		 *     @type bool   $send_link   Email the cashier a link to set their password (create only).
		 * }
		 * @return int|\WP_Error User ID or the reason it was not saved.
		 */
		public function ddfcpos_save_cashier( $data ) {
			$data = wp_parse_args(
				$data,
				[
					'id'         => 0,
					'username'   => '',
					'email'      => '',
					'first_name' => '',
					'last_name'  => '',
					'password'   => '',
					'send_link'  => false,
				]
			);

			$id     = absint( $data['id'] );
			$errors = new \WP_Error();

			if ( $id && ! self::ddfcpos_is_cashier( $id ) ) {
				return new \WP_Error( 'ddfcpos_not_cashier', __( 'This user is not a MultiPOS cashier. Edit them from the WordPress Users screen.', 'multipos-for-fluentcart' ) );
			}

			if ( ! $id ) {
				if ( '' === $data['username'] || ! validate_username( $data['username'] ) ) {
					$errors->add( 'username', __( 'Enter a username using letters, numbers, spaces and . - _ @ only.', 'multipos-for-fluentcart' ) );
				} elseif ( username_exists( $data['username'] ) ) {
					$errors->add( 'username', __( 'That username is already taken.', 'multipos-for-fluentcart' ) );
				}
			}

			$email_owner = $data['email'] ? email_exists( $data['email'] ) : false;

			if ( ! is_email( $data['email'] ) ) {
				$errors->add( 'email', __( 'Enter a valid email address.', 'multipos-for-fluentcart' ) );
			} elseif ( $email_owner && (int) $email_owner !== $id ) {
				$errors->add( 'email', __( 'Another user already has that email address.', 'multipos-for-fluentcart' ) );
			}

			if ( $errors->has_errors() ) {
				return $errors;
			}

			$display_name = trim( $data['first_name'] . ' ' . $data['last_name'] );
			$userdata     = [
				'user_email' => $data['email'],
				'first_name' => $data['first_name'],
				'last_name'  => $data['last_name'],
			];

			if ( $id ) {
				$userdata['ID'] = $id;

				if ( '' !== $display_name ) {
					$userdata['display_name'] = $display_name;
				}

				if ( '' !== $data['password'] ) {
					$userdata['user_pass'] = $data['password'];
				}

				$user_id = wp_update_user( $userdata );
			} else {
				$userdata['user_login']   = $data['username'];
				$userdata['display_name'] = '' !== $display_name ? $display_name : $data['username'];
				$userdata['role']         = self::ROLE;
				// Without a password the cashier gets one they never see, and sets their own from the emailed link.
				$userdata['user_pass'] = '' !== $data['password'] ? $data['password'] : wp_generate_password( 24 );
				$data['send_link']     = $data['send_link'] || '' === $data['password'];

				$user_id = wp_insert_user( $userdata );
			}

			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			if ( ! $id && $data['send_link'] ) {
				wp_new_user_notification( $user_id, null, 'user' );
			}

			do_action( 'ddfcpos/cashier/saved', $user_id, $data, ! $id );

			return $user_id;
		}
	}
}
