<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use Exception;
use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceWcRedeAbstract;
use Symfony\Component\Console\Event\ConsoleEvent;
use WC_Order;
use WP_Error;

final class LknIntegrationRedeForWoocommerceWcRedeCredit extends LknIntegrationRedeForWoocommerceWcRedeAbstract
{
    public function __construct()
    {
        $this->id = 'rede_credit';
        $this->has_fields = true;
        $this->method_title = esc_attr__('Pay with the Rede Credit', 'woo-rede');
        $this->method_description = esc_attr__('Enables and configures payments with Rede Credit', 'woo-rede');
        $this->supports = array(
            'products',
            'refunds',
        );

        $this->icon = LknIntegrationRedeForWoocommerceHelper::getUrlIcon();

        $this->initFormFields();

        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->environment = $this->get_option('environment');
        $this->pv = $this->get_option('pv');
        $this->token = $this->get_option('token');

        if ($this->get_option('enabled_soft_descriptor') === 'yes') {
            $this->soft_descriptor = preg_replace('/\W/', '', $this->get_option('soft_descriptor'));
        } elseif ($this->get_option('enabled_soft_descriptor') === 'no') {
            if (get_option('lknIntegrationRedeForWoocommerceSoftDescriptorErrorCredit')) {
                update_option('lknIntegrationRedeForWoocommerceSoftDescriptorErrorCredit', false);
            }
        }

        // Auto capture com validação PRO - se não tiver licença PRO válida, força auto_capture como true
        $auto_capture_option = $this->get_option('auto_capture');
        if (!LknIntegrationRedeForWoocommerceHelper::isProLicenseValid()) {
            // Se não tiver licença PRO válida, força auto_capture como 'yes' (true)
            $auto_capture_option = 'yes';
            // Persiste a alteração na base de dados
            $settings = get_option('woocommerce_rede_credit_settings', array());
            $settings['auto_capture'] = 'yes';
            update_option('woocommerce_rede_credit_settings', $settings);
        }
        // Auto capture com validação PRO - se não tiver licença PRO válida, força auto_capture como true
        $auto_capture_option = $this->get_option('auto_capture');
        if (!LknIntegrationRedeForWoocommerceHelper::isProLicenseValid()) {
            // Se não tiver licença PRO válida, força auto_capture como 'yes' (true)
            $auto_capture_option = 'yes';
            // Persiste a alteração na base de dados
            $settings = get_option('woocommerce_rede_credit_settings', array());
            $settings['auto_capture'] = 'yes';
            update_option('woocommerce_rede_credit_settings', $settings);
        }
        $this->auto_capture = sanitize_text_field($auto_capture_option) === 'no' ? false : true;
        $this->max_parcels_number = $this->get_option('max_parcels_number');
        $this->min_parcels_value = $this->get_option('min_parcels_value');

        $this->partner_module = $this->get_option('module');
        $this->partner_gateway = $this->get_option('gateway');

        $this->debug = $this->get_option('debug');

        $this->log = $this->get_logger();

        $this->configs = $this->getConfigsRedeCredit();
    }

