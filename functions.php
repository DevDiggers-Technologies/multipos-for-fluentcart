<?php
/**
 * Plugin Name: MultiPOS for FluentCart
 * Description: Point of sale for FluentCart. Sell in person from any browser, scan barcodes, print labels and receipts, manage cashiers and customers, and save every sale as a real FluentCart order.
 * Plugin URI: https://devdiggers.com/product/fluentcart-pos/
 * Author: DevDiggers
 * Author URI: https://devdiggers.com/
 * Version: 1.0.0
 * Text Domain: multipos-for-fluentcart
 * Domain Path: /i18n
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * DevDiggersPrefix: ddfcpos
 * Requires Plugins: fluent-cart
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package MultiPOS for FluentCart
 */

// ddfcpos: MultiPOS for FluentCart.

use DDFCMultiPOS\Includes\DDFCPOS_File_Handler;
use DDFCMultiPOS\API\DDFCPOS_API_Register_Routes;

defined( 'ABSPATH' ) || exit();

// The activation hook below runs before plugins_loaded, so the paths it needs are set here too.
defined( 'DDFCPOS_PLUGIN_FILE' ) || define( 'DDFCPOS_PLUGIN_FILE', plugin_dir_path( __FILE__ ) );
defined( 'DDFCPOS_PLUGIN_URL' ) || define( 'DDFCPOS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! class_exists( 'DDFCPOS_Free_Init' ) ) {
	/**
	 * Free Init class
	 */
	final class DDFCPOS_Free_Init {
		/**
		 * Instance variable
		 *
		 * @var DDFCPOS_Free_Init|null
		 */
		private static $_instance = null;

		/**
		 * Class constructor
		 */
		public function __construct() {
			add_action( 'init', [ $this, 'ddfcpos_init' ], 20 );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'ddfcpos_plugin_settings_link' ] );
			add_filter( 'plugin_row_meta', [ $this, 'ddfcpos_plugin_row_meta' ], 10, 2 );
		}

		/**
		 * Create a plugin instance.
		 *
		 * @return static
		 */
		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self();

				/**
				 * Fires when the main plugin instance is loaded.
				 *
				 * @since 1.0.0
				 */
				do_action( 'ddfcpos/loaded' );
			}

			return self::$_instance;
		}

		/**
		 * Whether FluentCart is loaded.
		 *
		 * @return bool
		 */
		public static function ddfcpos_is_fluentcart_active() {
			return defined( 'FLUENTCART_VERSION' ) && class_exists( '\FluentCart\App\Models\Order' );
		}

		/**
		 * Init function
		 *
		 * @return void
		 */
		public function ddfcpos_init() {
			// WordPress.org loads plugin translations automatically.

			if ( ! self::ddfcpos_is_fluentcart_active() ) {
				add_action(
					'admin_notices',
					function () {
						?>
						<div class="error">
							<p>
								<?php
								/* translators: %1$s for a opening tag and %2$s for a closing tag */
								printf( esc_html__( 'MultiPOS for FluentCart is activated but not effective. It requires %1$sFluentCart%2$s in order to use its functionalities.', 'multipos-for-fluentcart' ), '<a href="' . esc_url( 'https://wordpress.org/plugins/fluent-cart/' ) . '" target="_blank">', '</a>' );
								?>
							</p>
						</div>
						<?php
					}
				);

				return;
			}

			require_once DDFCPOS_PLUGIN_FILE . 'autoload/autoload.php';
			require_once DDFCPOS_PLUGIN_FILE . 'includes/globals-variables.php';

			global $ddfcpos_configuration;

			new DDFCPOS_API_Register_Routes( $ddfcpos_configuration );
			new DDFCPOS_File_Handler();

			// Ask for a review on the plugin's own pages only (and its dismiss request).
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input.
			$is_plugin_page = isset( $_GET['page'] ) && 0 === strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'ddfcpos-' );

			if ( is_admin() && ( $is_plugin_page || wp_doing_ajax() ) && class_exists( '\DevDiggers\Framework\Includes\DDFW_Review_Notice' ) ) {
				new \DevDiggers\Framework\Includes\DDFW_Review_Notice(
					[
						'plugin_name'   => esc_html__( 'MultiPOS for FluentCart', 'multipos-for-fluentcart' ),
						'plugin_prefix' => 'ddfcpos',
						'review_url'    => 'https://wordpress.org/support/plugin/multipos-for-fluentcart/reviews/#new-post',
					]
				);
			}
		}

		/**
		 * Plugin settings link
		 *
		 * @param array $links Links Array.
		 * @return array $links
		 */
		public function ddfcpos_plugin_settings_link( $links ) {
			ob_start();
			?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ddfcpos-dashboard' ) ); ?>"><?php esc_html_e( 'Dashboard', 'multipos-for-fluentcart' ); ?></a>
			|
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ddfcpos-dashboard&menu=configuration' ) ); ?>"><?php esc_html_e( 'Configuration', 'multipos-for-fluentcart' ); ?></a>
			|
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ddfcpos-dashboard&setup-wizard=true' ) ); ?>"><?php esc_html_e( 'Setup Wizard', 'multipos-for-fluentcart' ); ?></a>
			|
			<a href="https://devdiggers.com/product/fluentcart-pos/" style="color: #0256ff; font-weight: bold;" target="_blank"><?php esc_html_e( 'Upgrade to Pro', 'multipos-for-fluentcart' ); ?></a>
			<?php
			array_unshift( $links, ob_get_clean() );
			return $links;
		}

		/**
		 * Plugin Doc link
		 *
		 * @param array  $links Links.
		 * @param string $file File name.
		 * @return array $links
		 */
		public function ddfcpos_plugin_row_meta( $links, $file ) {
			if ( plugin_basename( __FILE__ ) === $file ) {
				$row_meta = [
					'support'       => '<a href="https://devdiggers.com/contact/" aria-label="' . esc_attr__( 'Support', 'multipos-for-fluentcart' ) . '">' . esc_html__( 'Support', 'multipos-for-fluentcart' ) . '</a>',
					'documentation' => '<a href="https://docs.devdiggers.com/multipos-for-fluentcart/" aria-label="' . esc_attr__( 'Documentation', 'multipos-for-fluentcart' ) . '">' . esc_html__( 'Documentation', 'multipos-for-fluentcart' ) . '</a>',
					'review'        => '<a href="https://wordpress.org/support/plugin/multipos-for-fluentcart/reviews/#new-post" target="_blank" aria-label="' . esc_attr__( 'Review', 'multipos-for-fluentcart' ) . '">' . esc_html__( 'Review', 'multipos-for-fluentcart' ) . '</a>',
				];
				$links    = array_merge( $links, $row_meta );
			}

			return $links;
		}
	}
}

