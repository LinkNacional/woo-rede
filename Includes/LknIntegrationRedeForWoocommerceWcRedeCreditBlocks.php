<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceWcRedeCredit;

final class LknIntegrationRedeForWoocommerceWcRedeCreditBlocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 'rede_credit';

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_rede_credit_settings', array());
        $this->gateway = new LknIntegrationRedeForWoocommerceWcRedeCredit();
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {
        wp_enqueue_style('select-style', plugin_dir_url(INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE) . '/Public/css/lknIntegrationRedeForWoocommerceSelectStyle.css', array(), '1.0.0', 'all');
        // Padronização dos campos de cartão (número/validade/CVC) no checkout em Blocos.
        wp_enqueue_script('rede-card-fields', plugin_dir_url(INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE) . '/Public/js/rede-card-fields.js', array(), '1.0.0', true);
        wp_enqueue_script('rede-card-fields-blocks', plugin_dir_url(INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE) . '/Public/js/rede-card-fields-blocks.js', array('rede-card-fields'), '1.0.0', true);
        wp_register_script(
            'rede_credit-blocks-integration',
            plugin_dir_url(__FILE__) . '../Public/js/creditCard/rede/lknIntegrationRedeForWoocommerceCheckoutCompiled.js',
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
            'rede_credit-blocks-integration',
            'redeCreditAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('redeCardNonce'),
                'installment_nonce' => wp_create_nonce('rede_payment_fields_nonce')
            )
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('rede_credit-blocks-integration');
        }
        
        apply_filters('integration_rede_for_woocommerce_set_custom_css', get_option('woocommerce_rede_credit_settings')['custom_css_block_editor'] ?? false);

        return array('rede_credit-blocks-integration');
    }

    public function get_payment_method_data()
    {
        $cart_total = LknIntegrationRedeForWoocommerceHelper::getCartTotal();
        $settings = get_option('woocommerce_rede_credit_settings');
        $maxParcels = $settings['max_parcels_number'];
        $minParcelValue = (float) $settings['min_parcels_value'];

        $phpArray = array(
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'nonceRedeCredit' => wp_create_nonce('redeCardNonce'),
            'minInstallmentsRede' => get_option('woocommerce_rede_credit_settings')['min_parcels_value'],
            'maxInstallmentsRede' => $maxParcels,
            'cartTotal' => $cart_total,
            'translations' => array(
                'fieldsNotFilled' => __('Please fill in all fields correctly.', 'woo-rede'),
                'cardNumber' => __('Card Number', 'woo-rede'),
                'cardExpiringDate' => __('Card Expiring Date', 'woo-rede'),
                'securityCode' => __('Security Code', 'woo-rede'),
                'nameOnCard' => __('Name on Card', 'woo-rede'),
                'installments' => __('Installments', 'woo-rede'),
                'interestFree' => ' ' . __('interest-free', 'woo-rede'),
            )
        );

        if (
            isset($settings['installment_interest']) &&
            ($settings['installment_interest'] === 'yes' || $settings['installment_discount']) &&
            is_plugin_active('rede-for-woocommerce-pro/rede-for-woocommerce-pro.php')
        ) {
            for ($i = 1; $i <= $maxParcels; ++$i) {
                $parcelAmount = $cart_total / $i;
                if (($i === 1 || $parcelAmount >= $minParcelValue) && isset($settings[$i . 'x'])) {
                    $interest = round((float) $settings[$i . 'x'], 2);
                    $customLabel = LknIntegrationRedeForWoocommerceHelper::lknIntegrationRedeProRedeInterest($cart_total, $interest, $i, 'label', $this->gateway);
                    if ($customLabel) {
                        $phpArray[$i . 'x'] = $customLabel;
                    }
                }
            }
        } else {
            for ($i = 1; $i <= $maxParcels; ++$i) {
                $parcelAmount = $cart_total / $i;
                if ($i === 1 || $parcelAmount >= $minParcelValue) {
                    /* translators: %1$d: number of installments, %2$s: installment price */
                    $phpArray[$i . 'x'] = html_entity_decode(sprintf(__('%dx of %s', 'woo-rede'), $i, wp_strip_all_tags(wc_price($parcelAmount))));
                }
            }
        }
        return $phpArray;
    }
}
