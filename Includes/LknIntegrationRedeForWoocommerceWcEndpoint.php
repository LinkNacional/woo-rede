<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use WP_Error;
use WP_REST_Response;

final class LknIntegrationRedeForWoocommerceWcEndpoint
{
    // Constantes para os gateways disponíveis
    const GATEWAY_DEBIT = 'rede_debit';
    const GATEWAY_CREDIT = 'rede_credit';
    const GATEWAY_PIX_FREE = 'integration_rede_pix';
    const GATEWAY_PIX_PRO = 'rede_pix';
    const GATEWAY_GOOGLE_PAY = 'rede_google_pay';
    
    // Classe padrão para fallback
    const DEFAULT_GATEWAY = self::GATEWAY_DEBIT;
    
    /**
     * Retorna o mapeamento completo dos gateways com suas respectivas classes
     * 
     * @return array Mapeamento de payment_method => ['gateway_id', 'class', 'pro_required']
     */
    private function get_gateway_mapping()
    {
        return array(
            self::GATEWAY_DEBIT => array(
                'gateway_id' => self::GATEWAY_DEBIT,
                'class' => '\\Lknwoo\\IntegrationRedeForWoocommerce\\Includes\\LknIntegrationRedeForWoocommerceWcRedeDebit',
                'pro_required' => false
            ),
            self::GATEWAY_CREDIT => array(
                'gateway_id' => self::GATEWAY_CREDIT,
                'class' => '\\Lknwoo\\IntegrationRedeForWoocommerce\\Includes\\LknIntegrationRedeForWoocommerceWcRedeCredit',
                'pro_required' => false
            ),
            self::GATEWAY_PIX_FREE => array(
                'gateway_id' => self::GATEWAY_PIX_FREE,
                'class' => '\\Lknwoo\\IntegrationRedeForWoocommerce\\Includes\\LknIntegrationRedeForWoocommerceWcPixRede',
                'pro_required' => false
            ),
            self::GATEWAY_PIX_PRO => array(
                'gateway_id' => self::GATEWAY_PIX_PRO,
                'class' => '\\Lkn\\RedeForWoocommercePro\\Includes\\LknRedeForWoocommerceProPixRede',
                'pro_required' => true
            ),
            self::GATEWAY_GOOGLE_PAY => array(
                'gateway_id' => self::GATEWAY_GOOGLE_PAY,
                'class' => '\\Lknwoo\\IntegrationRedeForWoocommerce\\Includes\\LknIntegrationRedeForWoocommerceGooglePay',
                'pro_required' => false
            ),
        );
    }
    
    /**
     * Retorna todos os IDs dos gateways disponíveis
     * 
     * @return array Array com todos os gateway IDs
     */
    private function get_available_gateway_ids()
    {
        return array(
            self::GATEWAY_DEBIT,
            self::GATEWAY_CREDIT,
            self::GATEWAY_PIX_FREE,
            self::GATEWAY_PIX_PRO,
            self::GATEWAY_GOOGLE_PAY,
        );
    }
    
    /**
     * Verifica se um payment method é um gateway válido da Rede
     * 
     * @param string $payment_method O método de pagamento
     * @return bool True se for um gateway Rede válido
     */
    private function is_valid_rede_gateway($payment_method)
    {
        return in_array($payment_method, $this->get_available_gateway_ids(), true);
    }
    
    /**
     * Retorna todos os gateways PIX (FREE e PRO)
     * 
     * @return array Array com os IDs dos gateways PIX
     */
    private function get_pix_gateway_ids()
    {
        return array(
            self::GATEWAY_PIX_FREE,
            self::GATEWAY_PIX_PRO,
        );
    }
    
    /**
     * Verifica se um payment method é um gateway PIX
     * 
     * @param string $payment_method O método de pagamento
     * @return bool True se for um gateway PIX
     */
    private function is_pix_gateway($payment_method)
    {
        return in_array($payment_method, $this->get_pix_gateway_ids(), true);
    }
    
    /**
     * Retorna todos os gateways de cartão (débito, crédito, Google Pay)
     * 
     * @return array Array com os IDs dos gateways de cartão
     */
    private function get_card_gateway_ids()
    {
        return array(
            self::GATEWAY_DEBIT,
            self::GATEWAY_CREDIT,
            self::GATEWAY_GOOGLE_PAY,
        );
    }
    
