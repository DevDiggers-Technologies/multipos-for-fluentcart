<?php
/**
 * Create Schema on Activation
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Install' ) ) {
	/**
	 * Activation class
	 */
	class DDFCPOS_Install {
		/**
		 * On plugin activation function
		 *
		 * @return void
		 */
		public static function ddfcpos_on_plugin_activation() {
			// Open the setup wizard after the first activation.
			set_transient( 'ddfw_activation_redirect_devdiggers-multipos-for-fluentcart', true, 30 );

			// The review notice counts from this date.
			add_option( 'ddfcpos_installed_at', time() );

			self::ddfcpos_create_schema();
		}

		/**
		 * Create Schema
		 *
		 * @return void
		 */
		public static function ddfcpos_create_schema() {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			// Transactions table: one row per POS sale.
			$transactions_table = $wpdb->prefix . 'ddfcpos_transactions';
			$transactions       = "CREATE TABLE IF NOT EXISTS $transactions_table (
				`id` bigint(20) NOT NULL AUTO_INCREMENT,
				`cashier_id` bigint(20) NOT NULL,
				`outlet_id` bigint(20) NOT NULL,
				`order_id` bigint(20) DEFAULT NULL,
				`amount_in` decimal(19,4) NOT NULL DEFAULT 0,
				`amount_out` decimal(19,4) NOT NULL DEFAULT 0,
				`method` varchar(200) NOT NULL,
				`reference` text NOT NULL,
				`date` datetime NOT NULL,
				PRIMARY KEY (id),
				KEY outlet_date (outlet_id,date),
				KEY cashier_date (cashier_id,date)
			) $charset_collate;";

			dbDelta( $transactions );

			self::ddfcpos_create_role();
		}

		/**
		 * Create POS Cashier role function
		 *
		 * @return void
		 */
		public static function ddfcpos_create_role() {
			if ( empty( get_role( 'ddfcpos_cashier' ) ) ) {
				add_role(
					'ddfcpos_cashier',
					'POS Cashier',
					[
						'read'    => true,
						'level_0' => true,
					]
				);
			}
		}
	}
}
