<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use Exception;
use WC_Logger;
use WC_Order;
use WC_Payment_Gateway;
use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LknIntegrationRedeForWoocommerceGooglePay extends LknIntegrationRedeForWoocommerceWcRedeAbstract
{
    public $configs;
    public $log;
    public $debug;
    public $pro_fields;

    public function __construct()
    {
        $this->id = 'rede_google_pay';
        $this->title = $this->get_option('title');
        $this->description = '';
        $this->has_fields = true;
        $this->method_title = esc_attr__('Pay with Google Pay via Rede', 'woo-rede');
        $this->method_description = esc_attr__('Enables and configures payments with Google Pay through Rede', 'woo-rede') . ' <a target="_blank" href="https://www.linknacional.com.br/wordpress/woocommerce/rede/doc/">' . esc_attr__('Documentation', 'woo-rede') . '</a>';
        $this->supports = array(
            'products',
            'refunds',
        );

        $this->icon = LknIntegrationRedeForWoocommerceHelper::getUrlIcon();
        
        // Define os campos de configuração do método de pagamento
        $this->init_form_fields();
        $this->init_settings();

        // Define as configurações do método de pagamento
        $this->log = $this->get_logger();
        $this->debug = $this->get_option('debug');

        $this->autoGenerateKeysIfNeeded();

        // Lista dinâmica de campos PRO (identificados pelo atributo customizado 'lkn-is-pro')
        $this->pro_fields = array();
        foreach ($this->form_fields as $key => $field) {
            if (isset($field['custom_attributes']['lkn-is-pro']) && $field['custom_attributes']['lkn-is-pro'] === 'true') {
                $this->pro_fields[] = $key;
            }
        }
    }
    /**
     * Sobrescreve o salvamento das opções para garantir que campos PRO sejam revertidos ao valor padrão se a licença não estiver ativa.
     */
    public function process_admin_options() {
        parent::process_admin_options();

        $proVersionActive = LknIntegrationRedeForWoocommerceHelper::isProLicenseValid();
        if (!$proVersionActive && !empty($this->pro_fields)) {
            // Obter o array de settings do gateway
            $settings_option_name = 'woocommerce_' . $this->id . '_settings';
            $settings = get_option($settings_option_name, array());
            $changed = false;
            foreach ($this->pro_fields as $field_key) {
                if (isset($this->form_fields[$field_key]['default'])) {
                    $default = $this->form_fields[$field_key]['default'];
                } elseif (isset($this->form_fields[$field_key]['options'])) {
                    $options = $this->form_fields[$field_key]['options'];
                    $default = is_array($options) ? array_key_first($options) : '';
                } else {
                    $default = '';
                }
                if (!isset($settings[$field_key]) || $settings[$field_key] !== $default) {
                    $settings[$field_key] = $default;
                    $changed = true;
                }
            }
            if ($changed) {
                update_option($settings_option_name, $settings);
            }
        }
    }

    public function init_form_fields(): void
    {
        if (isset($_GET['page']) && isset($_GET['section'])) {
            if ('wc-settings' == $_GET['page'] && $_GET['section'] == $this->id) {
                $proVersionActive = LknIntegrationRedeForWoocommerceHelper::isProLicenseValid();
                if($proVersionActive) {
                    wp_enqueue_script('LknRedeForWoocommerceProLicenseButton', REDE_FOR_WOOCOMMERCE_DIR_URL . '/Admin/js/LknRedeForWoocommerceProValidateLicenseButton.js', array('jquery', 'wp-api'), REDE_FOR_WOOCOMMERCE_PRO_VERSION, 'all');
                    wp_localize_script('LknRedeForWoocommerceProLicenseButton', 'LknRedeForWoocommerceProTranslations', array(
                        'ValidateLicense' => __('Validate License', 'woo-rede'),
                        'licenseSuccess' => __('License validated successfully!', 'woo-rede'),
                        'licenseError' => __('Invalid or expired license!', 'woo-rede'),
                        'licenseViewLogs' => __('View logs', 'woo-rede')
                    ));
                }

                $this->form_fields = array(
                    'google_pay_general' => array(
                        'title' => esc_attr__('General', 'woo-rede'),
                        'type' => 'title',
                    ),
                    'enabled' => array(
                        'title' => esc_attr__('Google Pay Payment', 'woo-rede'),
                        'type' => 'checkbox',
                        'label' => __('Enable', 'woo-rede'),
                        'default' => 'no',
                        'desc_tip'    => esc_attr__('Check this box and save to enable Google Pay settings.', 'woo-rede'),
                        'description' => esc_attr__('Enable or disable the Google Pay payment method.', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__('Enable this option to allow customers to pay with Google Pay using Rede API.', 'woo-rede')
                        )
                    ),
                    'title' => array(
                        'title' => esc_attr__('Title', 'woo-rede'),
                        'type' => 'text',
                        'default' => __('Pay with Google Pay', 'woo-rede'),
                        'desc_tip' => esc_attr__('Enter the title that will be shown to customers during the checkout process.', 'woo-rede'),
                        'description' => esc_attr__('This controls the title which the user sees during checkout.', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__('This text will appear as the payment method title during checkout. Choose something your customers will easily understand, like "Pay with Google Pay".', 'woo-rede')
                        )
                    ),
                    'description' => array(
                        'title' => __('Description', 'woo-rede'),
                        'type' => 'textarea',
                        'default' => __('Pay securely with Google Pay', 'woo-rede'),
                        'desc_tip' => esc_attr__('This description appears below the payment method title at checkout. Use it to inform your customers about the payment processing details.', 'woo-rede'),
                        'description' => esc_attr__('Payment method description that the customer will see on your checkout.', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__('Provide a brief message that informs the customer how the payment will be processed. For example: "Your payment will be securely processed by Rede."', 'woo-rede')
                        )
                    ),
                    'environment' => array(
                        'title' => esc_attr__('Environment', 'woo-rede'),
                        'type' => 'select',
                        'desc_tip' => esc_attr__('Choose between production or development mode for Rede API.', 'woo-rede'),
                        'description' => esc_attr__('Choose the environment', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__("Select 'Tests' to test transactions in sandbox mode. Use 'Production' for real transactions.", 'woo-rede')
                        ),
                        'class' => 'wc-enhanced-select',
                        'default' => esc_attr__('test', 'woo-rede'),
                        'options' => array(
                            'test' => esc_attr__('Tests', 'woo-rede'),
                            'production' => esc_attr__('Production', 'woo-rede'),
                        ),
                    ),
                    'pv' => array(
                        'title' => __('PV', 'woo-rede'),
                        'type' => 'password',
                        'desc_tip' => esc_attr__('Your Rede PV number.', 'woo-rede'),
                        'description' => esc_attr__('Rede credentials.', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__("Your PV (Point of Sale) number provided by Rede.", 'woo-rede')
                        ),
                        'default' => '',
                        'required' => true,
                    ),
                    'token' => array(
                        'title' => __('Token', 'woo-rede'),
                        'type' => 'password',
                        'desc_tip' => esc_attr__('Your Rede token.', 'woo-rede'),
                        'description' => esc_attr__('Rede credentials.', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__("Your Rede token for API authentication.", 'woo-rede')
                        ),
                        'required' => true,
                    ),
                    'google_merchant_name' => array(
                        'title'       => __('Google Pay', 'woo-rede'),
                        'type'        => 'text',
                        'desc_tip'    => __('Enter the name that will be displayed to customers in the Google Pay sheet.', 'woo-rede'),
                        'description' => __('Required for Production. This is the business name customers will see during the payment process.', 'woo-rede'),
                        'default'     => get_bloginfo('name'),
                        'custom_attributes' => array(
                            'data-title-label'       => esc_attr__('Merchant Name', 'woo-rede'),
                            'data-title-description' => esc_attr__('The public name of your store in the Google Pay interface.', 'woo-rede')
                        ),
                    ),
                    'google_merchant_id' => array(
                        'title'       => __('Google Merchant ID', 'woo-rede'),
                        'type'        => 'text',
                        'desc_tip'    => __('Your unique Merchant ID is required to process live transactions.', 'woo-rede'),
                        'description' => sprintf(
                            /* translators: %s: HTML link to the Google Pay Console for finding the Merchant ID. */
                            __('Required for Production. Find your ID in the Google Pay Console. %s', 'woo-rede'),
                            '<a href="https://pay.google.com/business/console/" target="_blank">' . __('Click here', 'woo-rede') . '</a>'
                        ),
                        'default'     => '',
                        'custom_attributes' => array(
                            'merge-top'              => "woocommerce_{$this->id}_google_merchant_name",
                            'data-title-description' => esc_attr__('The unique identifier provided by Google after your account is approved.', 'woo-rede')
                        ),
                        'required'    => true,
                    ),
                    'google_text_button' => array(
                        'title' => __('Google Pay Button Text', 'woo-rede'),
                        'type' => 'select',
                        'desc_tip' => __('Choose the text to display on the Google Pay button.', 'woo-rede'),
                        'description' => __('Defines the text that will appear on the Google Pay payment button.', 'woo-rede'),
                        'default' => 'pay',
                        'options' => array(
                            'pay' => __('Pay', 'woo-rede'),
                            'buy' => __('Buy', 'woo-rede'),
                            'checkout' => __('Checkout', 'woo-rede'),
                            'donate' => __('Donate', 'woo-rede'),
                        ),
                        'custom_attributes' => array_merge(
                            array(
                                'style' => 'min-width: 200px; width: 100%; max-width: 400px;',
                                'merge-top' => "woocommerce_{$this->id}_google_merchant_name",
                                'data-title-description' => esc_attr__('Choose the text to display on the Google Pay button.', 'woo-rede')
                            ),
                            !$proVersionActive ? array('lkn-is-pro' => 'true') : array()
                        ),
                    ),
                    'require_3ds' => array(
                        'title' => __('Require 3D Secure', 'woo-rede'),
                        'type' => 'checkbox',
                        'label' => __('Enable 3D Secure authentication', 'woo-rede'),
                        'desc_tip' => __('When enabled, 3D Secure authentication will be required for transactions.', 'woo-rede'),
                        'description' => __('3D Secure adds an extra layer of security to transactions, but may reduce conversion rates.', 'woo-rede'),
                        'default' => 'no',
                        'custom_attributes' => array_merge(
                            array(
                                'merge-top' => "woocommerce_{$this->id}_google_merchant_name",
                                'data-title-description' => esc_attr__('Requires 3D Secure authentication for greater security.', 'woo-rede')
                            ),
                            !$proVersionActive ? array('lkn-is-pro' => 'true') : array()
                        ),
                    ),
                    'google_pay_public_key' => array(
                        'title' => __('Public Key (Base64)', 'woo-rede'),
                        'type' => 'text', // Public key is a single-line string
                        'desc_tip' => __('Public key generated for Google Pay (uncompressed base64 format).', 'woo-rede'),
                        'description' => __('This key will be sent to the front-end.', 'woo-rede'),
                        'default' => '',
                        'custom_attributes' => array(
                            'merge-top' => "woocommerce_{$this->id}_google_merchant_name",
                            'data-title-description' => esc_attr__('Base64 public key for Google Pay. This key will be used to encrypt card data on the front-end.', 'woo-rede')
                        ),
                    ),
                    'google_pay_private_key' => array(
                        'title' => __('Private Key (PEM)', 'woo-rede'),
                        'type' => 'textarea', // Private key is multi-line
                        'desc_tip' => __('Private key generated for Google Pay.', 'woo-rede'),
                        'description' => __('PEM format (-----BEGIN PRIVATE KEY-----). Never exposed on the front-end.', 'woo-rede'),
                        'default' => '',
                        'custom_attributes' => array(
                            'merge-top' => "woocommerce_{$this->id}_google_merchant_name",
                            'data-title-description' => esc_attr__('PEM private key for Google Pay. This key is used to decrypt card data on the back-end and should never be exposed.', 'woo-rede')
                        ),
                    ),
                    'google_pay_pro' => array(
                        'title' => esc_attr__('Pro Settings', 'woo-rede'),
                        'type' => 'title',
                    ),
                );
                
                $this->form_fields['convert_to_brl'] = array(
                    'title' => __('Currency Converter', 'woo-rede'),
                    'type' => 'checkbox',
                    'description' => __("Automatically converts payment amounts to BRL.", 'woo-rede'),
                    'desc_tip' => __('If enabled, automatically converts the order amount to BRL when processing payment.', 'woo-rede'),
                    'default' => 'no',
                    'custom_attributes' => array_merge(
                        array(
                            'data-title-description' => esc_attr__('When enabled, orders in other currencies will be automatically converted to BRL during payment processing.', 'woo-rede')
                        ),
                        !$proVersionActive ? array('lkn-is-pro' => 'true') : array()
                    )
                );
                $this->form_fields['payment_complete_status'] = array(
                    'title' => esc_attr__('Payment Complete Status', 'woo-rede'),
                    'type' => 'select',
                    'desc_tip' => esc_attr__('Choose the order status after successful payment.', 'woo-rede'),
                    'description' => esc_attr__('Choose the status that will be automatically attributed to the order when payment is confirmed.', 'woo-rede'),
                    'class' => 'wc-enhanced-select',
                    'default' => 'wc-processing',
                    'options' => LknIntegrationRedeForWoocommerceHelper::lknIntegrationRedeGetOrderStatus(),
                    'custom_attributes' => array_merge(
                        array(
                            'data-title-description' => esc_attr__('Select the order status that should be set after payment is successfully completed.', 'woo-rede')
                        ),
                        !$proVersionActive ? array('lkn-is-pro' => 'true') : array()
                    ),
                );
                $this->form_fields['auto_capture'] = array(
                    'title' => __('Automatic Capture', 'woo-rede'),
                    'type' => 'checkbox',
                    'label' => __('Enable automatic capture', 'woo-rede'),
                    'description' => __('When enabled, payments will be captured automatically. When disabled, you will need to manually capture payments.', 'woo-rede'),
                    'desc_tip' => __('Choose whether to automatically capture payments or require manual capture.', 'woo-rede'),
                    'default' => 'yes',
                    'custom_attributes' => array_merge(
                        array(
                            'data-title-description' => esc_attr__('For Google Pay, automatic capture is recommended to provide immediate payment confirmation.', 'woo-rede')
                        ),
                        !$proVersionActive ? array('lkn-is-pro' => 'true') : array()
                    )
                );

                $this->form_fields['developers'] = array(
                    'title' => esc_attr__('Developer', 'woo-rede'),
                    'type' => 'title',
                );
                $this->form_fields['debug'] = array(
                    'title' => esc_attr__('Debug mode', 'woo-rede'),
                    'type' => 'checkbox',
                    'desc_tip' => esc_attr__('Enable debug mode to log transaction details for troubleshooting.', 'woo-rede'),
                    'description' => esc_attr__('Save transaction data for debugging and analysis. Helps identify integration issues.', 'woo-rede'),
                    'custom_attributes' => array(
                        'data-title-description' => esc_attr__('When enabled, detailed transaction logs will be saved to help troubleshoot any payment issues.', 'woo-rede')
                    ),
                    'default' => 'no',
                );

                // Verificar se estamos salvando configurações para usar valores atuais
                $is_saving = isset($_POST['save']) && isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings');
                $current_debug_value = 'no';

                $proVersionActive = LknIntegrationRedeForWoocommerceHelper::isProLicenseValid();
                
                if ($is_saving) {
                    // Se estamos salvando, usar valores do POST
                    $debug_field = 'woocommerce_' . $this->id . '_debug';
                    $current_debug_value = isset($_POST[$debug_field]) ? sanitize_text_field(wp_unslash($_POST[$debug_field])) : 'no';
                } else {
                    // Se não estamos salvando, usar valores salvos
                    $current_debug_value = $this->get_option('debug', 'no');
                }

                if ($proVersionActive && ($current_debug_value === true || $current_debug_value == 1 || $current_debug_value == 'yes')) {
                    $this->form_fields['send_configs'] = array(
                        'title' => __('WhatsApp Support', 'woo-rede'),
                        'type'  => 'button',
                        'id'    => 'sendConfigs',
                        'description' => __('Enable Debug Mode and click Save Changes to get quick support via WhatsApp.', 'woo-rede'),
                        'desc_tip' => null,
                        'custom_attributes' => array(
                            'merge-top' => "woocommerce_{$this->id}_debug",
                            'data-title-description' => __('Send the settings for this payment method to WordPress Support.', 'woo-rede')
                        )
                    );
                }

                $this->form_fields['transactions'] = array(
                    'title' => esc_attr__('Transactions', 'woo-rede'),
                    'id' => 'transactions_title',
                    'type'  => 'title',
                );

                if ($proVersionActive) {

                    $licenseResult = base64_decode(get_option('lknRedeForWoocommerceProLicense', base64_encode('inactive')), true);
                    $licenseResult = ('active' === $licenseResult) ? true : false;
    
                    $license = $this->get_option('license');
                    if ( ! empty( $license ) ) {
                        $new_form_fields = array();
                        foreach ( $this->form_fields as $key => $field ) {
                            $new_form_fields[ $key ] = $field;
                            if ( $key === 'google_pay_pro' ) {
                                $new_form_fields['license'] = array(
                                    'title' => esc_attr__('License', 'woo-rede'),
                                    'type' => 'password',
                                    'description' => esc_attr__('License for Rede plugin extensions.', 'woo-rede'),
                                    'desc_tip' => esc_attr__('License for Rede for WooCommerce plugin extensions.', 'woo-rede'),
                                    'default' => '',
                                    'custom_attributes' => array(
                                        'data-title-description' => esc_attr__("Save to enable other options.", 'woo-rede')
                                    ),
                                );
                                $new_form_fields['validate_license'] = array(
                                    'title'       => __( 'Validate License', 'woo-rede' ),
                                    'type'        => 'button',
                                    'description' => __( 'Click the button to validate your license.', 'woo-rede' ),
                                    'desc_tip'    => __( 'Click the button to validate your license.', 'woo-rede' ),
                                    'id'          => 'validateLicense',
                                    'class'       => 'woocommerce-save-button components-button is-primary',
                                );
                            }
                        }
                        $this->form_fields = $new_form_fields;
                    }
    
                    wp_enqueue_script('lkn-wc-rede-pro-license-admin', REDE_FOR_WOOCOMMERCE_DIR_URL . '/Admin/js/LknRedeForWoocommerceProLicenseExpired.js', array('wp-i18n'), REDE_FOR_WOOCOMMERCE_PRO_VERSION, 'all');
                    // License check removed for FREE version
                    wp_localize_script(
                        'lkn-wc-rede-pro-license-admin',
                        'lknPhpAttr',
                        array(
                            'paymentId' => $this->id,
                            'licenseResult' => $licenseResult
                        )
                    );
                }
            }
        }
    }

    public function is_available(): bool
    {
        // FREE version - no license check
        $is_available = ('yes' === $this->get_option('enabled'));

        // Verificar se as credenciais básicas da Rede estão preenchidas
        if ($is_available) {
            $pv = $this->get_option('pv');
            $token = $this->get_option('token');
            
            if (empty($pv) || empty($token)) {
                $is_available = false;
            }
        }

        return $is_available;
    }

    /**
     * Renderiza o formulário de checkout do Google Pay (substitui payment_fields)
     *
     * @param float $order_total
     */
    protected function getCheckoutForm($order_total = 0)
    {
        // External script required for Google Pay integration.
        // This is the official Google Pay JS library and is necessary for payment processing.
        // plugin-check-ignore
        wp_enqueue_script(
            'google-pay-api',
            'https://pay.google.com/gp/p/js/pay.js',
            array(),
            INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION,
            true
        );

        wp_enqueue_style( 'woo-rede-google-pay-style-blocks', plugin_dir_url(__FILE__) . '../Public/css/rede/LknIntegrationRedeForWoocommerceGooglePay.css', array(), INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION, 'all' );

        // Script unificado para checkout clássico
        wp_enqueue_script(
            'rede-google-pay-for-shortcode',
            plugin_dir_url(__FILE__) . '../Public/js/googlePay/LknIntegrationRedeForWoocommerceGooglePayForShortcode.js',
            array('jquery', 'google-pay-api'),
            INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION,
            true
        );

        $license_valid = LknIntegrationRedeForWoocommerceHelper::isProLicenseValid() ? true : false;

        // Passar configurações e nonce para o JavaScript
        wp_localize_script('rede-google-pay-for-shortcode', 'redeGooglePayConfig', array(
            'environment' => $this->get_option('environment') === 'production' ? 'PRODUCTION' : 'TEST',
            'merchant_id' => $this->get_option('pv', 'rede_merchant'),
            'merchant_name' => get_bloginfo('name'),
            'total' => $order_total,
            'currency' => get_woocommerce_currency(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('google_pay_nonce'),
            'gateway_id' => 'rede_google_pay',
            'google_merchant_id' => $this->get_option('google_merchant_id', 'google_merchant'),
            'google_pay_public_key' => $this->get_option('google_pay_public_key', ''),
            'google_text_button' => $this->get_option('google_text_button', 'pay'),
            'license_valid' => $license_valid,
        ));

        // Incluir template com variáveis
        wc_get_template(
            '/googlePay/PaymentGooglePayFields.php',
            array(
                'description' => $this->get_option('description')
            ),
            'woocommerce/rede/',
            plugin_dir_path(__FILE__) . 'templates/'
        );
    }

    public function process_payment($orderId)
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            wc_add_notice(__('Order not found.', 'woo-rede'), 'error');
            return;
        }

        // ===== PROTEÇÃO: Bloqueia pagamento se o pedido já foi pago =====
        if ($order->is_paid() || $order->get_meta('_wc_rede_transaction_status') === 'completed') {
            $order->add_order_note(
                '[' . $this->id . '] ' .
                __('Duplicate order attempt.', 'woo-rede')
            );
            $order->save();
            throw new Exception(
                esc_html__('This order has already been paid. It is not possible to process the payment again.', 'woo-rede')
            );
        }
        // ===== FIM PROTEÇÃO =====

        $google_pay_signature = isset($_POST['google_pay_signature']) ? sanitize_text_field(wp_unslash($_POST['google_pay_signature'])) : '';
        $google_pay_signed_key = isset($_POST['google_pay_signed_key']) ? sanitize_textarea_field(wp_unslash($_POST['google_pay_signed_key'])) : '';
        $google_pay_signature_value = isset($_POST['google_pay_signature_value']) ? sanitize_textarea_field(wp_unslash($_POST['google_pay_signature_value'])) : '';
        $google_pay_protocol_version = isset($_POST['google_pay_protocol_version']) ? sanitize_text_field(wp_unslash($_POST['google_pay_protocol_version'])) : 'ECv2';
        $google_pay_encrypted_message = isset($_POST['google_pay_encrypted_message']) ? sanitize_textarea_field(wp_unslash($_POST['google_pay_encrypted_message'])) : '';
        $google_pay_ephemeral_public_key = isset($_POST['google_pay_ephemeral_public_key']) ? sanitize_textarea_field(wp_unslash($_POST['google_pay_ephemeral_public_key'])) : '';
        $google_pay_tag = isset($_POST['google_pay_tag']) ? sanitize_textarea_field(wp_unslash($_POST['google_pay_tag'])) : '';
        $google_pay_card_network = isset($_POST['google_pay_card_network']) ? sanitize_text_field(wp_unslash($_POST['google_pay_card_network'])) : 'VISA';
        $google_pay_funding_source = isset($_POST['google_pay_funding_source']) ? sanitize_text_field(wp_unslash($_POST['google_pay_funding_source'])) : 'CREDIT';
        
        if (empty($google_pay_signature) || empty($google_pay_encrypted_message)) {
            wc_add_notice(__('Google Pay token data is required.', 'woo-rede'), 'error');
            return;
        }

        try {
            $order_total = $order->get_total();
            $decimals = get_option('woocommerce_price_num_decimals', 2);
            $default_currency = get_option('woocommerce_currency', 'BRL');
            $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;

            // Check if BRL conversion is enabled
            $convert_to_brl_enabled = LknIntegrationRedeForWoocommerceHelper::is_convert_to_brl_enabled($this->id);

            // Convert order total to BRL if enabled
            if ($convert_to_brl_enabled) {
                $order_total = LknIntegrationRedeForWoocommerceHelper::convert_order_total_to_brl($order_total, $order, $convert_to_brl_enabled);
            }

            if ($convert_to_brl_enabled) {
                $order->add_order_note(
                    '[' . $this->id . '] ' . sprintf(
                        /* translators: %s: The original order currency code (e.g., USD, EUR) that was converted to BRL. */
                        __('Order currency %s converted to BRL.', 'woo-rede'),
                        $order_currency
                    )
                );
            }

            // Process Google Pay payment
            $result = $this->processGooglePayPayment($order, array(
                'signature' => $google_pay_signature,
                'signed_key' => $google_pay_signed_key, 
                'signature_value' => $google_pay_signature_value,
                'protocol_version' => $google_pay_protocol_version,
                'encrypted_message' => $google_pay_encrypted_message,
                'ephemeral_public_key' => $google_pay_ephemeral_public_key,
                'tag' => $google_pay_tag,
                'card_network' => $google_pay_card_network,
                'funding_source' => $google_pay_funding_source
            ), $order_total);

            if ($result && isset($result['success']) && $result['success']) {
                // Check if 3D Secure authentication is required
                if (isset($result['redirect_3ds']) && $result['redirect_3ds']) {
                    // 3DS authentication required - redirect to 3DS URL
                    return array(
                        'result' => 'success',
                        'redirect' => $result['threeds_url'],
                    );
                }
                
                // Update order status based on configuration
                $payment_complete_status = $this->get_option('payment_complete_status', 'processing');
                if (!empty($payment_complete_status) && $payment_complete_status !== $order->get_status()) {
                    $order->set_date_paid(current_time('timestamp', true));
                    $order->update_status($payment_complete_status);
                }

                // Add order note
                $order->add_order_note('[' . $this->id . '] ' . __('Payment completed via Google Pay', 'woo-rede'));

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            } else {
                $error_message = isset($result['message']) ? $result['message'] : __('Payment failed', 'woo-rede');
                /* translators: %s: The error message returned when a Google Pay payment fails. */
                $order->add_order_note('[' . $this->id . '] ' . sprintf(__('Google Pay payment failed: %s', 'woo-rede'), $error_message));
                wc_add_notice($error_message, 'error');
                return array(
                    'result' => 'failure',
                    'messages' => array($error_message)
                );
            }

        } catch (Exception $e) {
            /* translators: %s: The error message returned when a Google Pay payment fails. */
            $order->add_order_note('[' . $this->id . '] ' . sprintf(__('Google Pay payment error: %s', 'woo-rede'), $e->getMessage()));
            wc_add_notice(__('Payment processing failed. Please try again.', 'woo-rede'), 'error');
            return array(
                'result' => 'failure',
                'messages' => array(__('Payment processing failed. Please try again.', 'woo-rede'))
            );
        }
    }

    private function processGooglePayPayment($order, $google_pay_data, $total)
    {
        // 1. Get OAuth token
        $access_token = LknIntegrationRedeForWoocommerceHelper::get_rede_oauth_token_for_gateway($this->id, $order->get_id());

        if ($access_token === null) {
            return array('success' => false, 'message' => __('Failed to get OAuth token', 'woo-rede'));
        }

        // 2. Configurações Base
        $environment = $this->get_option('environment');
        $apiUrl = ('production' === $environment) 
            ? 'https://api.userede.com.br/erede/v2/transactions' 
            : 'https://sandbox-erede.useredecloud.com.br/v2/transactions';

        $totalCents = intval(round($total * 100));
        $auto_capture = $this->get_option('auto_capture', 'yes') === 'yes';

        // 3. Reconstrói o token a partir dos campos separados (agora sempre strings simples)
        // Os campos já chegam como strings simples do JavaScript, evitando corrupção pelo WordPress sanitization
        $signed_key_data = $this->decodeGooglePayField($google_pay_data['signed_key']);
        
        // Reconstrói o signedMessage a partir dos campos fragmentados
        $signed_message_data = array(
            'encryptedMessage' => $this->decodeGooglePayField($google_pay_data['encrypted_message']),
            'ephemeralPublicKey' => $this->decodeGooglePayField($google_pay_data['ephemeral_public_key']),
            'tag' => $this->decodeGooglePayField($google_pay_data['tag'])
        );
        
        $token_structure = array(
            'signature' => $this->decodeGooglePayField($google_pay_data['signature']),
            'intermediateSigningKey' => array(
                'signedKey' => $signed_key_data, // Agora sempre uma string simples
                'signatures' => array($this->decodeGooglePayField($google_pay_data['signature_value']))
            ),
            'protocolVersion' => $google_pay_data['protocol_version'],
            'signedMessage' => $signed_message_data // Agora é array reconstruído dos campos fragmentados
        );

        $card_network = $google_pay_data['card_network'];
        $card_funding_source = $google_pay_data['funding_source'];
        $encrypted_token_string = json_encode($token_structure);

        // 4. DESCRIPTOGRAFIA
        $decrypted_data = $this->decryptGooglePayToken($encrypted_token_string);

        $require_3ds = $this->get_option('require_3ds', 'no');

        if (!$decrypted_data) {
            return array('success' => false, 'message' => __('Failed to decrypt Google Pay token.', 'woo-rede'));
        }

        // Save card metadata for Google Pay transaction
        $this->saveCardMetas($order, array(
            'card_number' => $decrypted_data['dpan'],
            'card_expiration_month' => $decrypted_data['expirationMonth'],
            'card_expiration_year' => $decrypted_data['expirationYear'],
            'card_holder' => 'Google Pay User', // Google Pay doesn't provide cardholder name
            'card_type' => $decrypted_data['cardFundingSource'] // credit or debit
        ));

        // Save basic transaction metadata
        $order->update_meta_data('_wc_rede_transaction_environment', $environment);
        $order->update_meta_data('_wc_rede_transaction_reference', $order->get_order_number());
        $order->update_meta_data('_wc_rede_google_pay_installments', '1'); // Google Pay always 1 installment
        $order->save();

        // 5. Montagem do Payload para a e.Rede
        // Verificamos se existe criptograma (CRYPTOGRAM_3DS) ou se é cartão puro (PAN_ONLY)
        $cryptogram = !empty($decrypted_data['cryptogram']) ? trim($decrypted_data['cryptogram']) : null;

        $body = array(
            'capture' => $auto_capture,
            'kind'    => strtolower($card_funding_source),
            'reference' => $order->get_order_number(),
            'amount'    => $totalCents,
            // Caso seja o ambiente de teste e sem 3DS da Google Pay, utiliza o cartão da Rede pra simular o pagamento
            'cardNumber' => $require_3ds === 'no' && $environment === 'test' ? strtolower($card_funding_source) === 'credit' ? '5448280000000007' : '5277696455399733' : $decrypted_data['dpan'],
            'expirationMonth' => $decrypted_data['expirationMonth'],
            'expirationYear' => $decrypted_data['expirationYear'],
            'softDescriptor' => substr(get_bloginfo('name'), 0, 13)
        );

        // Lógica Condicional: Se houver criptograma, enviamos como Wallet (03 ou 04)
        // Se não houver (PAN_ONLY), enviamos como transação padrão (01) para evitar erro de tamanho de parâmetro
        if ($cryptogram) {
            $processing_type = in_array(strtoupper($card_network), array('ELO', 'AMEX')) ? '03' : '04';
            $body['tokenCryptogram'] = $cryptogram;
            $body['wallet'] = array(
                'processingType' => $processing_type,
                'walletId' => '52894351835',
                'walletCode' => 'GEP'
            );
        } else {
            // Para PAN_ONLY, processamos como uma venda digitada padrão
            $body['wallet'] = array(
                'processingType' => '01',
                'walletId' => 52894351835, // Sem aspas, como inteiro
            );
        }

        // Add 3D Secure configuration if enabled
        if ($require_3ds === 'yes') {
            $body['threeDSecure'] = array(
                'embedded' => true, // Direct integration on page (true) or redirect (false)
                'onFailure' => 'decline', // For Google Pay, always decline on 3DS failure
                'device' => array(
                    'colorDepth' => 24,
                    'deviceType3ds' => 'BROWSER',
                    'javaEnabled' => false,
                    'language' => 'pt-BR',
                    'screenHeight' => 500,
                    'screenWidth' => 500,
                    'timeZoneOffset' => 3
                ),
            );
            
            // Return URLs for 3D Secure authentication
            $success_return_url = home_url('/wp-json/woorede/s/');
            $failed_return_url = home_url('/wp-json/woorede/f/');

            $body['urls'] = array(
                array(
                    'kind' => 'threeDSecureSuccess',
                    'url' => $success_return_url
                ),
                array(
                    'kind' => 'threeDSecureFailure',
                    'url' => $failed_return_url
                )
            );
        }

        // 6. Requisição para a API da Rede
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
            'Transaction-Response' => 'brand-return-opened' // Header para indicar que a resposta da transação deve incluir a marca do cartão, necessário para Google Pay
        );
        
        $response = wp_remote_post($apiUrl, array(
            'method' => 'POST',
            'timeout' => 60,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        $response_code = wp_remote_retrieve_response_code($response);
        $response_data['return_http'] = $response_code;

        // 7. Salvamento de Metadados e Logs
        $default_currency = get_option('woocommerce_currency', 'BRL');
        $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
        
        LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                $order, 
                $response_data ?: array('error' => 'Invalid response'), 
                $decrypted_data['dpan'] ?? 'N/A', // paymentNumber - número do cartão
                ($decrypted_data['expirationMonth'] ?? 'N/A') . '/' . ($decrypted_data['expirationYear'] ?? 'N/A'), // cardExpShort
                'Google Pay User', // cardHolder - Google Pay não fornece o nome
                1, // installments - Google Pay sempre 1x
                $total, 
                $order_currency, 
                $card_network, // brand - bandeira do cartão
                $this->get_option('pv', 'N/A'), 
                $this->get_option('token', 'N/A'),
                $response_data['reference'] ?? 'N/A', 
                $order->get_id(), 
                $auto_capture, // capture
                ucfirst(strtolower($card_funding_source)) ?? 'N/A', // gatewayType
                '', // cvvField - Google Pay não usa CVV
                $this, 
                $response_data['tid'] ?? '',
                $response_data['nsu'] ?? '',
                $response_data['authorizationCode'] ?? '',
                $response_data['returnCode'] ?? $response_code,
                $response_data['returnMessage'] ?? ''
            );

        if ($this->get_option('debug') == 'yes') {
            if($require_3ds === 'no' && $environment === 'test') {
                $body['cardNumber'] = $decrypted_data['dpan'];
            }
            $order->update_meta_data('lknWcRedeGooglePayOrderLogs', wp_json_encode(array(
                'url' => $apiUrl,
                'body' => $body,
                'response' => $response_data,
                'response_code' => $response_code
            )));
            $order->save();
        }

        // 8. Tratamento do Retorno
        if ($response_code === 200 || $response_code === 201) {
            // Check if 3D Secure authentication is required
            if (isset($response_data['threeDSecure'])) {
                // 3DS authentication required - redirect customer
                $threeds_url = $response_data['threeDSecure']['url'] ?? '';
                if (!empty($threeds_url)) {
                    // Return success with redirect to 3DS authentication
                    return array(
                        'success' => true, 
                        'redirect_3ds' => true,
                        'threeds_url' => $threeds_url,
                        'message' => __('3D Secure authentication required. Redirecting...', 'woo-rede')
                    );
                }
            }
            
            $returnCode = $response_data['returnCode'] ?? '';
            
            // Standard transaction approval (without 3DS or after 3DS completion)
            if (in_array($returnCode, array('00', '000'))) {
                $order->update_meta_data('_wc_rede_transaction_id', $response_data['tid'] ?? '');
                $order->update_meta_data('_wc_rede_transaction_nsu', $response_data['nsu'] ?? '');
                $order->update_meta_data('_wc_rede_transaction_authorization_code', $response_data['authorizationCode'] ?? '');
                $order->update_meta_data('_wc_rede_transaction_return_code', $returnCode);
                $order->update_meta_data('_wc_rede_transaction_return_message', $response_data['returnMessage'] ?? 'Success');
                if (isset($response_data['brand']['name'])) {
                    if($environment === 'test') {
                        $order->update_meta_data('_wc_rede_transaction_card_brand', $card_network);
                    } else {
                        $order->update_meta_data('_wc_rede_transaction_card_brand', $response_data['brand']['name']);
                    }
                }
                $order->save();

                return array('success' => true, 'response' => $response_data);
            }
        }

        $returnCode = $response_data['returnCode'] ?? '';
        $returnMessage = $response_data['returnMessage'] ?? __('Payment processing failed', 'woo-rede');

        return array('success' => false, 'message' => LknIntegrationRedeForWoocommerceAbecsCodes::translate($returnCode, $returnMessage));
    }

    private function decryptGooglePayToken($encrypted_token_string)
    {
        $private_key_pem = $this->get_option('google_pay_private_key');
        if (empty($private_key_pem)) {
            return false;
        }

        $private_key_pem = str_replace(array("\r\n", "\r", "\n", "\\n"), "\n", trim($private_key_pem));

        try {
            $token_data = json_decode($encrypted_token_string, true);
            if (!$token_data) {
                return false;
            }
            
            // Verificar se signedMessage é string ou array
            $signed_message = $token_data['signedMessage'];
            if (is_string($signed_message)) {
                $signed_message = json_decode($signed_message, true);
            }
            
            if (!$signed_message || !is_array($signed_message)) {
                return false;
            }
            
            $encrypted_message = base64_decode(str_replace(array('-', '_'), array('+', '/'), $this->decodeGooglePayField($signed_message['encryptedMessage'])));
            $ephemeral_public_key = base64_decode(str_replace(array('-', '_'), array('+', '/'), $this->decodeGooglePayField($signed_message['ephemeralPublicKey'])));
            $tag = base64_decode(str_replace(array('-', '_'), array('+', '/'), $this->decodeGooglePayField($signed_message['tag'])));
            
            $asn1_header = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
            $ephemeral_pem = "-----BEGIN PUBLIC KEY-----\n" . 
                             chunk_split(base64_encode($asn1_header . $ephemeral_public_key), 64, "\n") . 
                             "-----END PUBLIC KEY-----\n";

            $shared_secret = openssl_pkey_derive($ephemeral_pem, $private_key_pem);
            if (!$shared_secret) {
                throw new Exception('Falha no ECDH.');
            }
            
            // O Segredo Compartilhado é sempre os 32 bytes da coordenada X
            $shared_secret = str_pad($shared_secret, 32, "\x00", STR_PAD_LEFT);

            // >>> ECIES Tink (Google ECv2) <<<
            // 65 bytes da chave + 32 bytes do segredo = 97 bytes de IKM
            $ikm = $ephemeral_public_key . $shared_secret;

            // MUDANÇA 1: O Google exige 64 bytes (512 bits) no total gerados pelo HKDF
            // Salt VAZIO, Info 'Google'
            $derived_keys = hash_hkdf('sha256', $ikm, 64, 'Google', '');
            
            // MUDANÇA 2: 32 bytes para o AES-256 e 32 bytes para o HMAC-SHA256
            $encryption_key = substr($derived_keys, 0, 32);
            $mac_key = substr($derived_keys, 32, 32);

            $calculated_tag = hash_hmac('sha256', $encrypted_message, $mac_key, true);
            if (!hash_equals($tag, $calculated_tag)) {
                throw new Exception('Verificação MAC falhou. O Google usou uma chave diferente.');
            }

            $iv = str_repeat("\x00", 16);
            
            // MUDANÇA 3: Descriptografar usando aes-256-ctr
            $decrypted_message = openssl_decrypt($encrypted_message, 'aes-256-ctr', $encryption_key, OPENSSL_RAW_DATA, $iv);
            
            $payload = json_decode($decrypted_message, true);
            if (!$payload || !isset($payload['paymentMethodDetails'])) {
                throw new Exception('Payload corrompido ou lixo gerado.');
            }

            $details = $payload['paymentMethodDetails'];
            
            return array(
                'dpan' => $details['pan'],
                'expirationMonth' => $details['expirationMonth'],
                'expirationYear' => $details['expirationYear'],
                'cryptogram' => $details['cryptogram'] ?? '',
                'cardFundingSource' => strtolower($details['cardFundingSource'] ?? 'credit')
            );

        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Helper function to check if a string is valid JSON
     */
    private function isJson($string) {
        if (!is_string($string)) return false;
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Decodifica campos do Google Pay que podem conter HTML entities
     */
    private function decodeGooglePayField($field) {
        if (!is_string($field)) return $field;
        
        // Decodifica HTML entities (u003d = =, etc.)
        $decoded = html_entity_decode($field, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Se ainda tem unicode escapes, decodifica também
        $decoded = json_decode('"' . str_replace('"', '\\"', $decoded) . '"');
        
        return $decoded ?: $field; // Se falhou a decodificação, retorna original
    }

    /**
     * Gera o par de chaves ECv2 (prime256v1) para o Google Pay
     * 
     * @param string $log_message Mensagem personalizada para o log
     * @return array|false Array com as chaves geradas ou false se houve erro
     */
    private function generateGooglePayKeys($log_message = 'Par de chaves ECv2 para o Google Pay gerado com sucesso.')
    {
        // 1. Configurar a Curva Elíptica exigida pelo Google Pay
        $config = array(
            "curve_name" => "prime256v1",
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        );

        // 2. Gerar o par de chaves
        $res = openssl_pkey_new($config);
        if (!$res) {
            $this->log->add($this->id, 'Erro ao gerar chaves OpenSSL. Verifique se a extensão OpenSSL do PHP está ativa.');
            return false;
        }

        // 3. Extrair a Chave Privada (formato PEM)
        openssl_pkey_export($res, $generated_private_key);

        // 4. Extrair a Chave Pública (formato PEM)
        $key_details = openssl_pkey_get_details($res);
        $public_key_pem = $key_details["key"];

        // 5. Converter a Chave Pública PEM para Raw Uncompressed Base64 (Formato Google)
        // Removemos os cabeçalhos PEM para pegar o miolo em Base64
        $pem_lines = explode("\n", trim($public_key_pem));
        unset($pem_lines[0]); // Remove o BEGIN
        unset($pem_lines[count($pem_lines)]); // Remove o END
        $der_data = base64_decode(implode('', $pem_lines));

        // A estrutura ASN.1 da prime256v1 tem um cabeçalho fixo de 26 bytes.
        // Os 65 bytes restantes são a chave descompactada (0x04 + X + Y) que o Google pede.
        $raw_public_key = substr($der_data, 26);
        $google_pay_public_base64 = base64_encode($raw_public_key);

        // 6. Salvar as chaves nas configurações do WooCommerce
        $this->update_option('google_pay_private_key', $generated_private_key);
        $this->update_option('google_pay_public_key', $google_pay_public_base64);

        // 7. Atualizar a memória da classe para uso imediato
        $this->settings['google_pay_private_key'] = $generated_private_key;
        $this->settings['google_pay_public_key'] = $google_pay_public_base64;
        
        // 8. Log de sucesso
        $this->log->add($this->id, $log_message);

        // 9. Retornar as chaves geradas
        return array(
            'public_key' => $google_pay_public_base64,
            'private_key' => $generated_private_key
        );
    }

    /**
     * Gera automaticamente o par de chaves ECv2 (prime256v1) se elas não existirem.
     * Facilita a vida do lojista, evitando que ele precise usar o terminal.
     */
    public function autoGenerateKeysIfNeeded()
    {
        $private_key = $this->get_option('google_pay_private_key');
        $public_key  = $this->get_option('google_pay_public_key');

        // Só gera se algum dos campos estiver vazio
        if (empty($private_key) || empty($public_key)) {
            $this->generateGooglePayKeys('Par de chaves ECv2 para o Google Pay gerado automaticamente com sucesso.');
        }
    }

    /**
     * Gera novas chaves ECv2 para o Google Pay (sempre força a geração)
     * 
     * @return array|false Array com as chaves geradas ou false se houve erro
     */
    public function autoGenerateNewKeys()
    {
        try {
            return $this->generateGooglePayKeys('Novas chaves ECv2 para o Google Pay geradas manualmente com sucesso.');
        } catch (Exception $e) {
            $this->log->add($this->id, 'Erro ao gerar novas chaves: ' . $e->getMessage());
            return false;
        }
    }

    public function displayMeta($order): void
    {
        if ($order->get_payment_method() == $this->id) {
            $isProValid = LknIntegrationRedeForWoocommerceHelper::isProLicenseValid();

            $metaKeys = array(
                '_wc_rede_transaction_environment' => esc_html__('Environment', 'woo-rede'),
                '_wc_rede_transaction_return_code' => esc_html__('Return code', 'woo-rede'),
                '_wc_rede_transaction_return_message' => esc_html__('Return message', 'woo-rede'),
                '_wc_rede_transaction_reference' => esc_html__('Reference', 'woo-rede'),
                '_wc_rede_transaction_id' => esc_html__('Transaction ID', 'woo-rede'),
                '_wc_rede_transaction_nsu' => esc_html__('NSU', 'woo-rede'),
                '_wc_rede_transaction_authorization_code' => esc_html__('Authorization code', 'woo-rede'),
                '_wc_rede_transaction_bin' => esc_html__('BIN', 'woo-rede'),
                '_wc_rede_card_type' => esc_html__('Card type', 'woo-rede'),
                '_wc_rede_transaction_last4' => esc_html__('Last 4 digits', 'woo-rede'),
                '_wc_rede_google_pay_installments' => esc_html__('Installments', 'woo-rede'),
                '_wc_rede_transaction_holder' => esc_html__('Card holder', 'woo-rede'),
                '_wc_rede_transaction_expiration' => esc_html__('Expiration date', 'woo-rede'),
                '_wc_rede_transaction_card_brand' => esc_html__('Card brand', 'woo-rede'),
            );

            // Usar método personalizado ou padrão baseado na licença PRO
            if ($isProValid) {
                $this->generateMetaTableWithBrandIcon($order, $metaKeys, $this->title);
            } else {
                $this->generateMetaTable($order, $metaKeys, 'Rede');
            }

        }
    }

    /**
     * Método personalizado para exibir metadados com ícone da bandeira
     */
    private function generateMetaTableWithBrandIcon($order, $metaKeys, $title): void
    {
        ?>
        <h3 style="margin-bottom: 14px;"><?php echo esc_html($title); ?></h3>
        <table>
            <tbody>
                <?php
                foreach ($metaKeys as $meta_key => $label) {
                    $meta_value = $order->get_meta($meta_key);
                    if (! empty($meta_value)) :
                        // Se for o campo da bandeira, formatar com ícone
                        if ($meta_key === '_wc_rede_transaction_card_brand') {
                            $meta_value = $this->formatBrandWithIcon($meta_value);
                        } else {
                            $meta_value = esc_attr($meta_value);
                        }
                ?>
                        <tr>
                            <td style="color: #555; font-weight: bold;"><?php echo esc_attr($label); ?>:</td>
                            <td><?php echo wp_kses_post($meta_value); ?></td>
                        </tr>
                <?php
                    endif;
                }
                ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Formata o nome da bandeira com ícone de imagem (apenas para licença PRO)
     */
    private function formatBrandWithIcon($brandName): string
    {
        if (empty($brandName)) {
            return '';
        }

        // Verificar se tem licença PRO válida
        if (!LknIntegrationRedeForWoocommerceHelper::isProLicenseValid()) {
            // Modo padrão: apenas retorna o nome da bandeira sem formatação
            return esc_html($brandName);
        }

        // Modo PRO: aplicar formatação com ícone
        // Normalizar o nome da bandeira (tudo minúsculo para comparação)
        $normalizedBrand = strtolower(trim($brandName));
        
        // Mapear variações de bandeiras para arquivos de imagem
        $brandMappings = array(
            'visa' => array('visa', 'visa electron', 'visa debit', 'visa credit'),
            'mastercard' => array('mastercard', 'master', 'master card'),
            'amex' => array('american express', 'amex', 'american', 'express'),
            'elo' => array('elo', 'elo credit', 'elo debit'),
            'hipercard' => array('hipercard', 'hiper', 'hiper card'),
            'diners' => array('diners club', 'diners', 'dinners club'),
            'discover' => array('discover', 'discover card'),
            'jcb' => array('jcb', 'jcb card'),
            'aura' => array('aura', 'aura card'),
            'paypal' => array('paypal', 'pay pal')
        );

        // Buscar qual bandeira corresponde ao nome recebido
        $detectedBrand = 'other';
        $displayName = ucfirst($normalizedBrand);
        
        foreach ($brandMappings as $brandKey => $variations) {
            foreach ($variations as $variation) {
                // Verifica se o nome contém a variação ou vice-versa
                if (strpos($normalizedBrand, $variation) !== false || strpos($variation, $normalizedBrand) !== false) {
                    $detectedBrand = $brandKey;
                    $displayName = ucfirst($brandKey);
                    break 2; // Sair dos dois loops
                }
            }
        }

        // Definir o caminho base das imagens
        $imagesPath = plugin_dir_url(INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE) . 'Includes/assets/cardBrands/';
        
        // Nome do arquivo de imagem
        $imageName = $detectedBrand . '.webp';
        
        $imageHtml = sprintf(
            '<img src="%s" alt="%s" style="width: 20px; height: auto; margin-right: 5px; vertical-align: middle;" /> %s',
            esc_url($imagesPath . $imageName),
            esc_attr($displayName),
            esc_html($brandName) // Mantém o nome original da bandeira para exibição
        );
        
        return $imageHtml;
    }

    /**
     * Save censored card metadata for Google Pay transactions
     *
     * @param WC_Order $order
     * @param array $cardData
     */
    private function saveCardMetas($order, $cardData)
    {
        // Dados do cartão censurados
        if (isset($cardData['card_number'])) {
            // Censurar número do cartão - mostrar primeiros 4 e últimos 4 dígitos
            $card_number = $cardData['card_number'];
            $censored_number = substr($card_number, 0, 4) . str_repeat('*', strlen($card_number) - 8) . substr($card_number, -4);
            $order->update_meta_data('_wc_rede_transaction_card_number', $censored_number);
            
            // Extrair e salvar os últimos 4 dígitos separadamente
            $order->update_meta_data('_wc_rede_transaction_last4', substr($card_number, -4));
            
            // Extrair e salvar o BIN (primeiros 6 dígitos)
            $order->update_meta_data('_wc_rede_transaction_bin', substr($card_number, 0, 6));
        }
        
        if (isset($cardData['card_expiration_month'])) {
            // Censurar mês - mostrar apenas o último dígito
            $month = $cardData['card_expiration_month'];
            $order->update_meta_data('_wc_rede_transaction_expiration_month', $month);
        }
        
        if (isset($cardData['card_expiration_year'])) {
            // Censurar ano - mostrar apenas os últimos 2 dígitos
            $year = $cardData['card_expiration_year'];
            $order->update_meta_data('_wc_rede_transaction_expiration_year', $year);
            
            // Salvar data de expiração completa censurada
            $month = isset($cardData['card_expiration_month']) ? $cardData['card_expiration_month'] : '';
            $order->update_meta_data('_wc_rede_transaction_expiration', $month . '/' . $year);
        }
        
        if (isset($cardData['card_cvv'])) {
            // Censurar CVV - mostrar apenas o último dígito
            $cvv = $cardData['card_cvv'];
            $censored_cvv = str_repeat('*', strlen($cvv) - 1) . substr($cvv, -1);
            $order->update_meta_data('_wc_rede_transaction_cvv', $censored_cvv);
        }
        
        if (isset($cardData['card_holder'])) {
            $order->update_meta_data('_wc_rede_transaction_holder', $cardData['card_holder']);
        }
        
        if (isset($cardData['card_type'])) {
            $order->update_meta_data('_wc_rede_card_type', $cardData['card_type']);
        }
        
        $order->save();
    }
}