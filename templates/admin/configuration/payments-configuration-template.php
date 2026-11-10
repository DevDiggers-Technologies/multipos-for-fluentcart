<?php
/**
 * Payments Configuration template class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Configuration;

use DevDiggers\Framework\Includes\DDFW_Layout;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Payments_Configuration_Template' ) ) {
	/**
	 * Payments Configuration template class
	 */
	class DDFCPOS_Payments_Configuration_Template {
		/**
		 * Construct
		 * 
		 * @param array $ddfcpos_configuration
		 */
		public function __construct( $ddfcpos_configuration ) {
			ob_start();
			?>
			<div class="ddfw-table-wrapper">
				<table class="widefat fixed striped ddfw-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Payment Method Label', 'multipos-for-fluentcart' ); ?></th>
							<th><?php esc_html_e( 'Unique Slug', 'multipos-for-fluentcart' ); ?></th>
							<th><?php esc_html_e( 'Enable/Disable Status', 'multipos-for-fluentcart' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( ! empty( $ddfcpos_configuration['payment_method'] ) ) {
							foreach ( $ddfcpos_configuration['payment_method'] as $key => $payment_method ) {
								?>
								<tr valign="top">
									<td class="forminp forminp-text">
										<input type="hidden" name="_ddfcpos_payment_method[<?php echo esc_attr( $key ); ?>][permanent]" value="<?php echo esc_attr( $payment_method['permanent'] ); ?>" />
										<input type="text" name="_ddfcpos_payment_method[<?php echo esc_attr( $key ); ?>][name]" class="regular-text ddfcpos-width-90" autocomplete="off" value="<?php echo esc_attr( $payment_method['name'] ); ?>" />
									</td>
									<td class="forminp forminp-text">
										<input type="text" name="_ddfcpos_payment_method[<?php echo esc_attr( $key ); ?>][slug]" class="regular-text ddfcpos-width-90" autocomplete="off" value="<?php echo esc_attr( $payment_method['slug'] ); ?>" readonly />
									</td>
									<td class="forminp forminp-text ddfw-table-column-flex">
										<select class="regular-text ddfcpos-width-90" name="_ddfcpos_payment_method[<?php echo esc_attr( $key ); ?>][status]" data-placeholder="<?php esc_attr_e( 'Select Status', 'multipos-for-fluentcart' ); ?>">
											<option value="enabled" <?php selected( 'enabled', isset( $payment_method['status'] ) ? $payment_method['status'] : '' ); ?>><?php esc_html_e( 'Active', 'multipos-for-fluentcart' ); ?></option>
											<option value="disabled" <?php selected( 'disabled', isset( $payment_method['status'] ) ? $payment_method['status'] : '' ); ?>><?php esc_html_e( 'Inactive', 'multipos-for-fluentcart' ); ?></option>
										</select>
										<?php
										if ( 'no' === $payment_method['permanent'] ) {
											?>
											<span class="dashicons dashicons-trash ddfcpos-remove-row" title="<?php esc_attr_e( 'Remove', 'multipos-for-fluentcart' ); ?>"></span>
											<?php
										}
										?>
									</td>
								</tr>
								<?php
							}
						}
						?>
						<tr>
							<td colspan="3">
								<a href="javascript:void(0);" class="ddfcpos-add-row button" data-template="ddfcpos-payments-configuration-row"><?php esc_html_e( 'Register Custom Method', 'multipos-for-fluentcart' ); ?></a>
							</td>
						</tr>
					</tbody>
				</table>

				<input type="hidden" id="ddfcpos-max-index" value="<?php echo esc_attr( isset( $key ) ? $key : 0 ); ?>">
			</div>
			<?php
			$custom_html = ob_get_clean();

			$args = [
				[
					'header'            => [
						'heading'     => esc_html__( 'Payments', 'multipos-for-fluentcart' ),
						'description' => esc_html__( 'Add, edit, and enable payment methods available in POS.', 'multipos-for-fluentcart' ),
					],
					'after_header_html' => $custom_html,
					'fields'            => [],
				],
			];

			$layout = new DDFW_Layout();
			$layout->get_form_section_layout( $args, 'ddfcpos-payments-configuration-fields', '', 'ddfcpos-payments-container' );

			// Underscore.js templates are printed raw (outside the layout's wp_kses pass, which strips <script>).
			?>
			<!-- Payments Configuration Row Template -->
			<script id="tmpl-ddfcpos-payments-configuration-row" type="text/html">
				<tr valign="top">
					<td class="forminp forminp-text">
						<input type="hidden" name="_ddfcpos_payment_method[{{data.key}}][permanent]" value="no" />
						<input type="text" name="_ddfcpos_payment_method[{{data.key}}][name]" class="regular-text ddfcpos-width-90" autocomplete="off" />
					</td>
					<td class="forminp forminp-text">
						<input type="text" name="_ddfcpos_payment_method[{{data.key}}][slug]" class="regular-text ddfcpos-width-90" autocomplete="off" />
					</td>
					<td class="forminp forminp-text ddfw-table-column-flex">
						<select class="regular-text ddfcpos-width-90" name="_ddfcpos_payment_method[{{data.key}}][status]" data-placeholder="<?php esc_attr_e( 'Select Status', 'multipos-for-fluentcart' ); ?>">
							<option value="enabled"><?php esc_html_e( 'Active', 'multipos-for-fluentcart' ); ?></option>
							<option value="disabled"><?php esc_html_e( 'Inactive', 'multipos-for-fluentcart' ); ?></option>
						</select>
						<span class="dashicons dashicons-trash ddfcpos-remove-row" title="<?php esc_attr_e( 'Remove', 'multipos-for-fluentcart' ); ?>"></span>
					</td>
				</tr>
			</script>

			<!-- Invalid form data Template -->
			<script id="tmpl-ddfcpos_form_data_error" type="text/html">
				<div class='notice notice-error is-dismissible'>
					<p><?php esc_html_e( 'Some fields are missing or contain invalid formats.', 'multipos-for-fluentcart' ); ?></p>
				</div>
			</script>
			<?php
		}
	}
}
