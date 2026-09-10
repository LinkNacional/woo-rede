<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use DateTime;
use Exception;
use WC_Logger;
use WC_Payment_Gateway;

final class LknIntegrationRedeForWoocommerceWcPixRede extends WC_Payment_Gateway
{
    public $configs;
    public $log;
    public $debug;

    public function __construct()
    {
        $this->id = 'integration_rede_pix';
        $this->title = 'Integration Rede Pix';
        $this->has_fields = true;
        $this->method_title = esc_attr__('Pay with the Rede Pix FREE', 'woo-rede');
        $this->method_description = esc_attr__('Enables and configures payments with Rede Pix', 'woo-rede') . '<a target="_blank" href="https://www.linknacional.com.br/wordpress/woocommerce/rede/doc/">' . esc_attr__('Documentation', 'woo-rede') . '</a>';
        $this->supports = array(
            'products',
            'refunds',
        );

        $this->icon = LknIntegrationRedeForWoocommerceHelper::getUrlIcon();

        // Define os campos de configuração do método de pagamento
        $this->init_form_fields();
        $this->init_settings();

        // Define as configurações do método de pagamento
        $this->title = $this->get_option('title');
        $this->log = $this->get_logger();
        $this->debug = $this->get_option('debug');
    }

    public function init_form_fields(): void
    {
        if (isset($_GET['page']) && isset($_GET['section'])) {
            if ('wc-settings' == sanitize_text_field(wp_unslash($_GET['page'])) && sanitize_text_field(wp_unslash($_GET['section'])) == $this->id) {
                wp_enqueue_script(
                    'lkn-integration-rede-for-woocommerce-endpoint',
                    plugin_dir_url(__FILE__) . '../Admin/js/lkn-integration-rede-for-woocommerce-endpoint.js',
                    array('jquery', 'wp-api'),
                    INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION,
                    false
                );

                wp_localize_script('lkn-integration-rede-for-woocommerce-endpoint', 'lknRedeForWoocommerceProSettings', array(
                    'endpointStatus' => get_option('lknRedeForWoocommerceProEndpointStatus', false),
                    'translations' => array(
                        'endpointSuccess' => __('Request received!', 'woo-rede'),
                        'endpointError' => __('No requests received!', 'woo-rede'),
                        'howToConfigure' => __('How to Configure', 'woo-rede'),
                    ),
                ));

                $this->form_fields = array(
                    'rede' => array(
                        'title' => esc_attr__('General', 'woo-rede'),
                        'type' => 'title',
                    ),
                    'enabled' => array(
                        'title' => esc_attr__('Payment with Rede Pix', 'woo-rede'),
                        'type' => 'checkbox',
                        'label' => __('Enable', 'woo-rede'),
                        'default' => 'no',
                        'desc_tip' => esc_attr__('Check this box and save to enable pix settings.', 'woo-rede'),
                        'description' => esc_attr__('Enable or disable the pix payment method.', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__("Enable this option to allow customers to pay with pix using Rede API.", 'woo-rede')
                        ),
                    ),
                    'title' => array(
                        'title' => esc_attr__('Title', 'woo-rede'),
                        'type' => 'text',
                        'default' => __('Pay with the Rede Pix', 'woo-rede'),
                        'desc_tip' => esc_attr__('Enter the title that will be shown to customers during the checkout process.', 'woo-rede'),
                        'description' => esc_attr__('This controls the title which the user sees during checkout.', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__("This text will appear as the payment method title during checkout. Choose something your customers will easily understand, like “Pay with Pix (Rede)”.", 'woo-rede')
                        ),
                    ),
                    'description' => array(
                        'title' => __('Description', 'woo-rede'),
                        'type' => 'textarea',
                        'default' => __('Pay for your purchase with a pix through ', 'woo-rede'),
                        // ADICIONAR:
                        'desc_tip' => esc_attr__('This description appears below the payment method title at checkout. Use it to inform your customers about the payment processing details.', 'woo-rede'),
                        'description' => esc_attr__('Payment method description that the customer will see on your checkout.', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__("Provide a brief message that informs the customer how the payment will be processed. For example: “Your payment will be securely processed by Rede.”", 'woo-rede')
                        ),
                    ),
                    'endpoint' => array(
                        'title' => esc_attr__('Endpoint', 'woo-rede'),
                        'type' => 'text',
                        'desc_tip' => esc_attr__('Return URL to automatically update the status of orders paid via debit on the Rede.', 'woo-rede'),
                        'default' => site_url('/wp-json/rede/v1/webhook')
                    ),
                    'pv' => array(
                        'title' => esc_attr__('PV', 'woo-rede'),
                        'type' => 'password',
                        'desc_tip' => esc_attr__('Your Rede PV (affiliation number).', 'woo-rede'),
                        'description' => esc_attr__('Rede credentials.', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__("Your Rede PV (affiliation number) should be provided here.", 'woo-rede')
                        ),
                        'default' => '',
                    ),
                    'token' => array(
                        'title' => esc_attr__('Token', 'woo-rede'),
                        'type' => 'password',
                        'desc_tip' => esc_attr__('Your Rede Token.', 'woo-rede'),
                        'description' => esc_attr__('Rede credentials.', 'woo-rede'),
                        'custom_attributes' => array(
                            'data-title-description' => esc_attr__("Your Rede Token should be placed here.", 'woo-rede')
                        ),
                        'default' => '',
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
                    'fake_convert_to_brl' => array(
                        'title' => __('Currency Converter', 'woo-rede'),
                        'type' => 'checkbox',
                        'description' => __('If enabled, automatically converts the order amount to BRL when processing payment.', 'woo-rede'),
                        'desc_tip' => true,
                        'label' => __('Convert to BRL', 'woo-rede'),
                        'default' => 'no',
                        'custom_attributes' => array(
                            'readonly' => 'readonly',
                            'disabled' => 'disabled'
                        )
                    ),
                    'expiration_count' => array(
                        'title' => esc_attr__('PIX validity in hours', 'woo-rede'),
                        'type' => 'number',
                        'description' => esc_attr__('Enter the validity time of Pix in hours.', 'woo-rede'),
                        'desc_tip' => true,
                        'default' => '24',
                        'required' => true,
                        'custom_attributes' => array(
                            'min' => '1',
                            'step' => '1',
                            'readonly' => 'readonly',
                        ),
                    ),
                    'payment_complete_status' => array(
                        'title' => esc_attr__('Payment Complete Status', 'woo-rede'),
                        'type' => 'select',
                        'class' => 'wc-enhanced-select',
                        'desc_tip' => true,
                        'description' => esc_attr__('Select the order status after payment confirmation.', 'woo-rede'),
                        'options' => array(
                            'processing' => _x('Processing', 'Order status', 'woo-rede'),
                            'on-hold' => _x('On hold', 'Order status', 'woo-rede'),
                            'completed' => _x('Completed', 'Order status', 'woo-rede'),
                        ),
                        'default' => 'processing',
                        'custom_attributes' => array(
                            'disabled' => 'disabled',
                        ),
                    ),
                    'show_button' => array(
                        'title' => esc_attr__('Botão Gerar PIX', 'woo-rede'),
                        'type' => 'checkbox',
                        'label' => __('Habilitar', 'woo-rede'),
                        'desc_tip' => true,
                        'description' => esc_attr__('Exibe o botão "Finalizar e Gerar PIX" no checkout.', 'woo-rede'),
                        'default' => 'no',
                        'custom_attributes' => array(
                            'disabled' => 'disabled',
                        ),
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

                $this->form_fields['transactions'] = array(
                    'title' => esc_attr__('Transactions', 'woo-rede'),
                    'id' => 'transactions_title',
                    'type'  => 'title',
                );
            }
        }
    }

    public function payment_fields(): void
    {
        wc_get_template(
            '/pixRedePaymentPaymentFields.php',
            array(),
            'woocommerce/integration-rede/',
            plugin_dir_path(__FILE__) . 'templates/pix/'
        );
        wp_enqueue_style('integration-rede-pix-style', INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Public/css/rede/LknIntegrationRedeForWoocommercePaymentFields.css', array(), '1.0.0', 'all');
    }

    final public function get_logger()
    {
        if (class_exists('WC_Logger')) {
            return new WC_Logger();
        } else {
            global $woocommerce;

            return $woocommerce->logger();
        }
    }

    public function process_payment($orderId)
    {
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

        $valid = true;

        try {
            $pixInfos = array(
                'generated' => $order->get_meta('_wc_rede_pix_integration_transaction_pix_generated'),
                'amount' => $order->get_meta('_wc_rede_pix_integration_transaction_amount'),
            );
            $total = str_replace(".", "", $order->get_total());
            if (empty($pixInfos['generated']) || $total != $pixInfos['amount']) {
                $reference = $orderId;
                if ($total != $pixInfos['amount'] && !empty($pixInfos['amount'])) {
                    $reference = uniqid();
                }

                $reference = $reference . '-' . time();

                if (strlen($reference) > 20) {
                    $reference = substr($reference, 0, 20);
                }

                $pix = LknIntegrationRedeForWoocommerceWcPixHelper::getPixRede($order->get_total(), $this, $reference, $order);
                
                // Definir data de expiração do PIX
                $pixExpiration = 'N/A';
                if (isset($pix['qrCodeResponse']['dateTimeExpiration'])) {
                    try {
                        $dateTime = new DateTime($pix['qrCodeResponse']['dateTimeExpiration']);
                        $pixExpiration = $dateTime->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        $pixExpiration = 'N/A';
                    }
                }
                
                // Verificar se a resposta PIX contém o returnCode
                if (!is_array($pix) || !isset($pix['returnCode'])) {
                    throw new Exception(__('Invalid PIX response from Rede API.', 'woo-rede'));
                }
                
                if ("25" == $pix['returnCode'] || "89" == $pix['returnCode']) {
                    // Salvar metadados da transação para erro de credenciais
                    $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : get_option('woocommerce_currency', 'BRL');
                    $customErrorResponse = LknIntegrationRedeForWoocommerceHelper::createCustomErrorResponse(
                        401,
                        $pix['returnCode'] == '25' ? 24 : 89,
                        $pix['returnCode'] == '25' ? __('Affiliation: Invalid parameter size', 'woo-rede') : __('Token: Invalid token', 'woo-rede')
                    );
                    LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                        $order, $customErrorResponse, isset($pix['tid']) ? $pix['tid'] : 'N/A', $pixExpiration, $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        1, $order->get_total(), $order_currency, '', $this->get_option('pv'), $this->get_option('token'),
                        $reference, $orderId, true, 'Pix', 'N/A',
                        $this, '', '', '', $pix['returnCode'], $pix['returnMessage'] ?? ''
                    );
                    $order->save();
                    
                    throw new Exception(LknIntegrationRedeForWoocommerceAbecsCodes::translate($pix['returnCode'], $pix['returnMessage'] ?? __('PV or Token is invalid!', 'woo-rede')));
                }
                if ("00" != $pix['returnCode']) {
                    if ('yes' == $this->debug) {
                        $this->log->log('info', $this->id, array(
                            'order' => array(
                                'requestResponse' => $pix,
                            ),
                        ));
                    }
                    
                    // Salvar metadados da transação para erro geral PIX
                    $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : get_option('woocommerce_currency', 'BRL');
                    $customErrorResponse = LknIntegrationRedeForWoocommerceHelper::createCustomErrorResponse(
                        400,
                        33,
                        __('Failed', 'woo-rede')
                    );
                    LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                        $order, $customErrorResponse, isset($pix['tid']) ? $pix['tid'] : 'N/A', $pixExpiration, $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        1, $order->get_total(), $order_currency, '', $this->get_option('pv'), $this->get_option('token'),
                        $reference, $orderId, true, 'Pix', 'N/A',
                        $this, '', '', '', $pix['returnCode'] ?? '', $pix['returnMessage'] ?? __('An error occurred while processing the payment.', 'woo-rede')
                    );
                    $order->save();
                    
                    throw new Exception(LknIntegrationRedeForWoocommerceAbecsCodes::translate($pix['returnCode'] ?? '', $pix['returnMessage'] ?? __('An error occurred while processing the payment.', 'woo-rede')));
                }

                // Validar se todos os campos necessários estão presentes na resposta PIX
                $required_fields = array('reference', 'tid', 'amount', 'qrCodeResponse');
                foreach ($required_fields as $field) {
                    if (!isset($pix[$field])) {
                        // Salvar metadados da transação para erro de resposta incompleta
                        $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : get_option('woocommerce_currency', 'BRL');
                        $customErrorResponse = LknIntegrationRedeForWoocommerceHelper::createCustomErrorResponse(
                            400,
                            44,
                            __('Internal error occurred. Please, contact Rede', 'woo-rede')
                        );
                        LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                            $order, $customErrorResponse, isset($pix['tid']) ? $pix['tid'] : 'N/A', $pixExpiration, $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                            1, $order->get_total(), $order_currency, '', $this->get_option('pv'), $this->get_option('token'),
                            $reference, $orderId, true, 'Pix', 'N/A',
                            $this, '', '', '', 44, __('Incomplete PIX response from Rede API.', 'woo-rede')
                        );
                        $order->save();
                        
                        throw new Exception(__('Incomplete PIX response from Rede API.', 'woo-rede'));
                    }
                }
                
                // Validar se qrCodeResponse contém os campos necessários
                if (!isset($pix['qrCodeResponse']['qrCodeData']) || !isset($pix['qrCodeResponse']['qrCodeImage'])) {
                    // Salvar metadados da transação para erro de QR Code inválido
                    $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : get_option('woocommerce_currency', 'BRL');
                    $customErrorResponse = LknIntegrationRedeForWoocommerceHelper::createCustomErrorResponse(
                        400,
                        44,
                        __('Internal error occurred. Please, contact Rede', 'woo-rede')
                    );
                    LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                        $order, $customErrorResponse, isset($pix['tid']) ? $pix['tid'] : 'N/A', $pixExpiration, $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        1, $order->get_total(), $order_currency, '', $this->get_option('pv'), $this->get_option('token'),
                        $reference, $orderId, true, 'Pix', 'N/A',
                        $this, '', '', '', 44, __('Invalid QR Code data in PIX response.', 'woo-rede')
                    );
                    $order->save();
                    
                    throw new Exception(__('Invalid QR Code data in PIX response.', 'woo-rede'));
                }

                $pixReference = $pix['reference'];
                $pixTid = $pix['tid'];
                $pixAmount = $pix['amount'];
                $pixQrCodeData = $pix['qrCodeResponse']['qrCodeData'];
                $pixQrCodeImage = $pix['qrCodeResponse']['qrCodeImage'];

                $order->update_meta_data('_wc_rede_pix_integration_transaction_reference', $pixReference);
                $order->update_meta_data('_wc_rede_integration_pix_transaction_tid', $pixTid);
                $order->update_meta_data('_wc_rede_pix_integration_transaction_amount', $pixAmount);
                $order->update_meta_data('_wc_rede_pix_integration_transaction_pix_code', $pixQrCodeData);
                $order->update_meta_data('_wc_rede_pix_integration_transaction_pix_generated', true);
                $order->update_meta_data('_wc_rede_pix_integration_transaction_pix_qrcode_base64', $pixQrCodeImage);
                
                // Salvar metadados da transação PIX (sucesso)
                $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : get_option('woocommerce_currency', 'BRL');
                LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                    $order, $pix, isset($pix['tid']) ? $pix['tid'] : 'N/A', $pixExpiration, $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    1, $order->get_total(), $order_currency, 'PIX', $this->get_option('pv'), $this->get_option('token'),
                    $pixReference, $orderId, true, 'Pix', 'N/A',
                    $this, $pixTid, '', '', $pix['returnCode'] ?? '00', $pix['returnMessage'] ?? 'PIX gerado com sucesso'
                );

                if ('yes' == $this->debug) {
                    $this->log->log('info', $this->id, array(
                        'order' => array(
                            'pixReference' => $pixReference,
                            'pixTid' => $pixTid,
                            'pixAmount' => $pixAmount,
                            'pixQrCodeData' => $pixQrCodeData,
                            'pixQrCodeImage' => $pixQrCodeImage,
                        ),
                    ));
                }
                
                // Agendar verificação automática PIX se licença PRO estiver válida e versão >= 2.2.4
                if (LknIntegrationRedeForWoocommerceHelper::isProLicenseValid()) {
                    if (defined('REDE_FOR_WOOCOMMERCE_PRO_VERSION') && version_compare(REDE_FOR_WOOCOMMERCE_PRO_VERSION, '2.2.4', '>=')) {
                        // Verificar se já não existe um cron agendado para este pedido
                        if (!wp_next_scheduled('lkn_verify_pix_payment', array($orderId, $pixTid))) {
                            // Agendar verificação a cada 30 minutos
                            wp_schedule_event(time() + (30 * 60), 'lkn_pix_check_interval', 'lkn_verify_pix_payment', array($orderId, $pixTid));
                        }
                    }
                }
                
                $order->save();
            }
        } catch (Exception $e) {
            $this->add_error($e->getMessage());
            $valid = false;
        }

