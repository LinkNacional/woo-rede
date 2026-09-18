<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceWcRedeDebit;

final class LknIntegrationRedeForWoocommerceWcRedeDebitBlocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 'rede_debit';

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_rede_debit_settings', array());
        $this->gateway = new LknIntegrationRedeForWoocommerceWcRedeDebit();
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {
        // Registra o CSS do template moderno apenas quando o estilo efetivo é "modern"
        // (recurso PRO — sem licença ativa get3dsTemplateStyle() força "basic").
        if (LknIntegrationRedeForWoocommerceHelper::get3dsTemplateStyle($this->name) === 'modern') {
            wp_enqueue_style(
                'rede-modern-template-style',
                plugin_dir_url(__FILE__) . '../Public/css/rede/LknIntegrationRedeForWoocommerceModernTemplate.css',
                array(),
                '1.0.0',
                'all'
            );
        }
        
        wp_register_script(
            'rede_debit-blocks-integration',
            plugin_dir_url(__FILE__) . '../Public/js/debitCard/rede/lknIntegrationRedeForWoocommerceCheckoutCompiled.js',
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ),
            '1.0.0',
            true
        );
        wp_localize_script(
            'rede_debit-blocks-integration',
            'redeDebitAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('redeCardNonce'),
                'installment_nonce' => wp_create_nonce('rede_debit_payment_fields_nonce'),
                'bin_detection_nonce' => wp_create_nonce('redeCardNonce'),
                'cardTemplateAssets' => array(
                    'calendar' => plugin_dir_url(__FILE__) . 'assets/cardTemplate/calendar.svg',
                    'key' => plugin_dir_url(__FILE__) . 'assets/cardTemplate/key.svg',
                    'lock' => plugin_dir_url(__FILE__) . 'assets/cardTemplate/lock.svg',
                    'amex' => plugin_dir_url(__FILE__) . 'assets/cardTemplate/amex-icon.svg',
                    'elo' => plugin_dir_url(__FILE__) . 'assets/cardTemplate/elo-icon.svg',
                    'mastercard' => plugin_dir_url(__FILE__) . 'assets/cardTemplate/mastercard-icon.svg',
                    'visa' => plugin_dir_url(__FILE__) . 'assets/cardTemplate/visa-icon.svg',
                    'otherCard' => plugin_dir_url(__FILE__) . 'assets/cardTemplate/other-card.svg',
                ),
                'completeOrder' => __('Complete Order', 'woo-rede')
            )
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('rede_debit-blocks-integration');
        }

        apply_filters('integration_rede_for_woocommerce_set_custom_css', get_option('woocommerce_rede_debit_settings')['custom_css_block_editor'] ?? false);
        return array('rede_debit-blocks-integration');
    }

    public function get_payment_method_data()
    {
        $cart_total = LknIntegrationRedeForWoocommerceHelper::getCartTotal();

        return array(
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'nonceRedeDebit' => wp_create_nonce('redeCardNonce'),
            'minInstallmentsRede' => $this->gateway->get_option('min_parcels_value', '5'),
            'cartTotal' => $cart_total,
            'cardTypeRestriction' => LknIntegrationRedeForWoocommerceHelper::getCardTypeRestriction($this->gateway->id),
            'hideCardTypeSelector' => LknIntegrationRedeForWoocommerceHelper::isHideCardTypeSelectorEnabled($this->name) ? 'yes' : 'no',
            'maxParcels' => $this->gateway->get_option('max_parcels_number', '12'),
            'minParcelsValue' => $this->gateway->get_option('min_parcels_value', '5'),
            '3dsTemplateStyle' => LknIntegrationRedeForWoocommerceHelper::get3dsTemplateStyle($this->name),
            'gatewayDescription' => $this->gateway->get_option('description', __('Pay for your purchase with a debit card through', 'woo-rede')),
            'translations' => array(
                'fieldsNotFilled' => __('Please fill in all fields correctly.', 'woo-rede'),
                'cardNumber' => __('Card Number', 'woo-rede'),
                'cardExpiringDate' => __('Card Expiring Date', 'woo-rede'),
                'securityCode' => __('Security Code', 'woo-rede'),
                'nameOnCard' => __('Name on Card', 'woo-rede'),
                'cardType' => __('Card Type', 'woo-rede'),
                'debitCard' => __('Debit Card', 'woo-rede'),
                'creditCard' => __('Credit Card', 'woo-rede'),
                'installments' => __('Installments', 'woo-rede'),
            )
        );
    }
}