    /**
     * Verifica se um payment method é um gateway de cartão
     * 
     * @param string $payment_method O método de pagamento
     * @return bool True se for um gateway de cartão
     */
    private function is_card_gateway($payment_method)
    {
        return in_array($payment_method, $this->get_card_gateway_ids(), true);
    }
    public function registerorderRedeCaptureEndPoint(): void
    {
        // Só registra a rota redePixListener se o plugin PRO não estiver ativo
        if (!is_plugin_active('rede-for-woocommerce-pro/rede-for-woocommerce-pro.php')) {
            register_rest_route('redePRO', '/redePixListener', array(
                'methods' => 'POST',
                'callback' => array($this, 'redePixListenerPro'),
                'permission_callback' => '__return_true',
            ));
        }

        register_rest_route('redeIntegration', '/pixListener', array(
            'methods' => 'POST',
            'callback' => array($this, 'redePixListenerLegacy'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('redeIntegration', '/verifyPixRedeStatus', array(
            'methods' => 'GET',
            'callback' => array($this, 'verifyPixRedeStatus'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('redeIntegration', '/maxipagoDebitListener', array(
            'methods' => 'POST',
            'callback' => array($this, 'maxipagoDebitListener'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('redeIntegration', '/clearOrderLogs', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'clearOrderLogs'),
            'permission_callback' => array($this, 'check_clear_logs_permissions'),
        ));

        register_rest_route('woorede', '/s', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle3dsSuccess'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('woorede', '/f', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle3dsFailure'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Verifica se o usuário tem permissão para limpar logs de pedidos
     * 
     * @return bool True se autorizado, false caso contrário
     */
    public function check_clear_logs_permissions()
    {
        // Verifica se o usuário está logado e tem permissão para gerenciar WooCommerce
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    public function clearOrderLogs($request)
    {
        // Verificação adicional de segurança
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            return new WP_Error(
                'insufficient_permissions',
                __('You do not have permission to clear order logs.', 'woo-rede'),
                array('status' => 403)
            );
        }

        // Log da ação para auditoria
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $current_user = wp_get_current_user();
            $logger->info('Order logs cleared by user', array(
                'source' => 'rede-security-audit',
                'user_id' => $current_user->ID,
                'user_login' => $current_user->user_login,
                'user_ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown',
                'timestamp' => current_time('mysql')
            ));
        }

        $deleted_count = 0;
        $batch_size = 50; // Processa 50 pedidos por vez para evitar esgotamento de memória
        $offset = 0;

        do {
            $args = array(
                'limit' => $batch_size,
                'offset' => $offset,
                'meta_key' => 'lknWcRedeOrderLogs',
                'meta_compare' => 'EXISTS',
                'return' => 'ids', // Retorna apenas IDs para economizar memória
            );

            $order_ids = wc_get_orders($args);
            
            if (empty($order_ids)) {
                break; // Não há mais pedidos para processar
            }

            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->delete_meta_data('lknWcRedeOrderLogs');
                    $order->save();
                    $deleted_count++;
                }
                
                // Libera memória do objeto order
                unset($order);
            }

            $offset += $batch_size;
            
            // Força limpeza de memória entre lotes
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

        } while (count($order_ids) === $batch_size);

        return new WP_REST_Response(array(
            'success' => true,
            /* translators: %d: number of orders from which logs were cleared */
            'message' => sprintf(__('Logs cleared from %d orders.', 'woo-rede'), $deleted_count),
            'orders_affected' => $deleted_count
        ), 200);
    }

    public function maxipagoDebitListener($request)
    {
        add_option('LknIntegrationRedeForWoocommerceMaxipagoDebitEndpointStatus', true);
        update_option('LknIntegrationRedeForWoocommerceMaxipagoDebitEndpointStatus', true);
        $requestBody = $request->get_body();

        parse_str($requestBody, $parsedBody);
        $xmlString = urldecode($parsedBody['xml']);
        $xmlObject = simplexml_load_string($xmlString, "SimpleXMLElement", LIBXML_NOCDATA);
        $notification = $xmlObject->{'transaction-event'};

        // Converte o valor de orderID para string
        $referenceNumber = (string) $notification->referenceNumber;
        $transactionStatus = (string) $notification->transactionStatus;
        $maxipagoPixOptions = get_option('woocommerce_maxipago_debit_settings');

        $args = array(
            'limit' => -1,
            'status' => array_keys(wc_get_order_statuses()),
            'meta_key' => '_wc_maxipago_transaction_reference_num',
            'meta_value' => $referenceNumber,
        );
        $order = wc_get_orders($args)[0];

        switch ($transactionStatus) {
            case '3':
                $paymentCompleteStatus = $maxipagoPixOptions['payment_complete_status'];
                if ("" == $paymentCompleteStatus) {
                    $paymentCompleteStatus = 'processing';
                }
                $order->set_date_paid(current_time('timestamp', true));
                $order->update_status($paymentCompleteStatus);
                break;
            case '9':
                $order->update_status('cancelled');
                break;
            default:
                $order->update_status('cancelled');
                break;
        }

        return new WP_REST_Response('', 200);
    }

    /**
     * Listener PIX específico para versão FREE
     * 
     * SEGURANÇA: Valida a transação via API da Rede (server-to-server) antes de
     * alterar o status do pedido, prevenindo webhook forgery (CVE-2026-0939).
     */
    public function redePixListenerLegacy($request)
    {
        add_option('lknRedeForWoocommerceProEndpointStatus', true);
        update_option('lknRedeForWoocommerceProEndpointStatus', true);
        $requestParams = $request->get_params();

        $redePixOptions = get_option('woocommerce_' . self::GATEWAY_PIX_FREE . '_settings');
        $tid = isset($requestParams['data']['id']) ? sanitize_text_field($requestParams['data']['id']) : '';

        if (empty($tid)) {
            return new WP_REST_Response('', 400);
        }

        // Argumentos para buscar pedidos com o gateway FREE
        $args = array(
            'limit' => -1,
            'status' => array_keys(wc_get_order_statuses()),
            'meta_key' => '_wc_rede_integration_pix_transaction_tid',
            'meta_value' => $tid,
        );

        $orders = wc_get_orders($args);
        
        if (!empty($orders)) {
            $order = $orders[0];
            
            // Verificar se é realmente um pedido do gateway PIX FREE
            if ($this->is_pix_gateway($order->get_payment_method()) && $order->get_payment_method() === self::GATEWAY_PIX_FREE) {
                if ('PV.UPDATE_TRANSACTION_PIX' == $requestParams['events'][0]) {
                    // Só altera o status se estiver pendente de pagamento
                    if ($order->get_status() === 'pending') {
                        // SEGURANÇA: Valida a transação diretamente na API da Rede
                        if (!$this->validate_pix_transaction_with_rede_api($order, $tid)) {
                            $order->add_order_note('[' . self::GATEWAY_PIX_FREE . '] ' . __('PIX webhook rejected: Rede API validation failed', 'woo-rede'));
                            return new WP_REST_Response('', 403);
                        }

                        $paymentCompleteStatus = $redePixOptions['payment_complete_status'] ?? '';
                        if (empty($paymentCompleteStatus)) {
                            $paymentCompleteStatus = 'processing';
                        }
                        
                        $order->set_date_paid(current_time('timestamp', true));
                        $order->update_status($paymentCompleteStatus, __('PIX payment confirmed via webhook - FREE version', 'woo-rede'));
                        
                        // Limpar cron de verificação se existir
                        wp_clear_scheduled_hook('lkn_verify_pix_payment', array($order->get_id(), $tid));
                    }
                }
            }
        }

        return new WP_REST_Response('', 200);
    }

    /**
     * Valida transação PIX diretamente na API da Rede (server-to-server)
     * 
     * Previne webhook forgery: confirma que a transação realmente existe e
     * está aprovada antes de alterar o status do pedido.
     * 
     * @param \WC_Order $order O pedido do WooCommerce
     * @param string $tid Transaction ID
     * @return bool True se a transação está aprovada na Rede
     */
    private function validate_pix_transaction_with_rede_api($order, $tid)
    {
        try {
            $pixOptions = get_option('woocommerce_' . self::GATEWAY_PIX_FREE . '_settings');
            $environment = $pixOptions['environment'] ?? 'test';

            // Obter token OAuth2 válido
            LknIntegrationRedeForWoocommerceHelper::refresh_expired_rede_oauth_tokens(20);
            $token_data = LknIntegrationRedeForWoocommerceHelper::get_cached_rede_oauth_token_for_gateway(self::GATEWAY_PIX_FREE, $environment);

            if (!$token_data || empty($token_data['token'])) {
                return false;
            }

            // API v2 da Rede
            if ('production' === $environment) {
                $apiUrl = 'https://api.userede.com.br/erede/v2/transactions/' . $tid;
            } else {
                $apiUrl = 'https://sandbox-erede.useredecloud.com.br/v2/transactions/' . $tid;
            }

            $response = wp_remote_get($apiUrl, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token_data['token']
                ),
                'timeout' => 15
            ));

            if (is_wp_error($response)) {
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                return false;
            }

            $transaction_data = json_decode($response_body, true);

            if (!$transaction_data) {
                return false;
            }

            $authorization = $transaction_data['authorization'] ?? array();
            $api_status = $authorization['status'] ?? '';
            $api_tid = $authorization['tid'] ?? '';

            // Confirma que o TID da API bate com o recebido
            if ($api_tid !== $tid) {
                return false;
            }

            // Só permite se a transação estiver aprovada
            return $api_status === 'Approved';

        } catch (\Exception $e) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->error('PIX webhook validation error: ' . $e->getMessage(), array('source' => 'rede_security'));
            }
            return false;
        }
    }

    /**
     * Listener PIX específico para versão PRO (mantém compatibilidade)
     */
    public function redePixListenerPro($request)
    {
        add_option('lknRedeForWoocommerceProEndpointStatus', true);
        update_option('lknRedeForWoocommerceProEndpointStatus', true);
        $requestParams = $request->get_params();

        // Log 0: Recebimento inicial do webhook (sempre PRO)
        $redePixOptions = get_option('woocommerce_rede_pix_settings');
        $validPROLicense = LknIntegrationRedeForWoocommerceHelper::isProLicenseValid();
        if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
            $logger = wc_get_logger();
            $logger->info('Rede PIX PRO - Webhook Received - Initial', array(
                'source' => 'rede-pix-pro-advanced-debug',
                'request_params' => $requestParams,
                'request_body' => $request->get_body(),
                'timestamp' => current_time('mysql')
            ));
        }

        $tid = $requestParams['data']['id'] ?? null;

        // Log 1: Verificação de TID
        if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
            $logger = wc_get_logger();
            $logger->info('Rede PIX PRO - TID Validation', array(
                'source' => 'rede-pix-pro-advanced-debug',
                'tid' => $tid,
                'tid_valid' => !empty($tid),
                'timestamp' => current_time('mysql')
            ));
        }

        if (empty($tid)) {
            if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
                $logger = wc_get_logger();
                $logger->error('Rede PIX PRO - Missing TID - Aborting', array(
                    'source' => 'rede-pix-pro-advanced-debug',
                    'request_params' => $requestParams,
                    'timestamp' => current_time('mysql')
                ));
            }
            return new WP_REST_Response('', 400);
        }

