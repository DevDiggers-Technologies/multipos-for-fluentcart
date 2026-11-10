<?php
/**
 * Permission Helper class
 *
 * Guards store staff accounts from being changed at the POS.
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Helper\Permission;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Permission_Helper' ) ) {
	/**
	 * Permission Helper class
	 */
	class DDFCPOS_Permission_Helper {
		/**
		 * Whether the current user may edit or delete this customer from the POS.
		 *
		 * Store staff accounts are off limits: a cashier must never be able to change an
		 * administrator's email or delete them.
		 *
		 * @param int $customer_id FluentCart customer ID.
		 * @return bool
		 */
		public static function ddfcpos_can_manage_customer_account( $customer_id ) {
			// POS customers are FluentCart customers; only one linked to a WordPress account can be staff.
			$fc_customer = \FluentCart\App\Models\Customer::query()->find( absint( $customer_id ) );
			$customer_id = $fc_customer ? (int) $fc_customer->user_id : 0;

			if ( ! $customer_id || current_user_can( 'edit_user', $customer_id ) ) {
				return true;
			}

			$customer = get_userdata( $customer_id );

			if ( ! $customer ) {
				return false;
			}

			$is_staff = user_can( $customer, 'edit_posts' )
				|| user_can( $customer, 'ddfcpos_manage' )
				|| user_can( $customer, 'list_users' )
				|| in_array( 'ddfcpos_cashier', (array) $customer->roles, true );

			return ! apply_filters( 'ddfcpos/access/is_protected_account', $is_staff, $customer );
		}
	}
}