// Load the free plugin, and the bundled DevDiggers Framework, unless MultiPOS Pro is active.
add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'DDFCPOS_Init' ) ) {
			return;
		}

		DDFCPOS_Free_Init::get_instance();

		if ( ! defined( 'DDFW_LOADED' ) && file_exists( DDFCPOS_PLUGIN_FILE . 'devdiggers-framework/init.php' ) ) {
			$should_load = true;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input.
			if ( ! empty( $_GET['page'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input.
				$current_page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
				$prefix       = explode( '-', $current_page )[0];

				if ( 0 === strpos( $prefix, 'ddwc' ) || 0 === strpos( $prefix, 'ddwp' ) || 0 === strpos( $prefix, 'ddfc' ) ) {
					$pro_class  = strtoupper( $prefix ) . '_Init';
					$free_class = strtoupper( $prefix ) . '_Free_Init';

					// Leave another DevDiggers plugin's page to that plugin's own framework copy.
					if ( class_exists( $free_class ) && ! class_exists( $pro_class ) && 'ddfcpos' !== $prefix ) {
						$should_load = false;
					}
				}
			}

			if ( $should_load ) {
				require DDFCPOS_PLUGIN_FILE . 'devdiggers-framework/init.php';
			}
		}
	},
	10
);

// Serve barcode images through the REST API so hosts that block direct PHP file
// access inside wp-content can still render them.
add_action(
	'rest_api_init',
	function () {
		if ( class_exists( 'DDFCPOS_Init' ) ) {
			return;
		}

		require_once DDFCPOS_PLUGIN_FILE . 'autoload/autoload.php';

		\DDFCMultiPOS\Helper\Barcode\DDFCPOS_Barcode_Helper::ddfcpos_register_barcode_route();
	}
);

// __DIR__, not the constant: when Pro is active it has already defined the constant for its own folder.
require_once __DIR__ . '/includes/install.php';
register_activation_hook( __FILE__, [ 'DDFCPOS_Install', 'ddfcpos_on_plugin_activation' ] );