        if ($valid) {
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        } else {
            return array(
                'result' => 'fail',
                'redirect' => '',
            );
        }
    }

    public function process_refund($orderId, $amount = null, $reason = '')
    {
        if (! $amount) {
            return true;
        }

        $refund = LknIntegrationRedeForWoocommerceWcPixHelper::refundPixRede($amount, $this, $orderId);
        
        if ('yes' == $this->debug) {
            $this->log->log('info', $this->id, array(
                'order' => array(
                    'refund' => $refund,
                ),
            ));
        }

        // Verificar se a resposta do refund contém o returnCode
        if (!is_array($refund) || !isset($refund['returnCode'])) {
            throw new Exception(\esc_html(\__('Invalid refund response from Rede API.', 'woo-rede')));
        }

        if ('359' == $refund['returnCode']) {
            return true;
        } else {
            $refund_message = isset($refund['returnMessage']) ? $refund['returnMessage'] : __('Refund failed.', 'woo-rede');
            $refund_message = LknIntegrationRedeForWoocommerceAbecsCodes::translate($refund['returnCode'] ?? '', $refund_message);
            throw new Exception(esc_html($refund_message));
        }
    }

    public function add_error($message): void
    {
        global $woocommerce;

        $title = '<strong>' . esc_html($this->title) . ':</strong> ';

        if (function_exists('wc_add_notice')) {
            $message = wp_kses($message, array());
            throw new Exception(wp_kses_post("{$title} {$message}"));
        } else {
            $woocommerce->add_error($title . $message);
        }
    }

    public function displayMeta($order): void
    {
        if ($order->get_payment_method() === 'integration_rede_pix') {
            $metaKeys = array(
                '_wc_rede_pix_integration_transaction_reference' => esc_attr__('Reference Number', 'woo-rede'),
                '_wc_rede_integration_pix_transaction_tid' => esc_attr__('TID', 'woo-rede'),
            );

            LknIntegrationRedeForWoocommerceWcPixHelper::generateMetaTable($order, $metaKeys, 'Rede');
        }
    }

    public function showPix($orderWCID): void
    {
        $order = wc_get_order($orderWCID);

        if ($order->get_payment_method() == 'integration_rede_pix') {
            $orderBase64 = $order->get_meta('_wc_rede_pix_integration_transaction_pix_qrcode_base64');
            $pixCode = $order->get_meta('_wc_rede_pix_integration_transaction_pix_code');
            $filePath = INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Public/images/share.svg';
            $total = wc_price($order->get_total());
            $timeExpiration = $order->get_meta('_wc_rede_pix_integration_time_expiration');
            $dateTime = new DateTime($timeExpiration);
            $formattedDate = 'Vencimento: ' . $dateTime->format('d/m/Y');
            wc_get_template(
                '/paymentPixQRCode.php',
                array(
                    'donKey' => $pixCode,
                    'donQrCode' => $orderBase64,
                    'filePath' => $filePath,
                    'currencyTxt' => $total,
                    'dueDateMsg' => $formattedDate,
                    'donationId' => $order->get_id()
                ),
                'woocommerce/integration-pix/',
                plugin_dir_path(__FILE__) . 'templates/pix/'
            );
            wp_enqueue_style('integration-rede-pix-style', INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Public/css/rede/LknIntegrationRedeForWoocommercePix.css', array(), '1.0.0', 'all');

            if (wp_get_theme()->get('Name') == 'Divi') {
                wp_enqueue_style('integration-rede-pix-style-divi', INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Public/css/rede/LknIntegrationRedeForWoocommercePixDivi.css', array(), '1.0.0', 'all');
            }

            wp_enqueue_script('integration-rede-pix-js', INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Public/js/pix/LknIntegrationRedeForWoocommercePix.js', array(), '1.0.0', 'all');
            wp_localize_script('integration-rede-pix-js', 'currentTheme', wp_get_theme()->get('Name'));
            wp_localize_script('integration-rede-pix-js', 'phpVarsPix', array(
                'copied' => __('COPIED', 'woo-rede'),
                'copy' => __('COPY', 'woo-rede'),
                'shareError' => __('Sharing is not supported in this browser.', 'woo-rede'),
                'pixButton' => __('I have already paid the PIX', 'woo-rede'),
                'successPayment' => __('Payment successfully completed!', 'woo-rede'),
                'nextVerify' => __('Next verification in (N. attempts', 'woo-rede'),
                'shareTitle' => __('Payment via PIX', 'woo-rede')
            ));
        }
    }

    public function process_admin_options()
    {
        // Obter configurações atuais antes de salvar as novas
        $old_settings = get_option('woocommerce_' . $this->id . '_settings', array());
        $old_pv = isset($old_settings['pv']) ? $old_settings['pv'] : '';
        $old_token = isset($old_settings['token']) ? $old_settings['token'] : '';

        if (isset($_POST['woocommerce_integration_rede_pix_expiration_count'])) {
            $_POST['woocommerce_integration_rede_pix_expiration_count'] = '24';
        }

        if (isset($_POST['woocommerce_integration_rede_pix_payment_complete_status'])) {
            $_POST['woocommerce_integration_rede_pix_payment_complete_status'] = 'processing';
        }

        if (isset($_POST['woocommerce_integration_rede_pix_fake_convert_to_brl'])) {
            $_POST['woocommerce_integration_rede_pix_fake_convert_to_brl'] = 'no';
        }

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
