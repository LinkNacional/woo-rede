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
        // Registra o CSS do template moderno/compacto apenas quando o estilo efetivo
        // é "modern"/"compact" (recurso PRO — sem licença ativa get3dsTemplateStyle()
        // força "basic").
        // Versão por filemtime: garante que alterações no CSS carreguem sem hard
        // refresh (a versão fixa anterior deixava o browser em cache).
        $rede_css_dir = plugin_dir_path(__FILE__) . '../Public/css/rede/';
        $rede_css_ver = function ($file) use ($rede_css_dir) {
            $path = $rede_css_dir . $file;
            return '1.0.0.' . (file_exists($path) ? filemtime($path) : '0');
        };

        $lkn_template_style = LknIntegrationRedeForWoocommerceHelper::get3dsTemplateStyle($this->name);
        if ('modern' === $lkn_template_style) {
            wp_enqueue_style(
                'rede-modern-template-style',
                plugin_dir_url(__FILE__) . '../Public/css/rede/LknIntegrationRedeForWoocommerceModernTemplate.css',
                array(),
                $rede_css_ver('LknIntegrationRedeForWoocommerceModernTemplate.css'),
                'all'
            );
        } elseif ('compact' === $lkn_template_style) {
            wp_enqueue_style(
                'rede-compact-template-style',
                plugin_dir_url(__FILE__) . '../Public/css/rede/LknIntegrationRedeForWoocommerceCompactTemplate.css',
                array(),
                $rede_css_ver('LknIntegrationRedeForWoocommerceCompactTemplate.css'),
                'all'
            );
        } else {
            // Layout Basic (padrão): normaliza campos/selects do Basic em Blocos
            // (50px + borda 8px), que não tinham CSS próprio do Rede.
            wp_enqueue_style(
                'rede-debit-basic-blocks-style',
                plugin_dir_url(__FILE__) . '../Public/css/rede/LknIntegrationRedeForWoocommerceDebitBasicBlocks.css',
                array(),
                $rede_css_ver('LknIntegrationRedeForWoocommerceDebitBasicBlocks.css'),
                'all'
            );
        }
        
        // Padronização dos campos de cartão (número/validade/CVC) no checkout em Blocos.
        wp_enqueue_script('rede-card-fields', plugin_dir_url(INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE) . '/Public/js/rede-card-fields.js', array(), '1.0.0', true);
        wp_enqueue_script('rede-card-fields-blocks', plugin_dir_url(INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE) . '/Public/js/rede-card-fields-blocks.js', array('rede-card-fields'), '1.0.0', true);
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
                'completeOrder' => LknIntegrationRedeForWoocommerceHelper::getFieldLabel($this->gateway->id, LknIntegrationRedeForWoocommerceHelper::getActiveCheckoutTemplate($this->name), 'button', 'blocks')
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

        // Labels/placeholders personalizados (seção "Fields") do layout ativo.
        $lkn_active_tpl = LknIntegrationRedeForWoocommerceHelper::getActiveCheckoutTemplate($this->name);
        $lkn_label = function ($field) use ($lkn_active_tpl) {
            return LknIntegrationRedeForWoocommerceHelper::getFieldLabel($this->gateway->id, $lkn_active_tpl, $field, 'blocks');
        };
        $lkn_ph = function ($field) use ($lkn_active_tpl) {
            return LknIntegrationRedeForWoocommerceHelper::getFieldPlaceholder($this->gateway->id, $lkn_active_tpl, $field, 'blocks');
        };

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
            // Botão de finalizar custom = recurso PRO (controla a renderização do
            // botão no layout padrão do checkout em Blocos).
            'isProValid' => LknIntegrationRedeForWoocommerceHelper::isProLicenseValid(),
            // Cartão animado (grátis) e bandeiras (PRO) — default ligados.
            'showCardAnimation' => $this->gateway->get_option('show_card_animation', 'yes'),
            'showCardBrandIcons' => $this->gateway->get_option('show_card_brand_icons', 'yes'),
            'gatewayDescription' => $this->gateway->get_option('description', __('Pay for your purchase with a debit card through', 'woo-rede')),
            'fieldPlaceholders' => array(
                'holder_name' => $lkn_ph('holder_name'),
                'card_number' => $lkn_ph('card_number'),
                'expiry'      => $lkn_ph('expiry'),
                'cvc'         => $lkn_ph('cvc'),
            ),
            'translations' => array(
                'fieldsNotFilled' => __('Please fill in all fields correctly.', 'woo-rede'),
                'cardNumber' => $lkn_label('card_number'),
                'cardExpiringDate' => $lkn_label('expiry'),
                'securityCode' => $lkn_label('cvc'),
                'nameOnCard' => $lkn_label('holder_name'),
                'cardType' => $lkn_label('card_type'),
                'debitCard' => __('Debit Card', 'woo-rede'),
                'creditCard' => __('Credit Card', 'woo-rede'),
                'installments' => $lkn_label('installments'),
            )
        );
    }
}
