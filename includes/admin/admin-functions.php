<?php
/**
 * This file handles all admin end action callbacks.
 *
 * @author DevDiggers
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Includes\Admin;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Admin_Functions' ) ) {
	/**
	 * Admin Functions Class
	 */
	class DDFCPOS_Admin_Functions {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddfcpos_configuration;

		/**
		 * Construct
		 */
		public function __construct( $ddfcpos_configuration ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;
		}

		/**
		 * Register settings function
		 *
		 * @return void
		 */
		public function ddfcpos_register_settings() {
			$checkbox = [ 'sanitize_callback' => [ $this, 'ddfcpos_sanitize_checkbox' ] ];
			$text     = [ 'sanitize_callback' => 'sanitize_text_field' ];
			$color    = [ 'sanitize_callback' => 'sanitize_hex_color' ];
			$number   = [ 'sanitize_callback' => 'absint' ];

			register_setting( 'ddfcpos-general-configuration-fields', '_ddfcpos_enabled', $checkbox );
			register_setting( 'ddfcpos-general-configuration-fields', '_ddfcpos_order_status', [ 'sanitize_callback' => 'sanitize_key' ] );
			register_setting( 'ddfcpos-general-configuration-fields', '_ddfcpos_default_barcode', [ 'sanitize_callback' => [ $this, 'ddfcpos_sanitize_barcode_source' ] ] );
			register_setting( 'ddfcpos-general-configuration-fields', '_ddfcpos_order_mails_enabled', $checkbox );
			register_setting( 'ddfcpos-general-configuration-fields', '_ddfcpos_logo', $number );
			register_setting( 'ddfcpos-general-configuration-fields', '_ddfcpos_default_customer', $number );
			register_setting( 'ddfcpos-general-configuration-fields', '_ddfcpos_endpoint', [ 'sanitize_callback' => 'sanitize_title' ] );

			register_setting( 'ddfcpos-payments-configuration-fields', '_ddfcpos_payment_method', [ 'sanitize_callback' => [ $this, 'ddfcpos_sanitize_payment_methods' ] ] );

			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_heading_text', $text );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_subtitle_text', $text );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_footer_text', $text );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_button_text', $text );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_branding_enabled', $checkbox );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_rememberme_enabled', $checkbox );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_forgot_enabled', $checkbox );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_bg_primary_color', $color );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_bg_secondary_color', $color );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_canvas_bg_color', $color );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_card_bg_color', $text );
			register_setting( 'ddfcpos-login-configuration-fields', '_ddfcpos_login_font_color', $color );

			register_setting( 'ddfcpos-printer-configuration-fields', '_ddfcpos_barcode_printer_width', $text );
			register_setting( 'ddfcpos-printer-configuration-fields', '_ddfcpos_barcode_printer_height', $text );
			register_setting( 'ddfcpos-printer-configuration-fields', '_ddfcpos_barcode_printer_margin', $text );
			register_setting( 'ddfcpos-printer-configuration-fields', '_ddfcpos_barcode_height', $text );
			register_setting( 'ddfcpos-printer-configuration-fields', '_ddfcpos_barcode_margin', $text );
			register_setting( 'ddfcpos-printer-configuration-fields', '_ddfcpos_barcode_orientation', [ 'sanitize_callback' => 'sanitize_key' ] );
			register_setting( 'ddfcpos-printer-configuration-fields', '_ddfcpos_printer_width', $text );
			register_setting( 'ddfcpos-printer-configuration-fields', '_ddfcpos_printer_height', $text );
			register_setting( 'ddfcpos-printer-configuration-fields', '_ddfcpos_printer_margin', $text );

			register_setting( 'ddfcpos-tables-configuration-fields', '_ddfcpos_tables', [ 'sanitize_callback' => [ $this, 'ddfcpos_sanitize_tables' ] ] );

			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_primary_color', $color );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_secondary_color', $color );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_font_color', $color );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_surface_color', $color );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_muted_background_color_1', $color );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_muted_background_color_2', $color );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_button_font_color', $color );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_success_color', $color );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_border_color', $color );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_pos_font_family', [ 'sanitize_callback' => 'sanitize_key' ] );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_radius', $number );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_layout_font_size', $number );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_product_layout', [ 'sanitize_callback' => 'sanitize_key' ] );
			register_setting( 'ddfcpos-layout-configuration-fields', '_ddfcpos_show_product_stock_enabled', $checkbox );
		}

		/**
		 * FluentCart customers for the default (walk-in) customer select: id => "First Last (email)".
		 *
		 * @param int $selected Customer ID that must be in the list.
		 * @return array
		 */
		public static function ddfcpos_get_customer_options( $selected = 0 ) {
			// ponytail: newest 500 only, switch to an AJAX search if stores need to pick older customers.
			$customers = \FluentCart\App\Models\Customer::query()->orderBy( 'id', 'DESC' )->limit( 500 )->get( [ 'id', 'first_name', 'last_name', 'email' ] );
			$options   = [ '' => esc_html__( 'Select a customer', 'devdiggers-multipos-for-fluentcart' ) ];

			if ( $selected && ! $customers->firstWhere( 'id', absint( $selected ) ) ) {
				$current = \FluentCart\App\Models\Customer::query()->find( absint( $selected ) );

				if ( $current ) {
					$customers->push( $current );
				}
			}

			foreach ( $customers as $customer ) {
				$name                     = trim( $customer->first_name . ' ' . $customer->last_name );
				$options[ $customer->id ] = $name ? sprintf( '%s (%s)', $name, $customer->email ) : $customer->email;
			}

			return $options;
		}

		/**
		 * Checkbox value: 'yes' or ''. Saving '' (not deleting) keeps defaults of 'yes' from coming back.
		 *
		 * @param mixed $value Submitted value.
		 * @return string
		 */
		public function ddfcpos_sanitize_checkbox( $value ) {
			return empty( $value ) ? '' : 'yes';
		}

		/**
		 * Barcode source: the product ID or its SKU.
		 *
		 * @param mixed $value Submitted value.
		 * @return string
		 */
		public function ddfcpos_sanitize_barcode_source( $value ) {
			return 'sku' === $value ? 'sku' : 'id';
		}

		/**
		 * Sanitize the POS payment methods. Cash is always there and can not be removed.
		 *
		 * @param mixed $payment_methods Submitted payment methods.
		 * @return array
		 */
		public function ddfcpos_sanitize_payment_methods( $payment_methods ) {
			$sanitized = [];

			foreach ( (array) $payment_methods as $payment_method ) {
				$name = ! empty( $payment_method['name'] ) ? sanitize_text_field( $payment_method['name'] ) : '';
				$slug = ! empty( $payment_method['slug'] ) ? sanitize_key( $payment_method['slug'] ) : sanitize_key( $name );

				// Ledger words the POS uses for its own rows can not be payment methods.
				if ( '' === $name || '' === $slug || in_array( $slug, [ 'opencash', 'manual', 'refund', 'refund_noncash', 'split' ], true ) ) {
					continue;
				}

				$sanitized[ $slug ] = [
					'name'      => $name,
					'slug'      => $slug,
					'permanent' => 'cash' === $slug ? 'yes' : 'no',
					'status'    => ( ! empty( $payment_method['status'] ) && 'disabled' === $payment_method['status'] ) ? 'disabled' : 'enabled',
				];
			}

			if ( ! isset( $sanitized['cash'] ) ) {
				$sanitized = array_merge(
					[
						'cash' => [
							'name'      => esc_html__( 'Cash', 'devdiggers-multipos-for-fluentcart' ),
							'slug'      => 'cash',
							'permanent' => 'yes',
							'status'    => 'enabled',
						],
					],
					$sanitized
				);
			}

			return array_values( $sanitized );
		}

		/**
		 * Sanitize restaurant table definitions.
		 *
		 * @param mixed $tables Submitted table rows.
		 * @return array
		 */
		public function ddfcpos_sanitize_tables( $tables ) {
			$sanitized_tables = [];

			foreach ( (array) $tables as $table ) {
				$name = ! empty( $table['name'] ) ? sanitize_text_field( $table['name'] ) : '';
				$slug = ! empty( $table['slug'] ) ? sanitize_title( $table['slug'] ) : '';

				if ( '' === $name || '' === $slug ) {
					continue;
				}

				$sanitized_tables[] = [
					'name'   => $name,
					'slug'   => $slug,
					'seats'  => max( 1, absint( isset( $table['seats'] ) ? $table['seats'] : 1 ) ),
					'status' => ( ! empty( $table['status'] ) && 'disabled' === $table['status'] ) ? 'disabled' : 'enabled',
				];
			}

			return $sanitized_tables;
		}

		/**
		 * Admin bar menu function
		 *
		 * @param object $wp_admin_bar
		 * @return void
		 */
		public function ddfcpos_admin_bar_menu( $wp_admin_bar ) {
			if ( ! is_user_logged_in() ) {
				return;
			}

			// Show only when the user is a member of this site, or they're a super admin.
			if ( ! is_user_member_of_blog() && ! is_super_admin() ) {
				return;
			}

			// Add an option to visit the store.
			$wp_admin_bar->add_node( [
				'parent' => 'site-name',
				'id'     => 'view-pos',
				'title'  => esc_html__( 'Visit POS', 'devdiggers-multipos-for-fluentcart' ),
				'href'   => site_url( $this->ddfcpos_configuration[ 'endpoint' ] ),
			] );
		}
	}
}
