<?php
/**
 * DevDiggers Extensions Page Template
 *
 * @author DevDiggers
 * @version 1.0.0
 * @package DevDiggers\Framework
 */

defined( 'ABSPATH' ) || exit(); // Exit if accessed directly.

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template scope variables are local include variables.
$plugins_api = DDFW_Plugins_API::instance();

// The list comes from devdiggers.com, so it is only requested after the administrator clicks "Load extensions".
$load_requested = isset( $_GET['ddfw_load_extensions'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ddfw_load_extensions' );

if ( $load_requested ) {
	$plugins_api->allow_remote_request();
}

$extensions_loaded = $load_requested || $plugins_api->has_cached_data();
$load_url          = wp_nonce_url( add_query_arg( 'ddfw_load_extensions', '1' ), 'ddfw_load_extensions' );
$website_plugins   = $extensions_loaded ? $plugins_api->get_website_plugins() : [];
$featured_plugins  = $extensions_loaded ? $plugins_api->get_featured_plugins() : [];
$plugin_stats      = $extensions_loaded ? $plugins_api->get_plugin_statistics() : [ 'total_plugins' => '-' ];

// Featured extensions are not repeated in the full list.
$featured_urls = wp_list_pluck( $featured_plugins, 'url' );
$other_plugins = array_filter(
	$website_plugins,
	function ( $plugin ) use ( $featured_urls ) {
		return ! in_array( $plugin['url'] ?? '', $featured_urls, true );
	}
);

$svg_open = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
$svg_tags = ddfw_kses_allowed_svg_tags();

$stats = [
	[
		'label' => esc_html__( 'Total Extensions', 'devdiggers-multipos-for-fluentcart' ),
		'value' => $plugin_stats['total_plugins'],
		'icon'  => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/>',
	],
	[
		'label' => esc_html__( 'Years Experience', 'devdiggers-multipos-for-fluentcart' ),
		'value' => ( (int) gmdate( 'Y' ) - 2018 ) . '+',
		'icon'  => '<circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/>',
	],
	[
		'label' => esc_html__( '5 Star Reviews', 'devdiggers-multipos-for-fluentcart' ),
		'value' => esc_html__( '500+', 'devdiggers-multipos-for-fluentcart' ),
		'icon'  => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
	],
	[
		'label' => esc_html__( 'Support', 'devdiggers-multipos-for-fluentcart' ),
		'value' => esc_html__( '24/7', 'devdiggers-multipos-for-fluentcart' ),
		'icon'  => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.8-.9L3 21l1.9-5.2a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 8.5-8.5h.5a8.5 8.5 0 0 1 8 8z"/>',
	],
];

/**
 * Print one extension card.
 *
 * @param array $plugin Extension data from the DevDiggers API.
 * @return void
 */
$render_card = function ( $plugin ) {
	?>
	<div class="ddfw-plugin-card ddfw-extension-card">
		<a class="ddfw-plugin-image" href="<?php echo esc_url( $plugin['url'] ); ?>" target="_blank" rel="noopener noreferrer" tabindex="-1">
			<img src="<?php echo esc_url( $plugin['image'] ); ?>" alt="<?php echo esc_attr( $plugin['name'] ); ?>" loading="lazy" />
		</a>
		<div class="ddfw-plugin-content">
			<h3><?php echo esc_html( $plugin['name'] ); ?></h3>
			<p class="ddfw-plugin-description"><?php echo esc_html( ! empty( $plugin['one_liner'] ) ? $plugin['one_liner'] : $plugin['description'] ); ?></p>
		</div>
		<div class="ddfw-plugin-footer">
			<a href="<?php echo esc_url( $plugin['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="ddfw-button ddfw-button-primary"><?php esc_html_e( 'View Plugin', 'devdiggers-multipos-for-fluentcart' ); ?></a>
			<div class="ddfw-extension-links">
				<?php if ( ! empty( $plugin['demo_url'] ) ) : ?>
					<a href="<?php echo esc_url( $plugin['demo_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Demo', 'devdiggers-multipos-for-fluentcart' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $plugin['documentation_url'] ) ) : ?>
					<a href="<?php echo esc_url( $plugin['documentation_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Docs', 'devdiggers-multipos-for-fluentcart' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
};
?>
<div class="wrap devdiggers-wrap ddfw-hub">
	<?php include DDFW_FILE . 'templates/layout/devdiggers-header.php'; ?>

	<div class="ddfw-extensions-page ddfw-dashboard-container">
		<div class="ddfw-dashboard-header">
			<div class="ddfw-dashboard-welcome">
				<h1><?php esc_html_e( 'Extensions', 'devdiggers-multipos-for-fluentcart' ); ?></h1>
				<p><?php esc_html_e( 'Premium WooCommerce extensions, built and supported by DevDiggers.', 'devdiggers-multipos-for-fluentcart' ); ?></p>
			</div>
		</div>

		<div class="ddfw-dashboard-stats">
			<?php foreach ( $stats as $stat ) : ?>
				<div class="ddfw-stat-card">
					<div class="ddfw-stat-icon"><?php echo wp_kses( $svg_open . $stat['icon'] . '</svg>', $svg_tags ); ?></div>
					<div class="ddfw-stat-content">
						<p><?php echo esc_html( $stat['label'] ); ?></p>
						<h3><?php echo esc_html( $stat['value'] ); ?></h3>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( ! $extensions_loaded ) : ?>
			<div class="ddfw-dashboard-section">
				<div class="ddfw-section-header">
					<h2><?php esc_html_e( 'Load the extensions list', 'devdiggers-multipos-for-fluentcart' ); ?></h2>
					<p><?php esc_html_e( 'The list of DevDiggers extensions is loaded from devdiggers.com. Nothing is requested until you click the button below. Your server then sends one request to devdiggers.com (with its IP address and a user agent, no personal or store data), and the list is cached for 24 hours.', 'devdiggers-multipos-for-fluentcart' ); ?></p>
				</div>
				<p><a href="<?php echo esc_url( $load_url ); ?>" class="ddfw-button ddfw-button-primary"><?php esc_html_e( 'Load extensions', 'devdiggers-multipos-for-fluentcart' ); ?></a></p>
			</div>
		<?php elseif ( empty( $website_plugins ) ) : ?>
			<div class="ddfw-dashboard-section">
				<p><?php esc_html_e( 'The extensions list could not be loaded right now. Please try again later.', 'devdiggers-multipos-for-fluentcart' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $featured_plugins ) ) : ?>
			<div class="ddfw-dashboard-section">
				<div class="ddfw-section-header">
					<h2><?php esc_html_e( 'Featured', 'devdiggers-multipos-for-fluentcart' ); ?></h2>
					<p><?php esc_html_e( 'Our most popular extensions.', 'devdiggers-multipos-for-fluentcart' ); ?></p>
				</div>
				<div class="ddfw-plugins-grid">
					<?php array_map( $render_card, $featured_plugins ); ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $other_plugins ) ) : ?>
			<div class="ddfw-dashboard-section">
				<div class="ddfw-section-header">
					<h2><?php esc_html_e( 'All Extensions', 'devdiggers-multipos-for-fluentcart' ); ?></h2>
				</div>
				<div class="ddfw-plugins-grid" id="extensions-grid">
					<?php array_map( $render_card, $other_plugins ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
