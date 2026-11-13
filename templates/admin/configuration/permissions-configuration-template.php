<?php
/**
 * Permissions Configuration template class
 *
 * Introduces the cashier permissions of MultiPOS Pro. Cashiers have full POS access in the free version.
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Configuration;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Permissions_Configuration_Template' ) ) {
	/**
	 * Permissions Configuration template class
	 */
	class DDFCPOS_Permissions_Configuration_Template {
		/**
		 * Construct
		 *
		 * @param array $ddfcpos_configuration Configuration data.
		 */
		public function __construct( $ddfcpos_configuration ) {
			$rows = array(
				array(
					'icon'  => '<path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z"></path><path d="M9.5 12l1.8 1.8L15 10"></path>',
					'title' => esc_html__( 'Choose what cashiers may do', 'devdiggers-multipos-for-fluentcart' ),
					'text'  => esc_html__( 'Refunds, price changes, cash in and out, closing the register and editing customers, each on or off.', 'devdiggers-multipos-for-fluentcart' ),
				),
				array(
					'icon'  => '<rect x="5" y="10.5" width="14" height="10" rx="2"></rect><path d="M8.5 10.5V7.5a3.5 3.5 0 0 1 7 0v3"></path>',
					'title' => esc_html__( 'Manager approval with a PIN', 'devdiggers-multipos-for-fluentcart' ),
					'text'  => esc_html__( 'When a cashier needs more, a manager types their PIN at the till and the sale carries on.', 'devdiggers-multipos-for-fluentcart' ),
				),
				array(
					'icon'  => '<path d="M19 5L5 19"></path><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle>',
					'title' => esc_html__( 'A limit on discounts', 'devdiggers-multipos-for-fluentcart' ),
					'text'  => esc_html__( 'Set the biggest discount a cashier may give. Anything above it is flagged on the order.', 'devdiggers-multipos-for-fluentcart' ),
				),
			);
			?>
			<div class="ddfcpos-intro">
				<div class="ddfcpos-intro-copy">
					<span class="ddfcpos-intro-eyebrow"><?php esc_html_e( 'Pro', 'devdiggers-multipos-for-fluentcart' ); ?></span>
					<h2><?php esc_html_e( 'Give cashiers the right amount of control', 'devdiggers-multipos-for-fluentcart' ); ?></h2>
					<p class="ddfcpos-intro-lead"><?php esc_html_e( 'MultiPOS Pro lets you decide what a cashier can do alone, and lets a manager approve the rest with a PIN, right at the counter.', 'devdiggers-multipos-for-fluentcart' ); ?></p>
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
					<div class="ddfcpos-intro-approval">
						<strong><?php esc_html_e( 'Manager approval', 'devdiggers-multipos-for-fluentcart' ); ?></strong>
						<span class="ddfcpos-intro-approval-reason"><?php esc_html_e( 'Refund order #1042', 'devdiggers-multipos-for-fluentcart' ); ?></span>
						<span class="ddfcpos-intro-approval-dots"><i></i><i></i><i></i><i></i></span>
						<span class="ddfcpos-intro-approval-keys">
							<?php foreach ( array( '1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '' ) as $key ) : ?>
								<span><?php echo esc_html( $key ); ?></span>
							<?php endforeach; ?>
						</span>
						<span class="ddfcpos-intro-approval-button"><?php esc_html_e( 'Approve', 'devdiggers-multipos-for-fluentcart' ); ?></span>
					</div>
				</div>
			</div>
			<?php
		}
	}
}
