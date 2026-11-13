<?php
/**
 * Invoice Helper class
 *
 * @package MultiPOS for FluentCart
 */

namespace DDFCMultiPOS\Helper\Invoice;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Invoice_Helper' ) ) {
    /**
     * Invoice Helper class
     */
    class DDFCPOS_Invoice_Helper {

		/**
         * Configuration Variable
         * 
         * @var array
         */
        protected $ddfcpos_configuration;

		/**
		 * Construct
		 * 
		 * @param array $ddfcpos_configuration
		 */
		public function __construct( $ddfcpos_configuration ) {
            $this->ddfcpos_configuration = $ddfcpos_configuration;
		}

		/**
		 * Get invoice html function
		 *
		 * @return void
		 */
        public function ddfcpos_get_invoice_html() {
            $logo_url = ! empty( $this->ddfcpos_configuration[ 'logo' ] ) ? wp_get_attachment_url( $this->ddfcpos_configuration[ 'logo' ] ) : DDFCPOS_PLUGIN_URL . 'assets/images/logo.png';
            return (
                '<div class="ddfcpos-invoice-container">
                    <div class="invoice-header">
                        <img src="' . esc_url( $logo_url ) . '" alt="logo" />
                        <h2>${outlet_name}</h2>
                        <p>${outlet_address1} ${outlet_address2}</p>
                        <p>${outlet_city} ${outlet_state}</p>
                        <p>${outlet_phone}</p>
                        <p>${outlet_email}</p>
                    </div>

                    <h3 class="invoice-title">' . esc_html__( 'Sales Receipt', 'devdiggers-multipos-for-fluentcart' ) . '</h3>

                    <div class="invoice-meta">
                        <p><span>' . esc_html__( 'Order', 'devdiggers-multipos-for-fluentcart' ) . '</span> ${order_id}</p>
                        <p><span>' . esc_html__( 'Date', 'devdiggers-multipos-for-fluentcart' ) . '</span> ${order_date}</p>
                        <p><span>' . esc_html__( 'Cashier', 'devdiggers-multipos-for-fluentcart' ) . '</span> ${cashier_name}</p>
                        <p><span>' . esc_html__( 'Customer', 'devdiggers-multipos-for-fluentcart' ) . '</span> ${customer_first_name} ${customer_last_name}</p>
                        <p><span>' . esc_html__( 'Items', 'devdiggers-multipos-for-fluentcart' ) . '</span> ${total_quantity}</p>
                    </div>

                    <div class="product-details">
                        <table>
                            <thead>
                                <tr>
                                    <th>' . esc_html__( 'Item', 'devdiggers-multipos-for-fluentcart' ) . '</th>
                                    <th>' . esc_html__( 'Price', 'devdiggers-multipos-for-fluentcart' ) . '</th>
                                    <th>' . esc_html__( 'Qty', 'devdiggers-multipos-for-fluentcart' ) . '</th>
                                    <th>' . esc_html__( 'Total', 'devdiggers-multipos-for-fluentcart' ) . '</th>
                                </tr>
                            </thead>
                        </table>
                        ${product_row}
                    </div>

                    <div class="invoice-details invoice-subtotal">
                        <div class="left-details">
                            <p>' . esc_html__( 'Subtotal', 'devdiggers-multipos-for-fluentcart' ) . '</p>
                            ${tax_label}
                            ${coupon_name}
                        </div>
                        <div class="right-details">
                            <p>${sub_total}</p>
                            ${order_tax}
                            ${coupon_amount}
                        </div>
                    </div>

                    <div class="invoice-details invoice-total">
                        <div class="left-details">
                            <p>' . esc_html__( 'Total', 'devdiggers-multipos-for-fluentcart' ) . '</p>
                        </div>
                        <div class="right-details">
                            <p>${order_total}</p>
                        </div>
                    </div>

                    <div class="invoice-details invoice-payment">
                        <div class="left-details">
                            ${tendered_payment_name}
                            <p>' . esc_html__( 'Total Tendered', 'devdiggers-multipos-for-fluentcart' ) . '</p>
                            <p>' . esc_html__( 'Change', 'devdiggers-multipos-for-fluentcart' ) . '</p>
                            <p>' . esc_html__( 'Refunded', 'devdiggers-multipos-for-fluentcart' ) . '</p>
                        </div>
                        <div class="right-details">
                            ${tendered_payment_amount}
                            <p>${tendered_total}</p>
                            <p>${order_change}</p>
                            <p>${order_refunded}</p>
                        </div>
                    </div>

                    <div class="invoice-footer">
                        <p><strong>' . esc_html__( 'Thank you, have a nice day.', 'devdiggers-multipos-for-fluentcart' ) . '</strong></p>
                        <p>${store_url}</p>
                    </div>
                </div>'
            );
        }

		/**
		 * What the admin needs to draw a receipt with made-up data: the default template, the
		 * sample values for every ${token} and the receipt width.
		 *
		 * @return array
		 */
		public function ddfcpos_get_invoice_preview_data() {
			$money = function ( $amount ) {
				return \DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper::format_price_text( $amount );
			};

			$outlet  = ( new \DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper() )->ddfcpos_get_outlet_option();
			$outlet  = $outlet ? $outlet : [];
			$store   = \DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper::store_address();
			$items   = [
				[ __( 'Cotton T-Shirt, Medium', 'devdiggers-multipos-for-fluentcart' ), 18, 2 ],
				[ __( 'Ceramic Mug', 'devdiggers-multipos-for-fluentcart' ), 9.5, 1 ],
				[ __( 'Notebook A5', 'devdiggers-multipos-for-fluentcart' ), 4.25, 1 ],
			];
			$rows     = '';
			$subtotal = 0;

			foreach ( $items as $item ) {
				$subtotal += $item[1] * $item[2];
				$rows     .= '<tr><td><p>' . esc_html( $item[0] ) . '</p></td><td><p>' . esc_html( $money( $item[1] ) ) . '</p></td><td><p>' . esc_html( $item[2] ) . '</p></td><td><p>' . esc_html( $money( $item[1] * $item[2] ) ) . '</p></td></tr>';
			}

			$tax   = round( $subtotal * 0.1, 2 );
			$total = $subtotal + $tax - 2;

			$tokens = [
				'outlet_name'             => ! empty( $outlet['name'] ) ? $outlet['name'] : get_bloginfo( 'name' ),
				'outlet_address1'         => ! empty( $outlet['address1'] ) ? $outlet['address1'] : $store['address1'],
				'outlet_address2'         => ! empty( $outlet['address2'] ) ? $outlet['address2'] : $store['address2'],
				'outlet_city'             => ! empty( $outlet['city'] ) ? $outlet['city'] : $store['city'],
				'outlet_state'            => ! empty( $outlet['state'] ) ? $outlet['state'] : $store['state'],
				'outlet_country'          => ! empty( $outlet['country'] ) ? $outlet['country'] : $store['country'],
				'outlet_postcode'         => ! empty( $outlet['postcode'] ) ? $outlet['postcode'] : $store['postcode'],
				'outlet_phone'            => ! empty( $outlet['phone'] ) ? $outlet['phone'] : '',
				'outlet_email'            => ! empty( $outlet['email'] ) ? $outlet['email'] : get_option( 'admin_email' ),
				'order_id'                => '1042',
				'order_date'              => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				'customer_first_name'          => __( 'Alex', 'devdiggers-multipos-for-fluentcart' ),
				'customer_last_name'          => __( 'Morgan', 'devdiggers-multipos-for-fluentcart' ),
				'customer_phone'          => '+1 555 0100',
				'cashier_name'            => wp_get_current_user()->display_name,
				'table_name'              => __( 'Table 4', 'devdiggers-multipos-for-fluentcart' ),
				'product_row'             => '<table><tbody>' . $rows . '</tbody></table>',
				'total_quantity'          => '4',
				'sub_total'               => $money( $subtotal ),
				'tax_label'               => '<p>' . esc_html__( 'Tax (10%)', 'devdiggers-multipos-for-fluentcart' ) . '</p>',
				'order_tax'               => '<p>' . esc_html( $money( $tax ) ) . '</p>',
				'coupon_name'             => '',
				'coupon_amount'           => '',
				'order_total'             => $money( $total ),
				'order_refunded'          => $money( 0 ),
				'tendered_payment_name'   => '<p>' . esc_html__( 'Cash', 'devdiggers-multipos-for-fluentcart' ) . '</p>',
				'tendered_payment_amount' => '<p>' . esc_html( $money( 60 ) ) . '</p>',
				'tendered_total'          => $money( 60 ),
				'order_change'            => $money( 60 - $total ),
				'logo_invoice'            => ! empty( $this->ddfcpos_configuration['logo'] ) ? wp_get_attachment_url( $this->ddfcpos_configuration['logo'] ) : DDFCPOS_PLUGIN_URL . 'assets/images/logo.png',
				'store_name'              => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'store_url'               => untrailingslashit( preg_replace( '#^https?://#', '', home_url() ) ),
				'outlet_full_address'     => '',
				'customer_email'          => 'alex.morgan@example.com',
				'customer_address'        => __( '12 Market Street, Springfield IL 62701, United States', 'devdiggers-multipos-for-fluentcart' ),
				'payment_method'          => __( 'Cash', 'devdiggers-multipos-for-fluentcart' ),
				'print_date'              => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			];

			// Same order and separators as the POS builds it.
			$tokens['outlet_full_address'] = implode(
				', ',
				array_filter(
					[
						$tokens['outlet_address1'],
						$tokens['outlet_address2'],
						$tokens['outlet_city'],
						trim( $tokens['outlet_state'] . ' ' . $tokens['outlet_postcode'] ),
						$tokens['outlet_country'],
					]
				)
			);

			return [
				'html'      => $this->ddfcpos_get_invoice_html(),
				'css'       => $this->ddfcpos_get_invoice_css(),
				'width'     => $this->ddfcpos_configuration['printer_width'],
				'tokens'    => $tokens,
			];
		}

		/**
		 * Get invoice css function
		 *
		 * @return string
		 */
		public function ddfcpos_get_invoice_css() {
            return str_replace( '                ', '', '
                @font-face {
                    font-family: "OpenSans";
                    font-style: normal;
                    src: url(' . esc_url( DDFCPOS_PLUGIN_URL . 'assets/fonts/OpenSans-Regular.ttf' ) . ') format("truetype");
                }
                .ddfcpos-invoice-container {
                    padding: 8px 4px;
                    color: #000;
                    font-family: "OpenSans", sans-serif;
                    font-size: 12px;
                    line-height: 1.45;
                    word-break: break-word;
                }
                .ddfcpos-invoice-container * {
                    padding: 0;
                    margin: 0;
                }
                .invoice-header, .invoice-footer, .invoice-title {
                    text-align: center;
                }
                .invoice-header img {
                    width: auto;
                    max-width: 50%;
                    max-height: 56px;
                    margin: 0 auto 8px;
                }
                .invoice-header h2 {
                    font-size: 16px;
                    font-weight: 700;
                    margin-bottom: 2px;
                }
                .invoice-header p {
                    font-size: 11px;
                }
                .invoice-header p:empty, .invoice-meta p:empty {
                    display: none;
                }
                .invoice-title {
                    margin: 12px 0 8px;
                    padding: 6px 0;
                    border-top: 1px dashed #000;
                    border-bottom: 1px dashed #000;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: .12em;
                    text-transform: uppercase;
                }
                .invoice-meta p {
                    display: flex;
                    justify-content: space-between;
                    gap: 12px;
                }
                .invoice-meta p span, .invoice-meta p strong {
                    font-weight: 400;
                    color: #444;
                }
                .product-details {
                    margin-top: 10px;
                    border-top: 1px dashed #000;
                    border-bottom: 1px dashed #000;
                    padding-bottom: 4px;
                }
                .product-details table {
                    border-collapse: collapse;
                    table-layout: fixed;
                    width: 100%;
                }
                .product-details table th {
                    padding: 6px 0;
                    font-size: 10px;
                    font-weight: 700;
                    letter-spacing: .06em;
                    text-transform: uppercase;
                    text-align: right;
                    border-bottom: 1px solid #000;
                }
                .product-details table td, .product-details table td p {
                    font-size: 12px;
                }
                .product-details table td {
                    padding: 3px 0;
                    text-align: right;
                    vertical-align: top;
                }
                .product-details table tr th:nth-child(1), .product-details table tr td:nth-child(1) {
                    width: 46%;
                    padding-right: 6px;
                    text-align: left;
                }
                .product-details table tr th:nth-child(2), .product-details table tr td:nth-child(2) {
                    width: 20%;
                }
                .product-details table tr th:nth-child(3), .product-details table tr td:nth-child(3) {
                    width: 12%;
                    text-align: center;
                }
                .product-details table tr th:nth-child(4), .product-details table tr td:nth-child(4) {
                    width: 22%;
                }
                .product-details table td:nth-child(4) p {
                    font-weight: 700;
                }
                .invoice-details {
                    display: flex;
                    justify-content: space-between;
                    gap: 12px;
                    padding: 8px 0;
                }
                .invoice-details .right-details {
                    text-align: right;
                    white-space: nowrap;
                }
                .invoice-total {
                    padding: 8px 0;
                    border-top: 1px solid #000;
                    border-bottom: 1px solid #000;
                    font-size: 18px;
                    font-weight: 700;
                }
                .invoice-payment {
                    color: #333;
                    border-bottom: 1px dashed #000;
                }
                .invoice-footer {
                    padding-top: 12px;
                }
                .invoice-footer p + p {
                    font-size: 11px;
                    color: #444;
                }'
            );
        }
    }
}