        // Primeiro, buscar no gateway PRO
        $args = array(
            'limit' => -1,
            'status' => array_keys(wc_get_order_statuses()),
            'meta_key' => '_wc_rede_pix_transaction_tid',
            'meta_value' => $tid,
        );

        $orders = wc_get_orders($args);
        $order = !empty($orders) ? $orders[0] : null;

        // Log 2: Resultado busca PRO
        if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
            $logger = wc_get_logger();
            $logger->info('Rede PIX PRO - PRO Search Result', array(
                'source' => 'rede-pix-pro-advanced-debug',
                'tid' => $tid,
                'orders_found' => count($orders),
                'order_found' => $order ? true : false,
                'order_id' => $order ? $order->get_id() : null,
                'payment_method' => $order ? $order->get_payment_method() : null,
                'timestamp' => current_time('mysql')
            ));
        }

        // Se não encontrou no PRO, buscar no gateway FREE
        if (!$order) {
            $args = array(
                'limit' => -1,
                'status' => array_keys(wc_get_order_statuses()),
                'meta_key' => '_wc_rede_integration_pix_transaction_tid',
                'meta_value' => $tid,
            );

            $orders = wc_get_orders($args);
            $order = !empty($orders) ? $orders[0] : null;

            // Log 3: Resultado busca FREE
            if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
                $logger = wc_get_logger();
                $logger->info('Rede PIX PRO - FREE Search Result', array(
                    'source' => 'rede-pix-pro-advanced-debug',
                    'tid' => $tid,
                    'orders_found' => count($orders),
                    'order_found' => $order ? true : false,
                    'order_id' => $order ? $order->get_id() : null,
                    'payment_method' => $order ? $order->get_payment_method() : null,
                    'timestamp' => current_time('mysql')
                ));
            }
        }

        // Log 4: Nenhum pedido encontrado
        if (!$order) {
            if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
                $logger = wc_get_logger();
                $logger->error('Rede PIX PRO - No Order Found - Aborting', array(
                    'source' => 'rede-pix-pro-advanced-debug',
                    'tid' => $tid,
                    'searched_pro_meta' => '_wc_rede_pix_transaction_tid',
                    'searched_free_meta' => '_wc_rede_integration_pix_transaction_tid',
                    'timestamp' => current_time('mysql')
                ));
            }
            return new WP_REST_Response('', 404);
        }

        // Log 5: Validação do evento
        $webhook_event = $requestParams['events'][0] ?? '';
        if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
            $logger = wc_get_logger();
            $logger->info('Rede PIX PRO - Event Validation', array(
                'source' => 'rede-pix-pro-advanced-debug',
                'order_id' => $order->get_id(),
                'webhook_event' => $webhook_event,
                'event_valid' => $webhook_event === 'PV.UPDATE_TRANSACTION_PIX',
                'timestamp' => current_time('mysql')
            ));
        }

        // Processa o pedido se encontrado
        if ($order && 'PV.UPDATE_TRANSACTION_PIX' == $webhook_event) {
            
            // Log 6: Status do pedido
            if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
                $logger = wc_get_logger();
                $logger->info('Rede PIX PRO - Order Status Check', array(
                    'source' => 'rede-pix-pro-advanced-debug',
                    'order_id' => $order->get_id(),
                    'current_status' => $order->get_status(),
                    'is_pending' => $order->get_status() === 'pending',
                    'timestamp' => current_time('mysql')
                ));
            }

            // Só altera o status se estiver pendente de pagamento
            if ($order->get_status() === 'pending') {
                
                if ($order->get_payment_method() === self::GATEWAY_PIX_PRO) {
                    // Versão PRO
                    
                    // Log 7: Processamento PRO
                    if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
                        $logger = wc_get_logger();
                        $logger->info('Rede PIX PRO - Processing PRO Order', array(
                            'source' => 'rede-pix-pro-advanced-debug',
                            'gateway' => 'rede_pix (PRO)',
                            'order_id' => $order->get_id(),
                            'timestamp' => current_time('mysql')
                        ));
                    }
                    
                    $paymentCompleteStatus = $redePixOptions['payment_complete_status'] ?? '';
                    if (empty($paymentCompleteStatus)) {
                        $paymentCompleteStatus = 'processing';
                    }
                    
                    $order->set_date_paid(current_time('timestamp', true));
                    $order->update_status($paymentCompleteStatus, __('PIX payment confirmed via webhook - PRO version', 'woo-rede'));

                    // Log 8: Finalização PRO
                    if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
                        $logger = wc_get_logger();
                        $logger->info('Rede PIX PRO - Processing Complete', array(
                            'source' => 'rede-pix-pro-advanced-debug',
                            'gateway' => 'rede_pix (PRO)',
                            'order_id' => $order->get_id(),
                            'final_status' => $order->get_status(),
                            'cron_cleared' => true,
                            'timestamp' => current_time('mysql')
                        ));
                    }

                    wp_clear_scheduled_hook('lkn_verify_pix_payment', array($order->get_id(), $tid));
                    
                } else if ($order->get_payment_method() === self::GATEWAY_PIX_FREE) {
                    // Versão FREE - só logs PRO para debug

                    // SEGURANÇA: valida a transação server-to-server na API da Rede antes
                    // de alterar o status, prevenindo webhook forgery (mesma classe do CVE-2026-0939).
                    if (!$this->validate_pix_transaction_with_rede_api($order, $tid)) {
                        $order->add_order_note('[' . self::GATEWAY_PIX_FREE . '] ' . __('PIX webhook rejected: Rede API validation failed', 'woo-rede'));
                        return new WP_REST_Response('', 403);
                    }

                    // Log 9: Processamento FREE
                    if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
                        $logger = wc_get_logger();
                        $logger->info('Rede PIX PRO - Processing FREE Order', array(
                            'source' => 'rede-pix-pro-advanced-debug',
                            'gateway' => self::GATEWAY_PIX_FREE . ' (FREE)',
                            'order_id' => $order->get_id(),
                            'timestamp' => current_time('mysql')
                        ));
                    }
                    
                    $order->set_date_paid(current_time('timestamp', true));
                    $order->update_status('processing', __('PIX payment confirmed via webhook - FREE version', 'woo-rede'));
                    
                    // Log 10: Finalização FREE
                    if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
                        $logger = wc_get_logger();
                        $logger->info('Rede PIX PRO - FREE Processing Complete', array(
                            'source' => 'rede-pix-pro-advanced-debug',
                            'gateway' => self::GATEWAY_PIX_FREE . ' (FREE)',
                            'order_id' => $order->get_id(),
                            'final_status' => $order->get_status(),
                            'cron_cleared' => true,
                            'timestamp' => current_time('mysql')
                        ));
                    }
                    
                    // Limpar cron de verificação se existir
                    wp_clear_scheduled_hook('lkn_verify_pix_payment', array($order->get_id(), $tid));
                }
            } else {
                // Log 11: Status não pendente
                if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
                    $logger = wc_get_logger();
                    $logger->warning('Rede PIX PRO - Order Not Pending - Skipped', array(
                        'source' => 'rede-pix-pro-advanced-debug',
                        'order_id' => $order->get_id(),
                        'current_status' => $order->get_status(),
                        'expected_status' => 'pending',
                        'timestamp' => current_time('mysql')
                    ));
                }
            }
        } else {
            // Log 12: Evento inválido
            if ($validPROLicense && ($redePixOptions['advanced_debug'] ?? 'no') === 'yes') {
                $logger = wc_get_logger();
                $logger->warning('Rede PIX PRO - Invalid Event - Skipped', array(
                    'source' => 'rede-pix-pro-advanced-debug',
                    'order_id' => $order ? $order->get_id() : 'unknown',
                    'received_event' => $webhook_event,
                    'expected_event' => 'PV.UPDATE_TRANSACTION_PIX',
                    'timestamp' => current_time('mysql')
                ));
            }
        }

        return new WP_REST_Response('', 200);
    }

    public function verifyPixRedeStatus($request)
    {
        $parameters = $request->get_params();
        $order = wc_get_order($parameters['donationId']);
        $tId = $order->get_meta('_wc_rede_integration_pix_transaction_tid');
        if (empty($order)) {
            return new WP_Error('order_not_found', __('Order not found', 'woo-rede'), array('status' => 404));
        }

        $pixOptions = get_option('woocommerce_' . self::GATEWAY_PIX_FREE . '_settings');
        $environment = $pixOptions['environment'];

        // Obter token OAuth2 válido ou renovar se expirado (20 minutos)
        LknIntegrationRedeForWoocommerceHelper::refresh_expired_rede_oauth_tokens(20);
        $token_data = LknIntegrationRedeForWoocommerceHelper::get_cached_rede_oauth_token_for_gateway(self::GATEWAY_PIX_FREE, $environment);

        if (!$token_data || empty($token_data['token'])) {
            return new WP_REST_Response($response_body['authorization']['status'] ?? 'Invalid Auth', 400);
        }

        // API v2 da Rede
        if ('production' === $environment) {
            $apiUrl = 'https://api.userede.com.br/erede/v2/transactions';
        } else {
            $apiUrl = 'https://sandbox-erede.useredecloud.com.br/v2/transactions';
        }
        
        $response = wp_remote_get($apiUrl . '/' . $tId, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token_data['token']
            ),
        ));
        
        $response_body = wp_remote_retrieve_body($response);

        $response_body = json_decode($response_body, true);

        if (($response_body['authorization']['status'] ?? 'Accepted') == 'Approved') {
            $paymentCompleteStatus = $pixOptions['payment_complete_status'];
            if ("" == $paymentCompleteStatus) {
                $paymentCompleteStatus = 'processing';
            }
            $order->set_date_paid(current_time('timestamp', true));
            $order->update_status($paymentCompleteStatus);

            $order->save();
        }

        return new WP_REST_Response($response_body['authorization']['status'] ?? 'Accepted', 200);
    }

    /**
     * Processa callback de sucesso do 3D Secure
     * 
     * SEGURANÇA: Este endpoint foi protegido contra bypass de pagamento através de:
     * 1. Validação de TID contra dados armazenados no pedido
     * 2. Verificação de correspondência entre reference e order_id
     * 3. Consulta à API da Rede para confirmar autenticidade da transação
     * 4. Validação de amount para evitar alteração de valores
     * 
     * @param WP_REST_Request $request Requisição REST
     * @return WP_REST_Response|WP_Error
     */
    public function handle3dsSuccess($request)
    {
        $parameters = $request->get_params();
        
        // Extrai o order_id da reference (formato: order_id-timestamp)
        $reference = sanitize_text_field($parameters['reference'] ?? '');
        $tid = sanitize_text_field($parameters['tid'] ?? '');
        
        if (!$reference || !$tid) {
            return new WP_Error('invalid_parameters', __('Missing required parameters (reference or tid)', 'woo-rede'), array('status' => 400));
        }

        // Extrai order_id da reference usando explode
        $reference_parts = explode('-', $reference);
        $order_id = intval($reference_parts[0] ?? 0);
        
        if (!$order_id) {
            return new WP_Error('invalid_reference', __('Invalid reference format', 'woo-rede'), array('status' => 400));
        }

        // Valida o pedido
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', __('Order not found', 'woo-rede'), array('status' => 404));
        }

        // VALIDAÇÃO DE SEGURANÇA: Verifica autenticidade da requisição
        if (!$this->validate_webhook_security($order, $parameters, 'success')) {
            $order->add_order_note('[' . $order->get_payment_method() . '] ' . __('Security validation failed for 3DS success webhook', 'woo-rede'));
            // Redireciona de volta ao checkout em vez de deixar o cliente preso na URL do webhook
            $redirect_url = add_query_arg('3ds_error', '1', wc_get_checkout_url());
            wp_safe_redirect($redirect_url);
            exit;
        }

        try {
            // Usa os dados que já vêm no webhook da Rede
            $this->update_order_metadata_and_status($order, $parameters);
            
            $redirect_url = $order->get_checkout_order_received_url();
            wp_safe_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            $order->add_order_note('[' . $order->get_payment_method() . '] ' . __('Error processing 3DS success: ', 'woo-rede') . $e->getMessage());
            return new WP_REST_Response(array('status' => 'error', 'message' => $e->getMessage()), 500);
        }
    }

    /**
     * Processa callback de falha do 3D Secure
     * 
     * SEGURANÇA: Este endpoint foi protegido contra bypass de pagamento através de:
     * 1. Validação de TID contra dados armazenados no pedido
     * 2. Verificação de correspondência entre reference e order_id
     * 3. Consulta à API da Rede para confirmar autenticidade da transação
     * 
     * @param WP_REST_Request $request Requisição REST
     * @return WP_REST_Response|WP_Error
     */
    public function handle3dsFailure($request)
    {
        $parameters = $request->get_params();
        
        // Extrai o order_id da reference (formato: order_id-timestamp)
        $reference = sanitize_text_field($parameters['reference'] ?? '');
        
        if (!$reference) {
            return new WP_Error('invalid_parameters', __('Missing reference parameter', 'woo-rede'), array('status' => 400));
        }

        // Extrai order_id da reference usando explode
        $reference_parts = explode('-', $reference);
        $order_id = intval($reference_parts[0] ?? 0);
        
        if (!$order_id) {
            return new WP_Error('invalid_reference', __('Invalid reference format', 'woo-rede'), array('status' => 400));
        }

        // Valida o pedido
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', __('Order not found', 'woo-rede'), array('status' => 404));
        }

        // VALIDAÇÃO DE SEGURANÇA: Verifica autenticidade da requisição
        if (!$this->validate_webhook_security($order, $parameters, 'failure')) {
            $order->add_order_note('[' . $order->get_payment_method() . '] ' . __('Security validation failed for 3DS failure webhook', 'woo-rede'));
            // Redireciona de volta ao checkout em vez de deixar o cliente preso na URL do webhook
            $redirect_url = add_query_arg('3ds_error', '1', wc_get_checkout_url());
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Salvar metadados da transação usando helper (mesmo para falhas)
        $gateway_config = $this->get_dynamic_gateway_config($order);
        $gateway_settings = $gateway_config['settings'];
        $pv = $gateway_settings['pv'] ?? '';
        $token = $gateway_settings['token'] ?? '';
        $brand = !empty($parameters['brand_name']) && trim($parameters['brand_name']) !== '' && trim($parameters['brand_name']) !== 'undefined' ? $parameters['brand_name'] : '';
        
        // Adicionar dados do erro no webhook_data
        $error_webhook_data = $parameters;
        $error_webhook_data['return_http'] = '400'; // HTTP foi OK, mas transação falhou
        $error_webhook_data['3ds_auth'] = 'failed';
        
        // Preparar dados do cartão para o helper
        $cardNumber = $order->get_meta('_wc_rede_transaction_card_number') ?: ('**** **** **** ' . ($parameters['last4'] ?? '****'));
        $cardExpiry = $order->get_meta('_wc_rede_transaction_expiration') ?: '**/**';
        $cardHolder = $order->get_meta('_wc_rede_transaction_holder') ?: 'Card Holder';
        
        // Recuperar dados salvos do tipo de cartão e parcelas
        $saved_card_type = $order->get_meta('_wc_rede_card_type') ?: 'debit';
        $saved_installments = $order->get_meta('_wc_rede_installments') ?: 1;
        $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : get_option('woocommerce_currency', 'BRL');
        
        // Obter gateway e configurações dinamicamente
        $gateway_config = $this->get_dynamic_gateway_config($order);
        $gateway = $gateway_config['gateway_instance'];
        $gateway_settings = $gateway_config['settings'];

        // Fallback nível 1: TID disponível → buscar objeto brand completo via API
        if (empty($brand) && $gateway) {
            $tid_for_brand = !empty($parameters['tid']) && trim($parameters['tid']) !== '' ? trim($parameters['tid']) : $order->get_meta('_wc_rede_transaction_id');
            if (!empty($tid_for_brand)) {
                $brand_details = LknIntegrationRedeForWoocommerceHelper::getTransactionBrandDetails($tid_for_brand, $gateway);
                if (!empty($brand_details['brand']['name'])) {
                    $brand = $brand_details['brand']['name'];
                    $order->update_meta_data('_wc_rede_transaction_card_brand', ucfirst($brand));
                    $order->update_meta_data('_wc_rede_brand_return_code', $brand_details['brand']['returnCode'] ?? '');
                    $order->update_meta_data('_wc_rede_brand_tid', $brand_details['brand']['brandTid'] ?? '');
                    $order->update_meta_data('_wc_rede_brand_authorization_code', $brand_details['brand']['authorizationCode'] ?? '');
                    $order->update_meta_data('_wc_rede_brand_return_message', $brand_details['brand']['returnMessage'] ?? '');
                }
            }
        }

        // Fallback nível 2: sem TID (ex: returnCode 64) → detectar nome da bandeira pelo BIN
        // Neste caso os campos brandTid/authorizationCode não existem pois não houve autorização
        if (empty($brand)) {
            $bin = $order->get_meta('_wc_rede_transaction_bin');
            if (!empty($bin)) {
                $brand_from_bin = LknIntegrationRedeForWoocommerceHelper::getBrandFromBin($bin);
                if (!empty($brand_from_bin)) {
                    $brand = $brand_from_bin;
                    $order->update_meta_data('_wc_rede_transaction_card_brand', $brand);
                    $order->update_meta_data('_wc_rede_brand_return_code', !empty($parameters['brand_returnCode']) && trim($parameters['brand_returnCode']) !== '' ? $parameters['brand_returnCode'] : '');
                    $order->update_meta_data('_wc_rede_brand_tid', '');
                    $order->update_meta_data('_wc_rede_brand_authorization_code', '');
                    $order->update_meta_data('_wc_rede_brand_return_message', !empty($parameters['brand_returnMessage']) && trim($parameters['brand_returnMessage']) !== '' ? $parameters['brand_returnMessage'] : '');
                }
            }
        }

        LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
            $order, 
            $error_webhook_data, 
            $cardNumber, 
            $cardExpiry, 
            $cardHolder,
            $saved_installments, 
            $order->get_total(), 
            $order_currency, 
            $brand, 
            $gateway_settings['pv'] ?? '', 
            $gateway_settings['token'] ?? '',
            $parameters['reference'] ?? '', 
            $order->get_id(), 
            'N/A', // capture = false para falha
            $saved_card_type === 'credit' ? 'Credit' : 'Debit', 
            '',
            $gateway_settings, 
            !empty($parameters['tid']) && trim($parameters['tid']) !== '' ? $parameters['tid'] : '',
            !empty($parameters['nsu']) && trim($parameters['nsu']) !== '' ? $parameters['nsu'] : '',
            !empty($parameters['authorizationCode']) && trim($parameters['authorizationCode']) !== '' ? $parameters['authorizationCode'] : '',
            $parameters['returnCode'] ?? '',
            $parameters['returnMessage'] ?? ''
        );

        // Registrar logs de debug se habilitado
        if (($gateway_settings['debug'] ?? 'no') === 'yes') {
            $cardData = array(
                'card_number' => $cardNumber,
                'card_holder' => $cardHolder,
                'card_expiration_month' => $order->get_meta('_wc_rede_transaction_expiration_month') ?: '**',
                'card_expiration_year' => $order->get_meta('_wc_rede_transaction_expiration_year') ?: '****',
                'card_cvv' => $order->get_meta('_wc_rede_transaction_cvv') ?: '***',
                'card_type' => $saved_card_type,
            );
            if ($saved_card_type === 'credit') {
                $cardData['installments'] = $saved_installments;
            }
            $this->regOrderLogs($order->get_id(), $order->get_total(), $cardData, $error_webhook_data, $order);
        }

        // Marca a transação como falha internamente, mas mantém o status
        // do pedido como "pagamento pendente" para permitir nova tentativa.
        
        $order->update_meta_data('_wc_rede_transaction_status', 'failed');
        $order->delete_meta_data('_wc_rede_pending_3ds_time');
        $order->add_order_note('[' . $order->get_payment_method() . '] ' . __('3D Secure authentication failed', 'woo-rede'));
        $order->update_status('pending');
        $order->save();
        
        // Redireciona para a página de checkout com parâmetro de erro
        $redirect_url = add_query_arg('3ds_error', '1', wc_get_checkout_url());
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Processa status do pedido especificamente para retorno 3DS
     * A resposta 3DS tem estrutura diferente e precisamos do card_type dos metadados
     */
    private function process_3ds_order_status($order, $webhook_data, $note = '')
    {
        $return_code = $webhook_data['returnCode'] ?? '';
        $return_message = $webhook_data['returnMessage'] ?? '';
        
        // Para 3DS, recuperar o card_type dos metadados do pedido
        $saved_card_type = $order->get_meta('_wc_rede_card_type') ?: 'debit';
        
        // Obter configuração de auto_capture do gateway dinamicamente
        $gateway_config = $this->get_dynamic_gateway_config($order);
        $gateway_settings = $gateway_config['settings'];
        $auto_capture = sanitize_text_field($gateway_settings['auto_capture'] ?? 'yes') == 'no' ? false : true;

        // Determinar se foi capturado baseado no tipo de cartão e configuração
        $capture = ($saved_card_type === 'debit') ? true : $auto_capture;
        
        // Adiciona informação sobre o tipo de cartão detectado na nota
        $card_type_note = sprintf(' [Card Type: %s, Capture: %s]', $saved_card_type, $capture ? 'Yes' : 'No');
        $status_note = sprintf('Rede[%s]', LknIntegrationRedeForWoocommerceAbecsCodes::translate($return_code, $return_message));
        $order->add_order_note('[' . $order->get_payment_method() . '] ' . $status_note . ' ' . $note . $card_type_note);

        if ($return_code == '00') {
            // Marca transação como concluída com sucesso
            $order->update_meta_data('_wc_rede_transaction_status', 'completed');
            $order->delete_meta_data('_wc_rede_pending_3ds_time');
            
            if ($capture) {
                // Status configurável pelo usuário para pagamentos aprovados com captura
                $payment_complete_status = $gateway_settings['payment_complete_status'] ?? 'processing';
                $order->set_date_paid(current_time('timestamp', true));
                $order->update_status($payment_complete_status);
            } else {
                // Para pagamentos credit sem captura, aguardando captura manual
                $order->update_status('on-hold', 'Pagamento autorizado, aguardando captura manual.');
                wc_reduce_stock_levels($order->get_id());
            }
        } else {
            // Marca transação como falha para permitir retry
            $order->update_meta_data('_wc_rede_transaction_status', 'failed');
            $order->delete_meta_data('_wc_rede_pending_3ds_time');
            $order->update_status('failed', $status_note);
        }
    }

    public function regOrderLogs($orderId, $order_total, $cardData, $transaction, $order, $brand = null): void
    {
        // Obter configurações do gateway dinamicamente
        $gateway_config = $this->get_dynamic_gateway_config($order);
        $gateway_settings = $gateway_config['settings'];
        $gateway = $gateway_config['gateway_instance'];
        
        if (($gateway_settings['debug'] ?? 'no') === 'yes') {
            $tId = null;
            $returnCode = null;
            
            if ($brand === null && $transaction) {
                $brand = null;
                if (is_array($transaction)) {
                    $tId = $transaction['tid'] ?? null;
                    $returnCode = $transaction['returnCode'] ?? null;
                }

                if ($tId) {
                    $brand = LknIntegrationRedeForWoocommerceHelper::getTransactionBrandDetails($tId, $gateway);
                }

                // Fallback: se brand ainda é null (sem TID ou falha na API), usar metadados salvos no pedido
                if ($brand === null) {
                    $brand_name = $order->get_meta('_wc_rede_transaction_card_brand');
                    $brand = array(
                        'brand' => array(
                            'name'              => $brand_name ?: '',
                            'returnCode'        => $order->get_meta('_wc_rede_brand_return_code') ?: '',
                            'brandTid'          => $order->get_meta('_wc_rede_brand_tid') ?: '',
                            'authorizationCode' => $order->get_meta('_wc_rede_brand_authorization_code') ?: '',
                            'returnMessage'     => $order->get_meta('_wc_rede_brand_return_message') ?: '',
                        ),
                        'returnCode' => $order->get_meta('_wc_rede_brand_return_code') ?: '',
                    );
                }
            }

            $default_currency = get_option('woocommerce_currency', 'BRL');
            $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
            $currency_json_path = INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/files/linkCurrencies.json';
            $currency_data = LknIntegrationRedeForWoocommerceHelper::lkn_get_currency_rates($currency_json_path);
            $convert_to_brl_enabled = LknIntegrationRedeForWoocommerceHelper::is_convert_to_brl_enabled(self::GATEWAY_DEBIT);

            $exchange_rate_value = 1;
            if ($convert_to_brl_enabled && $currency_data !== false && is_array($currency_data) && isset($currency_data['rates']) && isset($currency_data['base'])) {
                // Exibe a cotação apenas se não for BRL
                if ($order_currency !== 'BRL' && isset($currency_data['rates'][$order_currency])) {
                    $rate = $currency_data['rates'][$order_currency];
                    // Converte para string, preservando todas as casas decimais
                    $exchange_rate_value = (string)$rate;
                }
            }

            // Recupera metadados do pedido se não estiverem em cardData
            $final_card_type = isset($cardData['card_type']) ? $cardData['card_type'] : ($order->get_meta('_wc_rede_card_type') ?: 'debit');
            $final_installments = isset($cardData['installments']) ? $cardData['installments'] : ($order->get_meta('_wc_rede_installments') ?: 1);
            
            $bodyArray = array(
                'orderId' => $orderId,
                'amount' => $order_total,
                'orderCurrency' => $order_currency,
                'currencyConverted' => $convert_to_brl_enabled ? 'BRL' : null,
                'exchangeRateValue' => $exchange_rate_value,
                'cardData' => $cardData,
                'cardType' => $final_card_type,
                'installments' => ($final_card_type === 'credit' && $final_installments >= 1) ? $final_installments : null,
                'brand' => isset($brand['brand']) ? $brand['brand'] : null,
                'returnCode' => isset($returnCode) ? $returnCode : null,
            );

            $bodyArray['cardData']['card_number'] = LknIntegrationRedeForWoocommerceHelper::censorString($bodyArray['cardData']['card_number'], 8);

            // Remove parâmetros desnecessários da resposta
            $cleanedTransaction = $transaction;
            if (is_array($cleanedTransaction)) {
                unset($cleanedTransaction['o'], $cleanedTransaction['k'], $cleanedTransaction['r']);
            }

            $orderLogsArray = array(
                'body' => $bodyArray,
                'response' => $cleanedTransaction
            );

            $orderLogs = json_encode($orderLogsArray);
            $order->update_meta_data('lknWcRedeOrderLogs', $orderLogs);
            $order->save();
        }
    }

    /**
     * Valida a segurança do webhook 3DS para evitar bypass de pagamento
     * 
     * @param WC_Order $order O pedido do WooCommerce
     * @param array $webhook_data Dados do webhook recebido
     * @return bool True se válido, false caso contrário
     */
    private function validate_webhook_security($order, $webhook_data, $validation_type)
    {
        try {
            $tid = sanitize_text_field($webhook_data['tid'] ?? '');
            if($tid === '') {
                return true;
            }
            
            return $this->verify_transaction_with_rede_api($order, $tid, $webhook_data, $validation_type);
            
        } catch (Exception $e) {
            // Log do erro para debug
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->error('3DS Webhook security validation error: ' . $e->getMessage(), array('source' => 'rede_security'));
            }
            return false;
        }
    }
    
    /**
     * Verifica a transação diretamente na API da Rede para validar autenticidade
     * 
     * @param WC_Order $order O pedido do WooCommerce
     * @param string $tid Transaction ID
     * @param array $webhook_data Dados do webhook
     * @return bool True se válido, false caso contrário
     */
    private function verify_transaction_with_rede_api($order, $tid, $webhook_data, $validation_type)
    {
        try {
            // Determina o gateway usado para buscar configurações corretas
            $payment_method = $order->get_payment_method();
            $gateway_mapping = $this->get_gateway_mapping();
            $gateway_config = $gateway_mapping[$payment_method] ?? $gateway_mapping[self::DEFAULT_GATEWAY];
            $gateway_id = $gateway_config['gateway_id'];
            
            $gateway_settings = get_option('woocommerce_' . $gateway_id . '_settings', array());
            $environment = isset($gateway_settings['environment']) ? $gateway_settings['environment'] : 'test';
            
            // Obtém token OAuth2 válido
            LknIntegrationRedeForWoocommerceHelper::refresh_expired_rede_oauth_tokens(20);
            $token_data = LknIntegrationRedeForWoocommerceHelper::get_cached_rede_oauth_token_for_gateway($gateway_id, $environment);

            if (!$token_data || empty($token_data['token'])) {
                return false;
            }

            // Determina ambiente (produção/teste) baseado nas configurações do gateway
            $gateway_settings = get_option('woocommerce_' . $gateway_id . '_settings');
            $environment = $gateway_settings['environment'] ?? 'test';

            // Define URL da API baseada no ambiente
            if ($environment === 'production') {
                $apiUrl = 'https://api.userede.com.br/erede/v2/transactions/' . $tid;
            } else {
                $apiUrl = 'https://sandbox-erede.useredecloud.com.br/v2/transactions/' . $tid;
            }

            // Faz requisição à API da Rede
            $response = wp_remote_get($apiUrl, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token_data['token']
                ),
                'timeout' => 15
            ));

            if (is_wp_error($response)) {
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                return false;
            }
            
            $transaction_data = json_decode($response_body, true);
            $authorization = $transaction_data['authorization'] ?? array();
            if (!$transaction_data || empty($authorization)) {
                return false;
            }
            // Valida dados críticos da transação
            $api_tid = $authorization['tid'] ?? '';
            $api_reference = $authorization['reference'] ?? '';
            $api_amount = $authorization['amount'] ?? 0;
            $api_status = $authorization['status'] ?? '';
            
            // Verifica se TID, reference e amount correspondem
            if ($api_tid !== $tid) {
                return false;
            }
            
            if ($api_reference !== $webhook_data['reference']) {
                return false;
            }
            
            if($api_status === 'Declined' && $validation_type === 'success') {
                return false;
            }

            if($api_status === 'Approved' && $validation_type === 'failure') {
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    private function query_rede_transaction_by_reference($reference)
    {
        try {
            // Obtém token OAuth2 válido
            LknIntegrationRedeForWoocommerceHelper::refresh_expired_rede_oauth_tokens(20);
            $token_data = LknIntegrationRedeForWoocommerceHelper::get_cached_rede_oauth_token_for_gateway(self::GATEWAY_DEBIT, 'test'); // ou 'production'

            if (!$token_data || empty($token_data['token'])) {
                throw new Exception('Could not obtain OAuth token');
            }

            // Determine environment (you might need to get this from settings)
            // Extrair order_id da reference para obter as configurações dinâmicas
            $reference_parts = explode('-', $reference);
            $order_id = intval($reference_parts[0] ?? 0);
            $gateway_settings = array('environment' => 'test'); // fallback
            
            if ($order_id > 0) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $gateway_config = $this->get_dynamic_gateway_config($order);
                    $gateway_settings = $gateway_config['settings'];
                }
            }
            
            $environment = $gateway_settings['environment'] ?? 'test';

            if ($environment === 'production') {
                $apiUrl = 'https://api.userede.com.br/erede/v2/transactions/' . $reference;
            } else {
                $apiUrl = 'https://sandbox-erede.useredecloud.com.br/v2/transactions/' . $reference;
            }

            $response = wp_remote_get($apiUrl, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token_data['token']
                ),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                throw new Exception('API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            if ($response_code !== 200) {
                throw new Exception('API returned error: ' . $response_code . ' - ' . $response_body);
            }
            
            return $response_data;
            
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Atualiza metadados e status do pedido usando dados do webhook 3DS
     */
    private function update_order_metadata_and_status($order, $webhook_data)
    {
        // Configurações do gateway dinamicamente
        $gateway_config = $this->get_dynamic_gateway_config($order);
        $gateway_settings = $gateway_config['settings'];
        $gateway = $gateway_config['gateway_instance'];
        
        // Configurações para conversão de moeda
        $convert_to_brl_enabled = LknIntegrationRedeForWoocommerceHelper::is_convert_to_brl_enabled(self::GATEWAY_DEBIT);
        $default_currency = get_option('woocommerce_currency', 'BRL');
        $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
        $decimals = get_option('woocommerce_price_num_decimals', 2);
        
        // Conversão do total do pedido
        $order_total = $order->get_total();
        $order_total_converted = LknIntegrationRedeForWoocommerceHelper::convert_order_total_to_brl($order_total, $order, $convert_to_brl_enabled);
        $order_total_converted = wc_format_decimal($order_total_converted, $decimals);

        // Dados de câmbio
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

        $brand = !empty($webhook_data['brand_name']) && trim($webhook_data['brand_name']) !== '' && trim($webhook_data['brand_name']) !== 'undefined' ? ucfirst($webhook_data['brand_name']) : '';


        // Salva todos os metadados da transação
        $order->update_meta_data('_wc_rede_transaction_return_code', $webhook_data['returnCode'] ?? '');
        $order->update_meta_data('_wc_rede_transaction_return_message', $webhook_data['returnMessage'] ?? '');
        $order->update_meta_data('_wc_rede_transaction_reference', $webhook_data['reference'] ?? '');
        $order->update_meta_data('_wc_rede_transaction_id', $webhook_data['tid'] ?? '');
        $order->update_meta_data('_wc_rede_transaction_refund_id', $webhook_data['refundId'] ?? '');
        $order->update_meta_data('_wc_rede_transaction_cancel_id', $webhook_data['cancelId'] ?? '');
        $order->update_meta_data('_wc_rede_transaction_nsu', $webhook_data['nsu'] ?? '');
        $order->update_meta_data('_wc_rede_transaction_authorization_code', $webhook_data['authorizationCode'] ?? '');
        
        // Dados do cartão - alguns podem não vir no webhook, usar valores padrão
        $order->update_meta_data('_wc_rede_transaction_bin', $webhook_data['bin'] ?? '');
        $order->update_meta_data('_wc_rede_transaction_card_brand', $brand);
        
        // Auto capture condicional baseado no tipo de cartão salvo nos metadados
        $saved_card_type = $order->get_meta('_wc_rede_card_type') ?: 'debit';
        $saved_installments = $order->get_meta('_wc_rede_installments') ?: 1;
        $auto_capture_setting = sanitize_text_field($gateway_settings['auto_capture'] ?? 'yes') == 'no' ? false : true;
        
        // Sempre true para debit, configurável para credit
        $capture_value = ($saved_card_type === 'debit') ? true : $auto_capture_setting;
        $order->update_meta_data('_wc_rede_captured', $capture_value);
        
        // Metadados financeiros
        $order->update_meta_data('_wc_rede_total_amount', $order->get_total());
        $order->update_meta_data('_wc_rede_total_amount_converted', $order_total_converted);
        $order->update_meta_data('_wc_rede_total_amount_is_converted', $convert_to_brl_enabled ? true : false);
        $order->update_meta_data('_wc_rede_exchange_rate', $exchange_rate_value);
        $order->update_meta_data('_wc_rede_decimal_value', $decimals);

        // Status de autorização se disponível
        if (isset($webhook_data['authorization_status'])) {
            $order->update_meta_data('_wc_rede_transaction_authorization_status', $webhook_data['authorization_status']);
        }

        // Ambiente de transação
        $environment = $gateway_settings['environment'] ?? 'test';
        $order->update_meta_data('_wc_rede_transaction_environment', $environment);

        $order->save();

        // Salvar metadados da transação usando helper
        $pv = $gateway_settings['pv'] ?? '';
        $token = $gateway_settings['token'] ?? '';

        $webhook_data['return_http'] = '200';
        $webhook_data['3ds_auth'] = 'success';
        
        // Preparar dados do cartão para o helper
        $cardNumber = $order->get_meta('_wc_rede_transaction_card_number') ?: ('**** **** **** ' . ($webhook_data['last4'] ?? '****'));
        $cardExpiry = $order->get_meta('_wc_rede_transaction_expiration') ?: '**/**';
        $cardHolder = $order->get_meta('_wc_rede_transaction_holder') ?: 'Card Holder';

        $cardData = array(
            'card_number' => $order->get_meta('_wc_rede_transaction_card_number') ?: ('**** **** **** ' . ($webhook_data['last4'] ?? '****')),
            'card_holder' => $order->get_meta('_wc_rede_transaction_holder') ?: 'Card Holder',
            'card_expiration_month' => $order->get_meta('_wc_rede_transaction_expiration_month') ?: '**',
            'card_expiration_year' => $order->get_meta('_wc_rede_transaction_expiration_year') ?: '****',
            'card_cvv' => $order->get_meta('_wc_rede_transaction_cvv') ?: '***',
            'card_type' => $saved_card_type
        );
        
        LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
            $order, 
            $webhook_data, 
            $cardNumber, 
            $cardExpiry, 
            $cardHolder,
            $saved_installments, 
            $order_total_converted, 
            $order_currency, 
            $brand, 
            $pv, 
            $token,
            $webhook_data['reference'] ?? '', 
            $order->get_id(), 
            $capture_value, 
            $saved_card_type === 'credit' ? 'Credit' : 'Debit', 
            isset($cardData['card_cvv']) && $cardData['card_cvv'] !== '***' ? $cardData['card_cvv'] : 'N/A',
            $gateway_settings, 
            $webhook_data['tid'] ?? '',
            $webhook_data['nsu'] ?? '',
            $webhook_data['authorizationCode'] ?? '',
            $webhook_data['returnCode'] ?? '',
            $webhook_data['returnMessage'] ?? ''
        );

        // Debug logging se habilitado
        if (($gateway_settings['debug'] ?? 'no') === 'yes') {
            $tId = $webhook_data['tid'] ?? null;
            $returnCode = $webhook_data['returnCode'] ?? null;
            $brandDetails = null;
            
            if ($tId) {
                // Usa a instância já obtida do gateway para usar o helper
                $brandDetails = LknIntegrationRedeForWoocommerceHelper::getTransactionBrandDetails($tId, $gateway);
            }

            $logger = wc_get_logger();
            $logger->info('3DS Webhook - Transaction processed', array(
                'source' => $gateway_config['gateway_id'],
                'transaction' => $webhook_data,
                'order' => array(
                    'orderId' => $order->get_id(),
                    'amount' => $order_total_converted,
                    'orderCurrency' => $order_currency,
                    'currencyConverted' => $convert_to_brl_enabled ? 'BRL' : null,
                    'exchangeRateValue' => $exchange_rate_value,
                    'status' => $order->get_status(),
                    'brand' => isset($brandDetails['brand']) ? $brandDetails['brand'] : ($webhook_data['brand_name'] ?? null),
                    'returnCode' => $returnCode,
                ),
            ));
            
            // Incluir installments apenas para cartão de crédito
            if ($saved_card_type === 'credit') {
                $cardData['installments'] = $saved_installments;
            }
            
            $this->regOrderLogs($order->get_id(), $order_total_converted, $cardData, $webhook_data, $order);
        }

        // Processa status do pedido usando a função específica para 3DS (inclui lógica de auto_capture)
        $this->process_3ds_order_status($order, $webhook_data, __('3D Secure authentication completed with challenge', 'woo-rede'));
    }

    /**
     * Detecta dinamicamente o gateway e suas configurações baseado no método de pagamento
     * 
     * @param WC_Order $order O pedido do WooCommerce
     * @return array Array contendo 'settings', 'gateway_instance' e 'gateway_id'
     */
    private function get_dynamic_gateway_config($order)
    {
        $payment_method = $order->get_payment_method();
        $gateway_mapping = $this->get_gateway_mapping();
        
        // Busca a configuração do gateway baseada no payment method
        $gateway_config = $gateway_mapping[$payment_method] ?? $gateway_mapping[self::DEFAULT_GATEWAY];
        
        $gateway_id = $gateway_config['gateway_id'];
        $settings = get_option('woocommerce_' . $gateway_id . '_settings', array());
        
        // Instancia a classe do gateway apropriada
        $gateway_instance = null;
        
        // Verifica se a classe existe antes de instanciar
        if (class_exists($gateway_config['class'])) {
            $gateway_instance = new $gateway_config['class']();
        } else {
            // Fallback para a classe padrão se a classe não existir (ex: plugin PRO não ativo)
            $fallback_config = $gateway_mapping[self::DEFAULT_GATEWAY];
            $gateway_instance = new $fallback_config['class']();
        }
        
        return array(
            'settings' => $settings,
            'gateway_instance' => $gateway_instance,
            'gateway_id' => $gateway_id,
            'config' => $gateway_config
        );
    }

}
