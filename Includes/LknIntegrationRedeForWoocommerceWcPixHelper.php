<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use DateTime;

final class LknIntegrationRedeForWoocommerceWcPixHelper
{
    public static function getPixRede($total, $pixInstance, $reference, $order)
    {
        // Determinar o ID do gateway baseado na instância
        $gateway_id = isset($pixInstance->id) ? $pixInstance->id : 'integration_rede_pix';
        $order_id = $order->get_id();
        
        // Obter token OAuth2 usando o sistema de cache específico do gateway
        $access_token = LknIntegrationRedeForWoocommerceHelper::get_rede_oauth_token_for_gateway($gateway_id, $order_id);
        
        if ($access_token === null) {
            return false;
        }
        
        // Agora usar o token para a requisição de PIX
        $pv = sanitize_text_field($pixInstance->get_option('pv'));
        $total = str_replace(".", "", $total);
        $token = sanitize_text_field($pixInstance->get_option('token'));
        $auth = base64_encode($pv . ':' . $token);
        $environment = $pixInstance->get_option('environment');
        $expirationCountOption = $pixInstance->get_option('expiration_count');
        $expirationCount = empty($expirationCountOption) ? 1 : $expirationCountOption;
        $date = new DateTime();
        $date->modify('+' . $expirationCount . ' hours');
        $dateTimeExpiration = $date->format('Y-m-d\TH:i:s');
        $order->update_meta_data('_wc_rede_pix_integration_time_expiration', $dateTimeExpiration);
        
        // Usar a nova URL da API v2 com o Bearer token
        if ('production' === $environment) {
            $apiUrl = 'https://api.userede.com.br/erede/v2/transactions';
        } else {
            $apiUrl = 'https://sandbox-erede.useredecloud.com.br/v2/transactions';
        }

        $body = array(
            'kind' => 'pix',
            'reference' => $reference,
            'amount' => $total,
            'qrCode' => array(
                'dateTimeExpiration' => $dateTimeExpiration
            )
        );

        $response = wp_remote_post($apiUrl, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            // Salvar metadados em caso de erro da requisição
            $customErrorResponse = LknIntegrationRedeForWoocommerceHelper::createCustomErrorResponse(
                500,
                44,
                __('Request error: ', 'woo-rede') . $response->get_error_message()
            );
            
            $default_currency = get_option('woocommerce_currency', 'BRL');
            $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
            
            LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                $order, $customErrorResponse, '', '', '',
                1, $total, $order_currency, '', $pv, $token,
                $reference, $order_id, true, 'PIX', '',
                $pixInstance, '', '', '', 44, __('Request error: ', 'woo-rede') . $response->get_error_message()
            );
            $order->save();
            
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_body = json_decode($response_body, true);
        
        // Adicionar o status HTTP na resposta
        if (is_array($response_body)) {
            $response_body['return_http'] = $response_code;
        } else {
            $response_body = array('return_http' => $response_code);
        }

        // Preparar dados para salvar metadados
        $default_currency = get_option('woocommerce_currency', 'BRL');
        $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
        
        if ($response_code !== 200 && $response_code !== 201) {
            // Salvar metadados em caso de erro HTTP
            LknIntegrationRedeForWoocommerceHelper::saveTransactionMetadata(
                $order, $response_body, '', '', '',
                1, $total, $order_currency, '', $pv, $token,
                $reference, $order_id, true, 'PIX', '',
                $pixInstance, 
                $response_body['tid'] ?? '',
                $response_body['nsu'] ?? '',
                '',
                $response_body['returnCode'] ?? '',
                $response_body['returnMessage'] ?? ''
            );
        }
        
        if ($pixInstance->get_option('debug') == 'yes') {
            $orderLogsArray = array(
                'oauth_token_cached' => true,
                'url' => $apiUrl,
                'body' => $body,
                'response' => $response_body
            );

            $orderLogs = json_encode($orderLogsArray);
            $order->update_meta_data('lknWcRedeOrderLogs', $orderLogs);
        }

        return $response_body;
    }

    public static function refundPixRede($total, $pixInstance, $orderId)
    {
        // Determinar o ID do gateway baseado na instância
        $gateway_id = isset($pixInstance->id) ? $pixInstance->id : 'integration_rede_pix';
        
        // Obter token OAuth2 usando o sistema de cache específico do gateway  
        $access_token = LknIntegrationRedeForWoocommerceHelper::get_rede_oauth_token_for_gateway($gateway_id);
        
        if ($access_token === null) {
            return false;
        }
        
        $total = str_replace(".", "", $total);
        $environment = $pixInstance->get_option('environment');
        $order = wc_get_order($orderId);
        $tid = $order->get_meta('_wc_rede_integration_pix_transaction_tid');
        
        if ('production' === $environment) {
            $apiUrl = 'https://api.userede.com.br/erede/v2/transactions';
        } else {
            $apiUrl = 'https://sandbox-erede.useredecloud.com.br/v2/transactions';
        }

        $body = array(
            'amount' => $total,
        );

        $response = wp_remote_post($apiUrl . '/' . $tid . '/refunds', array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ),
            'body' => wp_json_encode($body),
        ));

        $response_body = wp_remote_retrieve_body($response);
        $response_body = json_decode($response_body, true);

        return $response_body;
    }

    public static function generateMetaTable($order, $metaKeys, $title): void
    {
        require INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/views/LknIntegrationRedeForWoocommerceMetaTable.php';
    }
}
