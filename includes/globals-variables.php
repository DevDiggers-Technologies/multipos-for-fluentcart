<?php
/**
 * Global Variables
 *
 * @author DevDiggers
 * @package MultiPOS for FluentCart
 * @version 1.0.0
*/

defined( 'ABSPATH' ) || exit();

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file builds the plugin configuration array from local variables.
global $ddfcpos_configuration;

$order_status             = get_option( '_ddfcpos_order_status' );
$default_customer         = get_option( '_ddfcpos_default_customer' );

// Every sale needs a customer in FluentCart, so a walk-in account stands in when none is chosen.
if ( ! $default_customer ) {
    $walkin_customer  = \DDFCMultiPOS\Helper\Customer\DDFCPOS_Customer_Helper::ddfcpos_get_walkin_customer();
    $default_customer = $walkin_customer ? (string) $walkin_customer->id : '';
}
$default_barcode          = get_option( '_ddfcpos_default_barcode' );
$endpoint                 = get_option( '_ddfcpos_endpoint' );
$payment_method           = get_option( '_ddfcpos_payment_method' );
$tables                   = get_option( '_ddfcpos_tables' );
$login_heading_text       = get_option( '_ddfcpos_login_heading_text' );
$login_subtitle_text      = get_option( '_ddfcpos_login_subtitle_text' );
$login_footer_text        = get_option( '_ddfcpos_login_footer_text' );
$login_button_text        = get_option( '_ddfcpos_login_button_text' );
$login_bg_primary_color   = get_option( '_ddfcpos_login_bg_primary_color' );
$login_bg_secondary_color = get_option( '_ddfcpos_login_bg_secondary_color' );
$login_canvas_bg_color    = get_option( '_ddfcpos_login_canvas_bg_color' );
$login_card_bg_color      = get_option( '_ddfcpos_login_card_bg_color' );
$login_font_color         = get_option( '_ddfcpos_login_font_color' );
$barcode_printer_width    = get_option( '_ddfcpos_barcode_printer_width' );
$barcode_printer_height   = get_option( '_ddfcpos_barcode_printer_height' );
$barcode_printer_margin   = get_option( '_ddfcpos_barcode_printer_margin' );
$barcode_height           = get_option( '_ddfcpos_barcode_height' );
$barcode_margin           = get_option( '_ddfcpos_barcode_margin' );
$barcode_orientation      = get_option( '_ddfcpos_barcode_orientation' );
$printer_width            = get_option( '_ddfcpos_printer_width' );
$printer_height           = get_option( '_ddfcpos_printer_height' );
$printer_margin           = get_option( '_ddfcpos_printer_margin' );
$layout_primary_color     = get_option( '_ddfcpos_layout_primary_color' );
$layout_secondary_color   = get_option( '_ddfcpos_layout_secondary_color' );
$layout_font_color        = get_option( '_ddfcpos_layout_font_color' );
$layout_surface_color     = get_option( '_ddfcpos_layout_surface_color' );
$layout_muted_bg_color_1  = get_option( '_ddfcpos_layout_muted_background_color_1' );
$layout_muted_bg_color_2  = get_option( '_ddfcpos_layout_muted_background_color_2' );
$layout_muted_bg_color    = get_option( '_ddfcpos_layout_muted_background_color' );
$layout_button_font_color = get_option( '_ddfcpos_layout_button_font_color' );
$layout_success_color     = get_option( '_ddfcpos_layout_success_color' );
$layout_border_color      = get_option( '_ddfcpos_layout_border_color' );
$layout_pos_font_family   = get_option( '_ddfcpos_layout_pos_font_family' );
$layout_radius            = get_option( '_ddfcpos_layout_radius' );
$layout_font_size         = get_option( '_ddfcpos_layout_font_size' );

if ( empty( $payment_method ) ) {
    $payment_method = [];
    $payment_method[] = [
        'name'      => esc_html__( 'Cash', 'multipos-for-fluentcart' ),
        'slug'      => 'cash',
        'permanent' => 'yes',
        'status'    => 'enabled',
    ];
}

// Methods saved without every key ( older saves, other code ) get the defaults, so screens never read a missing key.
$payment_method = array_map( function( $method ) {
    return wp_parse_args( (array) $method, [ 'name' => '', 'slug' => '', 'permanent' => 'no', 'status' => 'enabled' ] );
}, (array) $payment_method );

