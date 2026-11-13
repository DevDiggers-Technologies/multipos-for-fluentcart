<?php
/**
 * Login Configuration template class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Configuration;

use DevDiggers\Framework\Includes\DDFW_Layout;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Login_Configuration_Template' ) ) {
	/**
	 * Login Configuration template class
	 */
	class DDFCPOS_Login_Configuration_Template {
		/**
		 * Construct
		 * 
		 * @param array $ddfcpos_configuration
		 */
		public function __construct( $ddfcpos_configuration ) {
			$args = [
				[
					'header' => [
						'heading'     => esc_html__( 'Login Text', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Edit text shown on POS login screen.', 'devdiggers-multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'        => 'text',
							'label'       => esc_html__( 'Welcome Heading', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Main title at top of login screen.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-login-heading-text',
							'value'       => $ddfcpos_configuration['login_heading_text'],
						],
						[
							'type'        => 'text',
							'label'       => esc_html__( 'Welcome Subtitle', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Short line shown below heading.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-login-subtitle-text',
							'value'       => $ddfcpos_configuration['login_subtitle_text'],
						],
						[
							'type'        => 'text',
							'label'       => esc_html__( 'Footer', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Text shown at bottom of login screen.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-login-footer-text',
							'value'       => $ddfcpos_configuration['login_footer_text'],
						],
						[
							'type'        => 'text',
							'label'       => esc_html__( 'Log in Button Label', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Text shown on main login button.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-login-button-text',
							'value'       => $ddfcpos_configuration['login_button_text'],
						],
						[
							'type'           => 'checkbox',
							'label'          => esc_html__( 'MultiPOS Branding', 'devdiggers-multipos-for-fluentcart' ),
							'checkbox_label' => esc_html__( 'Show MultiPOS branding link', 'devdiggers-multipos-for-fluentcart' ),
							'description'    => esc_html__( 'Optional. When enabled, shows a "Powered by MultiPOS" link on the POS login screen. Disabled by default.', 'devdiggers-multipos-for-fluentcart' ),
							'id'             => 'ddfcpos-login-branding-enabled',
							'value'          => $ddfcpos_configuration['login_branding_enabled'],
						],
					],
				],
				[
					'header' => [
						'heading'     => esc_html__( 'Login Access', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Choose login and session options for cashiers.', 'devdiggers-multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'           => 'checkbox',
							'label'          => esc_html__( 'Extended Session Access', 'devdiggers-multipos-for-fluentcart' ),
							'checkbox_label' => esc_html__( 'Enable "Remember Me" Option', 'devdiggers-multipos-for-fluentcart' ),
							'description'    => esc_html__( 'Let cashiers stay signed in on trusted devices.', 'devdiggers-multipos-for-fluentcart' ),
							'id'             => 'ddfcpos-login-rememberme-enabled',
							'value'          => $ddfcpos_configuration['login_rememberme_enabled'],
						],
						[
							'type'           => 'checkbox',
							'label'          => esc_html__( 'Password Recovery', 'devdiggers-multipos-for-fluentcart' ),
							'checkbox_label' => esc_html__( 'Enable Forgot Password Flow', 'devdiggers-multipos-for-fluentcart' ),
							'description'    => esc_html__( 'Show forgot password link on login screen.', 'devdiggers-multipos-for-fluentcart' ),
							'id'             => 'ddfcpos-login-forgot-enabled',
							'value'          => $ddfcpos_configuration['login_forgot_enabled'],
						],
					],
				],
				[
					'header' => [
						'heading'     => esc_html__( 'Login Colors', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Set colors used on login screen.', 'devdiggers-multipos-for-fluentcart' ),
					],
					'fields' => [
						[
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Mesh Gradient (Color 1)', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Main background accent color.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-login-bg-primary-color',
							'value'       => $ddfcpos_configuration['login_bg_primary_color'],
						],
						[
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Mesh Gradient (Color 2)', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Second background accent color.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-login-bg-secondary-color',
							'value'       => $ddfcpos_configuration['login_bg_secondary_color'],
						],
						[
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Canvas Base Color', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Base color for full login page.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-login-canvas-bg-color',
							'value'       => $ddfcpos_configuration['login_canvas_bg_color'],
						],
						[
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Card Backdrop Color', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Background color for login card.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-login-card-bg-color',
							'value'       => $ddfcpos_configuration['login_card_bg_color'],
						],
						[
							'type'        => 'colorpicker',
							'label'       => esc_html__( 'Font Color', 'devdiggers-multipos-for-fluentcart' ),
							'description' => esc_html__( 'Text color for headings and labels.', 'devdiggers-multipos-for-fluentcart' ),
							'id'          => 'ddfcpos-login-font-color',
							'value'       => $ddfcpos_configuration['login_font_color'],
						],
					],
				],
			];

			$layout = new DDFW_Layout();
			$layout->get_form_section_layout( $args, 'ddfcpos-login-configuration-fields' );
		}
	}
}
