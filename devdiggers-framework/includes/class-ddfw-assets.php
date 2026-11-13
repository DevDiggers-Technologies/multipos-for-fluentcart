<?php
/**
 * File for handling assets in the DevDiggers Framework Plugin.
 *
 * @author DevDiggers
 * @version 1.0.0
 * @package DevDiggers\Framework
 */

namespace DevDiggers\Framework\Includes;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFW_Assets' ) ) {
	/**
	 * Class for handling assets related to form fields.
	 */
	class DDFW_Assets {
		/**
		 * The single instance of the class.
		 *
		 * @var DDFW_Assets
		 */
		private static $instance;

		/**
		 * Handle for the framework CSS.
		 *
		 * @var string
		 */
		public static $framework_css_handle = 'ddfw-framework-style';

		/**
		 * Handle for the framework JS.
		 *
		 * @var string
		 */
		public static $framework_js_handle = 'ddfw-framework-script';

		/**
		 * Singleton implementation.
		 *
		 * @return DDFW_Assets
		 */
		public static function instance() {
			return ! is_null( self::$instance ) ? self::$instance : self::$instance = new self();
		}

		/**
		 * Constructor to initialize hooks.
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', [ $this, 'register_styles_and_scripts' ] );
		}

		/**
		 * Register styles and scripts for the fields.
		 * 
		 * @return void
		 */
		public function register_styles_and_scripts() {
			wp_register_style( 'select2', DDFW_URL . 'assets/css/select2.css', [], filemtime( DDFW_FILE . 'assets/css/select2.css' ) );
			wp_register_script( 'select2', DDFW_URL . 'assets/js/select2.js', [], filemtime( DDFW_FILE . 'assets/js/select2.js' ) , true );

			wp_register_style( self::$framework_css_handle, DDFW_URL . 'assets/css/framework.css', [ 'select2' ], filemtime( DDFW_FILE . 'assets/css/framework.css' ) );
			wp_register_script( self::$framework_js_handle, DDFW_URL . 'assets/js/framework.js', [ 'select2', 'wp-color-picker', 'wp-mediaelement' ], filemtime( DDFW_FILE . 'assets/js/framework.js' ) , true );

			// Shared analytics dashboard assets (enqueued on demand by DDFW_Dashboard).
			wp_register_script( 'ddfw-chart-js', DDFW_URL . 'assets/js/chart.js', [], self::asset_version( 'assets/js/chart.js' ), true );
			wp_register_style( 'ddfw-dashboard-analytics-style', DDFW_URL . 'assets/css/dashboard-analytics.css', [ self::$framework_css_handle ], self::asset_version( 'assets/css/dashboard-analytics.css' ) );
			wp_register_script( 'ddfw-dashboard-analytics-script', DDFW_URL . 'assets/js/dashboard-analytics.js', [ 'jquery', 'ddfw-chart-js', self::$framework_js_handle ], self::asset_version( 'assets/js/dashboard-analytics.js' ), true );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing parameter.
			if ( ! empty( $_GET['page'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['page'] ) ), [ 'devdiggers-plugins', 'devdiggers-extensions' ], true ) ) {
				wp_enqueue_style( 'ddfw-dashboard-style', DDFW_URL . 'assets/css/dashboard.css', [], filemtime( DDFW_FILE . 'assets/css/dashboard.css' ) );
				wp_enqueue_script( 'ddfw-dashboard-script', DDFW_URL . 'assets/js/dashboard.js', [], filemtime( DDFW_FILE . 'assets/js/dashboard.js' ) , true );

				wp_localize_script(
					'ddfw-dashboard-script',
					'ddfwDashboardObject',
					[
						'ajax' => [
							'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
							'ajaxNonce' => wp_create_nonce( 'ddfw-nonce' ),
						],
						'i18n' => [
							'subscribing'         => esc_html__( 'Subscribing...', 'devdiggers-multipos-for-fluentcart' ),
							'subscribe'           => esc_html__( 'Subscribe', 'devdiggers-multipos-for-fluentcart' ),
							'subscriptionSuccess' => esc_html__( 'Thank you for subscribing!', 'devdiggers-multipos-for-fluentcart' ),
							'subscriptionError'   => esc_html__( 'An error occurred. Please try again.', 'devdiggers-multipos-for-fluentcart' ),
						],
					]
				);
			}

			// Get current DevDiggers plugin dynamically
			$devdiggers_plugin = $this->get_current_devdiggers_plugin();

			wp_localize_script(
				self::$framework_js_handle,
				'ddfwFrameworkObject',
				[
					'ajax' => [
						'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
						'ajaxNonce' => wp_create_nonce( 'ddfw-nonce' ),
					],
					'i18n' => [
						'selectImage'         => esc_html__( 'Select Image', 'devdiggers-multipos-for-fluentcart' ),
						'useImage'            => esc_html__( 'Use Image', 'devdiggers-multipos-for-fluentcart' ),
						'pleaseEnter'         => esc_html__( 'Please enter', 'devdiggers-multipos-for-fluentcart' ),
						'moreCharacter'       => esc_html__( 'or more character', 'devdiggers-multipos-for-fluentcart' ),
						'noResult'            => esc_html__( 'No result Found', 'devdiggers-multipos-for-fluentcart' ),
						'deleteConfirm'       => esc_html__( 'Are you sure you want to delete?', 'devdiggers-multipos-for-fluentcart' ),
						'dismiss'             => esc_html__( 'Dismiss', 'devdiggers-multipos-for-fluentcart' ),
						'selectDate'          => esc_html__( 'Select date', 'devdiggers-multipos-for-fluentcart' ),
						'selectDateTime'      => esc_html__( 'Select date and time', 'devdiggers-multipos-for-fluentcart' ),
						'now'                 => esc_html__( 'Now', 'devdiggers-multipos-for-fluentcart' ),
						'done'                => esc_html__( 'Done', 'devdiggers-multipos-for-fluentcart' ),
						'time'                => esc_html__( 'Time', 'devdiggers-multipos-for-fluentcart' ),
						'hour'                => esc_html__( 'Hour', 'devdiggers-multipos-for-fluentcart' ),
						'minute'              => esc_html__( 'Minute', 'devdiggers-multipos-for-fluentcart' ),
						'today'               => esc_html__( 'Today', 'devdiggers-multipos-for-fluentcart' ),
						'clear'               => esc_html__( 'Clear', 'devdiggers-multipos-for-fluentcart' ),
						'previousMonth'       => esc_html__( 'Previous month', 'devdiggers-multipos-for-fluentcart' ),
						'nextMonth'           => esc_html__( 'Next month', 'devdiggers-multipos-for-fluentcart' ),
						'replaceImage'        => esc_html__( 'Replace', 'devdiggers-multipos-for-fluentcart' ),
						'uploadImage'         => esc_html__( 'Upload image', 'devdiggers-multipos-for-fluentcart' ),
					],
					'startOfWeek'       => (int) get_option( 'start_of_week', 1 ),
					'site_url'          => site_url(),
					'devdiggers_plugin' => $devdiggers_plugin,
				]
			);
		}

		/**
		 * Resolve an asset version, falling back to the framework version if the file is absent.
		 *
		 * @param string $relative_path Path relative to the framework root.
		 * @return string|int Version string.
		 */
		private static function asset_version( $relative_path ) {
			$path = DDFW_FILE . $relative_path;

			return file_exists( $path ) ? filemtime( $path ) : DDFW_VERSION;
		}

		/**
		 * Get current DevDiggers plugin dynamically
		 *
		 * @return array
		 */
		private function get_current_devdiggers_plugin() {
			$plugin = [];
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing parameter.
			$current_page = ! empty( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

			if ( strpos( $current_page, 'dd' ) !== false ) {
				$prefix = str_replace( '-dashboard', '', $current_page );
				$plugin = [
					'page_slug'          => $current_page,
					'configuration_menu' => 'configuration',
					'plugin_prefix'      => $prefix,
				];
			}

			return $plugin;
		}
	}
}

DDFW_Assets::instance();
