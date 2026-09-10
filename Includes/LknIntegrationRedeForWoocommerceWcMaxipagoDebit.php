<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use Exception;
use WC_Order;

final class LknIntegrationRedeForWoocommerceWcMaxipagoDebit extends LknIntegrationRedeForWoocommerceWcRedeAbstract
{
    public function __construct()
    {
        $this->id = 'maxipago_debit';
        $this->method_title = esc_attr__('Pay with the Maxipago Debit', 'woo-rede');
        $this->method_description = esc_attr__('Enables and configures payments with Maxipago Debit', 'woo-rede');
        $this->title = 'Maxipago';
        $this->has_fields = true;
        $this->supports = array(
            'products',
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
        $this->configs = $this->getConfigsMaxipagoDebit();

        $this->debug = $this->get_option('debug');

        $this->log = $this->get_logger();
    }

    /**
     * Fields validation.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if (empty(sanitize_text_field(wp_unslash($_POST['maxipago_debit_cpf']))) && empty(sanitize_text_field(wp_unslash($_POST['billing_cpf']))) && empty(sanitize_text_field(wp_unslash($_POST['billing_cnpj'])))) {
            wc_add_notice(esc_attr__('CPF is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (empty(sanitize_text_field(wp_unslash($_POST['maxipago_debit_number'])))) {
            wc_add_notice(esc_attr__('Card number is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (empty(sanitize_text_field(wp_unslash($_POST['maxipago_debit_expiry'])))) {
            wc_add_notice(esc_attr__('Card expiration is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (empty(sanitize_text_field(wp_unslash($_POST['maxipago_debit_cvc'])))) {
            wc_add_notice(esc_attr__('Card security code is a required field', 'woo-rede'), 'error');

            return false;
        }

        if (! ctype_digit(sanitize_text_field(wp_unslash($_POST['maxipago_debit_cvc'])))) {
            wc_add_notice(esc_attr__('Card security code must be a numeric value', 'woo-rede'), 'error');
            return false;
        }

        if (strlen(sanitize_text_field(wp_unslash($_POST['maxipago_debit_cvc']))) < 3) {
            wc_add_notice(esc_attr__('Card security code must be at least 3 digits long', 'woo-rede'), 'error');
            return false;
        }

        if (empty(sanitize_text_field(wp_unslash($_POST['maxipago_debit_holder_name'])))) {
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
    public function getConfigsMaxipagoDebit()
    {
        $configs = array();

        $configs['basePath'] = INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/logs/';
        $configs['base'] = $configs['basePath'] . gmdate('d.m.Y-H.i.s') . '.maxipagoDebit.log';
        $configs['debug'] = $this->get_option('debug');

        return $configs;
    }

    public function initFormFields(): void
    {
        LknIntegrationRedeForWoocommerceHelper::updateFixLoadScriptOption($this->id);

        wp_enqueue_script(
            'lkn-integration-rede-for-woocommerce-endpoint',
            plugin_dir_url(__FILE__) . '../Admin/js/lkn-integration-rede-for-woocommerce-endpoint.js',
            array('jquery', 'wp-api'),
            INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION,
            false
        );

        wp_localize_script('lkn-integration-rede-for-woocommerce-endpoint', 'lknRedeForWoocommerceProSettings', array(
            'endpointStatus' => get_option('LknIntegrationRedeForWoocommerceMaxipagoDebitEndpointStatus', false),
            'translations' => array(
                'endpointSuccess' => __('Request received!', 'woo-rede'),
                'endpointError' => __('No requests received!', 'woo-rede'),
                'howToConfigure' => __('How to Configure', 'woo-rede'),
            ),
        ));

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
                'desc_tip' => esc_attr__('Check this box and save to enable debit card settings', 'woo-rede'),
                'description' => esc_attr__('Enable or disable the debit card payment method.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("Enable this option to allow customers to pay with debit cards using maxipago API.", 'woo-rede')
                ),
            ),
            'title' => array(
                'title' => __('Title', 'woo-rede'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woo-rede'),
                'desc_tip' => esc_attr__('Enter the title that will be shown to customers during the checkout process.', 'woo-rede'),
                'description' => esc_attr__('This controls the title which the user sees during checkout.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("This text will appear as the payment method title during checkout. Choose something your customers will easily understand, like “Pay with debit card”.", 'woo-rede')
                ),
            ),

            'endpoint' => array(
                'title' => esc_attr__('Endpoint', 'woo-rede'),
                'type' => 'text',
                'desc_tip' => esc_attr__('Return URL to automatically update the status of orders paid via debit on the Maxipago.', 'woo-rede'),
            ),

            'company_name' => array(
                'title' => __('Seller Company Name', 'woo-rede'),
                'type' => 'text',
                'desc_tip' => esc_attr__("Name that appears on the cardholder's statement.", 'woo-rede'),
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

            'description' => array(
                'title' => __('Description', 'woo-rede'),
                'type' => 'textarea',
                'default' => __('Pay for your purchase with a debit card through ', 'woo-rede'),
                'desc_tip' => esc_attr__('This description appears below the payment method title at checkout. Use it to inform your customers about the payment processing details.', 'woo-rede'),
                'description' => esc_attr__('Payment method description that the customer will see on your checkout.', 'woo-rede'),
                'custom_attributes' => array(
                    'data-title-description' => esc_attr__("Provide a brief message that informs the customer how the payment will be processed. For example: “Your payment will be securely processed by Rede.”", 'woo-rede')
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

        $customConfigs = apply_filters('integration_rede_for_woocommerce_get_custom_configs', $this->form_fields, array(), $this->id);

        if (! empty($customConfigs)) {
            $this->form_fields = array_merge($this->form_fields, $customConfigs);
        }
    }

    protected function getCheckoutForm($order_total = 0): void
    {
        wc_get_template(
            'debitCard/maxipagoPaymentDebitForm.php',
            array(),
            'woocommerce/maxipago/',
            LknIntegrationRedeForWoocommerceWcRede::getTemplatesPath()
        );
    }

    public function regOrderLogs($xmlData, $xml, $orderId, $order, $apiUrl, $orderTotal = null)
    {
        if ('yes' == $this->debug) {
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

            // Convert XML to array for manipulation
            $requestArr = json_decode(json_encode(simplexml_load_string($xmlData)), true);
            // Build orderSummary
            $orderSummary = array(
                'orderId' => $orderId,
                'amount' => isset($orderTotal) ? $orderTotal : $order->get_total(),
                'orderCurrency' => $order_currency,
                'currencyConverted' => $convert_to_brl_enabled ? 'BRL' : null,
                'exchangeRateValue' => $exchange_rate_value,
                'status' => $order->get_status()
            );

            // Place orderSummary inside payment
            if (
                isset($requestArr['order']) &&
                isset($requestArr['order']['debitSale']) &&
                isset($requestArr['order']['debitSale']['payment'])
            ) {
                $requestArr['order']['debitSale']['payment'] = $orderSummary;
            }
            // Remove userAgent and device from log
            if (
                isset($requestArr['order']) &&
                isset($requestArr['order']['debitSale'])
            ) {
                unset($requestArr['order']['debitSale']['userAgent']);
                unset($requestArr['order']['debitSale']['device']);
            }

            if (is_object($xml) && method_exists($xml, 'get_error_message')) {
                $responseArr = $xml->get_error_message();
            } elseif (is_array($xml) && isset($xml['body'])) {
                $responseArr = json_decode(json_encode(simplexml_load_string($xml['body'])), true);
            } elseif (is_string($xml)) {
                $responseArr = json_decode(json_encode(simplexml_load_string($xml)), true);
            } else {
                $responseArr = json_decode(json_encode($xml), true);
            }

            $this->log->log('info', $this->id, array(
                'request' => $requestArr,
                'response' => $responseArr,
            ));

            // Monta o xmlBody como array, censura dados sensíveis, atualiza payment, remove userAgent/device
            $xmlBodyObj = simplexml_load_string($xmlData);
            $cardNumber = $xmlBodyObj->order->debitSale->transactionDetail->payType->debitCard->number;
            $xmlBodyObj->verification->merchantId = LknIntegrationRedeForWoocommerceHelper::censorString($xmlBodyObj->verification->merchantId, 3);
            $xmlBodyObj->verification->merchantKey = LknIntegrationRedeForWoocommerceHelper::censorString($xmlBodyObj->verification->merchantKey, 12);
            $xmlBodyObj->order->debitSale->transactionDetail->payType->debitCard->number = LknIntegrationRedeForWoocommerceHelper::censorString($cardNumber, 8);
            $xmlBodyArr = json_decode(json_encode($xmlBodyObj), true);

            // Place orderSummary inside payment
            if (
                isset($xmlBodyArr['order']) &&
                isset($xmlBodyArr['order']['debitSale']) &&
                isset($xmlBodyArr['order']['debitSale']['payment'])
            ) {
                $xmlBodyArr['order']['debitSale']['payment'] = $orderSummary;
            }

            if (
                isset($xmlBodyArr['order']) &&
                isset($xmlBodyArr['order']['debitSale'])
            ) {
                unset($xmlBodyArr['order']['debitSale']['userAgent']);
                unset($xmlBodyArr['order']['debitSale']['device']);
            }

            $response_body = wp_remote_retrieve_body($xml);
            $xml = simplexml_load_string($response_body);
            $xml_encode = wp_json_encode($xml);
            $xml_decode = json_decode($xml_encode, true);
            $orderLogsArray = array(
                'url' => $apiUrl,
                'body' => $xmlBodyArr,
                'response' => $xml_decode,
            );

            $orderLogs = json_encode($orderLogsArray);
            $order->update_meta_data('lknWcRedeOrderLogs', $orderLogs);

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

            $order->save();
        }
    }

    public function process_payment($orderId)
    {
        if (isset($_POST['maxipago_debit_nonce']) && ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['maxipago_debit_nonce'])), 'maxipago_debit_nonce')) {
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

        $order_total = wc_format_decimal($order_total, $decimals);

        $woocommerceCountry = get_option('woocommerce_default_country');
        // Extraindo somente o país da string
        $countryParts = explode(':', $woocommerceCountry);
        $countryCode = $countryParts[0];

        $merchantId = sanitize_text_field($this->get_option('merchant_id'));
        $merchantKey = sanitize_text_field($this->get_option('merchant_key'));
        $referenceNum = uniqid('order_', true);
        $browser = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        $debitExpiry = isset($_POST['maxipago_debit_expiry']) ? sanitize_text_field(wp_unslash($_POST['maxipago_debit_expiry'])) : '';

        if (strpos($debitExpiry, '/') !== false) {
            $expiration = explode('/', $debitExpiry);
        } else {
            $expiration = array(
                substr($debitExpiry, 0, 2),
                substr($debitExpiry, -2, 2),
            );
        }
        if (isset($_POST['billing_cpf']) && '' === sanitize_text_field(wp_unslash($_POST['billing_cpf']))) {
            $_POST['billing_cpf'] = isset($_POST['billing_cnpj']) ? sanitize_text_field(wp_unslash($_POST['billing_cnpj'])) : '';
        }
        if (isset($_POST['maxipago_debit_cpf']) && sanitize_text_field(wp_unslash($_POST['maxipago_debit_cpf'])) !== '') {
            $_POST['billing_cpf'] = isset($_POST['maxipago_debit_cpf']) ? sanitize_text_field(wp_unslash($_POST['maxipago_debit_cpf'])) : '';
        }
        $clientData = array(
            'billing_cpf' => isset($_POST['billing_cpf']) ? sanitize_text_field(wp_unslash($_POST['billing_cpf'])) : '',
            'billing_name' => isset($_POST['maxipago_debit_holder_name']) ? sanitize_text_field(wp_unslash($_POST['maxipago_debit_holder_name'])) : '',
            'billing_address_1' => isset($_POST['billing_address_1']) ? sanitize_text_field(wp_unslash($_POST['billing_address_1'])) : '',
            'billing_district' => isset($_POST['billingNeighborhood']) ? sanitize_text_field(wp_unslash($_POST['billingNeighborhood'])) : '',
            'billing_city' => isset($_POST['billing_city']) ? sanitize_text_field(wp_unslash($_POST['billing_city'])) : '',
            'billing_state' => isset($_POST['billing_state']) ? sanitize_text_field(wp_unslash($_POST['billing_state'])) : '',
            'billing_postcode' => isset($_POST['billing_postcode']) ? sanitize_text_field(wp_unslash($_POST['billing_postcode'])) : '',
            'billing_phone' => isset($_POST['billing_phone']) ? sanitize_text_field(wp_unslash($_POST['billing_phone'])) : '',
            'billing_email' => isset($_POST['billing_email']) ? sanitize_text_field(wp_unslash($_POST['billing_email'])) : '',
            'currency_code' => get_option('woocommerce_currency'),
            'country' => $countryCode,
        );

        $cardData = array(
            'card_number' => preg_replace('/[^\d]/', '', isset($_POST['maxipago_debit_number']) ? sanitize_text_field(wp_unslash($_POST['maxipago_debit_number'])) : ''),
            'card_expiration_month' => sanitize_text_field($expiration[0]),
            'card_expiration_year' => $this->normalize_expiration_year(sanitize_text_field($expiration[1])),
            'card_cvv' => isset($_POST['maxipago_debit_cvc']) ? sanitize_text_field(wp_unslash($_POST['maxipago_debit_cvc'])) : '',
            'card_holder' => isset($_POST['maxipago_debit_holder_name']) ? sanitize_text_field(wp_unslash($_POST['maxipago_debit_holder_name'])) : '',
        );

        try {
            $environment = $this->get_option('environment');

            $valid = $this->validate_card_number($cardData['card_number']);
            if (false === $valid) {
                throw new Exception(__('Please enter a valid debit card number', 'woo-rede'));
            }

            $valid = $this->validate_card_fields($_POST);
            if (false === $valid) {
                throw new Exception(__('One or more invalid fields', 'woo-rede'), 500);
            }

            if (! $this->validateCpfCnpj($clientData['billing_cpf'])) {
                throw new Exception(__("Please enter a valid cpf number", 'woo-rede'));
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
                            <debitSale>
                                <customerIdExt>" . $clientData['billing_cpf'] . "</customerIdExt>
                                <processorID>5</processorID>
                                <referenceNum>$referenceNum</referenceNum>
                                <fraudCheck>N</fraudCheck>
                                <authentication>
                                    <mpiProcessorID>41</mpiProcessorID>
                                    <onFailure>decline</onFailure>
                                </authentication>
                                <billing> 
                                    <name>" . $clientData['billing_name'] . "</name>
                                    <address>" . $clientData['billing_address_1'] . "</address>
                                    <city>" . $clientData['billing_city'] . "</city>
                                    <district>" . $clientData['billing_district'] . "</district>
                                    <state>" . $clientData['billing_state'] . "</state>
                                    <postalcode>" . $clientData['billing_postcode'] . "</postalcode>
                                    <country>" . $clientData['country'] . "</country>
                                    <email>" . $order->get_billing_email() . "</email>
                                </billing>
                                <shipping>
                                    <name>" . $clientData['billing_name'] . "</name>
                                    <address>" . $clientData['billing_address_1'] . "</address>
                                    <city>" . $clientData['billing_city'] . "</city>
                                    <state>" . $clientData['billing_state'] . "</state>
                                    <postalcode>" . $clientData['billing_postcode'] . "</postalcode>
                                    <country>" . $clientData['country'] . "</country>
                                </shipping>
                                <transactionDetail>
                                    <payType>
                                        <debitCard>
                                            <number>" . $cardData['card_number'] . "</number>
                                            <expMonth>" . $cardData['card_expiration_month'] . "</expMonth>
                                            <expYear>" . $cardData['card_expiration_year'] . "</expYear>
                                            <cvvNumber>" . $cardData['card_cvv'] . "</cvvNumber>
                                            <storageCard>0</storageCard>
                                            <credentialId>02</credentialId>
                                        </debitCard>
                                    </payType>
                                </transactionDetail>
                                <payment>
                                    <chargeTotal>" . $order_total . "</chargeTotal>
                                    <currencyCode>" . $order_currency . "</currencyCode>
                                </payment>
                                <userAgent>$browser</userAgent>
                                <device>
                                    <colorDepth>1</colorDepth>
                                    <deviceType3ds>BROWSER</deviceType3ds>
                                    <javaEnabled>true</javaEnabled>
                                    <language>BR</language>
                                    <screenHeight>550</screenHeight>
                                    <screenWidth>550</screenWidth>
                                    <timeZoneOffset>3</timeZoneOffset>
                                </device>
                            </debitSale>
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


            if (isset($xml_decode['responseCode']) && "0" == $xml_decode['responseCode']) {
                $order->update_meta_data('_wc_maxipago_transaction_return_message', $xml_decode['processorMessage']);
                $order->update_meta_data('_wc_maxipago_transaction_id', $xml_decode['orderID']);
                if (isset($xml_decode['debitCardBin'])) {
                    $order->update_meta_data('_wc_maxipago_transaction_bin', $xml_decode['debitCardBin']);
                }
                if (isset($xml_decode['debitCardLast4'])) {
                    $order->update_meta_data('_wc_maxipago_transaction_last4', $xml_decode['debitCardLast4']);
                }
                $order->update_meta_data('_wc_maxipago_transaction_nsu', $xml_decode['transactionID']);
                $order->update_meta_data('_wc_maxipago_transaction_reference_num', $referenceNum);
                $order->update_meta_data('_wc_maxipago_transaction_authorization_code', $xml_decode['authCode']);
                $order->update_meta_data('_wc_maxipago_transaction_environment', $environment);
                $order->update_meta_data('_wc_maxipago_transaction_holder', $cardData['card_holder']);
                $order->update_meta_data('_wc_maxipago_transaction_expiration', $debitExpiry);
                $order->set_date_paid(current_time('timestamp', true));
                $order->update_status('processing');
                apply_filters("integration_rede_for_woocommerce_change_order_status", $order, $this);
            } elseif (isset($xml_decode['responseCode']) && "1" == $xml_decode['responseCode']) {
                throw new Exception(LknIntegrationRedeForWoocommerceAbecsCodes::translate($xml_decode['processorCode'] ?? '', $xml_decode['processorMessage'] ?? ''));
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

        if (isset($xml_decode['authenticationURL'])) {
            $order->update_status('pending');

            return array(
                'result' => 'success',
                'redirect' => $xml_decode['authenticationURL'],
            );
        }

        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
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
        if ($order->get_payment_method() === 'maxipago_debit') {
            $metaKeys = array(
                '_wc_maxipago_transaction_environment' => esc_attr__('Environment', 'woo-rede'),
                '_wc_maxipago_transaction_return_message' => esc_attr__('Return Message', 'woo-rede'),
                '_wc_maxipago_transaction_id' => esc_attr__('Transaction ID', 'woo-rede'),
                '_wc_maxipago_transaction_nsu' => esc_attr__('Nsu', 'woo-rede'),
                '_wc_maxipago_transaction_authorization_code' => esc_attr__('Authorization Code', 'woo-rede'),
                '_wc_maxipago_transaction_bin' => esc_attr__('Bin', 'woo-rede'),
                '_wc_maxipago_transaction_last4' => esc_attr__('Last 4', 'woo-rede'),
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
        wp_enqueue_style('woo-maxipago-debit-style', $plugin_url . 'Public/css/maxipago/styleMaxipagoDebit.css', array(), '1.0.0', 'all');

        wp_enqueue_script('woo-maxipago-debit-js', $plugin_url . 'Public/js/debitCard/maxipago/wooMaxipagoDebit.js', array(), '1.0.0', true);
        wp_enqueue_script('woo-rede-animated-card-jquery', $plugin_url . 'Public/js/jquery.card.js', array('jquery', 'woo-maxipago-debit-js'), '2.5.0', true);

        wp_localize_script('woo-maxipago-debit-js', 'wooMaxipago', array(
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('maxipago_debit_payment_fields_nonce'),
        ));

        apply_filters('integration_rede_for_woocommerce_set_custom_css', get_option('woocommerce_maxipago_debit_settings')['custom_css_short_code'] ?? false);
    }
}
