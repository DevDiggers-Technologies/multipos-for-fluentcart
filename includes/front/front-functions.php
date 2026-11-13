<?php
/**
 * @author DevDiggers
 * @version 1.0.0
 * This file handles all front end action callbacks.
 */

namespace DDFCMultiPOS\Includes\Front;

use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;
use DDFCMultiPOS\Templates\Front\POS\DDFCPOS_Layout;
use DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper;
use DDFCMultiPOS\Helper\Invoice\DDFCPOS_Invoice_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Front_Functions' ) ) {    
    /**
     * Front functions class
     */
    class DDFCPOS_Front_Functions {
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
		 * Add Query Vars function
		 *
		 * @param array $vars
		 * @return array
		 */
		public function ddfcpos_add_query_vars( $vars ) {
			$vars[] = $this->ddfcpos_configuration[ 'endpoint' ];
            return $vars;
		}

        /**
         * Parse request function
         *
         * @return void
         */
        public function ddfcpos_parse_request( $wp ) {
            if ( array_key_exists( $this->ddfcpos_configuration[ 'endpoint' ], $wp->query_vars ) ) {
                $this->ddfcpos_check_pos_access();
                new DDFCPOS_Layout( $this->ddfcpos_configuration );
                exit();
            }
        }

        /**
         * Sends a signed-in user without POS access away from the POS screen. Runs from
         * parse_request, so it only fires on the real POS endpoint (its query var is set),
         * never on any other URL that happens to contain the endpoint word.
         *
         * @return void
         */
        protected function ddfcpos_check_pos_access() {
            if ( is_user_logged_in() ) {
                $user = wp_get_current_user();
                if ( ! ( in_array( 'ddfcpos_cashier', $user->roles, true ) || in_array( 'administrator', $user->roles, true ) || current_user_can( 'ddfcpos_manage' ) || apply_filters( 'ddfcpos/access/allow_administrator', false ) ) ) {
                    wp_safe_redirect( home_url() );
                    exit();
                }
            }
        }

		/**
		 * Sends a failed POS sign-in back to the POS login screen. Hooked on wp_login_failed.
		 *
		 * @return void
		 */
		public function ddfcpos_login_failed() {
			// check what page the login attempt is coming from
			$referrer = ! empty( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

			if ( strstr( $referrer, '/' . $this->ddfcpos_configuration[ 'endpoint' ] ) !== false ) {
				$url = site_url( $this->ddfcpos_configuration[ 'endpoint' ] );
				// make sure we don't already have a failed login attempt
				if ( ! strstr( $referrer, '?login=failed' ) ) {
					// Redirect to the login page and append a querystring of login failed
					wp_safe_redirect( $url . '?login=failed' );
				} else {
					wp_safe_redirect( $url );
				}

				exit();
			}
		}

		/**
		 * WordPress fires no wp_login_failed for an empty username or password, so catch that
		 * case on the authenticate filter. Always hands the incoming value on unchanged, so a
		 * user an earlier authenticate callback (SSO, 2FA) produced is never discarded.
		 *
		 * @param null|\WP_User|\WP_Error $user     Result so far.
		 * @param string                $username Username.
		 * @param string                $password Password.
		 * @return null|\WP_User|\WP_Error
		 */
		public function ddfcpos_login_empty_fields( $user, $username = '', $password = '' ) {
			if ( $user instanceof \WP_User ) {
				return $user;
			}

			if ( ( '' === (string) $username || '' === (string) $password ) && ! empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Login form, no nonce.
				$this->ddfcpos_login_failed();
			}

			return $user;
		}

		/**
		 * Front scripts enqueue function
		 *
		 * @return void
		 */
		public function ddfcpos_front_scripts() {
			global $wp;
			if ( array_key_exists( $this->ddfcpos_configuration[ 'endpoint' ], $wp->query_vars ) ) {
				wp_enqueue_style( 'ddfcpos-login-style', DDFCPOS_PLUGIN_URL . 'assets/css/login.css', [], filemtime( DDFCPOS_PLUGIN_FILE . 'assets/css/login.css' ) );
				wp_add_inline_style( 'ddfcpos-login-style', $this->ddfcpos_get_login_css_variables() );

				// The POS page prints only wp_head(), so its scripts load in the head.
				wp_enqueue_script( 'ddfcpos-login-script', DDFCPOS_PLUGIN_URL . 'assets/js/login.js', [], filemtime( DDFCPOS_PLUGIN_FILE . 'assets/js/login.js' ), false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter -- See above.

				if ( is_user_logged_in() ) {
					$dependencies = apply_filters( 'ddfcpos/pos/script_dependencies', [ 'wp-element' ] );

					wp_enqueue_style( 'ddfcpos-pos-style', DDFCPOS_PLUGIN_URL . 'assets/css/pos.css', [], filemtime( DDFCPOS_PLUGIN_FILE . 'assets/css/pos.css' ) );
					wp_add_inline_style( 'ddfcpos-pos-style', $this->ddfcpos_get_layout_css_variables() );
					wp_enqueue_script( 'ddfcpos-pos-script', DDFCPOS_PLUGIN_URL . 'assets/js/pos.js', $dependencies, filemtime( DDFCPOS_PLUGIN_FILE . 'assets/js/pos.js' ), false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter -- The POS page prints only wp_head().

					wp_set_script_translations( 'ddfcpos-pos-script', 'devdiggers-multipos-for-fluentcart' );

					$ddfcpos_configuration = $this->ddfcpos_configuration;
					$user                  = wp_get_current_user();
					$prices_include_tax    = DDFCPOS_FluentCart_Helper::prices_include_tax();
					$tax_display_cart      = $prices_include_tax ? 'incl' : 'excl';
					$outlet_helper         = new DDFCPOS_Outlet_Helper();
					$invoice_helper        = new DDFCPOS_Invoice_Helper( $this->ddfcpos_configuration );
					$site_url              = site_url();

					$user_data = (array) $user->data;

					$user_data[ 'roles' ]      = $user->roles;
					$user_data[ 'first_name' ] = $user->first_name;
					$user_data[ 'last_name' ]  = $user->last_name;

					// One outlet, open to every POS user while it is enabled.
					$outlet           = $outlet_helper->ddfcpos_get_outlet_option();
					$assigned_outlets = $outlet && 'enabled' === $outlet['status'] ? [ $outlet ] : [];

					$tax_name = DDFCPOS_FluentCart_Helper::tax_label();
					/* translators: %s: tax name, e.g. VAT. */
					$tax_included = sprintf( esc_html__( '(incl. %s)', 'devdiggers-multipos-for-fluentcart' ), $tax_name );
					/* translators: %s: tax name, e.g. VAT. */
					$tax_excluded = sprintf( esc_html__( '(excl. %s)', 'devdiggers-multipos-for-fluentcart' ), $tax_name );
					$tax_label    = ' <small class="tax_label">' . ( 'excl' !== $tax_display_cart ? $tax_included : $tax_excluded ) . '</small>';

					// With FluentCart tax off there is no tax in any price, so no line says "incl. Tax".
					if ( ! DDFCPOS_FluentCart_Helper::tax_enabled() ) {
						$tax_included = '';
						$tax_label    = '';
					}

					$ddfcpos_configuration[ 'tax_included_label' ]  = $tax_included;
					$ddfcpos_configuration[ 'tax_label' ]           = $tax_label;
					$ddfcpos_configuration[ 'current_date' ]        = current_time( 'Y-m-d' );
					$ddfcpos_configuration[ 'per_page' ]            = 200;
					$ddfcpos_configuration[ 'invoice_html' ]        = $invoice_helper->ddfcpos_get_invoice_html();
					$ddfcpos_configuration[ 'invoice_css' ]         = $invoice_helper->ddfcpos_get_invoice_css();
					$ddfcpos_configuration[ 'placeholder_image' ]   = DDFCPOS_FluentCart_Helper::placeholder_image_src();
					$ddfcpos_configuration[ 'language_attributes' ] = get_language_attributes( 'html' );
					$ddfcpos_configuration[ 'order_status_label' ]  = DDFCPOS_FluentCart_Helper::order_status_label( $ddfcpos_configuration[ 'order_status' ] );
					$ddfcpos_configuration[ 'logo_url' ]            = ! empty( $ddfcpos_configuration[ 'logo' ] ) ? wp_get_attachment_url( $ddfcpos_configuration[ 'logo' ] ) : DDFCPOS_PLUGIN_URL . 'assets/images/logo.png';
					$ddfcpos_configuration[ 'store_name' ]          = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
					$ddfcpos_configuration[ 'store_url' ]           = untrailingslashit( preg_replace( '#^https?://#', '', home_url() ) );

					$api = [
						'GET_PRODUCTS_ENDPOINT'            => $site_url . '/wp-json/ddfcpos/v1/get-products',
						'GET_CATEGORIES_ENDPOINT'          => $site_url . '/wp-json/ddfcpos/v1/get-product-categories',
						'GET_CUSTOMERS_ENDPOINT'           => $site_url . '/wp-json/ddfcpos/v1/get-customers',
						'GET_COUNTRIES_STATES_ENDPOINT'    => $site_url . '/wp-json/ddfcpos/v1/get-countries-states',
						'CHECK_COUPON_ENDPOINT'            => $site_url . '/wp-json/ddfcpos/v1/check-coupon',
						'CHECK_CENTRALIZED_STOCK_ENDPOINT' => $site_url . '/wp-json/ddfcpos/v1/check-centralized-stock',
						'MANAGE_CUSTOMER_ENDPOINT'         => $site_url . '/wp-json/ddfcpos/v1/manage-customer',
						'DELETE_CUSTOMER_ENDPOINT'         => $site_url . '/wp-json/ddfcpos/v1/delete-customer',
						'CREATE_ORDER_ENDPOINT'            => $site_url . '/wp-json/ddfcpos/v1/create-order',
						'GET_ORDERS_ENDPOINT'              => $site_url . '/wp-json/ddfcpos/v1/get-orders',
						'SAVE_CASHIER_ENDPOINT'            => $site_url . '/wp-json/ddfcpos/v1/save-cashier',
						'GET_TRANSACTIONS_ENDPOINT'        => $site_url . '/wp-json/ddfcpos/v1/get-transactions',
					];

					$parsed_url = wp_parse_url( $site_url, PHP_URL_PATH );

					wp_localize_script(
						'ddfcpos-pos-script',
						'ddfcposPOSObj',
						[
							'restNonce'                    => wp_create_nonce( 'wp_rest' ),
							'site_url'                     => $site_url,
							'siteUrl'                      => $parsed_url ? $parsed_url : '',
							'assignedOutlets'              => ! empty( $assigned_outlets ) ? $assigned_outlets : [],
							'ddfcpos_configuration'        => $ddfcpos_configuration,
							'user'                         => $user_data,
							'API'                          => $api,
							'currency_format_num_decimals' => esc_attr( DDFCPOS_FluentCart_Helper::decimals() ),
							'currency_format_symbol'       => DDFCPOS_FluentCart_Helper::currency_symbol(),
							'currency_code'                => DDFCPOS_FluentCart_Helper::currency(),
							'currency_format_decimal_sep'  => esc_attr( DDFCPOS_FluentCart_Helper::decimal_separator() ),
							'currency_format_thousand_sep' => esc_attr( DDFCPOS_FluentCart_Helper::thousand_separator() ),
							'currency_format'              => esc_attr( DDFCPOS_FluentCart_Helper::price_format() ),
							'logout_url'                   => wp_logout_url( site_url( $ddfcpos_configuration[ 'endpoint' ] ) ),
							'tax_enabled'                  => DDFCPOS_FluentCart_Helper::tax_enabled() ? 'yes' : 'no',
							'tax_type'                     => $prices_include_tax ? 'yes' : 'no',
							'price_num_decimals'           => DDFCPOS_FluentCart_Helper::decimals(),
							'tax_round_at_subtotal'        => 'subtotal' === ( DDFCPOS_FluentCart_Helper::tax_settings()['tax_rounding'] ?? '' ) ? 'yes' : 'no',
							'tax_display_cart'             => $tax_display_cart,
							'current_date'                 => apply_filters( 'ddfcpos/transaction/today', current_time( 'Y-m-d' ) ),
							'plugin_url'                   => DDFCPOS_PLUGIN_URL,
						]
					);
				}
			}
		}

		/**
		 * Deregister front scripts function
		 *
		 * @return void
		 */
		public function ddfcpos_deregister_front_scripts() {
			global $wp;
			if ( array_key_exists( $this->ddfcpos_configuration[ 'endpoint' ], $wp->query_vars ) ) {
				global $wp_styles, $wp_scripts;
				$styles = apply_filters( 'ddfcpos/pos/css_handles', [ 'ddfcpos-login-style', 'ddfcpos-pos-style', 'ddfcpos-variables-style' ] );
				foreach ( $wp_styles->queue as $s ) {
					if ( ! in_array( $s, $styles, true ) && ! empty( $wp_styles->registered[ $s ] ) ) {
						wp_dequeue_style( $wp_styles->registered[$s]->handle );
						wp_deregister_style( $wp_styles->registered[$s]->handle );
					}
				}

				$scripts = apply_filters( 'ddfcpos/pos/js_handles', [ 'ddfcpos-login-script', 'ddfcpos-pos-script' ] );
				foreach ( $wp_scripts->queue as $s ) {
					if ( ! in_array( $s, $scripts, true ) && ! empty( $wp_scripts->registered[ $s ] ) ) {
						wp_dequeue_script( $wp_scripts->registered[$s]->handle );
						wp_deregister_script( $wp_scripts->registered[$s]->handle );
					}
				}
			}
		}

		/**
		 * Get layout CSS variables.
		 *
		 * @return string
		 */
		private function ddfcpos_get_layout_css_variables() {
			$configuration = $this->ddfcpos_configuration;
			$pos_font_family = $this->ddfcpos_get_pos_font_family_stack( $configuration[ 'layout_pos_font_family' ] );

			return sprintf(
				':root{--pos-layout-primary-color:%1$s;--pos-layout-secondary-color:%2$s;--pos-layout-font-color:%3$s;--pos-font-color:%4$s;--pos-secondary-color:%5$s;--pos-grey-background:%6$s;--pos-muted-background-1:%10$s;--pos-muted-background-2:%11$s;--pos-font-family:%12$s;--pos-border-color:%13$s;--pos-layout-font-size:%7$spx;--pos-layout-radius:%8$spx;--pos-layout-success-color:%9$s;}',
				esc_attr( $configuration[ 'layout_primary_color' ] ),
				esc_attr( $configuration[ 'layout_secondary_color' ] ),
				esc_attr( $configuration[ 'layout_button_font_color' ] ),
				esc_attr( $configuration[ 'layout_font_color' ] ),
				esc_attr( $configuration[ 'layout_surface_color' ] ),
				esc_attr( $configuration[ 'layout_muted_background_color_2' ] ),
				esc_attr( $configuration[ 'layout_font_size' ] ),
				esc_attr( $configuration[ 'layout_radius' ] ),
				esc_attr( $configuration[ 'layout_success_color' ] ),
				esc_attr( $configuration[ 'layout_muted_background_color_1' ] ),
				esc_attr( $configuration[ 'layout_muted_background_color_2' ] ),
				$pos_font_family,
				esc_attr( $configuration[ 'layout_border_color' ] )
			);
		}

		/**
		 * Get POS font family stack from saved option.
		 *
		 * @param string $font_family font family option.
		 * @return string
		 */
		private function ddfcpos_get_pos_font_family_stack( $font_family ) {
			$font_family_map = [
				'open_sans' => "'DDFCPOS Open Sans','Open Sans','Segoe UI',Roboto,Helvetica,Arial,sans-serif",
				'poppins'   => "'DDFCPOS Poppins',Poppins,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
				'lato'      => "'DDFCPOS Lato',Lato,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
				'montserrat'=> "'DDFCPOS Montserrat',Montserrat,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
				'nunito'    => "'DDFCPOS Nunito',Nunito,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
			];

			if ( empty( $font_family ) || ! isset( $font_family_map[ $font_family ] ) ) {
				return $font_family_map['open_sans'];
			}

			return $font_family_map[ $font_family ];
		}

		/**
		 * Get login CSS variables.
		 *
		 * @return string
		 */
		private function ddfcpos_get_login_css_variables() {
			$configuration = $this->ddfcpos_configuration;

			return sprintf(
				':root{--login-mesh-1:%1$s;--login-mesh-2:%2$s;--login-canvas:%3$s;--login-card-bg:%4$s;--login-text:%5$s;}',
				esc_attr( $configuration[ 'login_bg_primary_color' ] ),
				esc_attr( $configuration[ 'login_bg_secondary_color' ] ),
				esc_attr( $configuration[ 'login_canvas_bg_color' ] ),
				esc_attr( $configuration[ 'login_card_bg_color' ] ),
				esc_attr( $configuration[ 'login_font_color' ] )
			);
		}
	}
}