    /**
     * Fields validation.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if (empty($_POST['rede_credit_number'])) {
            wc_add_notice(esc_attr__('Card number is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (empty($_POST['rede_credit_expiry'])) {
            wc_add_notice(esc_attr__('Card expiration is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (empty($_POST['rede_credit_cvc'])) {
            wc_add_notice(esc_attr__('Card security code is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (! ctype_digit(sanitize_text_field(wp_unslash($_POST['rede_credit_cvc'])))) {
            wc_add_notice(esc_attr__('Card security code must be a numeric value', 'woo-rede'), 'error');
            return false;
        }

        if (strlen(sanitize_text_field(wp_unslash($_POST['rede_credit_cvc']))) < 3) {
            wc_add_notice(esc_attr__('Card security code must be at least 3 digits long', 'woo-rede'), 'error');
            return false;
        }

        if (empty($_POST['rede_credit_holder_name'])) {
            wc_add_notice(esc_attr__('Cardholder name is a required field', 'woo-rede'), 'error');

            return false;
        }

        return true;
    }

    public function getConfigsRedeCredit()
    {
        $configs = array();

        $configs['basePath'] = INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/logs/';
        $configs['base'] = $configs['basePath'] . gmdate('d.m.Y-H.i.s') . '.RedeCredit.log';
        $configs['debug'] = $this->get_option('debug');

        return $configs;
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

    public function displayMeta($order): void
    {
        if ($order->get_payment_method() === 'rede_credit') {
            // Verificar licença PRO no topo para múltiplas verificações
            $isProValid = LknIntegrationRedeForWoocommerceHelper::isProLicenseValid();
            
            // Tentar preencher metadados faltantes usando TID se disponível
            $tid = $order->get_meta('_wc_rede_transaction_id');
            if (!empty($tid)) {
                // Verificar se brand ou reference estão faltando
                $missing_brand = empty($order->get_meta('_wc_rede_transaction_brand'));
                $missing_reference = empty($order->get_meta('_wc_rede_transaction_reference'));
                
                if ($missing_brand || $missing_reference) {
                    // Buscar dados completos da transação e preencher metadados faltantes
                    LknIntegrationRedeForWoocommerceHelper::getTransactionCompleteData($tid, $this, $order);
                }
            }
            
            $metaKeys = array(
                '_wc_rede_transaction_environment' => esc_attr__('Environment', 'woo-rede'),
                '_wc_rede_transaction_return_code' => esc_attr__('Return Code', 'woo-rede'),
                '_wc_rede_transaction_return_message' => esc_attr__('Return Message', 'woo-rede'),
                '_wc_rede_transaction_reference' => esc_attr__('Reference', 'woo-rede'),
                '_wc_rede_transaction_id' => esc_attr__('Transaction ID', 'woo-rede'),
                '_wc_rede_transaction_refund_id' => esc_attr__('Refund ID', 'woo-rede'),
                '_wc_rede_transaction_cancel_id' => esc_attr__('Cancellation ID', 'woo-rede'),
                '_wc_rede_transaction_nsu' => esc_attr__('Nsu', 'woo-rede'),
                '_wc_rede_transaction_authorization_code' => esc_attr__('Authorization Code', 'woo-rede'),
                '_wc_rede_transaction_bin' => esc_attr__('Bin', 'woo-rede'),
                '_wc_rede_transaction_last4' => esc_attr__('Last 4', 'woo-rede'),
                '_wc_rede_transaction_installments' => esc_attr__('Installments', 'woo-rede'),
                '_wc_rede_transaction_holder' => esc_attr__('Cardholder', 'woo-rede'),
                '_wc_rede_transaction_expiration' => esc_attr__('Card Expiration', 'woo-rede')
            );

            // Adicionar campo da bandeira apenas na versão PRO
            if ($isProValid) {
                $metaKeys['_wc_rede_transaction_brand'] = esc_attr__('Brand', 'woo-rede');
            }

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
                        if ($meta_key === '_wc_rede_transaction_brand') {
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

    public function initFormFields(): void
    {
        $options = get_option('woocommerce_rede_credit', array());
        LknIntegrationRedeForWoocommerceHelper::updateFixLoadScriptOption($this->id);

        $this->form_fields = array(
            'rede' => array(
                'title' => esc_attr__('General', 'woo-rede'),
                'type' => 'title',
            ),
            'enabled' => array(
                'title' => esc_attr__('Enable/Disable', 'woo-rede'),
                'type' => 'checkbox',
                'label' => esc_attr__('Enables payment with Rede', 'woo-rede'),
                'default' => $options['enabled'] ?? 'no',
                'desc_tip'    => esc_attr__('Check this box and save to enable credit card settings.', 'woo-rede'),
                // TODO Fix: component out of the scope
                // 'description' => esc_attr__('Enable or disable the credit card payment method.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Enable this option to allow customers to pay with credit cards using Rede API.', 'woo-rede')
                )
            ),
            'title' => array(
                'title' => esc_attr__('Title', 'woo-rede'),
                'type' => 'text',
                'default' => esc_attr__('Pay with the Rede Credit', 'woo-rede'),
                'desc_tip' => esc_attr__('Enter the title that will be shown to customers during the checkout process.', 'woo-rede'),
                'description' => esc_attr__('This controls the title which the user sees during checkout.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('This text will appear as the payment method title during checkout. Choose something your customers will easily understand, like “Pay with credit card (Rede)”.', 'woo-rede')
                )

            ),
            'description' => array(
                'title' => esc_attr__('Description', 'woo-rede'),
                'type' => 'textarea',
                'default' => esc_attr__('Pay for your purchase with a credit card through ', 'woo-rede'),
                'desc_tip' => esc_attr__('This description appears below the payment method title at checkout. Use it to inform your customers about the payment processing details.', 'woo-rede'),
                'description' => esc_attr__('Payment method description that the customer will see on your checkout.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Provide a brief message that informs the customer how the payment will be processed. For example: “Your payment will be securely processed by Rede.”', 'woo-rede')
                )
            ),
            'rede' => array(
                'title' => esc_attr__('General', 'woo-rede'),
                'type' => 'title',
            ),
            'environment' => array(
                'title' => esc_attr__('Environment', 'woo-rede'),
                'type' => 'select',
                'description' => esc_attr__('Choose the environment', 'woo-rede'),
                'desc_tip' => esc_attr__('Choose between production or development mode for Rede API.', 'woo-rede'),
                'class' => 'wc-enhanced-select',
                'default' => esc_attr__('test', 'woo-rede'),
                'options' => array(
                    'test' => esc_attr__('Tests', 'woo-rede'),
                    'production' => esc_attr__('Production', 'woo-rede'),
                ),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Select "Tests" to test transactions in sandbox mode. Use "Production" for real transactions.', 'woo-rede')
                )
            ),
            'pv' => array(
                'title' => esc_attr__('PV', 'woo-rede'),
                'type' => 'password',
                'description' => esc_attr__('Rede credentials.', 'woo-rede'),
                'desc_tip' => esc_attr__('Your Rede PV (affiliation number).', 'woo-rede'),
                'default' => $options['pv'] ?? '',
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Your Rede PV (affiliation number) should be provided here.', 'woo-rede')
                )
            ),
            'token' => array(
                'title' => esc_attr__('Token', 'woo-rede'),
                'type' => 'password',
                'description' => esc_attr__('Rede credentials.', 'woo-rede'),
                'desc_tip' => esc_attr__('Your Rede Token.', 'woo-rede'),
                'default' => $options['token'] ?? '',
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Your Rede Token should be placed here.', 'woo-rede')
                )
            ),

            'enabled_soft_descriptor' => array(
                'title' => __('Payment Description', 'woo-rede'),
                'type' => 'checkbox',
                'description' => esc_attr__('Enable sending the payment description to Rede.', 'woo-rede'),
                'desc_tip' => esc_attr__('Send payment description to the Rede. If errors occur, disable this option to ensure correct transaction processing.', 'woo-rede'),
                'label' => __('I have enabled the payment description feature in the', 'woo-rede') . ' ' . wp_kses_post('<a href="' . esc_url('https://meu.userede.com.br/ecommerce/identificacao-fatura') . '" target="_blank">' . __('Rede Dashboard', 'woo-rede') . '</a>') . '. ' . __('Default (Disabled)', 'woo-rede'),
                'default' => 'no',
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Send payment description to Rede. Disable if it causes errors.', 'woo-rede')
                )
            ),

            'soft_descriptor' => array(
                'title' => esc_attr__('Payment Description', 'woo-rede'),
                'type' => 'text',
                'description' => esc_attr__('Description to be sent to Rede.', 'woo-rede'),
                'desc_tip' => esc_attr__('Set the description to be sent to Rede along with the payment transaction.', 'woo-rede'),
                'custom_attributes' => array(
                    'maxlength' => 20,
                ),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Payment description sent to Rede.', 'woo-rede'),
                    'merge-top' => 'woocommerce_rede_credit_enabled_soft_descriptor'
                )
            ),

            'enabled_fix_load_script' => array(
                'title' => __('Load on checkout', 'woo-rede'),
                'type' => 'checkbox',
                'description' => __('Disable to load the plugin during checkout.', 'woo-rede'),
                'desc_tip' => __('Disable to load the plugin during checkout. Enable to prevent infinite loading errors.', 'woo-rede'),
                'label' => __('Load plugin on checkout. Default (enabled)', 'woo-rede'),
                'default' => 'yes',
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("This feature controls the plugin's loading on the checkout page. It's enabled by default to prevent infinite loading errors and should only be disabled if you're experiencing issues with the gateway.", 'woo-rede')
                )
            ),

            'credit_options' => array(
                'title' => esc_attr__('Credit Card', 'woo-rede'),
                'type' => 'title',
            ),

            'min_parcels_value' => array(
                'title' => esc_attr__('Value of the smallest installment', 'woo-rede'),
                'type' => 'number',
                'default' => 5,
                'description' => esc_attr__('Set the minimum installment value for credit card payments. Accepted minimum value by REDE: 5.', 'woo-rede'),
                'desc_tip' => esc_attr__('Set the minimum allowed amount for each installment in credit transactions.', 'woo-rede'),
                'custom_attributes' => array(
                    'min' => 5,
                    'step' => 'any',
                    'data-title-description' => esc_attr__('Enter the minimum value each installment must have.', 'woo-rede')
                )
            ),
            'max_parcels_number' => array(
                'title' => esc_attr__('Max installments', 'woo-rede'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => '12',
                'options' => array(
                    '1' => '1x',
                    '2' => '2x',
                    '3' => '3x',
                    '4' => '4x',
                    '5' => '5x',
                    '6' => '6x',
                    '7' => '7x',
                    '8' => '8x',
                    '9' => '9x',
                    '10' => '10x',
                    '11' => '11x',
                    '12' => '12x',
                ),
                'description' => esc_attr__('Define the maximum number of credit installments.', 'woo-rede'),
                'desc_tip' => esc_attr__('Set the maximum number of installments allowed in credit transactions.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Choose the maximum number of installments per order.', 'woo-rede')
                )
            ),

            'payment_complete_status' => array(
                'title' => esc_attr__('Payment Complete Status', 'woo-rede'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => esc_attr__('Choose what status to set orders after successful payment.', 'woo-rede'),
                'desc_tip' => esc_attr__('Select the order status that will be applied when payment is successfully processed.', 'woo-rede'),
                'default' => 'processing',
                'options' => array(
                    'processing' => esc_attr__('Processing', 'woo-rede'),
                    'completed' => esc_attr__('Completed', 'woo-rede'),
                    'on-hold' => esc_attr__('On Hold', 'woo-rede'),
                ),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Choose the status that approved payments should have. "Processing" is recommended for most cases.', 'woo-rede')
                )
            ),

            'developers' => array(
                'title' => esc_attr__('Developer', 'woo-rede'),
                'type' => 'title',
            ),

            'debug' => array(
                'title' => esc_attr__('Debug', 'woo-rede'),
                'type' => 'checkbox',
                'label' => esc_attr__('Enable debug logs.', 'woo-rede') . ' ' . wp_kses_post('<a href="' . esc_url(admin_url('admin.php?page=wc-status&tab=logs')) . '" target="_blank">' . __('See logs', 'woo-rede') . '</a>'),
                'default' => 'no',
                'description' => esc_attr__('Enable this option to log payment requests and responses for troubleshooting purposes.', 'woo-rede'),
                'desc_tip' => esc_attr__('Enable transaction logging.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('When enabled, all Rede transactions will be logged.', 'woo-rede')
                )
            )
        );

        // PRO section (send configs)
        $pro_plugin_active = LknIntegrationRedeForWoocommerceHelper::isProLicenseValid();
        if ($pro_plugin_active && $this->get_option('debug') == 'yes') {
            $this->form_fields['send_configs'] = array(
                'title' => __('WhatsApp Support', 'woo-rede'),
                'type'  => 'button',
                'id'    => 'sendConfigs',
                'description' => __('Enable Debug Mode and click Save Changes to get quick support via WhatsApp.', 'woo-rede'),
                'desc_tip' => '',
                'custom_attributes' => array(
                    'merge-top' => "woocommerce_{$this->id}_debug",
                    'data-title-description' => __('Send the settings for this payment method to WordPress Support.', 'woo-rede')
                )
            );
        }

        if ($this->get_option('debug') == 'yes') {
            $this->form_fields['show_order_logs'] =  array(
                'title' => __('Visualizar Log no Pedido', 'woo-rede'),
                'type' => 'checkbox',
                'label' => sprintf('Habilita visualização do log da transação dentro do pedido.', 'woo-rede'),
                'default' => 'no',
                'description' => esc_attr__('Displays Rede transaction logs inside WooCommerce order details.', 'woo-rede'),
                'desc_tip' => esc_attr__('Useful for quickly viewing payment log data without accessing the system log files.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Enable this to show the transaction details for Rede payments directly in each order’s admin panel.', 'woo-rede')
                )
            );
            $this->form_fields['clear_order_records'] =  array(
                'title' => __('Limpar logs nos Pedidos', 'woo-rede'),
                'type' => 'button',
                'id' => 'validateLicense',
                'class' => 'woocommerce-save-button components-button is-primary',
                'description' => esc_attr__('Click this button to delete all Rede log data stored in orders.', 'woo-rede'),
                'desc_tip' => esc_attr__('Use only if you no longer need the Rede transaction logs for past orders.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__('Choose the maximum number of installments per order.', 'woo-rede')
                )
            );
        }

        $this->form_fields['transactions'] = array(
            'title' => esc_attr__('Transactions', 'woo-rede'),
            'id' => 'transactions_title',
            'type'  => 'title',
        );

        $customConfigs = apply_filters('integration_rede_for_woocommerce_get_custom_configs', $this->form_fields, array(
            'installment_interest' => $this->get_option('installment_interest'),
            'max_parcels_number' => $this->get_option('max_parcels_number'),
        ), $this->id);

        if (! empty($customConfigs)) {
            $this->form_fields = array_merge($this->form_fields, $customConfigs);
        }
    }

    public function getInstallments($order_total = 0)
    {
        $installments = array();
        $defaults = array(
            'min_value' => str_replace(',', '.', $this->min_parcels_value),
            'max_parcels' => $this->max_parcels_number,
        );

        $installments_result = wp_parse_args(apply_filters('integration_rede_installments', $defaults), $defaults);

        $min_value = (float) $installments_result['min_value'];
        $max_parcels = (int) $installments_result['max_parcels'];

        // Limita ao menor valor de parcelas permitido entre os produtos do carrinho
        if (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                if ($this->id == 'rede_credit') {
                    $product_limit = get_post_meta($product_id, 'lknRedeProdutctInterest', true);
                } else {
                    $product_limit = get_post_meta($product_id, 'lknMaxipagoProdutctInterest', true);
                }
                
                if ($product_limit !== 'default' && is_numeric($product_limit)) {
                    $product_limit = (int) $product_limit;
                    if ($product_limit > 0 && $product_limit < $max_parcels) {
                        $max_parcels = $product_limit;
                    }
                }
            }
        }

        for ($i = 1; $i <= $max_parcels; ++$i) {
            // Para 1x à vista, sempre permite mesmo se for menor que o valor mínimo
            if ($i === 1 || ($order_total / $i) >= $min_value) {
                $customLabel = null; // Resetar a variável a cada iteração
                $interest = round((float) $this->get_option($i . 'x'), 2);
                /* translators: %1$d: number of installments, %2$s: installment price */
                $label = sprintf('%dx de %s', $i, wp_strip_all_tags(wc_price($order_total / $i)));