$ddfcpos_configuration = [
    'enabled'                              => get_option( '_ddfcpos_enabled' ),
    'order_status'                         => $order_status ? $order_status : 'completed',
    'default_barcode'                      => $default_barcode ?: 'id',
    'order_mails_enabled'                  => get_option( '_ddfcpos_order_mails_enabled' ),
    'show_order_status_enabled'            => '',
    'logo'                                 => get_option( '_ddfcpos_logo' ),
    'default_customer'                     => $default_customer,
    'endpoint'                             => ! empty( $endpoint ) ? $endpoint : 'pos',
    'payment_method'                       => $payment_method,
    'tables'                               => ! empty( $tables ) ? $tables : [],
    'login_heading_text'                   => ! empty( $login_heading_text ) ? $login_heading_text : esc_html__( 'Welcome to Point of Sale', 'multipos-for-fluentcart' ),
    'login_subtitle_text'                  => ! empty( $login_subtitle_text ) ? $login_subtitle_text : esc_html__( 'Access your point of sale terminal to manage orders and retail operations.', 'multipos-for-fluentcart' ),
    'login_footer_text'                    => ! empty( $login_footer_text ) ? $login_footer_text : esc_html__( 'Thanks for using the Point of Sale', 'multipos-for-fluentcart' ),
    'login_button_text'                    => ! empty( $login_button_text ) ? $login_button_text : esc_html__( 'Log in', 'multipos-for-fluentcart' ),
    'login_branding_enabled'               => get_option( '_ddfcpos_login_branding_enabled', '' ),
    'login_rememberme_enabled'             => get_option( '_ddfcpos_login_rememberme_enabled' ),
    'login_forgot_enabled'                 => get_option( '_ddfcpos_login_forgot_enabled' ),
    'login_bg_primary_color'               => ! empty( $login_bg_primary_color ) ? $login_bg_primary_color : '#f0f7ff',
    'login_bg_secondary_color'             => ! empty( $login_bg_secondary_color ) ? $login_bg_secondary_color : '#add8e6',
    'login_canvas_bg_color'                => ! empty( $login_canvas_bg_color ) ? $login_canvas_bg_color : '#f0f7ff',
    'login_card_bg_color'                  => ! empty( $login_card_bg_color ) ? $login_card_bg_color : 'rgba(255, 255, 255, 0.4)',
    'login_font_color'                     => ! empty( $login_font_color ) ? $login_font_color : '#1a1a1a',
    'barcode_printer_width'                => ! empty( $barcode_printer_width ) ? $barcode_printer_width : '28mm',
    'barcode_printer_height'               => ! empty( $barcode_printer_height ) ? $barcode_printer_height : '89mm',
    'barcode_printer_margin'               => ! empty( $barcode_printer_margin ) ? $barcode_printer_margin : '0mm',
    'barcode_height'                       => ! empty( $barcode_height ) ? $barcode_height : '40mm',
    'barcode_margin'                       => ! empty( $barcode_margin ) ? $barcode_margin : '50mm -3mm',
    'barcode_orientation'                  => ! empty( $barcode_orientation ) ? $barcode_orientation : 'vertical',
    'printer_width'                        => ! empty( $printer_width ) ? $printer_width : '80mm',
    'printer_height'                       => ! empty( $printer_height ) ? $printer_height : '297mm',
    'printer_margin'                       => ! empty( $printer_margin ) ? $printer_margin : '4mm',
    'layout_primary_color'                 => ! empty( $layout_primary_color ) ? $layout_primary_color : '#18181b',
    'layout_secondary_color'               => ! empty( $layout_secondary_color ) ? $layout_secondary_color : '#18181b',
    'layout_font_color'                    => ! empty( $layout_font_color ) ? $layout_font_color : '#52525b',
    'layout_surface_color'                 => ! empty( $layout_surface_color ) ? $layout_surface_color : '#fff',
    'layout_muted_background_color_1'      => ! empty( $layout_muted_bg_color_1 ) ? $layout_muted_bg_color_1 : '#f1f1f3',
    'layout_muted_background_color_2'      => ! empty( $layout_muted_bg_color_2 ) ? $layout_muted_bg_color_2 : ( ! empty( $layout_muted_bg_color ) ? $layout_muted_bg_color : '#f9f9fa' ),
    'layout_button_font_color'             => ! empty( $layout_button_font_color ) ? $layout_button_font_color : '#fff',
    'layout_success_color'                 => ! empty( $layout_success_color ) ? $layout_success_color : '#16a34a',
    'layout_border_color'                  => ! empty( $layout_border_color ) ? $layout_border_color : '#e4e4e7',
    'layout_pos_font_family'               => ! empty( $layout_pos_font_family ) ? $layout_pos_font_family : 'open_sans',
    'layout_radius'                        => ! empty( $layout_radius ) ? $layout_radius : '6',
    'layout_font_size'                     => ! empty( $layout_font_size ) ? $layout_font_size : '14',
    'product_layout'                       => get_option( '_ddfcpos_product_layout', 'image_top' ),
    'show_product_stock_enabled'           => get_option( '_ddfcpos_show_product_stock_enabled', 'yes' ),
];
