<?php
/**
 * Cash Register Configuration template class
 *
 * Introduces the cash control of MultiPOS Pro. Nothing here closes a register in the free version.
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Configuration;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Register_Configuration_Template' ) ) {
	/**
	 * Cash Register Configuration template class
	 */
	class DDFCPOS_Register_Configuration_Template {
		/**
		 * Construct
		 *
		 * @param array $ddfcpos_configuration Configuration data.
		 */
		public function __construct( $ddfcpos_configuration ) {
			$rows  = array(
				array(
					'icon'  => '<rect x="3" y="11" width="18" height="9" rx="2"></rect><path d="M7 11V5h10v6"></path><path d="M10 8h4"></path>',
					'title' => esc_html__( 'Close the register', 'devdiggers-multipos-for-fluentcart' ),
					'text'  => esc_html__( 'The cashier counts the drawer and sees at once if it is balanced, over or short.', 'devdiggers-multipos-for-fluentcart' ),
				),
				array(
					'icon'  => '<path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path>',
					'title' => esc_html__( 'X-report and Z-report', 'devdiggers-multipos-for-fluentcart' ),
					'text'  => esc_html__( 'Print the shift so far at any time, and a Z-report slip when the register closes.', 'devdiggers-multipos-for-fluentcart' ),
				),
				array(
					'icon'  => '<path d="M4 4h16v16H4z"></path><path d="M4 9h16"></path><path d="M9 20V9"></path>',
					'title' => esc_html__( 'Every close on file', 'devdiggers-multipos-for-fluentcart' ),
					'text'  => esc_html__( 'A Registers screen keeps each Z-report, and the owner gets the end of day numbers by email.', 'devdiggers-multipos-for-fluentcart' ),
				),
			);
			$store = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$lines = array(
				array( esc_html__( 'Opening float', 'devdiggers-multipos-for-fluentcart' ), 100 ),
				array( esc_html__( 'Cash sales', 'devdiggers-multipos-for-fluentcart' ), 486.5 ),
				array( esc_html__( 'Cash in', 'devdiggers-multipos-for-fluentcart' ), 20 ),
				array( esc_html__( 'Cash out', 'devdiggers-multipos-for-fluentcart' ), -50 ),
			);
			?>
			<div class="ddfcpos-intro">
				<div class="ddfcpos-intro-copy">
					<span class="ddfcpos-intro-eyebrow"><?php esc_html_e( 'Pro', 'devdiggers-multipos-for-fluentcart' ); ?></span>
					<h2><?php esc_html_e( 'Know your drawer is right at every close', 'devdiggers-multipos-for-fluentcart' ); ?></h2>
					<p class="ddfcpos-intro-lead"><?php esc_html_e( 'MultiPOS Pro adds cash control to the POS: open with a float, record cash in and out, and close each shift with a counted drawer and a Z-report.', 'devdiggers-multipos-for-fluentcart' ); ?></p>
					<ul class="ddfcpos-intro-rows">
						<?php foreach ( $rows as $row ) : ?>
							<li>
								<span class="ddfcpos-intro-row-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?php echo wp_kses( $row['icon'], ddfw_kses_allowed_svg_tags() ); ?></svg></span>
								<span>
									<strong><?php echo esc_html( $row['title'] ); ?></strong>
									<?php echo esc_html( $row['text'] ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
					<a class="button button-primary" href="https://devdiggers.com/product/fluentcart-pos/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'devdiggers-multipos-for-fluentcart' ); ?></a>
					<p class="ddfcpos-intro-status"><?php esc_html_e( 'Available in MultiPOS Pro', 'devdiggers-multipos-for-fluentcart' ); ?></p>
				</div>
				<div class="ddfcpos-intro-preview" aria-hidden="true">
					<div class="ddfcpos-intro-slip">
						<strong class="ddfcpos-intro-slip-store"><?php echo esc_html( $store ); ?></strong>
						<span class="ddfcpos-intro-slip-title"><?php esc_html_e( 'Z-Report', 'devdiggers-multipos-for-fluentcart' ); ?></span>
						<?php foreach ( $lines as $line ) : ?>
							<span class="ddfcpos-intro-slip-line"><span><?php echo esc_html( $line[0] ); ?></span><span><?php echo esc_html( $this->ddfcpos_price( $line[1] ) ); ?></span></span>
						<?php endforeach; ?>
						<span class="ddfcpos-intro-slip-line ddfcpos-intro-slip-total"><span><?php esc_html_e( 'Expected in drawer', 'devdiggers-multipos-for-fluentcart' ); ?></span><span><?php echo esc_html( $this->ddfcpos_price( 556.5 ) ); ?></span></span>
						<span class="ddfcpos-intro-slip-line"><span><?php esc_html_e( 'Counted', 'devdiggers-multipos-for-fluentcart' ); ?></span><span><?php echo esc_html( $this->ddfcpos_price( 556.5 ) ); ?></span></span>
						<span class="ddfcpos-intro-slip-result"><?php esc_html_e( 'Balanced', 'devdiggers-multipos-for-fluentcart' ); ?></span>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * A plain text price for the example report.
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		protected function ddfcpos_price( $amount ) {
			return \DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper::format_price_text( $amount );
		}
	}
}