                if (($this->get_option('installment_interest') == 'yes' || $this->get_option('installment_discount') == 'yes') && is_plugin_active('rede-for-woocommerce-pro/rede-for-woocommerce-pro.php')) {
                    $customLabel = LknIntegrationRedeForWoocommerceHelper::lknIntegrationRedeProRedeInterest($order_total, $interest, $i, 'label', $this);
                }

                if (gettype($customLabel) === 'string' && $customLabel) {
                    $label = $customLabel;
                }

                $has_interest_or_discount = (
                    $this->get_option('installment_interest') === 'yes' ||
                    $this->get_option('installment_discount') === 'yes'
                );

                $installments[] = array(
                    'num'   => $i,
                    'label' => $label,
                );
            }
        }

        return $installments;
    }

    public function checkoutScripts(): void
    {
        $plugin_url = plugin_dir_url(LknIntegrationRedeForWoocommerceWcRede::FILE) . '../';
        if ($this->get_option('enabled_fix_load_script') === 'yes') {
            wp_enqueue_script('fixInfiniteLoading-js', $plugin_url . 'Public/js/fixInfiniteLoading.js', array(), '1.0.0', true);
        }

        if (! is_checkout()) {
            return;
        }

        if (! $this->is_available()) {
            return;
        }

        wp_enqueue_style('wc-rede-checkout-webservice');

        wp_enqueue_style('card-style', $plugin_url . 'Public/css/card.css', array(), '1.0.0', 'all');
        wp_enqueue_style('select-style', $plugin_url . 'Public/css/lknIntegrationRedeForWoocommerceSelectStyle.css', array(), '1.0.0', 'all');

        // Enfileira CSS específico para débito apenas se não estiver enfileirado
        if (!wp_style_is('rede-debit-style', 'enqueued')) {
            wp_enqueue_style('rede-debit-style', $plugin_url . 'Public/css/rede/LknIntegrationRedeForWoocommerceCardShortcode.css', array(), '1.0.0', 'all');
        }

        wp_enqueue_script('woo-rede-js', $plugin_url . 'Public/js/creditCard/rede/wooRedeCredit.js', array(), '1.0.0', true);
        wp_localize_script('woo-rede-js', 'wooRedeVars', array(
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('rede_payment_fields_nonce'),
        ));
        wp_enqueue_script('woo-rede-animated-card-jquery', $plugin_url . 'Public/js/jquery.card.js', array('jquery', 'woo-rede-js'), '2.5.0', true);


        apply_filters('integration_rede_for_woocommerce_set_custom_css', get_option('woocommerce_rede_credit_settings')['custom_css_short_code'] ?? false);
    }

    public function regOrderLogs($orderId, $order_total, $installments, $cardData, $transaction, $order, $brand = null): void
    {
        if ('yes' == $this->debug) {
            $tId = null;
            $returnCode = null;
            
            if ($brand === null && $transaction) {
                $brand = null;
                if (is_array($transaction)) {
                    $tId = $transaction['tid'] ?? null;
                    $returnCode = $transaction['returnCode'] ?? null;
                }
                
                if ($tId) {
                    $brand = LknIntegrationRedeForWoocommerceHelper::getTransactionBrandDetails($tId, $this);
                }
            }
            $default_currency = get_option('woocommerce_currency', 'BRL');
            $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
            $currency_json_path = INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/files/linkCurrencies.json';
            $currency_data = LknIntegrationRedeForWoocommerceHelper::lkn_get_currency_rates($currency_json_path);
            $convert_to_brl_enabled = LknIntegrationRedeForWoocommerceHelper::is_convert_to_brl_enabled($this->id);

            $exchange_rate_value = 1;
            if ($convert_to_brl_enabled && $currency_data !== false && is_array($currency_data) && isset($currency_data['rates']) && isset($currency_data['base'])) {
                // Exibe a cotação apenas se não for BRL
                if ($order_currency !== 'BRL' && isset($currency_data['rates'][$order_currency])) {
                    $rate = $currency_data['rates'][$order_currency];
                    // Converte para string, preservando todas as casas decimais
                    $exchange_rate_value = (string)$rate;
                }
            }

            $bodyArray = array(
                'orderId' => $orderId,
                'amount' => $order_total,
                'orderCurrency' => $order_currency,
                'currencyConverted' => $convert_to_brl_enabled ? 'BRL' : null,
                'exchangeRateValue' => $exchange_rate_value,
                'installments' => $installments,
                'cardData' => $cardData,
                'brand' => isset($tId) && isset($brand) ? $brand['brand'] : null,
                'returnCode' => isset($returnCode) ? $returnCode : null,
            );

            $bodyArray['cardData']['card_number'] = LknIntegrationRedeForWoocommerceHelper::censorString($bodyArray['cardData']['card_number'], 8);

            $orderLogsArray = array(
                'body' => $bodyArray,
                'response' => $transaction
            );

            $orderLogs = json_encode($orderLogsArray);
            $order->update_meta_data('lknWcRedeOrderLogs', $orderLogs);
            $order->save();
        }
    }

    /**
     * Obtém token OAuth2
     */
    private function get_oauth_token($order_id = null)
    {
        $token = LknIntegrationRedeForWoocommerceHelper::get_rede_oauth_token_for_gateway($this->id, $order_id);
        
        if ($token === null) {
            throw new Exception('Não foi possível obter token de autenticação OAuth2 para ' . esc_html($this->id));
        }
        
        return $token;
    }

    /**
     * Processa status do pedido
     */
    private function process_order_status_v2($order, $transaction_response, $note = '')
    {
        $return_code = $transaction_response['returnCode'] ?? '';
        $return_message = $transaction_response['returnMessage'] ?? '';
        $capture = $transaction_response['capture'] ?? $this->auto_capture;

        // Adiciona notas ao pedido
        /* translators: %s: return message from payment processor */
        $status_note = sprintf('Rede[%s]', LknIntegrationRedeForWoocommerceAbecsCodes::translate($return_code, $return_message));
        $order->add_order_note('[' . $this->id . '] ' . $status_note . ' ' . $note);

        if ($return_code == '00') {
            if ($capture) {
                // Status configurável pelo usuário para pagamentos aprovados com captura
                $payment_complete_status = $this->get_option('payment_complete_status', 'processing');
                $order->set_date_paid(current_time('timestamp', true));
                $order->update_status($payment_complete_status);
                apply_filters("integration_rede_for_woocommerce_change_order_status", $order, $this);
            } else {
                // Para pagamentos sem captura, sempre aguardando
                $order->update_status('on-hold');
                wc_reduce_stock_levels($order->get_id());
            }
        } else {
            $order->update_status('failed', $status_note);
        }

        WC()->cart->empty_cart();
    }

    /**
     * Processa transação de crédito
     */
    private function process_credit_transaction_v2($reference, $order_total, $installments, $cardData, $order_id, $order = null, $order_currency = 'BRL', $creditExpiry = '')
    {
        $access_token = $this->get_oauth_token($order_id);

        $amount = str_replace(".", "", number_format($order_total, 2, '.', ''));
        
        if ($this->environment === 'production') {
            $apiUrl = 'https://api.userede.com.br/erede/v2/transactions';
        } else {
            $apiUrl = 'https://sandbox-erede.useredecloud.com.br/v2/transactions';
        }

        $body = array(
            'capture' => $this->auto_capture,
            'kind' => 'credit',
            'reference' => (string)$reference,
            'amount' => (int)$amount,
            'installments' => $installments,
            'cardholderName' => $cardData['card_holder'],
            'cardNumber' => $cardData['card_number'],
            'expirationMonth' => (int)$cardData['card_expiration_month'],
            'expirationYear' => (int)$cardData['card_expiration_year'],
            'securityCode' => $cardData['card_cvv'],
            'subscription' => false,
            'origin' => 1,
            'distributorAffiliation' => 0
        );
        
        if ($this->get_option('enabled_soft_descriptor') === 'yes' && !empty($this->soft_descriptor)) {
            $body['softDescriptor'] = $this->soft_descriptor;
        }

        $response = wp_remote_post($apiUrl, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
                'Transaction-Response' => 'brand-return-opened'
            ),
            'body' => wp_json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            // Salvar metadados em caso de erro da requisição
            if ($order) {
                $customErrorResponse = LknIntegrationRedeForWoocommerceHelper::createCustomErrorResponse(
                    500,
                    44,
                    'Erro na requisição: ' . $response->get_error_message()
                );
                LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                    $order, $customErrorResponse, $cardData['card_number'], $creditExpiry, $cardData['card_holder'],
                    $installments, $order_total, $order_currency, '', $this->pv, $this->token,
                    $reference, $order_id, $this->auto_capture, 'Credit', $cardData['card_cvv'],
                    $this, '', '', '', 44, 'Erro na requisição: ' . $response->get_error_message()
                );
                $order->save();
            }
            throw new Exception('Erro na requisição: ' . esc_html($response->get_error_message()));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Adicionar o status HTTP no response_data
        if (is_array($response_data)) {
            $response_data['return_http'] = $response_code;
        } else {
            $response_data = array('return_http' => $response_code);
        }
        
        if ($response_code !== 200 && $response_code !== 201) {
            $error_message = 'Transaction error';
            $return_code = $response_data['returnCode'] ?? '';

            if (isset($response_data['returnMessage'])) {
                $error_message = $response_data['returnMessage'];
            } elseif (isset($response_data['errors']) && is_array($response_data['errors'])) {
                $error_message = implode(', ', $response_data['errors']);
            }

            $error_message = LknIntegrationRedeForWoocommerceAbecsCodes::translate($return_code, $error_message);
            if ('' !== $return_code) {
                $error_message .= ' (Error code: ' . $return_code . ')';
            }
            
            // Salvar metadados em caso de erro HTTP
            if ($order) {
                $brand = isset($response_data['brand']['name']) ? $response_data['brand']['name'] : '';
                LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                    $order, $response_data, $cardData['card_number'], $creditExpiry, $cardData['card_holder'],
                    $installments, $order_total, $order_currency, $brand, $this->pv, $this->token,
                    $reference, $order_id, $this->auto_capture, 'Credit', $cardData['card_cvv'],
                    $this, 
                    $response_data['tid'] ?? '',
                    $response_data['nsu'] ?? '',
                    $response_data['brand']['authorizationCode'] ?? '',
                    $response_data['returnCode'] ?? '',
                    $response_data['returnMessage'] ?? ''
                );
                $order->save();
            }
            
            throw new Exception(esc_html($error_message));
        }
        
        if (!isset($response_data['returnCode']) || $response_data['returnCode'] !== '00') {
            $return_code = $response_data['returnCode'] ?? '';
            $raw_message = isset($response_data['returnMessage']) ? $response_data['returnMessage'] : 'Transaction declined';
            $error_message = LknIntegrationRedeForWoocommerceAbecsCodes::translate($return_code, $raw_message);
            if ('' !== $return_code) {
                $error_message .= ' (Error code: ' . $return_code . ')';
            }
            
            // Salvar metadados em caso de transação recusada
            if ($order) {
                $brand = isset($response_data['brand']['name']) ? $response_data['brand']['name'] : '';
                LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                    $order, $response_data, $cardData['card_number'], $creditExpiry, $cardData['card_holder'],
                    $installments, $order_total, $order_currency, $brand, $this->pv, $this->token,
                    $reference, $order_id, $this->auto_capture, 'Credit', $cardData['card_cvv'],
                    $this, 
                    $response_data['tid'] ?? '',
                    $response_data['nsu'] ?? '',
                    $response_data['brand']['authorizationCode'] ?? '',
                    $response_data['returnCode'] ?? '',
                    $response_data['returnMessage'] ?? ''
                );
                $order->save();
            }
            
            throw new Exception(esc_html($error_message));
        }
        
        // Salvar metadados em caso de sucesso
        if ($order) {
            $brand = isset($response_data['brand']['name']) ? $response_data['brand']['name'] : '';
            LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                $order, $response_data, $cardData['card_number'], $creditExpiry, $cardData['card_holder'],
                $installments, $order_total, $order_currency, $brand, $this->pv, $this->token,
                $reference, $order_id, $this->auto_capture, 'Credit', $cardData['card_cvv'],
                $this, 
                $response_data['tid'] ?? '',
                $response_data['nsu'] ?? '',
                $response_data['brand']['authorizationCode'] ?? '',
                $response_data['returnCode'] ?? '',
                $response_data['returnMessage'] ?? ''
            );
            $order->save();
        }
        
        return $response_data;
    }

    public function process_payment($order_id)
    {

        if (isset($_POST['rede_card_nonce']) && ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rede_card_nonce'])), 'redeCardNonce')) {
            return array(
                'result' => 'fail',
                'redirect' => '',
            );
        }

        $order = wc_get_order($order_id);

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
        
        // Define variables needed throughout the function
        $orderId = $order->get_id();
        $default_currency = get_option('woocommerce_currency', 'BRL');
        $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
        $cardNumber = isset($_POST['rede_credit_number']) ?
            sanitize_text_field(wp_unslash($_POST['rede_credit_number'])) : '';

        $installments = isset($_POST['rede_credit_installments']) ?
            absint(sanitize_text_field(wp_unslash($_POST['rede_credit_installments']))) : 1;

        $creditExpiry = isset($_POST['rede_credit_expiry']) ? sanitize_text_field(wp_unslash($_POST['rede_credit_expiry'])) : '';

        if (strpos($creditExpiry, '/') !== false) {
            $expiration = explode('/', $creditExpiry);
        } else {
            $expiration = array(
                substr($creditExpiry, 0, 2),
                substr($creditExpiry, -2, 2),
            );
        }

        $cardData = array(
            'card_number' => preg_replace('/[^\d]/', '', sanitize_text_field(wp_unslash($_POST['rede_credit_number']))),
            'card_expiration_month' => sanitize_text_field($expiration[0]),
            'card_expiration_year' => $this->normalize_expiration_year(sanitize_text_field($expiration[1])),
            'card_cvv' => isset($_POST['rede_credit_cvc']) ? sanitize_text_field(wp_unslash($_POST['rede_credit_cvc'])) : '',
            'card_holder' => isset($_POST['rede_credit_holder_name']) ? sanitize_text_field(wp_unslash($_POST['rede_credit_holder_name'])) : '',
        );
        try {
            $valid = $this->validate_card_number($cardNumber);
            if (false === $valid) {
                // Salvar metadados da transação com dados customizados para erro de validação
                $customErrorResponse = LknIntegrationRedeForWoocommerceHelper::createCustomErrorResponse(
                    400,
                    38,
                    __('CardNumber: Required parameter missing', 'woo-rede')
                );
                LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                    $order, $customErrorResponse, $cardData['card_number'], $creditExpiry, $cardData['card_holder'],
                    $installments, $order->get_total(), $order_currency, '', $this->pv, $this->token,
                    $orderId . '-' . time(), $orderId, $this->auto_capture, 'Credit', $cardData['card_cvv'],
                    $this, '', '', '', 38, __('CardNumber: Required parameter missing', 'woo-rede')
                );
                $order->save();
                
                throw new Exception(__('Please enter a valid credit card number', 'woo-rede'));
            }

            $valid = $this->validate_card_fields($_POST);
            if (false === $valid) {
                // Salvar metadados da transação com dados customizados para erro de validação
                $customErrorResponse = LknIntegrationRedeForWoocommerceHelper::createCustomErrorResponse(
                    400,
                    37,
                    __('CardNumber: Invalid parameter format', 'woo-rede')
                );
                LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                    $order, $customErrorResponse, $cardData['card_number'], $creditExpiry, $cardData['card_holder'],
                    $installments, $order->get_total(), $order_currency, '', $this->pv, $this->token,
                    $orderId . '-' . time(), $orderId, $this->auto_capture, 'Credit', $cardData['card_cvv'],
                    $this, '', '', '', 37, __('CardNumber: Invalid parameter format', 'woo-rede')
                );
                $order->save();
                
                throw new Exception(__('One or more invalid fields', 'woo-rede'), 500);
            }

            $valid = $this->validate_installments($_POST, $order->get_total());
            if (false === $valid) {
                // Salvar metadados da transação com dados customizados para erro de validação
                $customErrorResponse = LknIntegrationRedeForWoocommerceHelper::createCustomErrorResponse(
                    400,
                    50,
                    __('Invalid installments number', 'woo-rede')
                );
                LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                    $order, $customErrorResponse, $cardData['card_number'], $creditExpiry, $cardData['card_holder'],
                    $installments, $order->get_total(), $order_currency, '', $this->pv, $this->token,
                    $orderId . '-' . time(), $orderId, $this->auto_capture, 'Credit', $cardData['card_cvv'],
                    $this, '', '', '', 50, __('Invalid installments', 'woo-rede')
                );
                $order->save();
                
                throw new Exception(__('Invalid number of installments', 'woo-rede'));
            }

            $interest = round((float) $this->get_option($installments . 'x'), 2);
            $order_total = $order->get_total();
            $decimals = get_option('woocommerce_price_num_decimals', 2);
            $convert_to_brl_enabled = false;

            // Check if BRL conversion is enabled via pro plugin
            $convert_to_brl_enabled = LknIntegrationRedeForWoocommerceHelper::is_convert_to_brl_enabled($this->id);

            // Convert order total to BRL if enabled
            $order_total = LknIntegrationRedeForWoocommerceHelper::convert_order_total_to_brl($order_total, $order, $convert_to_brl_enabled);

            if ($convert_to_brl_enabled) {
                $order->add_order_note(
                    '[' . $this->id . '] ' . sprintf(
                        // translators: %s is the original order currency code (e.g., USD, EUR, etc.)
                        __('Order currency %s converted to BRL.', 'woo-rede'),
                        $order_currency,
                    )
                );
            }

            $order_total = wc_format_decimal($order_total, $decimals);

            $reference = $orderId . '-' . time();
            
            try {
                $transaction_response = $this->process_credit_transaction_v2($reference, $order_total, $installments, $cardData, $order_id, $order, $order_currency, $creditExpiry);
                $this->regOrderLogs($orderId, $order_total, $installments, $cardData, $transaction_response, $order);
                
                // Salvar metadados da transação (em caso de sucesso)
                $brand = isset($transaction_response['brand']['name']) ? $transaction_response['brand']['name'] : '';
                LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                    $order, $transaction_response, $cardData['card_number'], $creditExpiry, $cardData['card_holder'],
                    $installments, $order_total, $order_currency, $brand, $this->pv, $this->token,
                    $reference, $orderId, $this->auto_capture, 'Credit', $cardData['card_cvv'],
                    $this, 
                    $transaction_response['tid'] ?? '',
                    $transaction_response['nsu'] ?? '',
                    $transaction_response['brand']['authorizationCode'] ?? '',
                    $transaction_response['returnCode'] ?? '',
                    $transaction_response['returnMessage'] ?? ''
                );
                
            } catch (Exception $e) {
                $this->regOrderLogs($orderId, $order_total, $installments, $cardData, $e->getMessage(), $order);
                
                throw $e;
            }

            $default_currency = get_option('woocommerce_currency', 'BRL');
            $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
            $currency_json_path = INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/files/linkCurrencies.json';
            $currency_data = LknIntegrationRedeForWoocommerceHelper::lkn_get_currency_rates($currency_json_path);

            $exchange_rate_value = 1;
            if ($convert_to_brl_enabled && $currency_data !== false && is_array($currency_data) && isset($currency_data['rates']) && isset($currency_data['base'])) {
                // Exibe a cotação apenas se não for BRL
                if ($order_currency !== 'BRL' && isset($currency_data['rates'][$order_currency])) {
                    $rate = $currency_data['rates'][$order_currency];
                    // Converte para string, preservando todas as casas decimais
                    $exchange_rate_value = (string)$rate;
                }
            }

            $order->update_meta_data('_wc_rede_transaction_return_code', $transaction_response['returnCode'] ?? '');
            $order->update_meta_data('_wc_rede_transaction_return_message', $transaction_response['returnMessage'] ?? '');
            $order->update_meta_data('_wc_rede_transaction_installments', $installments);
            $order->update_meta_data('_wc_rede_transaction_reference', $reference);
            $order->update_meta_data('_wc_rede_transaction_id', $transaction_response['tid'] ?? '');
            $order->update_meta_data('_wc_rede_transaction_refund_id', $transaction_response['refundId'] ?? '');
            $order->update_meta_data('_wc_rede_transaction_cancel_id', $transaction_response['cancelId'] ?? '');
            $order->update_meta_data('_wc_rede_transaction_bin', $transaction_response['cardBin'] ?? '');
            $order->update_meta_data('_wc_rede_transaction_last4', $transaction_response['last4'] ?? '');
            $order->update_meta_data('_wc_rede_transaction_brand', $transaction_response['brand']['name'] ?? '');
            $order->update_meta_data('_wc_rede_transaction_nsu', $transaction_response['nsu'] ?? '');
            $order->update_meta_data('_wc_rede_transaction_authorization_code', $transaction_response['brand']['authorizationCode'] ?? '');
            $order->update_meta_data('_wc_rede_captured', $transaction_response['capture'] ?? $this->auto_capture);
            $order->update_meta_data('_wc_rede_total_amount', $order->get_total());
            $order->update_meta_data('_wc_rede_total_amount_converted', $order_total);
            $order->update_meta_data('_wc_rede_total_amount_is_converted', $convert_to_brl_enabled ? true : false);
            $order->update_meta_data('_wc_rede_exchange_rate', $exchange_rate_value);
            $order->update_meta_data('_wc_rede_decimal_value', $decimals);

            if (isset($transaction_response['authorization'])) {
                $order->update_meta_data('_wc_rede_transaction_authorization_status', $transaction_response['authorization']['status'] ?? '');
            }

            $order->update_meta_data('_wc_rede_transaction_holder', $cardData['card_holder']);
            $order->update_meta_data('_wc_rede_transaction_expiration', sprintf('%02d/%d', $expiration[0], (int) ($expiration[1])));

            $order->update_meta_data('_wc_rede_transaction_environment', $this->environment);

            // Adaptar o process_order_status para trabalhar com array em vez de objeto SDK
            $this->process_order_status_v2($order, $transaction_response, '');

            // Salvar o pedido para garantir que todos os metadados sejam persistidos
            $order->save();

            if ('yes' == $this->debug) {
                $tId = $transaction_response['tid'] ?? null;
                $returnCode = $transaction_response['returnCode'] ?? null;
                $brandDetails = null;
                
                if ($tId) {
                    $brandDetails = LknIntegrationRedeForWoocommerceHelper::getTransactionBrandDetails($tId, $this);
                }

                $this->log->log('info', $this->id, array(
                    'transaction' => $transaction_response,
                    'order' => array(
                        'orderId' => $orderId,
                        'amount' => $order_total,
                        'orderCurrency' => $order_currency,
                        'currencyConverted' => $convert_to_brl_enabled ? 'BRL' : null,
                        'exchangeRateValue' => $exchange_rate_value,
                        'status' => $order->get_status(),
                        'brand' => isset($brandDetails['brand']) ? $brandDetails['brand'] : ($transaction_response['card']['brand'] ?? null),
                        'returnCode' => $returnCode,
                    ),
                ));
            }
        } catch (Exception $e) {
            if ($e->getCode() == 63) {
                add_option('lknIntegrationRedeForWoocommerceSoftDescriptorErrorCredit', true);
                update_option('lknIntegrationRedeForWoocommerceSoftDescriptorErrorCredit', true);
            }

            $this->add_error($e->getMessage());

            return array(
                'result' => 'fail',
                'redirect' => '',
            );
        }

        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Processa reembolso
     */
    private function process_refund_v2($tid, $amount)
    {
        $access_token = $this->get_oauth_token();
        
        if ($this->environment === 'production') {
            $apiUrl = 'https://api.userede.com.br/erede/v2/transactions/' . $tid . '/refunds';
        } else {
            $apiUrl = 'https://sandbox-erede.useredecloud.com.br/v2/transactions/' . $tid . '/refunds';
        }

        $amount_int = str_replace(".", "", number_format($amount, 2, '.', ''));
        
        $body = array(
            'amount' => (int)$amount_int
        );

        $response = wp_remote_post($apiUrl, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ),
            'body' => wp_json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            throw new Exception('Erro na requisição de reembolso: ' . esc_html($response->get_error_message()));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code !== 200 && $response_code !== 201) {
            $error_message = 'Erro no reembolso';
            if (isset($response_data['message'])) {
                $error_message = $response_data['message'];
            } elseif (isset($response_data['errors']) && is_array($response_data['errors'])) {
                $error_message = implode(', ', $response_data['errors']);
            }
            throw new Exception(esc_html($error_message));
        }
        
        return $response_data;
    }

    public function process_refund($order_id, $amount = 0, $reason = '')
    {
        $order = new WC_Order($order_id);
        if ($order->get_payment_method() === 'rede_credit') {
            $totalAmount = $order->get_meta('_wc_rede_total_amount');
            $is_converted = $order->get_meta('_wc_rede_total_amount_is_converted');
            $exchange_rate = $order->get_meta('_wc_rede_exchange_rate');
            $decimals = $order->get_meta('_wc_rede_decimal_value');
            $amount_converted = $order->get_meta('_wc_rede_total_amount_converted');
            $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : 'BRL';

            if (!empty($order->get_meta('_wc_rede_transaction_canceled'))) {
                $order->add_order_note('[' . $this->id . '] Rede[Refund Error] ' . esc_attr__('Total refund already processed, check the order notes block.', 'woo-rede'));
                $order->save();
                return false;
            }

            if (! $order || ! $order->get_meta('_wc_rede_transaction_id')) {
                $order->add_order_note('[' . $this->id . '] Rede[Refund Error] ' . esc_attr__('Order or transaction invalid for refund.', 'woo-rede'));
                $order->save();
                return false;
            }

            if (empty($order->get_meta('_wc_rede_transaction_canceled'))) {
                $tid = $order->get_meta('_wc_rede_transaction_id');
                $amount = wc_format_decimal($amount, 2);

                // Se conversão está ativa, usa o valor convertido
                if ($is_converted && $exchange_rate) {
                    $amount_brl = floatval($amount) / floatval($exchange_rate);
                    $amount_brl = number_format($amount_brl, (int)$decimals, '.', '');
                    $amount = $amount_brl;
                } else if ($amount == $order->get_total()) {
                    $amount = $totalAmount;
                }

                try {
                    if ($amount > 0) {
                        if (isset($amount) && ($amount > 0 && $amount < $totalAmount) || ($is_converted && $amount > 0 && $amount < $amount_converted)) {
                            $order->add_order_note('[' . $this->id . '] Rede[Refund Error] ' . esc_attr__('Partial refunds are not allowed. You must refund the total order amount.', 'woo-rede'));
                            $order->save();
                            return false;
                        } elseif ($order->get_total() == $amount || ($is_converted && $amount == $amount_converted)) {
                            $refund_response = $this->process_refund_v2($tid, $amount);
                        }

                        update_post_meta($order_id, '_wc_rede_transaction_refund_id', $refund_response['refundId'] ?? '');
                        if (empty($refund_response['cancelId'])) {
                            update_post_meta($order_id, '_wc_rede_transaction_cancel_id', $refund_response['tid'] ?? $tid);
                        } else {
                            update_post_meta($order_id, '_wc_rede_transaction_cancel_id', $refund_response['cancelId']);
                        }
                        update_post_meta($order_id, '_wc_rede_transaction_canceled', true);

                        // Formata o valor conforme moeda
                        if ($is_converted) {
                            $formatted_amount = wc_price($amount, array('currency' => 'BRL'));
                        } else {
                            $formatted_amount = wc_price($amount, array('currency' => $order_currency));
                        }
                        $order->add_order_note('[' . $this->id . '] ' . esc_attr__('Refunded:', 'woo-rede') . ' ' . $formatted_amount);
                        $order->save();
                    } else {
                        $order->add_order_note('[' . $this->id . '] Rede[Refund Error] ' . esc_attr__('Invalid refund amount.', 'woo-rede'));
                        $order->save();
                        return false;
                    }
                } catch (Exception $e) {
                    $order->add_order_note('[' . $this->id . '] Rede[Refund Error] ' . sanitize_text_field($e->getMessage()));
                    $order->save();
                    return false;
                }

                return true;
            }
        } else {
            return false;
        }
    }

    protected function getCheckoutForm($order_total = 0): void
    {
        $wc_get_template = 'woocommerce_get_template';

        if (function_exists('wc_get_template')) {
            $wc_get_template = 'wc_get_template';
        }

        $session = null;
        // Buscar valor da sessão ao invés de fixar em 1
        $installments_number = 1;
        if (function_exists('WC') && WC()->session) {
            $session_value = WC()->session->get('lkn_installments_number_rede_credit');
            if (!empty($session_value) && is_numeric($session_value) && $session_value > 0) {
                $installments_number = intval($session_value);
            }
        }

        $wc_get_template(
            'creditCard/redePaymentCreditForm.php',
            array(
                'installments' => $this->getInstallments($order_total),
                'installments_number' => $installments_number,
            ),
            'woocommerce/rede/',
            LknIntegrationRedeForWoocommerceWcRede::getTemplatesPath()
        );
    }
    /**
     * Renderiza campos de pagamento
     */
    public function render_payment_fields_with_total($order_total = null): void
    {
        if ($description = $this->get_description()) {
            echo wp_kses_post(wpautop($description));
        }

        if ($order_total === null) {
            $order_total = $this->get_cart_subtotal_without_taxes();
        }

        $this->getCheckoutForm($order_total);
    }

    /**
     * Processa as opções administrativas com reset de token OAuth2
     * 
     * @return bool
     */
    public function process_admin_options()
    {
        // Obter configurações atuais antes de salvar as novas
        $old_settings = get_option('woocommerce_' . $this->id . '_settings', array());
        $old_pv = isset($old_settings['pv']) ? $old_settings['pv'] : '';
        $old_token = isset($old_settings['token']) ? $old_settings['token'] : '';

        $saved = parent::process_admin_options();

        // Verificar se PV ou Token foram alterados
        $new_pv = $this->get_option('pv');
        $new_token = $this->get_option('token');
        
        if ($old_pv !== $new_pv || $old_token !== $new_token) {
            // Limpar tokens OAuth2 em cache para este gateway em ambos ambientes
            $environments = array('test', 'production');
            
            foreach ($environments as $environment) {
                delete_option('lkn_rede_oauth_token_' . $this->id . '_' . $environment);
            }
        }

        return $saved;
    }
}
