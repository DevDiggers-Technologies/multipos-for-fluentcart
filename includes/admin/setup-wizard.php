<?php
/**
 * MultiPOS Setup Wizard Integration
 */

namespace DDFCMultiPOS\Includes\Admin;

use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;

defined( 'ABSPATH' ) || exit();

use DevDiggers\Framework\Includes\DDFW_Form_Field;
use DevDiggers\Framework\Includes\DDFW_Setup_Wizard;

if ( ! class_exists( 'DDFCPOS_Setup_Wizard' ) ) {
    /**
     * DDFCPOS_Setup_Wizard class
     */
    class DDFCPOS_Setup_Wizard {
        /**
         * Configuration array
         *
         * @var array
         */
        private $ddfcpos_configuration;

        /**
         * Constructor
         */
        public function __construct() {
            global $ddfcpos_configuration;
            $this->ddfcpos_configuration = $ddfcpos_configuration;

            $slug = 'multipos-for-fluentcart';

            // If the plugin already has existing configuration, mark the wizard as completed.
            if ( ! get_option( 'ddfw_setup_wizard_completed_' . $slug ) ) {
                if ( get_option( '_ddfcpos_enabled' ) ) {
                    update_option( 'ddfw_setup_wizard_completed_' . $slug, true );
                }
            }

            new DDFW_Setup_Wizard( $this->get_wizard_config() );
        }

        /**
         * Get the wizard configuration
         *
         * @return array
         */
        public function get_wizard_config() {
            return [
                'plugin_slug'    => 'multipos-for-fluentcart',
                'plugin_file'    => 'multipos-for-fluentcart/functions.php',
                'dashboard_page' => 'ddfcpos-dashboard',
                'redirect_url'   => admin_url( 'admin.php?page=ddfcpos-dashboard' ),
                'brand'          => [
                    'name'        => esc_html__( 'MultiPOS for FluentCart', 'multipos-for-fluentcart' ),
                    'description' => esc_html__( "Welcome to MultiPOS for FluentCart. Let's set up your POS in a few quick steps.", 'multipos-for-fluentcart' ),
                    'logo'        => '<svg class="ddfw-success-svg" width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="16" cy="16" r="15" fill="var(--ddfw-tab-background-color)"/>
                        <path d="M9 21C9 19.8954 9.89543 19 11 19H21C22.1046 19 23 19.8954 23 21V25C23 25.5523 22.5523 26 22 26H10C9.44772 26 9 25.5523 9 25V21Z" fill="var(--ddfw-primary-color)"/>
                        <path d="M10 11C10 9.89543 10.8954 9 12 9H20C21.1046 9 22 9.89543 22 11V19H10V11Z" fill="var(--ddfw-primary-color)" fill-opacity="0.7"/>
                        <rect x="12" y="11" width="2" height="1.5" rx="0.5" fill="white"/>
                        <rect x="15" y="11" width="2" height="1.5" rx="0.5" fill="white"/>
                        <rect x="18" y="11" width="2" height="1.5" rx="0.5" fill="white"/>
                        <rect x="12" y="14" width="2" height="1.5" rx="0.5" fill="white"/>
                        <rect x="15" y="14" width="2" height="1.5" rx="0.5" fill="white"/>
                        <rect x="18" y="14" width="2" height="1.5" rx="0.5" fill="white"/>
                        <circle cx="16" cy="22.5" r="1.2" fill="white"/>
                        <path d="M18 9V4L19 5L20 4L21 5L22 4V9H18Z" fill="var(--ddfw-primary-color)" fill-opacity="0.5"/>
                    </svg>',
                ],
                'steps'          => [
                    'welcome'     => [
                        'label'         => esc_html__( 'Welcome', 'multipos-for-fluentcart' ),
                        'view_callback' => [ $this, 'welcome_view' ],
                    ],
                    'general'     => [
                        'label'         => esc_html__( 'General', 'multipos-for-fluentcart' ),
                        'title'         => esc_html__( 'General', 'multipos-for-fluentcart' ),
                        'description'   => esc_html__( 'Turn POS on and set core order behavior.', 'multipos-for-fluentcart' ),
                        'view_callback' => [ $this, 'general_settings_view' ],
                        'save_callback' => [ $this, 'save_fields' ],
                    ],
                    'checkout'    => [
                        'label'         => esc_html__( 'Checkout', 'multipos-for-fluentcart' ),
                        'title'         => esc_html__( 'Checkout', 'multipos-for-fluentcart' ),
                        'description'   => esc_html__( 'Choose what happens after a POS checkout.', 'multipos-for-fluentcart' ),
                        'view_callback' => [ $this, 'checkout_features_view' ],
                        'save_callback' => [ $this, 'save_fields' ],
                    ],
                    'layout'      => [
                        'label'         => esc_html__( 'Layout', 'multipos-for-fluentcart' ),
                        'title'         => esc_html__( 'Layout', 'multipos-for-fluentcart' ),
                        'description'   => esc_html__( 'Set colors, logo, and product display style for POS.', 'multipos-for-fluentcart' ),
                        'view_callback' => [ $this, 'layout_settings_view' ],
                        'save_callback' => [ $this, 'save_fields' ],
                    ],
                    'customers'   => [
                        'label'         => esc_html__( 'Customers', 'multipos-for-fluentcart' ),
                        'title'         => esc_html__( 'Customers', 'multipos-for-fluentcart' ),
                        'description'   => esc_html__( 'Choose how POS handles guest and saved customers.', 'multipos-for-fluentcart' ),
                        'view_callback' => [ $this, 'customer_preferences_view' ],
                        'save_callback' => [ $this, 'save_fields' ],
                    ],
                    'ready'       => [
                        'label'             => esc_html__( 'Ready!', 'multipos-for-fluentcart' ),
                        'ready_title'       => esc_html__( 'Your POS is ready!', 'multipos-for-fluentcart' ),
                        'ready_description' => esc_html__( 'Your POS is ready. Set up your outlet, add cashiers, and update settings anytime.', 'multipos-for-fluentcart' ),
                    ],
                ],
            ];
        }

        /**
         * Welcome view
         *
         * @return void
         */
        public function welcome_view() {
            ?>
            <div class="ddfw-setup-wizard-ready ddfw-setup-wizard-onboarding">
                <div class="ddfw-success-icon-wrap">
                    <svg class="ddfw-success-svg" width="100" height="100" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="16" cy="16" r="15" fill="var(--ddfw-tab-background-color)"/>
                        <path d="M9 21C9 19.8954 9.89543 19 11 19H21C22.1046 19 23 19.8954 23 21V25C23 25.5523 22.5523 26 22 26H10C9.44772 26 9 25.5523 9 25V21Z" fill="var(--ddfw-primary-color)"/>
                        <path d="M10 11C10 9.89543 10.8954 9 12 9H20C21.1046 9 22 9.89543 22 11V19H10V11Z" fill="var(--ddfw-primary-color)" fill-opacity="0.7"/>
                        <rect x="12" y="11" width="2" height="1.5" rx="0.5" fill="white"/>
                        <rect x="15" y="11" width="2" height="1.5" rx="0.5" fill="white"/>
                        <rect x="18" y="11" width="2" height="1.5" rx="0.5" fill="white"/>
                        <rect x="12" y="14" width="2" height="1.5" rx="0.5" fill="white"/>
                        <rect x="15" y="14" width="2" height="1.5" rx="0.5" fill="white"/>
                        <rect x="18" y="14" width="2" height="1.5" rx="0.5" fill="white"/>
                        <circle cx="16" cy="22.5" r="1.2" fill="white"/>
                        <path d="M18 9V4L19 5L20 4L21 5L22 4V9H18Z" fill="var(--ddfw-primary-color)" fill-opacity="0.5"/>
                    </svg>
                </div>
                <h2 class="ddfw-setup-wizard-ready-title"><?php esc_html_e( 'Welcome to MultiPOS!', 'multipos-for-fluentcart' ); ?></h2>
                <p class="ddfw-setup-wizard-ready-desc">
                    <?php esc_html_e( 'Let\'s quickly set up your Point of Sale system so you can start selling in your physical store. This wizard will guide you through the essential settings.', 'multipos-for-fluentcart' ); ?>
                </p>
            </div>
            <?php
        }

        /**
         * General settings view
         *
         * @return void
         */
        public function general_settings_view() {
            ?>
            <div class="ddfw-fields-section">
                <table class="form-table">
                    <tbody>
                        <?php
                        $fields = [
                            [
                                'type'           => 'checkbox',
                                'label'          => esc_html__( 'Plugin Status', 'multipos-for-fluentcart' ),
                                'checkbox_label' => esc_html__( 'Enable Point of Sale System', 'multipos-for-fluentcart' ),
                                'description'    => esc_html__( 'Turn POS on or off for this site.', 'multipos-for-fluentcart' ),
                                'id'             => 'ddfcpos-enabled',
                                'name'           => '_ddfcpos_enabled',
                                'value'          => $this->ddfcpos_configuration['enabled'],
                            ],
                            [
                                'type'        => 'select',
                                'label'       => esc_html__( 'Initial Order Status', 'multipos-for-fluentcart' ),
                                'description' => esc_html__( 'Choose default status for new POS orders.', 'multipos-for-fluentcart' ),
                                'options'     => DDFCPOS_FluentCart_Helper::order_statuses(),
                                'id'          => 'ddfcpos-order-status',
                                'name'        => '_ddfcpos_order_status',
                                'value'       => $this->ddfcpos_configuration['order_status'],
                            ],
                            [
                                'type'        => 'select',
                                'label'       => esc_html__( 'Barcode Synchronization', 'multipos-for-fluentcart' ),
                                'description' => esc_html__( 'Choose whether barcodes use product ID or SKU.', 'multipos-for-fluentcart' ),
                                'options'     => [
                                    'id'  => esc_html__( 'Use Product ID', 'multipos-for-fluentcart' ),
                                    'sku' => esc_html__( 'Use Product SKU', 'multipos-for-fluentcart' ),
                                ],
                                'id'          => 'ddfcpos-default-barcode',
                                'name'        => '_ddfcpos_default_barcode',
                                'value'       => $this->ddfcpos_configuration['default_barcode'],
                            ],
                            [
                                'type'        => 'text',
                                'label'       => esc_html__( 'POS Terminal URL Path', 'multipos-for-fluentcart' ),
                                'description' => esc_html__( 'Choose URL path for POS screen, like /pos.', 'multipos-for-fluentcart' ),
                                'id'          => 'ddfcpos-endpoint',
                                'name'        => '_ddfcpos_endpoint',
                                'value'       => $this->ddfcpos_configuration['endpoint'],
                            ],
                        ];

                        foreach ( $fields as $field ) {
                            DDFW_Form_Field::display_form_field( $field );
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php
        }

        /**
         * Checkout features view
         *
         * @return void
         */
        public function checkout_features_view() {
            ?>
            <div class="ddfw-fields-section">
                <table class="form-table">
                    <tbody>
                        <?php
                        $fields = [
                            [
                                'type'           => 'checkbox',
                                'label'          => esc_html__( 'Order Notifications', 'multipos-for-fluentcart' ),
                                'checkbox_label' => esc_html__( 'Enable Automated Order Mails', 'multipos-for-fluentcart' ),
                                'description'    => esc_html__( 'Send FluentCart order emails after POS checkout.', 'multipos-for-fluentcart' ),
                                'id'             => 'ddfcpos-order-mails-enabled',
                                'name'           => '_ddfcpos_order_mails_enabled',
                                'value'          => $this->ddfcpos_configuration['order_mails_enabled'],
                            ],
                        ];

                        foreach ( $fields as $field ) {
                            DDFW_Form_Field::display_form_field( $field );
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php
        }

        /**
         * Layout settings view
         *
         * @return void
         */
        public function layout_settings_view() {
            ?>
            <div class="ddfw-fields-section">
                <table class="form-table">
                    <tbody>
                        <?php
                        $fields = [
                            [
                                'type'          => 'image',
                                'label'         => esc_html__( 'POS Terminal Branding Logo', 'multipos-for-fluentcart' ),
                                'description'   => esc_html__( 'Shown on receipts and POS admin screens. Recommended size: 200x60 pixels.', 'multipos-for-fluentcart' ),
                                'id'            => 'ddfcpos-logo',
                                'name'          => '_ddfcpos_logo',
                                'value'         => $this->ddfcpos_configuration['logo'],
                                'default_image' => DDFCPOS_PLUGIN_URL . 'assets/images/logo.png',
                            ],
                            [
                                'type'        => 'colorpicker',
                                'label'       => esc_html__( 'Primary Brand Color', 'multipos-for-fluentcart' ),
                                'description' => esc_html__( 'Main color for active states and primary buttons.', 'multipos-for-fluentcart' ),
                                'id'          => 'ddfcpos-layout-primary-color',
                                'name'        => '_ddfcpos_layout_primary_color',
                                'value'       => $this->ddfcpos_configuration['layout_primary_color'],
                            ],
                            [
                                'type'        => 'select',
                                'label'       => esc_html__( 'Product Card Orientation', 'multipos-for-fluentcart' ),
                                'description' => esc_html__( 'Choose whether product image sits left or top.', 'multipos-for-fluentcart' ),
                                'options'     => [
                                    'image_left' => esc_html__( 'Horizontal (Image Left, Text Right)', 'multipos-for-fluentcart' ),
                                    'image_top'  => esc_html__( 'Vertical (Image Top, Text Bottom)', 'multipos-for-fluentcart' ),
                                ],
                                'id'          => 'ddfcpos-product-layout',
                                'name'        => '_ddfcpos_product_layout',
                                'value'       => $this->ddfcpos_configuration['product_layout'],
                            ],
                            [
                                'type'           => 'checkbox',
                                'label'          => esc_html__( 'Real-time Stock Indicators', 'multipos-for-fluentcart' ),
                                'checkbox_label' => esc_html__( 'Display Current Stock Count', 'multipos-for-fluentcart' ),
                                'description'    => esc_html__( 'Show stock count on each product card.', 'multipos-for-fluentcart' ),
                                'id'             => 'ddfcpos-show-product-stock-enabled',
                                'name'           => '_ddfcpos_show_product_stock_enabled',
                                'value'          => $this->ddfcpos_configuration['show_product_stock_enabled'],
                            ],
                        ];

                        foreach ( $fields as $field ) {
                            DDFW_Form_Field::display_form_field( $field );
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php
        }

        /**
         * Customer preferences view
         *
         * @return void
         */
        public function customer_preferences_view() {
            ?>
            <div class="ddfw-fields-section">
                <table class="form-table">
                    <tbody>
                        <?php
                        $fields = [
                            [
                                'type'        => 'select',
                                'label'       => esc_html__( 'Default Guest Account', 'multipos-for-fluentcart' ),
                                'description' => esc_html__( 'Choose the FluentCart customer used for walk-in (guest) orders.', 'multipos-for-fluentcart' ),
                                'options'     => DDFCPOS_Admin_Functions::ddfcpos_get_customer_options( $this->ddfcpos_configuration['default_customer'] ),
                                'id'          => 'ddfcpos-default-customer',
                                'name'        => '_ddfcpos_default_customer',
                                'value'       => $this->ddfcpos_configuration['default_customer'],
                            ],
                        ];

                        foreach ( $fields as $field ) {
                            DDFW_Form_Field::display_form_field( $field );
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php
        }

        /**
         * Save the wizard step. Only the settings the wizard shows are written.
         *
         * @param array $form_data Form data array.
         * @return true|\WP_Error
         */
        public function save_fields( $form_data ) {
            if ( ! current_user_can( 'ddfcpos_manage' ) ) {
                return new \WP_Error( 'ddfcpos_insufficient_permissions', esc_html__( 'Insufficient permissions.', 'multipos-for-fluentcart' ) );
            }

            $allowed_options = [
                '_ddfcpos_enabled',
                '_ddfcpos_order_status',
                '_ddfcpos_default_barcode',
                '_ddfcpos_endpoint',
                '_ddfcpos_order_mails_enabled',
                '_ddfcpos_logo',
                '_ddfcpos_layout_primary_color',
                '_ddfcpos_product_layout',
                '_ddfcpos_show_product_stock_enabled',
                '_ddfcpos_default_customer',
            ];

            foreach ( (array) $form_data as $field ) {
                if ( isset( $field['name'], $field['value'] ) && in_array( $field['name'], $allowed_options, true ) ) {
                    update_option( $field['name'], $this->sanitize_wizard_field( $field['name'], $field['value'] ) );
                }
            }

            return true;
        }

        /**
         * Sanitize a wizard field by its option name.
         *
         * @param string $field_name  Option name.
         * @param mixed  $field_value Submitted value.
         * @return mixed
         */
        protected function sanitize_wizard_field( $field_name, $field_value ) {
            switch ( $field_name ) {
                case '_ddfcpos_logo':
                case '_ddfcpos_default_customer':
                    return absint( $field_value );
                case '_ddfcpos_endpoint':
                    return sanitize_title( $field_value );
                case '_ddfcpos_layout_primary_color':
                    return sanitize_hex_color( $field_value );
                case '_ddfcpos_order_status':
                    return sanitize_key( $field_value );
                case '_ddfcpos_default_barcode':
                    return 'sku' === $field_value ? 'sku' : 'id';
                default:
                    return sanitize_text_field( $field_value );
            }
        }
    }
}
