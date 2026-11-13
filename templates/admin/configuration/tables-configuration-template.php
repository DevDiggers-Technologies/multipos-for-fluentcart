<?php
/**
 * Tables Configuration template class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Templates\Admin\Configuration;

use DevDiggers\Framework\Includes\DDFW_Layout;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Tables_Configuration_Template' ) ) {
	/**
	 * Tables Configuration template class
	 */
	class DDFCPOS_Tables_Configuration_Template {
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
							<th><?php esc_html_e( 'Identification Name', 'devdiggers-multipos-for-fluentcart' ); ?></th>
							<th><?php esc_html_e( 'Unique Slug', 'devdiggers-multipos-for-fluentcart' ); ?></th>
							<th><?php esc_html_e( 'Seating Capacity', 'devdiggers-multipos-for-fluentcart' ); ?></th>
							<th><?php esc_html_e( 'Status', 'devdiggers-multipos-for-fluentcart' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( ! empty( $ddfcpos_configuration['tables'] ) ) {
							foreach ( $ddfcpos_configuration['tables'] as $key => $tables ) {
								?>
								<tr valign="top">
									<td class="forminp forminp-text">
										<input type="text" name="_ddfcpos_tables[<?php echo esc_attr( $key ); ?>][name]" class="regular-text ddfcpos-width-90" autocomplete="off" value="<?php echo esc_attr( $tables['name'] ); ?>" />
									</td>
									<td class="forminp forminp-text">
										<input type="text" name="_ddfcpos_tables[<?php echo esc_attr( $key ); ?>][slug]" class="regular-text ddfcpos-width-90" autocomplete="off" value="<?php echo esc_attr( $tables['slug'] ); ?>" readonly />
									</td>
									<td class="forminp forminp-text">
										<input type="number" min="1" name="_ddfcpos_tables[<?php echo esc_attr( $key ); ?>][seats]" class="regular-text ddfcpos-width-90" autocomplete="off" value="<?php echo esc_attr( $tables['seats'] ); ?>" />
									</td>
									<td class="forminp forminp-text ddfw-table-column-flex">
										<select class="regular-text ddfcpos-width-90" name="_ddfcpos_tables[<?php echo esc_attr( $key ); ?>][status]" data-placeholder="<?php esc_attr_e( 'Select Status', 'devdiggers-multipos-for-fluentcart' ); ?>">
											<option value="enabled" <?php selected( 'enabled', isset( $tables['status'] ) ? $tables['status'] : '' ); ?>><?php esc_html_e( 'Active', 'devdiggers-multipos-for-fluentcart' ); ?></option>
											<option value="disabled" <?php selected( 'disabled', isset( $tables['status'] ) ? $tables['status'] : '' ); ?>><?php esc_html_e( 'Inactive', 'devdiggers-multipos-for-fluentcart' ); ?></option>
										</select>
										<span class="dashicons dashicons-trash ddfcpos-remove-row" title="<?php esc_attr_e( 'Remove', 'devdiggers-multipos-for-fluentcart' ); ?>"></span>
									</td>
								</tr>
								<?php
							}
						}
						?>
						<tr>
							<td colspan="4">
								<a href="javascript:void(0);" class="ddfcpos-add-row button" data-template="ddfcpos-tables-configuration-row"><?php esc_html_e( 'Add Guest Table', 'devdiggers-multipos-for-fluentcart' ); ?></a>
							</td>
						</tr>
					</tbody>
				</table>

				<input type="hidden" id="ddfcpos-max-index" value="<?php echo esc_attr( isset( $key ) ? $key : -1 ); ?>">
			</div>
			<?php
			$custom_html = ob_get_clean();

			$args = [
				[
					'header'            => [
						'heading'     => esc_html__( 'Tables', 'devdiggers-multipos-for-fluentcart' ),
						'description' => esc_html__( 'Add and manage tables available in restaurant POS.', 'devdiggers-multipos-for-fluentcart' ),
					],
					'after_header_html' => $custom_html,
					'fields'            => [],
				],
			];

			$layout = new DDFW_Layout();
			$layout->get_form_section_layout( $args, 'ddfcpos-tables-configuration-fields', '', 'ddfcpos-tables-container' );

			// Underscore.js templates are printed raw (outside the layout's wp_kses pass, which strips <script>).
			?>
			<!-- Tables Configuration Row Template -->
			<script id="tmpl-ddfcpos-tables-configuration-row" type="text/html">
				<tr valign="top">
					<td class="forminp forminp-text">
						<input type="text" name="_ddfcpos_tables[{{data.key}}][name]" class="regular-text ddfcpos-width-90" autocomplete="off" />
					</td>
					<td class="forminp forminp-text">
						<input type="text" name="_ddfcpos_tables[{{data.key}}][slug]" class="regular-text ddfcpos-width-90" autocomplete="off" />
					</td>
					<td class="forminp forminp-text">
						<input type="number" min="1" name="_ddfcpos_tables[{{data.key}}][seats]" class="regular-text ddfcpos-width-90" autocomplete="off" />
					</td>
					<td class="forminp forminp-text ddfw-table-column-flex">
						<select class="regular-text ddfcpos-width-90" name="_ddfcpos_tables[{{data.key}}][status]" data-placeholder="<?php esc_attr_e( 'Select Status', 'devdiggers-multipos-for-fluentcart' ); ?>">
							<option value="enabled"><?php esc_html_e( 'Active', 'devdiggers-multipos-for-fluentcart' ); ?></option>
							<option value="disabled"><?php esc_html_e( 'Inactive', 'devdiggers-multipos-for-fluentcart' ); ?></option>
						</select>
						<span class="dashicons dashicons-trash ddfcpos-remove-row" title="<?php esc_attr_e( 'Remove', 'devdiggers-multipos-for-fluentcart' ); ?>"></span>
					</td>
				</tr>
			</script>

			<!-- Invalid form data Template -->
			<script id="tmpl-ddfcpos_form_data_error" type="text/html">
				<div class='notice notice-error is-dismissible'>
					<p><?php esc_html_e( 'Required table attributes are missing or invalid.', 'devdiggers-multipos-for-fluentcart' ); ?></p>
				</div>
			</script>
			<?php
		}
	}
}
