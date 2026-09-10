<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use WC_Order;
use HelgeSverre\Toon\Toon;

class LknIntegrationRedeForWoocommerceHelper
{
    final public static function getCartTotal()
    {
        global $woocommerce;
        if (empty($woocommerce)) {
            return 0;
        }
        if ($woocommerce->cart) {
            return (float) $woocommerce->cart->total;
        }
        return 0;
    }

    final public static function updateFixLoadScriptOption($id): void
    {
        $wpnonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';

        if (! empty($wpnonce) && $section === $id) {
            $enabledFixLoadScript = isset($_POST["woocommerce_" . $id . "_enabled_fix_load_script"]) ? 'yes' : 'no';

            $optionsToUpdate = array(
                'maxipago_credit',
                'maxipago_debit',
                'rede_credit',
                'rede_debit',
            );

            foreach ($optionsToUpdate as $option) {
                $paymentOptions = get_option('woocommerce_' . $option . '_settings', array());
                $paymentOptions['enabled_fix_load_script'] = $enabledFixLoadScript;
                update_option('woocommerce_' . $option . '_settings', $paymentOptions);
            }
        }
    }

    final public static function getTransactionBrandDetails($tid, $instance)
    {
        // Usar OAuth2 para API v2
        $oauth_token = self::get_rede_oauth_token_for_gateway($instance->id);
        
        if (!$oauth_token) {
            return null;
        }

        $apiUrl = ('production' === $instance->environment)
            ? 'https://api.userede.com.br/erede/v2/transactions'
            : 'https://sandbox-erede.useredecloud.com.br/v2/transactions';

        $headers = array(
            'Authorization' => 'Bearer ' . $oauth_token,
            'Content-Type' => 'application/json',
            'Transaction-Response' => 'brand-return-opened',
        );

        $response = wp_remote_get($apiUrl . '/' . $tid, array(
            'headers' => $headers,
        ));


        if (is_wp_error($response)) {
            return null;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (isset($response_data['authorization']['brand'])) {
            return [
                'brand' => $response_data['authorization']['brand'] ?? null,
                'returnCode' => $response_data['authorization']['returnCode'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Detecta a bandeira do cartão a partir do BIN (primeiros 6 dígitos).
     * Usado como fallback quando o TID não está disponível (ex: returnCode 64).
     * Retorna apenas o nome da bandeira; os demais campos do objeto brand não
     * são recuperáveis sem TID pois dependem de uma autorização concluída.
     */
    final public static function getBrandFromBin($bin)
    {
        if (empty($bin)) {
            return '';
        }

        $bin = preg_replace('/\D/', '', $bin);
        $bin6 = substr($bin, 0, 6);
        $bin4 = substr($bin, 0, 4);
        $bin2 = substr($bin, 0, 2);
        $bin1 = substr($bin, 0, 1);

        // Hipercard
        $hipercard_bins = array('606282', '637095', '637568', '637599', '637609', '637612');
        if (in_array($bin6, $hipercard_bins, true)) {
            return 'Hipercard';
        }

        // Elo — principais faixas usadas no Brasil
        $elo_bin6_list = array(
            '401178', '401179', '438935', '451416', '457393', '504175',
            '506699', '506778', '509000', '509999', '627780', '636297',
            '636368', '650031', '650033', '650035', '650051', '650405',
            '650439', '650485', '650486', '650487', '650488', '650489',
            '650491', '650495', '650501', '650503', '650506', '650533',
            '650536', '650540', '650541', '650598', '650720', '650727',
            '650901', '650978', '651652', '651679', '655000', '655019',
            '655021', '655058',
        );
        $elo_bin4_list = array('4576', '4011', '5067');
        if (in_array($bin6, $elo_bin6_list, true) || in_array($bin4, $elo_bin4_list, true)) {
            return 'Elo';
        }

        // American Express
        if (in_array($bin2, array('34', '37'), true)) {
            return 'Amex';
        }

        // Diners Club
        $diners_prefixes = array('300', '301', '302', '303', '304', '305');
        if (in_array(substr($bin, 0, 3), $diners_prefixes, true) || in_array($bin2, array('36', '38'), true)) {
            return 'Diners';
        }

        // Discover
        if ($bin4 === '6011' || $bin2 === '65') {
            return 'Discover';
        }

        // Mastercard — BIN 51-55 e faixa 2221-2720
        if (in_array($bin2, array('51', '52', '53', '54', '55'), true)) {
            return 'Mastercard';
        }
        $bin_int4 = intval($bin4);
        if ($bin_int4 >= 2221 && $bin_int4 <= 2720) {
            return 'Mastercard';
        }

        // Visa
        if ($bin1 === '4') {
            return 'Visa';
        }

        return '';
    }

    /**
     * Busca dados completos da transação pela API da Rede usando TID
     * e preenche metadados faltantes no pedido
     */
    final public static function getTransactionCompleteData($tid, $instance, $order = null)
    {
        // Usar OAuth2 para API v2
        $oauth_token = self::get_rede_oauth_token_for_gateway($instance->id);
        
        if (!$oauth_token) {
            return null;
        }

        $apiUrl = ('production' === $instance->environment)
            ? 'https://api.userede.com.br/erede/v2/transactions'
            : 'https://sandbox-erede.useredecloud.com.br/v2/transactions';

        $headers = array(
            'Authorization' => 'Bearer ' . $oauth_token,
            'Content-Type' => 'application/json',
            'Transaction-Response' => 'brand-return-opened',
        );

        $response = wp_remote_get($apiUrl . '/' . $tid, array(
            'headers' => $headers,
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return null;
        }

        $response_body = wp_remote_retrieve_body($response);
        $transaction_data = json_decode($response_body, true);

        if (!$transaction_data || !isset($transaction_data['authorization'])) {
            return null;
        }

        $authorization = $transaction_data['authorization'];
        $complete_data = array(
            'tid' => $authorization['tid'] ?? $tid,
            'reference' => $authorization['reference'] ?? '',
            'returnCode' => $authorization['returnCode'] ?? '',
            'returnMessage' => $authorization['returnMessage'] ?? '',
            'nsu' => $authorization['nsu'] ?? '',
            'authorizationCode' => $authorization['authorizationCode'] ?? '',
            'cardBin' => $authorization['cardBin'] ?? '',
            'last4' => $authorization['last4'] ?? '',
            'brand' => isset($authorization['brand']['name']) ? $authorization['brand']['name'] : '',
            'capture' => $transaction_data['capture'] ?? false,
            'installments' => $transaction_data['installments'] ?? 1,
            'amount' => $transaction_data['amount'] ?? 0,
        );

        // Se um pedido foi fornecido, preencher metadados faltantes
        if ($order && $order instanceof \WC_Order) {
            $meta_mappings = array(
                '_wc_rede_transaction_reference' => 'reference',
                '_wc_rede_transaction_return_code' => 'returnCode',
                '_wc_rede_transaction_return_message' => 'returnMessage',
                '_wc_rede_transaction_nsu' => 'nsu',
                '_wc_rede_transaction_authorization_code' => 'authorizationCode',
                '_wc_rede_transaction_bin' => 'cardBin',
                '_wc_rede_transaction_last4' => 'last4',
                '_wc_rede_transaction_brand' => 'brand',
                '_wc_rede_transaction_installments' => 'installments',
            );

            $updated = false;
            foreach ($meta_mappings as $meta_key => $data_key) {
                // Só atualiza se o metadado estiver vazio e tivermos o dado da API
                if (empty($order->get_meta($meta_key)) && !empty($complete_data[$data_key])) {
                    $order->update_meta_data($meta_key, $complete_data[$data_key]);
                    $updated = true;
                }
            }

            // Salvar se algum metadado foi atualizado
            if ($updated) {
                $order->save();
            }
        }

        return $complete_data;
    }

    final public static function getCardBrand($tid, $instance)
    {
        // Usar OAuth2 para API v2
        $oauth_token = self::get_rede_oauth_token_for_gateway($instance->id);
        
        if (!$oauth_token) {
            return null;
        }

        if ('production' === $instance->environment) {
            $apiUrl = 'https://api.userede.com.br/erede/v2/transactions';
        } else {
            $apiUrl = 'https://sandbox-erede.useredecloud.com.br/v2/transactions';
        }

        $response = wp_remote_get($apiUrl . '/' . $tid, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $oauth_token,
                'Content-Type' => 'application/json',
                'Transaction-Response' => 'brand-return-opened'
            ),
        ));

        $response_body = wp_remote_retrieve_body($response);
        $response_body = json_decode($response_body, true);

        // Verificar se a estrutura brand existe na authorization
        if (isset($response_body['authorization']['brand']['name'])) {
            return $response_body['authorization']['brand']['name'];
        }
        
        // Fallback para casos onde não há informação da brand
        return null;
    }

    final public static function censorString($string, $censorLength)
    {
        $length = strlen($string);

        if ($censorLength >= $length) {
            // Se o número de caracteres a censurar for maior ou igual ao comprimento total, censura tudo
            return str_repeat('*', $length);
        }

        $startLength = floor(($length - $censorLength) / 2); // Dividir o restante igualmente entre início e fim
        $endLength = $length - $startLength - $censorLength; // O que sobra para o final

        $start = substr($string, 0, $startLength);
        $end = substr($string, -$endLength);

        $censored = str_repeat('*', $censorLength);
        return $start . $censored . $end;
    }

    public function showOrderLogs(): void
    {
        $id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : '';
        if (empty($id)) {
            $id = isset($_GET['post']) ? sanitize_text_field(wp_unslash($_GET['post'])) : '';
        }
        if (! empty($id)) {
            $order_id = $id;
            $order = wc_get_order($order_id);

            if ($order && $order instanceof WC_Order) {
                $orderLogs = $order->get_meta('lknWcRedeOrderLogs');
                $payment_method_id = $order->get_payment_method();
                $options = get_option('woocommerce_' . $payment_method_id . '_settings');
                if (isset($options['show_order_logs']) && $orderLogs && 'yes' === $options['show_order_logs']) {
                    $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && wc_get_container()->get('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')->custom_orders_table_usage_is_enabled()
                        ? wc_get_page_screen_id('shop-order')
                        : 'shop_order';

                    add_meta_box(
                        'showOrderLogs',
                        'Logs das transações',
                        array($this, 'showLogsContent'),
                        $screen,
                        'advanced',
                    );
                }
            }
        }
    }

    /**
     * Checks if BRL conversion is enabled via pro plugin license and option.
     * @return bool
     */
    public static function is_convert_to_brl_enabled($id)
    {
        if (function_exists('is_plugin_active') && is_plugin_active('rede-for-woocommerce-pro/rede-for-woocommerce-pro.php')) {
            $pro_license = get_option('lknRedeForWoocommerceProLicense');
            if ($pro_license) {
                $license_data = base64_decode($pro_license);
                if (strpos($license_data, 'active') !== false) {
                    $options = get_option('woocommerce_' . $id . '_settings', []);
                    $convert_to_brl_option = isset($options['convert_to_brl']) ? $options['convert_to_brl'] : 'no';
                    return ($convert_to_brl_option === 'yes');
                }
            }
        }
        return false;
    }

    /**
     * Converts the order total to BRL if enabled and rates are available.
     * @param float|string $order_total
     * @param WC_Order $order
     * @param bool $convert_to_brl_enabled
     * @return float|string Converted order total or original if not converted
     */
    public static function convert_order_total_to_brl($order_total, $order, $convert_to_brl_enabled)
    {
        if ($convert_to_brl_enabled) {
            $currency_json_path = INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/files/linkCurrencies.json';
            // Garante que o diretório e arquivo existem
            LknIntegrationRedeForWoocommerceHelper::ensure_currency_json_path($currency_json_path);
            $currency_transient = INTEGRATION_REDE_FOR_WOOCOMMERCE_RATE_CACHE_KEY;
            if (!get_transient($currency_transient)) {
                LknIntegrationRedeForWoocommerceHelper::lkn_update_currency_rates($currency_json_path);
            }
            $currency_data = LknIntegrationRedeForWoocommerceHelper::lkn_get_currency_rates($currency_json_path);
            $default_currency = get_option('woocommerce_currency', 'BRL');
            $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $default_currency;
            if ($order_currency !== 'BRL' && !empty($currency_data['rates'][$order_currency])) {
                $rate = floatval($currency_data['rates'][$order_currency]);
                if ($rate > 0) {
                    $order_total = $order_total * (1 / $rate); // Convert to BRL
                }
            }
        }
        return $order_total;
    }

    // Update currency rates from JSON file
    public static function lkn_update_currency_rates($json_path)
    {
        $url = INTEGRATION_REDE_FOR_WOOCOMMERCE_LINK_URL_API . '/cotacao/cotacao-BRL.json';
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            delete_transient(INTEGRATION_REDE_FOR_WOOCOMMERCE_RATE_CACHE_KEY);
            return false;
        }
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            delete_transient(INTEGRATION_REDE_FOR_WOOCOMMERCE_RATE_CACHE_KEY);
            return false;
        }
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $result = $wp_filesystem->put_contents($json_path, $body, FS_CHMOD_FILE);
        if ($result) {
            set_transient(INTEGRATION_REDE_FOR_WOOCOMMERCE_RATE_CACHE_KEY, true, 2 * HOUR_IN_SECONDS);
            return json_decode($body, true);
        } else {
            delete_transient(INTEGRATION_REDE_FOR_WOOCOMMERCE_RATE_CACHE_KEY);
            return false;
        }
    }

    /**
     * Ensures the currency JSON directory and file exist, creating them if necessary.
     * Uses WordPress filesystem APIs for permissions.
     */
    public static function ensure_currency_json_path($json_path)
    {
        $dir = dirname($json_path);
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        // Cria o diretório se não existir
        if (!is_dir($dir)) {
            $wp_filesystem->mkdir($dir, FS_CHMOD_DIR);
            delete_transient(INTEGRATION_REDE_FOR_WOOCOMMERCE_RATE_CACHE_KEY);
        }
        // Cria o arquivo vazio se não existir
        if (!file_exists($json_path)) {
            $wp_filesystem->put_contents($json_path, '{}', FS_CHMOD_FILE);
            delete_transient(INTEGRATION_REDE_FOR_WOOCOMMERCE_RATE_CACHE_KEY);
        }

        // Verifica se o conteúdo do arquivo é vazio, apenas '{}' ou estrutura inválida
        $content = $wp_filesystem->get_contents($json_path);
        $is_invalid = false;
        if (trim($content) === '{}' || trim($content) === '') {
            $is_invalid = true;
        } else {
            $json = json_decode($content, true);
            // Estrutura esperada: base, date, rates (rates é array)
            if (!is_array($json) || !isset($json['base']) || !isset($json['date']) || !isset($json['rates']) || !is_array($json['rates'])) {
                $is_invalid = true;
            }
        }
        if ($is_invalid) {
            delete_transient(INTEGRATION_REDE_FOR_WOOCOMMERCE_RATE_CACHE_KEY);
        }
    }

    // Get currency rates from JSON file
    public static function lkn_get_currency_rates($json_path)
    {
        if (!file_exists($json_path)) return false;
        $json = file_get_contents($json_path);
        if (empty($json)) return false;
        return json_decode($json, true);
    }

    public function showLogsContent($object): void
    {
        // Obter o objeto WC_Order
        $order = is_a($object, 'WP_Post') ? wc_get_order($object->ID) : $object;
        $orderLogs = $order->get_meta('lknWcRedeOrderLogs');

        // Decodificar o JSON armazenado
        $decodedLogs = json_decode($orderLogs, true);

        if ($decodedLogs && is_array($decodedLogs)) {
            // Preparar cada seção para exibição com formatação
            $url = $decodedLogs['url'] ?? 'N/A';
            $body = isset($decodedLogs['body']) ? json_encode($decodedLogs['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'N/A';
            $response = isset($decodedLogs['response']) ? json_encode($decodedLogs['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'N/A';

            // Exibir as seções formatadas
?>
            <div id="lknWcRedeOrderLogs">
                <div>
                    <h3>URL:</h3>
                    <pre class="wc-pre"><?php echo esc_html($url); ?></pre>
                </div>

                <h3>Body:</h3>
                <pre class="wc-pre"><?php echo esc_html($body); ?></pre>

                <h3>Response:</h3>
                <pre class="wc-pre"><?php echo esc_html($response); ?></pre>
            </div>
<?php
        }
    }

    public static function getUrlIcon()
    {
        return plugin_dir_url(__DIR__) . "Includes/assets/icons/icon.svg";
    }

    public static function lknIntegrationRedeProRedeInterest($order_total, $interest, $i, $option, $instance, $order_id = null) 
    {

        $installments = isset($_POST[$instance->id . '_installments']) ?
        absint(sanitize_text_field(wp_unslash($_POST[$instance->id . '_installments']))) : 1;
        $interest = round((float) $instance->get_option($i . 'x'), 2);

        // Usar o subtotal + frete como base para cálculo de juros
        $base_amount = $order_total;
        $additional_fees = 0;
        $discount_amount = 0;
        $tax_amount = 0;
        
        if (WC()->cart && !WC()->cart->is_empty()) {
            $cart_subtotal = WC()->cart->get_subtotal();
            $cart_shipping = WC()->cart->get_shipping_total();
            if ($cart_subtotal > 0) {
                // Pegar desconto de cupom
                $discount_amount = WC()->cart->get_discount_total();
                
                $base_amount = $cart_subtotal + $cart_shipping - $discount_amount;
                
                // Pegar fees externos (não criados por este plugin)
                $additional_fees = 0;
                foreach (WC()->cart->get_fees() as $fee) {
                    // Ignorar fees criados pelo próprio plugin
                    if ($fee->name !== __('Interest', 'woo-rede') && 
                        $fee->name !== __('Discount', 'woo-rede')) {
                        $additional_fees += $fee->total;
                    }
                }
                
                // Pegar taxes
                $tax_amount = WC()->cart->get_total_tax();
            }
        } elseif ($order_id && function_exists('wc_get_order')) {
            // Se estivermos processando um pedido, usar dados do pedido
            $order = wc_get_order($order_id);
            if ($order) {
                $order_subtotal = $order->get_subtotal();
                $order_shipping = $order->get_shipping_total();
                if ($order_subtotal > 0) {
                    // Pegar desconto de cupom do pedido
                    $discount_amount = $order->get_total_discount();
                    
                    $base_amount = $order_subtotal + $order_shipping - $discount_amount;
                    
                    // Pegar fees externos do pedido (não criados por este plugin)
                    $additional_fees = 0;
                    foreach ($order->get_fees() as $fee) {
                        // Ignorar fees criados pelo próprio plugin
                        if ($fee->get_name() !== __('Interest', 'woo-rede') && 
                            $fee->get_name() !== __('Discount', 'woo-rede')) {
                            $additional_fees += $fee->get_total();
                        }
                    }
                    
                    // Pegar taxes do pedido
                    $tax_amount = $order->get_total_tax();
                }
            }
        }

        switch ($option) {
            case 'label':
                // Verificar se existe um limite de parcelas por produto
                $extra_fees = 0;

                if ($instance->get_option('installment_interest') == 'yes') {
                    $total = $base_amount / $i;
                    if ($total > $instance->get_option('min_interest') && $instance->get_option('min_interest') > 0) {
                        $interest = 0;
                    }
                    if ($interest >= 1) {
                        // Calcular juros apenas sobre a base (subtotal + shipping - cupom)
                        $total_with_interest = $base_amount + ($base_amount * ($interest * 0.01));
                        
                        // Adicionar outros valores: fees externos + taxes
                        $final_total = $total_with_interest + $additional_fees + $tax_amount;
                        
                        if ($instance->get_option('interest_show_percent') == 'yes') {
                            /* translators: %1$d: number of installments, %2$s: installment price, %3$s: interest percentage */
                            return html_entity_decode(sprintf('%dx de %s (%s%% de juros)', $i, wp_strip_all_tags( wc_price( $final_total / $i)), $interest));
                        }
                            /* translators: %1$d: number of installments, %2$s: installment price */
                            return html_entity_decode(sprintf('%dx de %s', $i, wp_strip_all_tags( wc_price(($final_total / $i)))));
                    } else {
                        // Sem juros, mas ainda aplicar outros valores
                        $final_total = $base_amount + $additional_fees + $tax_amount;
                        if ($instance->get_option('interest_show_percent') == 'yes') {
                            /* translators: %1$d: number of installments, %2$s: installment price */
                            return html_entity_decode(sprintf('%dx de %s', $i, wp_strip_all_tags( wc_price( $final_total / $i)))) . ' ' . __("interest-free", 'woo-rede');
                        }
                        /* translators: %1$d: number of installments, %2$s: installment price */
                        return html_entity_decode(sprintf('%dx de %s', $i, wp_strip_all_tags( wc_price( $final_total / $i))));
                    }
                } else {
                    $discount = round((float) $instance->get_option($i . 'x_discount'), 0);
                    $total_with_discount = $base_amount - ($base_amount * ($discount * 0.01));
                    
                    // Adicionar outros valores: fees externos + taxes
                    $final_total = $total_with_discount + $additional_fees + $tax_amount;
                    
                    if ($discount >= 1) {
                        if ($instance->get_option('interest_show_percent') == 'yes') {
                            /* translators: %1$d: number of installments, %2$s: installment price, %3$s: discount percentage */
                            return html_entity_decode(sprintf( '%dx de %s (%s%% de desconto)', $i, wp_strip_all_tags( wc_price(($final_total / $i))), $discount));
                        }
                        /* translators: %1$d: number of installments, %2$s: installment price */
                        return html_entity_decode(sprintf( '%dx de %s', $i, wp_strip_all_tags( wc_price(($final_total / $i)))));
                    } else {
                        /* translators: %1$d: number of installments, %2$s: installment price */
                        return html_entity_decode(sprintf( '%dx de %s', $i, wp_strip_all_tags( wc_price(($final_total / $i)))));
                    }
                }

                break;
        }
    }

    /**
     * Obtém as credenciais de um gateway específico
     */
    final public static function get_gateway_credentials($gateway_id)
    {
        $gateway_settings = get_option('woocommerce_' . $gateway_id . '_settings', array());
        
        // Verificar se o gateway está habilitado
        if (!isset($gateway_settings['enabled']) || $gateway_settings['enabled'] !== 'yes') {
            return false;
        }
        
        // Verificar se as credenciais estão configuradas
        $pv = isset($gateway_settings['pv']) ? trim($gateway_settings['pv']) : '';
        $token = isset($gateway_settings['token']) ? trim($gateway_settings['token']) : '';
        $environment = isset($gateway_settings['environment']) ? $gateway_settings['environment'] : 'test';
        
        if (empty($pv) || empty($token)) {
            return false;
        }
        
        return array(
            'pv' => $pv,
            'token' => $token,
            'environment' => $environment
        );
    }

    /**
     * Gera Basic Authorization para um gateway específico
     */
    final public static function generate_basic_auth($gateway_id)
    {
        $credentials = self::get_gateway_credentials($gateway_id);
        
        if ($credentials === false) {
            return false;
        }
        
        return base64_encode($credentials['pv'] . ':' . $credentials['token']);
    }

    /**
     * Gera token OAuth2 para API Rede v2 usando credenciais específicas de um gateway
     */
    final public static function generate_rede_oauth_token_for_gateway($gateway_id, $order_id = null)
    {
        $credentials = self::get_gateway_credentials($gateway_id);
        
        if ($credentials === false) {
            return false;
        }
        
        $auth = base64_encode($credentials['pv'] . ':' . $credentials['token']);
        $environment = $credentials['environment'];
        
        $oauth_url = $environment === 'production' 
            ? 'https://api.userede.com.br/redelabs/oauth2/token'
            : 'https://rl7-sandbox-api.useredecloud.com.br/oauth2/token';

        $oauth_response = wp_remote_post($oauth_url, array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => 'grant_type=client_credentials',
            'timeout' => 30
        ));

        if (is_wp_error($oauth_response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($oauth_response);
        $oauth_body = wp_remote_retrieve_body($oauth_response);
        $oauth_data = json_decode($oauth_body, true);

        // Se a requisição falhou e temos um order_id, logar o erro
        if ($response_code !== 200 && $response_code !== 201 && !empty($order_id)) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : get_option('woocommerce_currency', 'BRL');
                // Adaptar: returnCode = código HTTP, returnMessage = error (ex: invalid_client)
                $customErrorResponse = self::createCustomErrorResponse(
                    $response_code,
                    $response_code,
                    isset($oauth_data['error']) ? $oauth_data['error'] : __('OAuth token generation failed', 'woo-rede')
                );
                self::saveTransactionMetadata(
                    $order, $customErrorResponse, 'N/A', 'N/A', $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    1, $order->get_total(), $order_currency, '', $credentials['pv'], $credentials['token'],
                    $order_id . '-' . time(), $order_id, true, 'OAuth', 'N/A',
                    null, '', '', '', $response_code, isset($oauth_data['error']) ? $oauth_data['error'] : __('OAuth token generation failed', 'woo-rede')
                );
                $order->save();
            }
        }

        if (!isset($oauth_data['access_token'])) {
            return false;
        }
        
        return $oauth_data;
    }

    /**
     * Salva token OAuth2 específico de um gateway no cache
     */
    final public static function cache_rede_oauth_token_for_gateway($gateway_id, $token_data, $environment)
    {
        $cache_data = array(
            'token' => $token_data['access_token'],
            'expires_in' => $token_data['expires_in'],
            'generated_at' => time(),
            'environment' => $environment,
            'gateway_id' => $gateway_id
        );
        
        // Codifica em base64 para segurança
        $encoded_data = base64_encode(json_encode($cache_data));
        
        $option_name = 'lkn_rede_oauth_token_' . $gateway_id . '_' . $environment;
        update_option($option_name, $encoded_data);
        
        return $cache_data;
    }

    /**
     * Recupera token OAuth2 específico de um gateway do cache
     */
    final public static function get_cached_rede_oauth_token_for_gateway($gateway_id, $environment)
    {
        $option_name = 'lkn_rede_oauth_token_' . $gateway_id . '_' . $environment;
        $cached_data = get_option($option_name, '');
        
        if (empty($cached_data)) {
            return null;
        }
        
        // Decodifica do base64
        $decoded_data = json_decode(base64_decode($cached_data), true);
        
        if (!$decoded_data || !isset($decoded_data['token']) || !isset($decoded_data['generated_at'])) {
            return null;
        }
        
        return $decoded_data;
    }

    /**
     * Obtém token OAuth2 válido específico de um gateway
     */
    final public static function get_rede_oauth_token_for_gateway($gateway_id, $order_id = null)
    {
        $credentials = self::get_gateway_credentials($gateway_id);
        
        if ($credentials === false) {
            return null;
        }
        
        $environment = $credentials['environment'];
        
        // Tenta recuperar do cache
        $cached_token = self::get_cached_rede_oauth_token_for_gateway($gateway_id, $environment);
        
        // Se token está válido, retorna ele
        if ($cached_token && self::is_rede_oauth_token_valid($cached_token)) {
            return $cached_token['token'];
        }
        
        // Token não existe ou expirou, tenta gerar novo
        $token_data = self::generate_rede_oauth_token_for_gateway($gateway_id, $order_id);
        
        // Se falhou ao gerar novo token
        if ($token_data === false) {
            // Se há um token em cache (mesmo expirado), usa ele como fallback
            if ($cached_token && isset($cached_token['token'])) {
                return $cached_token['token'];
            }
            
            // Se não há token em cache, retorna null para forçar erro na API
            return null;
        }
        
        // Salva o novo token no cache
        self::cache_rede_oauth_token_for_gateway($gateway_id, $token_data, $environment);
        
        return $token_data['access_token'];
    }

    /**
     * Força renovação dos tokens OAuth2 para todos os gateways configurados
     */
    final public static function refresh_all_rede_oauth_tokens()
    {
        $gateways = array('rede_credit', 'rede_debit', 'integration_rede_pix', 'rede_pix');
        $renewed_count = 0;
        
        foreach ($gateways as $gateway_id) {
            $credentials = self::get_gateway_credentials($gateway_id);
            
            if ($credentials === false) {
                continue;
            }
            
            $environment = $credentials['environment'];
            $token_data = self::generate_rede_oauth_token_for_gateway($gateway_id, null);
            
            if ($token_data === false) {
                continue;
            }
            
            self::cache_rede_oauth_token_for_gateway($gateway_id, $token_data, $environment);
            $renewed_count++;
        }
        
        return $renewed_count;
    }

    /**
     * Verifica e renova apenas tokens OAuth2 expirados com base em tempo limite
     * 
     * @param int $expiry_minutes Minutos após criação para considerar token expirado
     * @return int Número de tokens renovados
     */
    final public static function refresh_expired_rede_oauth_tokens($expiry_minutes = 15)
    {
        $gateways = array('rede_credit', 'rede_debit', 'integration_rede_pix', 'rede_pix');
        $renewed_count = 0;
        $expiry_seconds = $expiry_minutes * 60;
        
        foreach ($gateways as $gateway_id) {
            $credentials = self::get_gateway_credentials($gateway_id);
            
            if ($credentials === false) {
                continue;
            }
            
            $environment = $credentials['environment'];
            $token_option_name = 'lkn_rede_oauth_token_' . $gateway_id . '_' . $environment;
            $cached_data = get_option($token_option_name, false);
            
            $should_refresh = false;
            
            if ($cached_data === false || empty($cached_data)) {
                // Token não existe, precisa gerar
                $should_refresh = true;
            } else {
                // Decodifica token do cache
                $cached_token = json_decode(base64_decode($cached_data), true);
                
                if (!$cached_token) {
                    // Token corrompido, precisa renovar
                    $should_refresh = true;
                } else {
                    // Verifica se token expirou baseado no tempo
                    $token_created = isset($cached_token['generated_at']) ? $cached_token['generated_at'] : 0;
                    $time_elapsed = time() - $token_created;
                    
                    if ($time_elapsed >= $expiry_seconds) {
                        $should_refresh = true;
                    }
                }
            }
            
            if ($should_refresh) {
                $token_data = self::generate_rede_oauth_token_for_gateway($gateway_id, null);
                
                if ($token_data !== false) {
                    self::cache_rede_oauth_token_for_gateway($gateway_id, $token_data, $environment);
                    $renewed_count++;
                }
            }
        }
        
        return $renewed_count;
    }

    /**
     * Verifica se o token está válido (não expirou)
     */
    final public static function is_rede_oauth_token_valid($cached_token)
    {
        if (!$cached_token || !isset($cached_token['generated_at'])) {
            return false;
        }
        
        $current_time = time();
        $token_age_minutes = ($current_time - $cached_token['generated_at']) / 60;
        
        // Token é válido se tem menos de 20 minutos (margem de segurança)
        return $token_age_minutes < 20;
    }

    /**
     * Verifica se a licença PRO está ativa e válida
     * 
     * @return bool
     */
    final public static function isProLicenseValid(): bool
    {
        // Verifica se o plugin PRO está ativo
        if (!is_plugin_active('rede-for-woocommerce-pro/rede-for-woocommerce-pro.php')) {
            return false;
        }

        // Pega a licença do banco de dados
        $license = get_option('lknRedeForWoocommerceProLicense');
        
        if (empty($license)) {
            return false;
        }

        // Decodifica a licença base64
        $decoded_license = base64_decode($license);
        
        if ($decoded_license === false) {
            return false;
        }

        // Verifica se o status é 'active'
        return $decoded_license === 'active';
    }

    /**
     * Força valores padrão para campos PRO se a licença não for válida (com notificação)
     * 
     * @param string $gateway_id ID do gateway (rede_credit, rede_debit, etc.)
     */
    final public static function enforceProFieldDefaults($gateway_id): void
    {
        $option_key = "woocommerce_{$gateway_id}_settings";
        $settings = get_option($option_key, array());

        // Campos PRO que devem ser resetados para valores padrão
        $pro_fields_defaults = array(
            'interest_or_discount' => 'interest',
            'interest_show_percent' => 'yes',
            'installment_interest' => 'no',
            'installment_discount' => 'no',
            'min_interest' => '0',
            'convert_to_brl' => 'no',
            'auto_capture' => 'yes',
            '3ds_template_style' => 'basic',
            'payment_complete_status' => 'processing'
        );

        // Reset campos de parcelas específicas
        $max_installments = (int) ($settings['max_parcels_number'] ?? 12);
        for ($i = 1; $i <= $max_installments; $i++) {
            $pro_fields_defaults["{$i}x"] = '0';
            $pro_fields_defaults["{$i}x_discount"] = '0';
        }

        // Aplica os valores padrão para campos PRO
        foreach ($pro_fields_defaults as $field => $default_value) {
            if (isset($settings[$field])) {
                $settings[$field] = $default_value;
            }
        }

        // Atualiza as configurações no banco
        update_option($option_key, $settings);
    }

    /**
     * Força valores padrão para campos PRO se a licença não for válida (sem notificação para uso interno)
     * 
     * @param string $gateway_id ID do gateway (rede_credit, rede_debit, etc.)
     */
    final public static function resetProFieldsQuietly($gateway_id): void
    {
        $option_key = "woocommerce_{$gateway_id}_settings";
        $settings = get_option($option_key, array());

        // Campos PRO que devem ser resetados para valores padrão
        $pro_fields_defaults = array(
            'interest_or_discount' => 'interest',
            'interest_show_percent' => 'yes',
            'installment_interest' => 'no',
            'installment_discount' => 'no',
            'min_interest' => '0',
            'convert_to_brl' => 'no',
            'auto_capture' => 'yes',
            '3ds_template_style' => 'basic',
            'payment_complete_status' => 'processing'
        );

        // Reset campos de parcelas específicas
        $max_installments = (int) ($settings['max_parcels_number'] ?? 12);
        for ($i = 1; $i <= $max_installments; $i++) {
            $pro_fields_defaults["{$i}x"] = '0';
            $pro_fields_defaults["{$i}x_discount"] = '0';
        }

        // Aplica os valores padrão para campos PRO
        foreach ($pro_fields_defaults as $field => $default_value) {
            if (isset($settings[$field])) {
                $settings[$field] = $default_value;
            }
        }

        // Atualiza as configurações no banco
        update_option($option_key, $settings);
    }

    /**
     * Verifica e redefine configurações PRO apenas para o gateway de débito se a licença não for válida
     */
    final public static function checkAndResetProConfigurations(): void
    {
        // Só executa se a licença PRO não for válida
        if (self::isProLicenseValid()) {
            return;
        }

        // Aplica reset apenas ao gateway de débito
        $gateway_id = 'rede_debit';
        $settings = get_option("woocommerce_{$gateway_id}_settings", array());
        
        // Verifica se o gateway está habilitado antes de resetar
        if (isset($settings['enabled']) && $settings['enabled'] === 'yes') {
            self::resetProFieldsQuietly($gateway_id);
        }
    }

    /**
     * Create a standardized custom error response object
     *
     * @param int $httpStatus HTTP status code (e.g., 400, 401)
     * @param string $returnCode Cielo/Braspag error code (e.g., '126', 'BP172')
     * @param string $returnMessage Error message
     * @param string $paymentId Payment ID (optional, defaults to empty)
     * @param string $proofOfSale Proof of sale (optional, defaults to empty)
     * @param string $tid Transaction ID (optional, defaults to empty)
     * @return object Standardized error response object
     */
    public static function createCustomErrorResponse($httpStatus, $returnCode, $returnMessage, $paymentId = '', $proofOfSale = '', $tid = '')
    {
        return (object) [
            'return_http' => $httpStatus,
            'return_code' => $returnCode,
            'return_message' => $returnMessage,
            'payment_id' => $paymentId,
            'tid' => $tid
        ];
    }

    /**
     * Encode data using TOON format
     *
     * @param array $data
     * @return string|false
     */
    public static function encodeToonData($data)
    {
        try {
            return Toon::encode($data);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Decode TOON data
     *
     * @param string $toonString
     * @return array|false
     */
    public static function decodeToonData($toonString)
    {
        try {
            return Toon::decode($toonString);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Mask credentials dynamically based on string length.
     *
     * @param string $credential
     * @return string
     */
    public static function maskCredential($credential)
    {
        if (empty($credential)) {
            return 'N/A';
        }
        
        $length = strlen($credential);
        
        // Para strings muito pequenas, mascarar tudo
        if ($length <= 6) {
            return str_repeat('*', $length);
        }
        
        // Para strings de 7-8 caracteres, usar 3+asteriscos+3
        if ($length <= 8) {
            $showChars = 3;
        } 
        // Para strings de 9-12 caracteres, usar 4+asteriscos+4
        elseif ($length <= 12) {
            $showChars = 4;
        }
        // Para strings maiores que 12, usar mais caracteres visíveis
        else {
            $showChars = min(6, floor($length / 3)); // Máximo 6 caracteres de cada lado
        }
        
        $start = substr($credential, 0, $showChars);
        $end = substr($credential, -$showChars);
        $middleLength = $length - (2 * $showChars);
        $middle = str_repeat('*', $middleLength);
        
        return $start . $middle . $end;
    }

    /**
     * Get HTTP status description.
     *
     * @param int $httpStatus
     * @return string
     */
    public static function getHttpStatusDescription($httpStatus)
    {
        $httpStatusDescriptions = array(
            200 => 'Sucesso',
            201 => 'Criado com sucesso',
            400 => 'Requisição inválida',
            401 => 'Não autorizado',
            403 => 'Proibido',
            404 => 'Não encontrado',
            405 => 'Método não permitido',
            422 => 'Entidade não processável',
            429 => 'Muitas requisições',
            500 => 'Erro interno do servidor',
            502 => 'Gateway inválido',
            503 => 'Serviço indisponível',
            504 => 'Timeout do gateway'
        );

        return isset($httpStatusDescriptions[$httpStatus]) ? $httpStatusDescriptions[$httpStatus] : 'N/A';
    }

    /**
     * Salva metadados da transação para o gateway Rede.
     *
     * @param WC_Order $order
     * @param array|object $responseDecoded
     * @param string $cardNumber
     * @param string $cardExpShort
     * @param string $cardHolder
     * @param int $installments
     * @param float $amount
     * @param string $currency
     * @param string $brand
     * @param string $pv
     * @param string $token
     * @param string $merchantOrderId
     * @param int $order_id
     * @param bool $capture
     * @param string $gatewayType
     * @param string $cvvField
     * @param object|null $gatewayInstance
     * @param string $tid
     * @param string $nsu
     * @param string $authorizationCode
     * @param string $returnCode
     * @param string $returnMessage
     */
    public static function saveTransactionMetadata(
        $order,
        $responseDecoded,
        $paymentNumber, // Renomeado de cardNumber para paymentNumber
        $cardExpShort,
        $cardHolder,
        $installments,
        $amount,
        $currency,
        $brand,
        $pv,
        $token,
        $merchantOrderId,
        $order_id,
        $capture,
        $gatewayType = 'Credit',
        $cvvField = 'N/A',
        $gatewayInstance = null,
        $tid = '',
        $nsu = '',
        $authorizationCode = '',
        $returnCode = '',
        $returnMessage = ''
    ) {
        // Calcular valor das parcelas
        $installmentAmount = $installments > 1 ? ($amount / $installments) : $amount;
        $installmentAmount = round($installmentAmount, wc_get_price_decimals());

        // Calcular juros/desconto baseado nas parcelas
        $interestDiscountAmount = 0;
        $totalWithFees = $amount;
        $originalAmount = $order->get_subtotal() + $order->get_shipping_total();
        $difference = $totalWithFees - $originalAmount;
        if ($difference != 0) {
            $interestDiscountAmount = round(abs($difference), wc_get_price_decimals());
        }

        // Data da requisição
        $requestDateTime = current_time('Y-m-d H:i:s');

        // Formatar dados baseado no tipo de gateway
        // Se for Pix, usar a função de mascaramento do merchant key/id
        if (stripos($gatewayType, 'pix') !== false) {
            $gatewayMasked = !empty($paymentNumber) && strlen($paymentNumber) >= 8 ?
                substr($paymentNumber, 0, 4) . '********' . substr($paymentNumber, -4) : 'N/A';
        } else {
            $gatewayMasked = !empty($paymentNumber) && strlen($paymentNumber) >= 8 ?
                substr($paymentNumber, 0, 4) . ' **** **** ' . substr($paymentNumber, -4) : 'N/A';
        }

        // Status HTTP da requisição
        $httpStatus = 'N/A';
        if (is_array($responseDecoded)) {
            $httpStatus = isset($responseDecoded['return_http']) ? $responseDecoded['return_http'] : 'N/A';
        } elseif (is_object($responseDecoded)) {
            $httpStatus = isset($responseDecoded->return_http) ? $responseDecoded->return_http : 'N/A';
        }
        
        $httpStatusDescription = self::getHttpStatusDescription($httpStatus);
        $httpStatusFormatted = $httpStatus && $httpStatus !== 'N/A' ? $httpStatus . ' - ' . $httpStatusDescription : 'N/A';

        $translatedReturnMessage = LknIntegrationRedeForWoocommerceAbecsCodes::translate($returnCode, $returnMessage);
        $returnCodeRaw = !empty($returnCode) ? (string) $returnCode : '';
        $returnCodeFormatted = '' !== $returnCodeRaw ? $returnCodeRaw . ' - ' . $translatedReturnMessage : 'N/A';

        // Environment baseado no gateway
        $environment = 'Sandbox';
        if ($gatewayInstance) {
            if (is_array($gatewayInstance)) {
                // É um array de configurações
                $env = $gatewayInstance['environment'] ?? 'test';
                $environment = ($env === 'production') ? 'Produção' : 'Sandbox';
            } else {
                // É uma instância do gateway
                $environment = ($gatewayInstance->get_option('environment', 'test') === 'production') ? 'Produção' : 'Sandbox';
            }
        }

        // Validar e mascarar credentials dinamicamente
        $pvMasked = self::maskCredential($pv);
        $tokenMasked = self::maskCredential($token);

        // Validar data de expiração
        $cardExpiryFormatted = !empty($cardExpShort) ? $cardExpShort : 'N/A';

        // Validar CVV baseado no tipo de pagamento
        $cvvSent = 'N/A';
        if (in_array($gatewayType, ['Credit', 'Debit'])) {
            $cvvSent = !empty($cvvField) && $cvvField !== '***' ? 'Sim' : 'Não';
        }

        // Validar Capture baseado no tipo de pagamento
        $captureFormatted = 'N/A';
        if (in_array($gatewayType, ['Credit', 'Debit']) && $capture !== null && $capture !== 'N/A') {
            $captureFormatted = $capture ? 'Auto' : 'Manual';
        }

        // Verificar se é pagamento recorrente
        $isRecurrent = 'Não';
        if ($gatewayType === 'Credit' && class_exists('WC_Subscriptions_Order') && function_exists('WC_Subscriptions_Order::order_contains_subscription')) {
            if (WC_Subscriptions_Order::order_contains_subscription($order_id)) {
                $isRecurrent = 'Sim';
            }
        }

        // Validar Recorrente baseado no tipo de pagamento
        $recurrentFormatted = 'N/A';
        if ($gatewayType === 'Credit') {
            $recurrentFormatted = $isRecurrent;
        }

        $threeDSFormatted = 'N/A';
        if (is_array($responseDecoded) && isset($responseDecoded['3ds_auth'])) {
            $threeDSFormatted = $responseDecoded['3ds_auth'] === 'success' ? 'Sucesso' : 'Falhou';
        } elseif (is_object($responseDecoded) && isset($responseDecoded->{'3ds_auth'})) {
            $threeDSFormatted = $responseDecoded->{'3ds_auth'} === 'success' ? 'Sucesso' : 'Falhou';
        }

        // Formatar valor das parcelas - apenas valor numérico
        $installmentFormatted = 'N/A';
        if ($installments > 0 && $installmentAmount > 0) {
            $installmentFormatted = round((float) $installmentAmount, wc_get_price_decimals());
        }

        // Determinar tipo de gateway/cartão para exibição
        $displayType = 'N/A';
        if ($gatewayType === 'Pix') {
            $displayType = 'PIX';
        } elseif ($gatewayType === 'Debit') {
            $displayType = 'Débito';
        } elseif ($gatewayType === 'Credit') {
            $displayType = 'Crédito';
        }

        // Criar estrutura centralizada com metadados da transação para Rede
        $transactionMetadata = [
            'gateway' => [
                'masked' => $gatewayMasked,
                'type' => $displayType,
                'brand' => !empty($brand) ? ucfirst($brand) : 'N/A',
                'expiry' => $cardExpiryFormatted,
                'holder_name' => !empty($cardHolder) ? $cardHolder : 'N/A',
            ],
            'transaction' => [
                'cvv_sent' => $cvvSent,
                'installments' => $installments > 0 ? $installments : 'N/A',
                'installment_amount' => $installmentFormatted,
                'capture' => $captureFormatted,
                'tid' => !empty($tid) ? $tid : 'N/A',
                'nsu' => !empty($nsu) ? $nsu : 'N/A',
                'authorization_code' => !empty($authorizationCode) ? $authorizationCode : 'N/A',
                'recurrent' => $recurrentFormatted,
                '3ds_auth' => $threeDSFormatted,
            ],
            'amounts' => [
                'total' => round((float) $amount, wc_get_price_decimals()),
                'subtotal' => round((float) $order->get_subtotal(), wc_get_price_decimals()),
                'shipping' => round((float) $order->get_shipping_total(), wc_get_price_decimals()),
                'interest_discount' => round((float) $interestDiscountAmount, wc_get_price_decimals()),
                'currency' => $currency
            ],
            'system' => [
                'request_datetime' => $requestDateTime,
                'environment' => $environment,
                'gateway' => $order->get_payment_method(),
                'order_id' => $order_id,
                'reference' => !empty($merchantOrderId) ? $merchantOrderId : 'N/A',
                'version_free' => defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION') ? INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION : 'N/A',
                'version_pro' => defined('REDE_FOR_WOOCOMMERCE_PRO_VERSION') ? REDE_FOR_WOOCOMMERCE_PRO_VERSION : 'N/A'
            ],
            'credentials' => [
                'pv_masked' => $pvMasked,
                'token_masked' => $tokenMasked
            ],
            'response' => [
                'http_status' => $httpStatusFormatted,
                'return_code' => $returnCodeFormatted,
                'return_message' => $translatedReturnMessage,
            ]
        ];

        // Tentar codificar com TOON
        $toonEncoded = self::encodeToonData($transactionMetadata);

        if ($toonEncoded !== false) {
            // Salvar dados como TOON
            $order->add_meta_data('lkn_rede_transaction_data', $toonEncoded, true);
            $order->add_meta_data('lkn_rede_data_format', 'toon', true);
        } else {
            // Fallback para JSON se TOON falhar
            $jsonEncoded = wp_json_encode($transactionMetadata);
            $order->add_meta_data('lkn_rede_transaction_data', $jsonEncoded, true);
            $order->add_meta_data('lkn_rede_data_format', 'json', true);
        }
    }

    public static function lknIntegrationRedeGetOrderStatus()
    {
        $order_statuses = (wc_get_order_statuses());
        unset($order_statuses["wc-failed"]);
        unset($order_statuses["wc-checkout-draft"]);
        unset($order_statuses["wc-refunded"]);
        unset($order_statuses["wc-cancelled"]);

        return $order_statuses;
    }
}
?>