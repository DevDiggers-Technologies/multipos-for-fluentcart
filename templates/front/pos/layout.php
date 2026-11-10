<?php
/**
 * Layout template class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Front\POS;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Layout' ) ) {
	/**
	 * Layout template class
	 */
	class DDFCPOS_Layout {
		/**
		 * Construct
		 */
		public function __construct( $ddfcpos_configuration ) {
            $icons_path = DDFCPOS_PLUGIN_URL . 'assets/images/';

            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
				<head>
					<title><?php echo esc_html( get_bloginfo() ); ?></title>
					<meta charset="<?php bloginfo( 'charset' ); ?>">
					<meta name="viewport" content="width=device-width, initial-scale=1"/>
					<link rel="icon" type="image/x-icon" href="<?php echo esc_url( get_site_icon_url() ); ?>">
					<?php wp_head(); ?>
				</head>
				<body>
					<?php
					if ( is_user_logged_in() ) {
						$user = wp_get_current_user();

						if ( in_array( 'ddfcpos_cashier', $user->roles, true ) || in_array( 'administrator', $user->roles, true ) || current_user_can( 'ddfcpos_manage' ) || apply_filters( 'ddfcpos/access/allow_administrator', false ) ) {
							?>
							<div id="app"></div>
							<?php
						}
					} else {
						?>
						<div class="ddfcpos-login-wrapper">
							<div class="ddfcpos-mesh-background"></div>
							<div class="ddfcpos-login-card">
								<div class="ddfcpos-login-header">
									<div class="ddfcpos-brand-icon">
										<img src="<?php echo esc_url( ! empty( $ddfcpos_configuration[ 'logo' ] ) ? wp_get_attachment_url( $ddfcpos_configuration[ 'logo' ] ) : $icons_path . 'logo.png' ); ?>" />
									</div>
									<h1><?php echo esc_html( $ddfcpos_configuration[ 'login_heading_text' ] ); ?></h1>
									<p class="ddfcpos-login-subtitle"><?php echo esc_html( $ddfcpos_configuration[ 'login_subtitle_text' ] ); ?></p>
								</div>

								<?php
								// In case of a login error.
								if ( isset( $_GET['login'] ) && 'failed' === $_GET['login'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks the login error message to show.
									?>
									<div class="ddfcpos-login-error">
										<p>
											<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 18C14.4183 18 18 14.4183 18 10C18 5.58172 14.4183 2 10 2C5.58172 2 2 5.58172 2 10C2 14.4183 5.58172 18 10 18Z" stroke="currentColor" stroke-width="1.5"/><path d="M10 6V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="14" r="1" fill="currentColor"/></svg>
											<?php esc_html_e( 'Wrong Credentials, Try Again!', 'multipos-for-fluentcart' ); ?>
										</p>
									</div>
									<?php
								}
								$args = [
									'echo'           => true,
									'redirect'       => site_url( $ddfcpos_configuration[ 'endpoint' ] ),
									'form_id'        => 'ddfcpos-login-form',
									'label_username' => esc_html__( 'Username or Email', 'multipos-for-fluentcart' ),
									'label_password' => esc_html__( 'Password', 'multipos-for-fluentcart' ),
									'label_remember' => esc_html__( 'Remember Me', 'multipos-for-fluentcart' ),
									'label_log_in'   =>  $ddfcpos_configuration[ 'login_button_text' ],
									'id_username'    => 'user_login',
									'id_password'    => 'user_pass',
									'id_remember'    => 'rememberme',
									'id_submit'      => 'ddfcpos-login-submit',
									'remember'       => ! empty( $ddfcpos_configuration[ 'login_rememberme_enabled' ] ),
									'value_username' => null,
									'value_remember' => true,
								];

								$before_login_button = apply_filters( 'ddfcpos/login/before_button', '', $args );

								$form =
									sprintf(
										'<form name="%1$s" id="%1$s" action="%2$s" method="post">',
										esc_attr( $args[ 'form_id' ] ),
										esc_url( site_url( 'wp-login.php', 'login_post' ) )
									) .
									sprintf(
										'<div class="login-input-group">
											<label for="%1$s">%2$s</label>
											<div class="input-with-icon">
												<span class="input-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.25" r="3.75"></circle><path d="M4.75 20c.9-3.5 3.85-5.5 7.25-5.5s6.35 2 7.25 5.5"></path></svg></span>
												<input type="text" name="log" id="%1$s" autocomplete="username" class="input" value="%3$s" placeholder="%2$s" />
											</div>
										</div>',
										esc_attr( $args[ 'id_username' ] ),
										esc_html( $args[ 'label_username' ] ),
										esc_attr( $args[ 'value_username' ] )
									) .
									sprintf(
										'<div class="login-input-group">
											<label for="%1$s">%2$s</label>
											<div class="input-with-icon">
												<span class="input-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10.5" width="14" height="10" rx="2.5"></rect><path d="M8.25 10.5V7.75a3.75 3.75 0 0 1 7.5 0v2.75"></path></svg></span>
												<input type="password" name="pwd" id="%1$s" autocomplete="current-password" spellcheck="false" class="input" value="" placeholder="%2$s" />
												<button type="button" class="ddfcpos-toggle-password" aria-label="%3$s" aria-pressed="false" onclick="var i=document.getElementById(\'%1$s\'),s=i.type===\'password\';i.type=s?\'text\':\'password\';this.setAttribute(\'aria-pressed\',s);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle><line class="eye-slash" x1="3" y1="3" x2="21" y2="21"></line></svg></button>
											</div>
										</div>',
										esc_attr( $args[ 'id_password' ] ),
										esc_html( $args[ 'label_password' ] ),
										esc_attr__( 'Show password', 'multipos-for-fluentcart' )
									) .
                                    '<div class="login-extra-options">' .
									( $args[ 'remember' ] ?
										sprintf(
											'<div class="login-remember"><label><input name="rememberme" type="checkbox" id="%1$s" value="forever"%2$s /> <span>%3$s</span></label></div>',
											esc_attr( $args[ 'id_remember' ] ),
											( $args['value_remember'] ? ' checked="checked"' : '' ),
											esc_html( $args[ 'label_remember' ] )
										) : ''
									) .
									( ! empty( $ddfcpos_configuration[ 'login_forgot_enabled' ] ) ?
										sprintf(
											'<div class="login-forgot-password"><a href="%1$s" class="ddfcpos-lost-password">%2$s</a></div>',
											esc_url( wp_lostpassword_url() ),
											esc_html__( 'Forgot Password?', 'multipos-for-fluentcart' )
										) : ''
									) .
                                    '</div>' .
									$before_login_button .
									sprintf(
										'<div class="login-submit">
											<button type="submit" name="wp-submit" id="%1$s" class="button button-primary">
												<span>%2$s</span>
												<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
											</button>
											<input type="hidden" name="redirect_to" value="%3$s" />
										</div>',
										esc_attr( $args[ 'id_submit' ] ),
										esc_attr( $args[ 'label_log_in' ] ),
										esc_url( $args[ 'redirect' ] )
									) .
									'</form>';

								echo wp_kses( $form, ddfw_kses_allowed_form_html() );

								?>
								<div class="ddfcpos-login-footer">
									<p><?php echo esc_html( $ddfcpos_configuration[ 'login_footer_text' ] ); ?></p>
									<?php if ( ! empty( $ddfcpos_configuration['login_branding_enabled'] ) ) : ?>
										<p class="ddfcpos-login-branding">
											<?php esc_html_e( 'Powered by ', 'multipos-for-fluentcart' ); ?><a href="https://devdiggers.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'MultiPOS', 'multipos-for-fluentcart' ); ?></a>
										</p>
									<?php endif; ?>
								</div>
							</div>
						</div>
						<?php
					}
					?>
				</body>

				<!-- Prompt a message in the browser if users disabled JS -->
				<noscript><?php esc_html_e( 'Your browser does not support JavaScript!', 'multipos-for-fluentcart' ); ?></noscript>
            </html>
            <?php
        }
	}
}
