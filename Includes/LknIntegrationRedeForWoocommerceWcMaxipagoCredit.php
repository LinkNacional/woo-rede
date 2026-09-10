<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use Exception;
use WC_Order;
use WP_Error;

final class LknIntegrationRedeForWoocommerceWcMaxipagoCredit extends LknIntegrationRedeForWoocommerceWcRedeAbstract
{
    public function __construct()
    {
        $this->id = 'maxipago_credit';
        $this->method_title = esc_attr__('Pay with the Maxipago Credit', 'woo-rede');
        $this->method_description = esc_attr__('Enables and configures payments with Maxipago Credit', 'woo-rede');
        $this->title = 'Maxipago';
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'refunds',
        );

        $this->icon = LknIntegrationRedeForWoocommerceHelper::getUrlIcon();
        // Define os campos de configuração
        $this->initFormFields();
        $this->init_settings();

        // Define as propriedades dos campos de configuração
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_key = $this->get_option('merchant_key');

        // Define as configurações
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Carrega os valores dos campos de configuração
        $this->enabled = $this->get_option('enabled');

        $this->debug = $this->get_option('debug');

        $this->log = $this->get_logger();

        $this->configs = $this->getConfigsMaxipagoCredit();
    }

    /**
     * Fields validation.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if (empty(sanitize_text_field(wp_unslash($_POST['maxipago_credit_card_cpf']))) && empty(sanitize_text_field(wp_unslash($_POST['billing_cpf']))) && empty(sanitize_text_field(wp_unslash($_POST['billing_cnpj'])))) {
            wc_add_notice(esc_attr__('CPF is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (empty($_POST['maxipago_credit_number'])) {
            wc_add_notice(esc_attr__('Card number is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (empty($_POST['maxipago_credit_expiry'])) {
            wc_add_notice(esc_attr__('Card expiration is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (empty($_POST['maxipago_credit_cvc'])) {
            wc_add_notice(esc_attr__('Card security code is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (! ctype_digit(sanitize_text_field(wp_unslash($_POST['maxipago_credit_cvc'])))) {
            wc_add_notice(esc_attr__('Card security code must be a numeric value', 'woo-rede'), 'error');
            return false;
        }

        if (strlen(sanitize_text_field(wp_unslash($_POST['maxipago_credit_cvc']))) < 3) {
            wc_add_notice(esc_attr__('Card security code must be at least 3 digits long', 'woo-rede'), 'error');
            return false;
        }

        if (empty($_POST['maxipago_credit_holder_name'])) {
            wc_add_notice(esc_attr__('Cardholder name is a required field', 'woo-rede'), 'error');

            return false;
        }

        return true;
    }

    public function addNeighborhoodFieldToCheckout($fields)
    {
        if (
            ! is_plugin_active('woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php')
            && $this->is_available()
        ) {
            $fields['billing']['billing_neighborhood'] = array(
                'label' => __('District', 'woo-rede'),
                'placeholder' => __('District', 'woo-rede'),
                'required' => true,
                'class' => array('form-row-wide'),
                'clear' => true,
            );

            // Obtém a posição do campo de endereço
            $address_position = array_search('billing_address_1', array_keys($fields['billing']), true);

            // Insere o campo de bairro após o campo de endereço
            $fields['billing'] = array_slice($fields['billing'], 0, $address_position + 2, true) +
                array('billing_neighborhood' => $fields['billing']['billing_neighborhood']) +
                array_slice($fields['billing'], $address_position + 2, null, true);
        }
        return $fields;
    }

    /**
     * This function centralizes the data in one spot for ease mannagment
     *
     * @return array
     */
    public function getConfigsMaxipagoCredit()
    {
        $configs = array();

        $configs['basePath'] = INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/logs/';
        $configs['base'] = $configs['basePath'] . gmdate('d.m.Y-H.i.s') . '.maxipagoCredit.log';
        $configs['debug'] = $this->get_option('debug');

        return $configs;
    }

    public function initFormFields(): void
    {
        LknIntegrationRedeForWoocommerceHelper::updateFixLoadScriptOption($this->id);

        $this->form_fields = array(
            'maxipago' => array(
                'title' => esc_attr__('General', 'woo-rede'),
                'type' => 'title',
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'woo-rede'),
                'type' => 'checkbox',
                'label' => __('Enables payment with Maxipago', 'woo-rede'),
                'default' => 'no',
                'desc_tip' => esc_attr__('Check this box and save to enable credit card settings.', 'woo-rede'),
                'description' => esc_attr__('Enable or disable the credit card payment method.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("Enable this option to allow customers to pay with credit cards using Maxipago API.", 'woo-rede')
                ),
            ),
            'title' => array(
                'title' => __('Title', 'woo-rede'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woo-rede'),
                'desc_tip' => esc_attr__('Enter the title that will be shown to customers during the checkout process.', 'woo-rede'),
                'description' => esc_attr__('This controls the title which the user sees during checkout.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("Provide a brief message that informs the customer how the payment will be processed. For example: “Your payment will be securely processed by Maxipago.”", 'woo-rede')
                ),
            ),
            'description' => array(
                'title' => __('Description', 'woo-rede'),
                'type' => 'textarea',
                'default' => __('Pay for your purchase with a credit card through ', 'woo-rede'),
                'desc_tip' => esc_attr__('This description appears below the payment method title at checkout. Use it to inform your customers about the payment processing details.', 'woo-rede'),
                'description' => esc_attr__('Payment method description that the customer will see on your checkout.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("Provide a brief message that informs the customer how the payment will be processed. For example: “Your payment will be securely processed by Maxipago.”", 'woo-rede')
                ),
            ),
            'company_name' => array(
                'title' => __('Seller Company Name', 'woo-rede'),
                'type' => 'text',
                'desc_tip' => esc_attr__("Name that appears on the cardholder's statement.", 'woo-rede'),
            ),

            'environment' => array(
                'title' => esc_attr__('Environment', 'woo-rede'),
                'type' => 'select',
                'desc_tip' => esc_attr__('Choose between production or development mode for Maxipago API.', 'woo-rede'),
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
            'merchant_id' => array(
                'title' => __('Merchant ID', 'woo-rede'),
                'type' => 'password',
                'desc_tip' => esc_attr__('Your Maxipago Merchant ID.', 'woo-rede'),
                'description' => esc_attr__('Maxipago credentials.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("Your Merchant ID should be provided here.", 'woo-rede')
                ),
                'default' => '',
            ),
            'merchant_key' => array(
                'title' => __('Merchant Key', 'woo-rede'),
                'type' => 'password',
                'desc_tip' => esc_attr__('Your Maxipago Merchant Key.', 'woo-rede'),
                'description' => esc_attr__('Maxipago credentials.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("Your Merchant Key should be provided here.", 'woo-rede')
                ),
                'default' => '',
            ),
            'enabled_fix_load_script' => array(
                'title' => __('Load on checkout', 'woo-rede'),
                'type' => 'checkbox',
                'desc_tip' => esc_attr__('Disable to load the plugin during checkout. Enable to prevent infinite loading errors.', 'woo-rede'),
                'description' => esc_attr__('Selecione a posição onde o layout PIX será exibido na página de checkout.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("This feature controls the plugin's loading on the checkout page. It's enabled by default to prevent infinite loading errors and should only be disabled if you're experiencing issues with the gateway.", 'woo-rede')
                ),
                'label' => __('Load plugin on checkout. Default (enabled)', 'woo-rede'),
                'default' => 'yes',
            ),
            'credit_options' => array(
                'title' => esc_attr__('Credit Card', 'woo-rede'),
                'type' => 'title',
            ),
            'min_parcels_value' => array(
                'title' => esc_attr__('Value of the smallest installment', 'woo-rede'),
                'type' => 'number',
                'default' => 5,
                'desc_tip' => esc_attr__('Set the minimum allowed amount for each installment in credit transactions. Accepted minimum value by MaxiPago: 5.', 'woo-rede'),
                'description' => esc_attr__('Set the minimum installment value for credit card payments.', 'woo-rede'),
                'custom_attributes' => array(
                    'min' => 5,
                    'step' => 'any',
                    'data-title-description' => esc_attr__("Enter the minimum value each installment must have.", 'woo-rede')
                ),
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
                'description' => esc_attr__('Set the maximum number of installments allowed in credit transactions.', 'woo-rede'),
                'desc_tip' => true,
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
                'desc_tip' => esc_attr__('Enable transaction logging.', 'woo-rede'),
                'description' => esc_attr__('Enable this option to log payment requests and responses for troubleshooting purposes.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("When enabled, all Rede transactions will be logged.", 'woo-rede')
                ),
            )
        );

        if ($this->get_option('debug') == 'yes') {
            $this->form_fields['show_order_logs'] =  array(
                'title' => __('Visualizar Log no Pedido', 'woo-rede'),
                'type' => 'checkbox',
                'label' => sprintf('Habilita visualização do log da transação dentro do pedido.', 'woo-rede'),
                'default' => 'no',
                'desc_tip' => esc_attr__('Useful for quickly viewing payment log data without accessing the system log files.', 'woo-rede'),
                'description' => esc_attr__('Enable this option to log payment requests and responses for troubleshooting purposes.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("Enable this to show the transaction details for Rede payments directly in each order’s admin panel.", 'woo-rede')
                ),
            );
            $this->form_fields['clear_order_records'] =  array(
                'title' => __('Limpar logs nos Pedidos', 'woo-rede'),
                'type' => 'button',
                'id' => 'validateLicense',
                'class' => 'woocommerce-save-button components-button is-primary',
                'desc_tip' => esc_attr__('Use only if you no longer need the Rede transaction logs for past orders.', 'woo-rede'),
                'description' => esc_attr__('Click this button to delete all Rede log data stored in orders.', 'woo-rede'),
            );
        }

        $customConfigs = apply_filters('integration_rede_for_woocommerce_get_custom_configs', $this->form_fields, array(
            'installment_interest' => $this->get_option('installment_interest'),
            'max_parcels_number' => $this->get_option('max_parcels_number'),
        ), $this->id);

        if (! empty($customConfigs)) {
            $this->form_fields = array_merge($this->form_fields, $customConfigs);
        }
    }

    protected function getCheckoutForm($order_total = 0): void
    {
        $session = null;
        // Buscar valor da sessão ao invés de fixar em 1
        $installments_number = 1;
        if (function_exists('WC') && WC()->session) {
            $session_value = WC()->session->get('lkn_installments_number_maxipago_credit');
            if (!empty($session_value) && is_numeric($session_value) && $session_value > 0) {
                $installments_number = intval($session_value);
            }
        }

        wc_get_template(
            'creditCard/maxipagoPaymentCreditForm.php',
            array(
                'installments' => $this->getInstallments($order_total),
                'installments_number' => $installments_number,
            ),
            'woocommerce/maxipago/',
            LknIntegrationRedeForWoocommerceWcRede::getTemplatesPath()
        );
    }

    public function getInstallments($order_total = 0)
    {
        $installments = array();
        $defaults = array(
            'min_value' => str_replace(',', '.', $this->get_option('min_parcels_value')),
            'max_parcels' => $this->get_option('max_parcels_number'),
        );

        $installments_result = wp_parse_args(apply_filters('integration_rede_installments', $defaults), $defaults);

        $min_value = (float) $installments_result['min_value'];
        $max_parcels = (int) $installments_result['max_parcels'];

        // Verificar limites de parcelamento por produto
        $max_installments = $max_parcels;
        if (WC()->cart && !WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                if ($this->id == 'rede_credit') {
                    $product_limit = get_post_meta($product_id, 'lknRedeProdutctInterest', true);
                } else {
                    $product_limit = get_post_meta($product_id, 'lknMaxipagoProdutctInterest', true);
                }

                if ($product_limit !== 'default' && is_numeric($product_limit)) {
                    $product_limit = (int) $product_limit;
                    // Limita ao menor valor encontrado entre os produtos
                    if ($product_limit > 0 && $product_limit < $max_installments) {
                        $max_installments = $product_limit;
                    }
                }
            }
            WC()->cart->calculate_totals();
        }

        for ($i = 1; $i <= $max_installments; ++$i) {
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

                $installments[] = array(
                    'num'   => $i,
                    'label' => $label,
                );
            }
        }

        return $installments;
    }

    public function regOrderLogs($xmlData, $xml, $orderId, $order, $apiUrl, $orderTotal = null)
    {
        if ('yes' == $this->debug) {
            $default_currency = get_option('woocommerce_currency', 'BRL');
            $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
            // Define $capture as in process_payment
            $capture = sanitize_text_field($this->get_option('auto_capture')) == 'no' ? 'auth' : 'sale';
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

            $xmlBody = simplexml_load_string($xmlData);
            $cardNumber = null;
            if (
                isset($xmlBody->order) &&
                isset($xmlBody->order->debitSale) &&
                isset($xmlBody->order->debitSale->transactionDetail) &&
                isset($xmlBody->order->debitSale->transactionDetail->payType) &&
                isset($xmlBody->order->debitSale->transactionDetail->payType->debitCard) &&
                isset($xmlBody->order->debitSale->transactionDetail->payType->debitCard->number)
            ) {
                $cardNumber = $xmlBody->order->debitSale->transactionDetail->payType->debitCard->number;
                $xmlBody->order->debitSale->transactionDetail->payType->debitCard->number = LknIntegrationRedeForWoocommerceHelper::censorString($cardNumber, 8);
            }
            if (isset($xmlBody->verification)) {
                if (isset($xmlBody->verification->merchantId)) {
                    $xmlBody->verification->merchantId = LknIntegrationRedeForWoocommerceHelper::censorString($xmlBody->verification->merchantId, 3);
                }
                if (isset($xmlBody->verification->merchantKey)) {
                    $xmlBody->verification->merchantKey = LknIntegrationRedeForWoocommerceHelper::censorString($xmlBody->verification->merchantKey, 12);
                }
            }

            $response_body = wp_remote_retrieve_body($xml);
            $xml = simplexml_load_string($response_body);

            //Reconstruindo o $xml para facilitar o uso da variavel
            $xml_encode = wp_json_encode($xml);
            $xml_decode = json_decode($xml_encode, true);

            // Convert XML body to array for manipulation
            $bodyArr = json_decode(json_encode($xmlBody), true);
            $orderSummary = array(
                'orderId' => $orderId,
                'amount' => isset($orderTotal) ? $orderTotal : $order->get_total(),
                'orderCurrency' => $order_currency,
                'currencyConverted' => $convert_to_brl_enabled ? 'BRL' : null,
                'exchangeRateValue' => $exchange_rate_value,
                'status' => $order->get_status()
            );
            // Force replace payment with orderSummary and installments
            if (
                isset($bodyArr['order']) &&
                isset($bodyArr['order'][$capture]) &&
                isset($bodyArr['order'][$capture]['payment'])
            ) {
                $installments = array();
                if (isset($bodyArr['order'][$capture]['payment']['creditInstallment'])) {
                    $installments = $bodyArr['order'][$capture]['payment']['creditInstallment'];
                }
                $bodyArr['order'][$capture]['payment'] = array_merge($orderSummary, array('installments' => $installments));
            }
            $orderLogsArray = array(
                'url' => $apiUrl,
                'body' => $bodyArr,
                'response' => $xml_decode,
            );

            $orderLogs = json_encode($orderLogsArray);
            $order->update_meta_data('lknWcRedeOrderLogs', $orderLogs);



            $order->save();
        }
    }

    public function process_payment($orderId)
    {
        if (isset($_POST['maxipago_card_nonce']) && ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['maxipago_card_nonce'])), 'maxipagoCardNonce')) {
            return array(
                'result' => 'fail',
                'redirect' => '',
            );
        }

        $order = wc_get_order($orderId);
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
        $order_total = $order->get_total();
        $decimals = get_option('woocommerce_price_num_decimals', 2);
        $convert_to_brl_enabled = false;
        $default_currency = get_option('woocommerce_currency', 'BRL');
        $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
        $woocommerceCountry = get_option('woocommerce_default_country');

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
            $order_currency = 'BRL';
        }

        // Extraindo somente o país da string
        $countryParts = explode(':', $woocommerceCountry);
        $countryCode = $countryParts[0];

        $merchantId = sanitize_text_field($this->get_option('merchant_id'));
        $companyName = sanitize_text_field($this->get_option('company_name'));
        $merchantKey = sanitize_text_field($this->get_option('merchant_key'));
        $capture = sanitize_text_field($this->get_option('auto_capture')) == 'no' ? 'auth' : 'sale';
        $referenceNum = uniqid('order_', true);

        $installments = isset($_POST['maxipago_credit_installments']) ?
            absint(sanitize_text_field(wp_unslash($_POST['maxipago_credit_installments']))) : 1;

        $order_total = wc_format_decimal($order_total, $decimals);

        $creditExpiry = isset($_POST['maxipago_credit_expiry']) ? sanitize_text_field(wp_unslash($_POST['maxipago_credit_expiry'])) : 0;

        if (strpos($creditExpiry, '/') !== false) {
            $expiration = explode('/', $creditExpiry);
        } else {
            $expiration = array(
                substr($creditExpiry, 0, 2),
                substr($creditExpiry, -2, 2),
            );
        }

        $billingCPF = isset($_POST['billing_cpf']) ? sanitize_text_field(wp_unslash($_POST['billing_cpf'])) : '';

        if (isset($_POST['maxipago_credit_card_cpf'])) {
            $billingCPF = sanitize_text_field(wp_unslash($_POST['maxipago_credit_card_cpf']));
        }

        $clientData = array(
            'billing_cpf' => $billingCPF,
            'billing_name' => isset($_POST['maxipago_credit_holder_name']) ? sanitize_text_field(wp_unslash($_POST['maxipago_credit_holder_name'])) : '',
            'billing_address_1' => isset($_POST['billing_address_1']) ? sanitize_text_field(wp_unslash($_POST['billing_address_1'])) : '',
            'billing_district' => isset($_POST['billing_neighborhood']) ? sanitize_text_field(wp_unslash($_POST['billing_neighborhood'])) : '',
            'billing_city' => isset($_POST['billing_city']) ? sanitize_text_field(wp_unslash($_POST['billing_city'])) : '',
            'billing_state' => isset($_POST['billing_state']) ? sanitize_text_field(wp_unslash($_POST['billing_state'])) : '',
            'billing_postcode' => isset($_POST['billing_postcode']) ? sanitize_text_field(wp_unslash($_POST['billing_postcode'])) : '',
            'billing_phone' => isset($_POST['billing_phone']) ? sanitize_text_field(wp_unslash($_POST['billing_phone'])) : '',
            'billing_email' => isset($_POST['billing_email']) ? sanitize_text_field(wp_unslash($_POST['billing_email'])) : '',
            'currency_code' => get_option('woocommerce_currency'),
            'country' => $countryCode,
        );

        $cardData = array(
            'card_number' => preg_replace('/[^\d]/', '', isset($_POST['maxipago_credit_number']) ? sanitize_text_field(wp_unslash($_POST['maxipago_credit_number'])) : ''),
            'card_expiration_month' => sanitize_text_field($expiration[0]),
            'card_expiration_year' => $this->normalize_expiration_year(sanitize_text_field($expiration[1])),
            'card_cvv' => isset($_POST['maxipago_credit_cvc']) ? sanitize_text_field(wp_unslash($_POST['maxipago_credit_cvc'])) : '',
            'card_holder' => isset($_POST['maxipago_credit_holder_name']) ? sanitize_text_field(wp_unslash($_POST['maxipago_credit_holder_name'])) : '',
            'card_installments' => isset($_POST['maxipago_credit_installments']) ? sanitize_text_field(wp_unslash($_POST['maxipago_credit_installments'])) : '',
        );

        try {
            $environment = $this->get_option('environment');

            $valid = $this->validate_card_number($cardData['card_number']);
            if (false === $valid) {
                throw new Exception(__('Please enter a valid credit card number', 'woo-rede'));
            }

            $valid = $this->validate_card_fields($_POST);
            if (false === $valid) {
                throw new Exception(__('One or more invalid fields', 'woo-rede'), 500);
            }

            $valid = $this->validate_installments($_POST, $order->get_total());
            if (false === $valid) {
                throw new Exception(__('Invalid number of installments', 'woo-rede'));
            }

            if (! $this->validateCpfCnpj($clientData['billing_cpf'])) {
                throw new Exception(__("Please enter a valid cpf number", 'woo-rede'));
            }
            if ('production' === $environment) {
                $apiUrl = 'https://api.maxipago.net/UniversalAPI/postXML';
                $processorID = '1';
            } else {
                $apiUrl = 'https://testapi.maxipago.net/UniversalAPI/postXML';
                $processorID = '5';
            }
            $xmlData = "<?xml version='1.0' encoding='UTF-8'?>
                    <transaction-request>
                        <version>3.1.1.15</version>
                        <verification>
                            <merchantId>$merchantId</merchantId>
                            <merchantKey>$merchantKey</merchantKey>
                        </verification>
                        <order>
                            <$capture>
                                <processorID>$processorID</processorID>
                                <referenceNum>$referenceNum</referenceNum>
                                <customerIdExt>" . $clientData['billing_cpf'] . "</customerIdExt>
                                <billing>
                                    <name>" . $clientData['billing_name'] . "</name>
                                    <address>" . $clientData['billing_address_1'] . "</address>
                                    <district>" . $clientData['billing_district'] . "</district>
                                    <city>" . $clientData['billing_city'] . "</city>
                                    <state>" . $clientData['billing_state'] . "</state>
                                    <postalcode>" . $clientData['billing_postcode'] . "</postalcode>
                                    <country>" . $clientData['country'] . "</country>
                                    <phone>" . $clientData['billing_phone'] . "</phone>
                                    <companyName>$companyName</companyName>
                                </billing>
                                <transactionDetail>
                                    <payType>
                                        <creditCard>
                                            <number>" . $cardData['card_number'] . "</number>
                                            <expMonth>" . $cardData['card_expiration_month'] . "</expMonth>
                                            <expYear>" . $cardData['card_expiration_year'] . "</expYear>
                                            <cvvNumber>" . $cardData['card_cvv'] . "</cvvNumber>
                                        </creditCard>
                                    </payType>
                                </transactionDetail>
                                <payment>
                                    <chargeTotal>" . $order_total . "</chargeTotal>
                                    <currencyCode>" . $order_currency . "</currencyCode>
                                    <creditInstallment>
                                        <numberOfInstallments>" . $installments . "</numberOfInstallments>
                                        <chargeInterest>N</chargeInterest>
                                    </creditInstallment>
                                </payment>
                            </$capture>
                        </order>
                    </transaction-request>";

            $args = array(
                'body' => $xmlData,
                'headers' => array(
                    'Content-Type' => 'application/xml'
                ),
                'timeout' => 60,
                'sslverify' => false // Desativa a verificação do certificado SSL
            );

            try {
                $response = wp_remote_post($apiUrl, $args);
                $this->regOrderLogs(
                    $xmlData,
                    $response,
                    $orderId,
                    $order,
                    $apiUrl,
                    $order_total
                );
            } catch (Exception $e) {
                $this->regOrderLogs(
                    $xmlData,
                    $e->getMessage(),
                    $orderId,
                    $order,
                    $apiUrl,
                    $order_total
                );

                throw $e;
            }

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                throw new Exception(esc_attr($error_message));
            } else {
                $response_body = wp_remote_retrieve_body($response);
                $xml = simplexml_load_string($response_body);
            }

            //Reconstruindo o $xml para facilitar o uso da variavel
            $xml_encode = wp_json_encode($xml);
            $xml_decode = json_decode($xml_encode, true);

            // Adiciona nota de status do pagamento estilo Maxipago[Success.] ou Maxipago[Failed.]
            $maxipago_processor_code = $xml_decode['processorCode'] ?? '';
            $maxipago_processor_message = LknIntegrationRedeForWoocommerceAbecsCodes::translate($maxipago_processor_code, $xml_decode['processorMessage'] ?? '');
            if (isset($xml_decode['responseCode']) && "0" == $xml_decode['responseCode']) {
                $order->add_order_note(
                    '[' . $this->id . '] ' . sprintf(
                        'Maxipago[Success.] %s',
                        $maxipago_processor_message
                    )
                );
            } else {
                $order->add_order_note(
                    '[' . $this->id . '] ' . sprintf(
                        'Maxipago[Failed.] %s',
                        $maxipago_processor_message
                    )
                );
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

            if (isset($xml_decode['responseCode']) && "0" == $xml_decode['responseCode']) {
                $order->update_meta_data('_wc_maxipago_transaction_return_message', $xml_decode['processorMessage']);
                $order->update_meta_data('_wc_maxipago_transaction_installments', $installments);
                $order->update_meta_data('_wc_maxipago_transaction_id', $xml_decode['orderID']);
                $order->update_meta_data('_wc_maxipago_transaction_bin', $xml_decode['creditCardBin']);
                $order->update_meta_data('_wc_maxipago_transaction_last4', $xml_decode['creditCardLast4']);
                $order->update_meta_data('_wc_maxipago_transaction_nsu', $xml_decode['transactionID']);
                $order->update_meta_data('_wc_maxipago_transaction_reference_num', $referenceNum);
                $order->update_meta_data('_wc_maxipago_transaction_authorization_code', $xml_decode['authCode']);
                $order->update_meta_data('_wc_maxipago_transaction_environment', $environment);
                $order->update_meta_data('_wc_maxipago_transaction_holder', $cardData['card_holder']);
                $order->update_meta_data('_wc_maxipago_transaction_expiration', $creditExpiry);
                $order->update_meta_data('_wc_maxipago_total_amount', $order->get_total());
                $order->update_meta_data('_wc_maxipago_total_amount_converted', $order_total);
                $order->update_meta_data('_wc_maxipago_total_amount_is_converted', $convert_to_brl_enabled ? true : false);
                $order->update_meta_data('_wc_maxipago_exchange_rate', $exchange_rate_value);
                $order->update_meta_data('_wc_maxipago_decimal_value', $decimals);

                if ('sale' == $capture) {
                    $order->update_meta_data('_wc_rede_captured', true);
                    $order->set_date_paid(current_time('timestamp', true));
                    $order->update_status('processing');
                    apply_filters("integration_rede_for_woocommerce_change_order_status", $order, $this);
                }
                if ('auth' == $capture) {
                    $order->update_meta_data('_wc_rede_captured', false);
                    $order->update_status('on-hold');
                }
            } elseif (isset($xml_decode['responseCode']) && "1" == $xml_decode['responseCode']) {
                throw new Exception(LknIntegrationRedeForWoocommerceAbecsCodes::translate($xml_decode['processorCode'] ?? '', $xml_decode['processorMessage'] ?? ''));
            }
            if ('yes' == $this->debug) {
                // Convert XML to array for manipulation
                $requestArr = json_decode(json_encode(simplexml_load_string($xmlData)), true);
                // Build orderSummary
                $orderSummary = array(
                    'orderId' => $orderId,
                    'amount' => $order_total,
                    'orderCurrency' => $order_currency,
                    'currencyConverted' => $convert_to_brl_enabled ? 'BRL' : null,
                    'exchangeRateValue' => $exchange_rate_value,
                    'status' => $order->get_status()
                );
                // Place orderSummary inside payment, and add installments
                if (
                    isset($requestArr['order']) &&
                    isset($requestArr['order'][$capture]) &&
                    isset($requestArr['order'][$capture]['payment'])
                ) {
                    $requestArr['order'][$capture]['payment'] = $orderSummary;
                    $requestArr['order'][$capture]['payment']['installments'] = $installments;
                }
                $this->log->log('info', $this->id, array(
                    'request' => $requestArr,
                    'response' => $xml,
                ));
            }

            if ("INVALID REQUEST" == $xml_decode['responseMessage']) {
                throw new Exception($xml_decode['errorMessage']);
            }
            //Caso não exista nenhuma das Message, o Merchant ID ou Merchant Key estão invalidos
            if (! isset($xml_decode['processorMessage']) && ! isset($xml_decode['processorMessage'])) {
                throw new Exception(__("Merchant ID or Merchant Key is invalid!", 'woo-rede'));
            }

            $order->save();
        } catch (Exception $e) {
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

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = new WC_Order($order_id);
        if ($order->get_payment_method() === 'maxipago_credit') {

            $totalAmount = $order->get_meta('_wc_maxipago_total_amount');
            $environment = $this->get_option('environment');
            $orderId = $order->get_meta('_wc_maxipago_transaction_id');
            $referenceNum = $order->get_meta('_wc_maxipago_transaction_reference_num');
            $merchantId = sanitize_text_field($this->get_option('merchant_id'));
            $merchantKey = sanitize_text_field($this->get_option('merchant_key'));
            $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : 'BRL';

            // Get conversion meta
            $is_converted = $order->get_meta('_wc_maxipago_total_amount_is_converted');
            $exchange_rate = $order->get_meta('_wc_maxipago_exchange_rate');
            $decimals = $order->get_meta('_wc_maxipago_decimal_value');
            $amount_converted = $order->get_meta('_wc_maxipago_total_amount_converted');

            if (empty($order->get_meta('_wc_maxipago_transaction_canceled'))) {
                $amount = wc_format_decimal($amount);

                try {
                    if ($order->get_total() == $amount) {
                        $amount = $totalAmount;
                    }

                    // If converted, calculate refund in BRL using exchange rate
                    if ($is_converted && $exchange_rate) {
                        $amount_brl = floatval($amount) / floatval($exchange_rate);
                        // Format with correct decimals
                        $amount_brl = number_format($amount_brl, (int)$decimals, '.', '');
                        $amount = $amount_brl;
                    }

                    if ('production' === $environment) {
                        $apiUrl = 'https://api.maxipago.net/UniversalAPI/postXML';
                    } else {
                        $apiUrl = 'https://testapi.maxipago.net/UniversalAPI/postXML';
                    }

                    $xmlData = "<?xml version='1.0' encoding='UTF-8'?>
                        <transaction-request>
                            <version>3.1.1.15</version>
                            <verification>
                                <merchantId>$merchantId</merchantId>
                                <merchantKey>$merchantKey</merchantKey>
                            </verification>
                            <order>
                                <return>
                                    <orderID>$orderId</orderID>
                                    <referenceNum>$referenceNum</referenceNum>
                                    <payment>
                                        <chargeTotal>$amount</chargeTotal>
                                    </payment>
                                </return>
                            </order>
                        </transaction-request>
                    ";

                    $args = array(
                        'body' => $xmlData,
                        'headers' => array(
                            'Content-Type' => 'application/xml'
                        ),
                        'sslverify' => false // Desativa a verificação do certificado SSL
                    );

                    $response = wp_remote_post($apiUrl, $args);

                    if (is_wp_error($response)) {
                        $error_message = $response->get_error_message();
                        $order->add_order_note('[' . $this->id . '] Maxipago[Refund Error] ' . esc_attr($error_message));
                        $order->save();
                        return false;
                    } else {
                        $response_body = wp_remote_retrieve_body($response);
                        $xml = simplexml_load_string($response_body);
                    }

                    //Reconstruindo o $xml para facilitar o uso da variavel
                    $xml_encode = wp_json_encode($xml);
                    $xml_decode = json_decode($xml_encode, true);

                    if ("APPROVED" == $xml_decode['processorMessage']) {
                        update_post_meta($order_id, '_wc_maxipago_transaction_refund_id', $xml_decode['transactionID']);
                        update_post_meta($order_id, '_wc_maxipago_transaction_cancel_id', $xml_decode['transactionID']);
                        update_post_meta($order_id, '_wc_maxipago_transaction_canceled', true);

                        if ($is_converted) {
                            $formatted_amount = wc_price($amount, array('currency' => 'BRL'));
                        } else {
                            $formatted_amount = wc_price($amount, array('currency' => $order_currency));
                        }
                        $order->add_order_note(esc_attr__('Refunded:', 'woo-rede') . ' ' . $formatted_amount);
                    }

                    if ("INVALID REQUEST" == $xml_decode['responseMessage']) {
                        $order->add_order_note('Maxipago[Refund Error] ' . esc_attr__('Unknown error.', 'woo-rede'));
                        $order->save();
                        return false;
                    }

                    $order->save();

                    $this->log->log('info', $this->id, array(
                        'order' => array(
                            'amount' => $amount,
                            'totalAmount' => $totalAmount,
                            'totalOrder' => $order->get_total(),
                            'is_converted' => $is_converted,
                            'exchange_rate' => $exchange_rate,
                            'decimals' => $decimals,
                        ),
                    ));
                } catch (Exception $e) {
                    $order->add_order_note('[' . $this->id . '] Maxipago[Refund Error] ' . sanitize_text_field($e->getMessage()));
                    $order->save();
                    return false;
                }

                return true;
            } else {
                $order->add_order_note(
                    '[' . $this->id . '] Maxipago[Refund Error] ' . esc_attr__('Total refund already processed, check the order notes block.', 'woo-rede')
                );
                $order->save();
                return false;
            }
        } else {
            return false;
        }
    }

    public function validateCpfCnpj($cpfCnpj)
    {
        // Remove caracteres especiais
        $cpfCnpj = preg_replace('/[^0-9]/', '', $cpfCnpj);

        // Verifica se é CPF
        if (strlen($cpfCnpj) === 11) {
            // Verifica se todos os dígitos são iguais
            if (preg_match('/(\d)\1{10}/', $cpfCnpj)) {
                return false;
            }

            // Calcula o primeiro dígito verificador
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += (int) ($cpfCnpj[$i]) * (10 - $i);
            }
            $digit1 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);

            // Calcula o segundo dígito verificador
            $sum = 0;
            for ($i = 0; $i < 10; $i++) {
                $sum += (int) ($cpfCnpj[$i]) * (11 - $i);
            }
            $digit2 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);

            // Verifica se os dígitos verificadores estão corretos
            if ($cpfCnpj[9] == $digit1 && $cpfCnpj[10] == $digit2) {
                return true;
            } else {
                return false;
            }
        }
        // Verifica se é CNPJ
        elseif (strlen($cpfCnpj) === 14) {
            // Verifica se todos os dígitos são iguais
            if (preg_match('/(\d)\1{13}/', $cpfCnpj)) {
                return false;
            }

            // Calcula o primeiro dígito verificador
            $sum = 0;
            $weights = array(5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2);
            for ($i = 0; $i < 12; $i++) {
                $sum += (int) ($cpfCnpj[$i]) * $weights[$i];
            }
            $digit1 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);

            // Calcula o segundo dígito verificador
            $sum = 0;
            $weights = array(6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2);
            for ($i = 0; $i < 13; $i++) {
                $sum += (int) ($cpfCnpj[$i]) * $weights[$i];
            }
            $digit2 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);

            // Verifica se os dígitos verificadores estão corretos
            if ($cpfCnpj[12] == $digit1 && $cpfCnpj[13] == $digit2) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function displayMeta($order): void
    {
        if ($order->get_payment_method() === 'maxipago_credit') {
            $metaKeys = array(
                '_wc_maxipago_transaction_environment' => esc_attr__('Environment', 'woo-rede'),
                '_wc_maxipago_transaction_return_message' => esc_attr__('Return Message', 'woo-rede'),
                '_wc_maxipago_transaction_id' => esc_attr__('Transaction ID', 'woo-rede'),
                '_wc_maxipago_transaction_nsu' => esc_attr__('Nsu', 'woo-rede'),
                '_wc_maxipago_transaction_authorization_code' => esc_attr__('Authorization Code', 'woo-rede'),
                '_wc_maxipago_transaction_bin' => esc_attr__('Bin', 'woo-rede'),
                '_wc_maxipago_transaction_last4' => esc_attr__('Last 4', 'woo-rede'),
                '_wc_maxipago_transaction_installments' => esc_attr__('Installments', 'woo-rede'),
                '_wc_maxipago_transaction_holder' => esc_attr__('Cardholder', 'woo-rede'),
                '_wc_maxipago_transaction_expiration' => esc_attr__('Card Expiration', 'woo-rede'),
                '_wc_maxipago_transaction_reference_num' => esc_attr__('Reference Number', 'woo-rede')
            );

            $this->generateMetaTable($order, $metaKeys, 'Maxipago');
        }
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
        wp_enqueue_style('woo-maxipago-style', $plugin_url . 'Public/css/maxipago/styleMaxipagoCredit.css', array(), '1.0.0', 'all');

        wp_enqueue_script('woo-maxipago-js', $plugin_url . 'Public/js/creditCard/maxipago/wooMaxipagoCredit.js', array(), '1.0.0', true);
        wp_enqueue_script('woo-rede-animated-card-jquery', $plugin_url . 'Public/js/jquery.card.js', array('jquery', 'woo-maxipago-js'), '2.5.0', true);

        wp_localize_script('woo-maxipago-js', 'wooMaxipagoVars', array(
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('maxipago_payment_fields_nonce'),
        ));

        apply_filters('integration_rede_for_woocommerce_set_custom_css', get_option('woocommerce_maxipago_credit_settings')['custom_css_short_code'] ?? false);
    }

    public function getMerchantAuth()
    {
        $plugin_url = plugin_dir_url(LknIntegrationRedeForWoocommerceWcRede::FILE) . '../';

        return array(
            'merchantId' => $this->get_option('merchant_id'),
            'merchantKey' => $this->get_option('merchant_key'),
            'environment' => $this->get_option('environment')
        );
        wp_enqueue_script('woo-maxipago-js', $plugin_url . 'Public/js/creditCard/maxipago/wooMaxipagoCredit.js', array(), '1.0.0', true);
        wp_enqueue_script('woo-rede-animated-card-jquery', $plugin_url . 'Public/js/jquery.card.js', array('jquery', 'woo-maxipago-js'), '2.5.0', true);

        wp_localize_script('woo-maxipago-js', 'wooMaxipagoVars', array(
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('maxipago_payment_fields_nonce'),
        ));
    }
    /**
     * Renderiza os campos de pagamento com total atualizado (para AJAX)
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
}
