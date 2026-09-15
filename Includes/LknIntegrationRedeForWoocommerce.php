<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

if ( ! defined( 'ABSPATH' ) ) exit;

use Lknwoo\IntegrationRedeForWoocommerce\Admin\LknIntegrationRedeForWoocommerceAdmin;
use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceLoader;
use Lknwoo\IntegrationRedeForWoocommerce\PublicView\LknIntegrationRedeForWoocommercePublic;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://linknacional.com.br
 * @since      1.0.0
 *
 * @package    LknIntegrationRedeForWoocommerce
 * @subpackage LknIntegrationRedeForWoocommerce/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    LknIntegrationRedeForWoocommerce
 * @subpackage LknIntegrationRedeForWoocommerce/includes
 * @author     Link Nacional <contato@linknacional.com>
 */
final class LknIntegrationRedeForWoocommerce
{
    // Gateway constants for consistent reference
    const GATEWAY_DEBIT = 'rede_debit';
    const GATEWAY_CREDIT = 'rede_credit';
    const GATEWAY_PIX_FREE = 'integration_rede_pix';
    const GATEWAY_PIX_PRO = 'rede_pix';
    const GATEWAY_GOOGLE_PAY = 'rede_google_pay';

    /**
     * Get all available gateway IDs as an array
     * @return array Array of gateway IDs
     */
    public static function get_available_gateway_ids()
    {
        return [
            self::GATEWAY_DEBIT,
            self::GATEWAY_CREDIT,
            self::GATEWAY_PIX_FREE,
            self::GATEWAY_PIX_PRO,
            self::GATEWAY_GOOGLE_PAY
        ];
    }

    /**
     * Check if a gateway ID is a valid Rede gateway
     * @param string $gateway_id Gateway ID to check
     * @return bool True if valid Rede gateway
     */
    public static function is_valid_rede_gateway($gateway_id)
    {
        return in_array($gateway_id, self::get_available_gateway_ids(), true);
    }

    /**
     * Check if a gateway ID is a PIX gateway
     * @param string $gateway_id Gateway ID to check
     * @return bool True if PIX gateway
     */
    public static function is_pix_gateway($gateway_id)
    {
        return in_array($gateway_id, [self::GATEWAY_PIX_FREE, self::GATEWAY_PIX_PRO], true);
    }

    /**
     * Check if a gateway ID is a card gateway (debit/credit)
     * @param string $gateway_id Gateway ID to check
     * @return bool True if card gateway
     */
    public static function is_card_gateway($gateway_id)
    {
        return in_array($gateway_id, [self::GATEWAY_DEBIT, self::GATEWAY_CREDIT], true);
    }

    /**
     * The loader that's responsible for maintaining and registering all hooks that power the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      LknIntegrationRedeForWoocommerceLoader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        if (defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION')) {
            $this->version = INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'lkn-integration-rede-for-woocommerce';

        $this->load_dependencies();
        $this->loader->add_action('woocommerce_init', $this, 'define_hooks');
    }
    public $wc_rede_class;
    public $wc_rede_credit_class;
    public $wc_rede_debit_class;
    public $wc_maxipago_credit_class;
    public $wc_maxipago_debit_class;
    public $LknIntegrationRedeForWoocommercePixRedeClass;
    public $LknIntegrationRedeForWoocommerceEndpointClass;
    public $LknIntegrationRedeForWoocommercePixHelperClass;
    public $LknIntegrationRedeForWoocommerceHelperClass;
    public $LknIntegrationRedeForWoocommerceGooglePayClass;

    //Define os hooks somente quando woocommerce está ativo
    public function define_hooks(): void
    {
        $this->wc_rede_class = new LknIntegrationRedeForWoocommerceWcRede();
        if (class_exists('WC_Payment_Gateway')) {
            $this->LknIntegrationRedeForWoocommerceGooglePayClass = new LknIntegrationRedeForWoocommerceGooglePay();
            $this->wc_rede_credit_class = new LknIntegrationRedeForWoocommerceWcRedeCredit();
            $this->wc_rede_debit_class = new LknIntegrationRedeForWoocommerceWcRedeDebit();
            $this->wc_maxipago_credit_class = new LknIntegrationRedeForWoocommerceWcMaxipagoCredit();
            $this->wc_maxipago_debit_class = new LknIntegrationRedeForWoocommerceWcMaxipagoDebit();
            $this->LknIntegrationRedeForWoocommercePixRedeClass = new LknIntegrationRedeForWoocommerceWcPixRede();

            $this->define_admin_hooks();
            $this->define_public_hooks();
        } else {
            $this->loader->add_action('admin_notices', $this->wc_rede_class, 'woocommerceMissingNotice');
        }
        $this->run();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - LknIntegrationRedeForWoocommerceLoader. Orchestrates the hooks of the plugin.
     * - LknIntegrationRedeForWoocommerce_Admin. Defines all hooks for the admin area.
     * - LknIntegrationRedeForWoocommercePublic. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies(): void
    {
        $this->loader = new LknIntegrationRedeForWoocommerceLoader();
        $this->LknIntegrationRedeForWoocommerceEndpointClass = new LknIntegrationRedeForWoocommerceWcEndpoint();
        $this->LknIntegrationRedeForWoocommercePixHelperClass = new LknIntegrationRedeForWoocommerceWcPixHelper();
        $this->LknIntegrationRedeForWoocommerceHelperClass = new LknIntegrationRedeForWoocommerceHelper();
        $this->loader->add_filter('integration_rede_for_woocommerce_get_card_token', $this->LknIntegrationRedeForWoocommercePixHelperClass, 'getCardToken', 10, 3);
        $this->loader->add_filter('integration_rede_for_woocommerce_set_supports', $this->LknIntegrationRedeForWoocommercePixHelperClass, 'setSupports', 10, 1);
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks(): void
    {
        $plugin_admin = new LknIntegrationRedeForWoocommerceAdmin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        $this->loader->add_action('woocommerce_update_options_payment_gateways_' . $this->LknIntegrationRedeForWoocommercePixRedeClass->id, $this->LknIntegrationRedeForWoocommercePixRedeClass, "process_admin_options");
        $this->loader->add_action('woocommerce_admin_order_data_after_billing_address', $this->LknIntegrationRedeForWoocommercePixRedeClass, 'displayMeta');
        $this->loader->add_action('woocommerce_admin_order_data_after_billing_address', $this->LknIntegrationRedeForWoocommerceGooglePayClass, 'displayMeta');
        $this->loader->add_action('woocommerce_order_details_after_order_table', $this->LknIntegrationRedeForWoocommercePixRedeClass, "showPix");

        $this->loader->add_filter('plugin_action_links_' . INTEGRATION_REDE_FOR_WOOCOMMERCE_BASENAME, $this, 'addSettings');

        $this->loader->add_action('woocommerce_update_options_payment_gateways_' . $this->wc_rede_credit_class->id, $this->wc_rede_credit_class, 'process_admin_options');
        $this->loader->add_action('woocommerce_api_wc_rede_credit', $this->wc_rede_credit_class, 'check_return');
        $this->loader->add_filter('woocommerce_get_order_item_totals', $this->wc_rede_credit_class, 'order_items_payment_details', 10, 2);
        $this->loader->add_action('woocommerce_admin_order_data_after_billing_address', $this->wc_rede_credit_class, 'displayMeta', 10, 1);

        $this->loader->add_action('woocommerce_update_options_payment_gateways_' . $this->wc_rede_debit_class->id, $this->wc_rede_debit_class, 'process_admin_options');
        // Adiciona o hook para salvar as opções do gateway Google Pay
        $this->loader->add_action('woocommerce_update_options_payment_gateways_' . $this->LknIntegrationRedeForWoocommerceGooglePayClass->id, $this->LknIntegrationRedeForWoocommerceGooglePayClass, 'process_admin_options');
        $this->loader->add_action('woocommerce_api_wc_rede_credit', $this->wc_rede_debit_class, 'check_return');
        $this->loader->add_filter('woocommerce_get_order_item_totals', $this->wc_rede_debit_class, 'order_items_payment_details', 10, 2);
        $this->loader->add_action('woocommerce_admin_order_data_after_billing_address', $this->wc_rede_debit_class, 'displayMeta', 10, 1);

        $this->loader->add_action('woocommerce_update_options_payment_gateways_' . $this->wc_maxipago_credit_class->id, $this->wc_maxipago_credit_class, 'process_admin_options');
        $this->loader->add_action('woocommerce_admin_order_data_after_billing_address', $this->wc_maxipago_credit_class, 'displayMeta', 10, 1);

        $this->loader->add_action('admin_notices', $this->wc_rede_class, 'softDescriptorNotice');

        $this->loader->add_action('woocommerce_update_options_payment_gateways_' . $this->wc_maxipago_debit_class->id, $this->wc_maxipago_debit_class, 'process_admin_options');
        $this->loader->add_action('woocommerce_admin_order_data_after_billing_address', $this->wc_maxipago_debit_class, 'displayMeta', 10, 1);
        $this->loader->add_filter('lknRedeGetMerchantAuth', $this->wc_maxipago_credit_class, 'getMerchantAuth');

        $this->loader->add_filter('plugin_action_links_' . INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE_BASENAME, $this, 'lknIntegrationRedeForWoocommercePluginRowMeta', 10, 2);
        $this->loader->add_filter('plugin_action_links_' . INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE_BASENAME, $this, 'lknIntegrationRedeForWoocommercePluginRowMetaPro', 10, 2);

        // Adicionar link do Changelog
        $this->loader->add_filter('plugin_row_meta', $this, 'add_changelog_link', 10, 2);

        $this->loader->add_action('rest_api_init', $this->LknIntegrationRedeForWoocommerceEndpointClass, 'registerorderRedeCaptureEndPoint');
        $this->loader->add_filter('woocommerce_gateway_title', $this, 'customize_wc_payment_gateway_pix_name', 10, 2);

        $this->loader->add_action('add_meta_boxes', $this->LknIntegrationRedeForWoocommerceHelperClass, 'showOrderLogs');
        
        // Hook para verificar e corrigir ambiente dos pedidos Rede
        $this->loader->add_action('admin_init', $this, 'check_and_fix_rede_orders_environment');

        $this->loader->add_action('admin_notices', $this, 'lkn_admin_notice');

        // Aviso crítico para atualização do plugin PRO
        $this->loader->add_action('admin_notices', $this, 'lkn_pro_update_critical_notice');

        // Hook para ações personalizadas da ordem PIX
        $this->loader->add_filter('woocommerce_order_actions', $this, 'add_pix_verification_action');
        $this->loader->add_action('woocommerce_order_action_verify_pix_status', $this, 'process_pix_verification_action');

        // Limpa o cron 'update_rede_orders' ao atualizar o plugin via admin
        $this->loader->add_action('upgrader_process_complete', $this, 'clear_update_rede_orders_on_plugin_update', 10, 2);

        // Adiciona endpoint AJAX para parcelas Rede Credit
        $this->loader->add_action('wp_ajax_lkn_get_rede_credit_data', $this, 'ajax_get_rede_credit_data');
        $this->loader->add_action('wp_ajax_nopriv_lkn_get_rede_credit_data', $this, 'ajax_get_rede_credit_data');

        // Adiciona endpoint AJAX para parcelas Rede Debit
        $this->loader->add_action('wp_ajax_lkn_get_rede_debit_data', $this, 'ajax_get_rede_debit_data');
        $this->loader->add_action('wp_ajax_nopriv_lkn_get_rede_debit_data', $this, 'ajax_get_rede_debit_data');

        // Adiciona endpoint AJAX para parcelas Maxipago
        $this->loader->add_action('wp_ajax_lkn_get_maxipago_credit_data', $this, 'ajax_get_maxipago_credit_data');
        $this->loader->add_action('wp_ajax_nopriv_lkn_get_maxipago_credit_data', $this, 'ajax_get_maxipago_credit_data');

        // Hooks para atualizar tokens OAuth2 no checkout (blocks e shortcode)
        $this->loader->add_action('wp_enqueue_scripts', $this, 'lkn_rede_refresh_oauth_token_on_checkout');
        // Adiciona endpoint AJAX para atualização da sessão de parcelas
        $this->loader->add_action('wp_ajax_lkn_update_installment_session', $this, 'ajax_update_installment_session');
        $this->loader->add_action('wp_ajax_nopriv_lkn_update_installment_session', $this, 'ajax_update_installment_session');

        // Analytics - registra script e CSS do WooCommerce Admin
        $this->loader->add_action('admin_enqueue_scripts', $this, 'register_rede_analytics_script');
        $this->loader->add_filter('woocommerce_analytics_report_menu_items', $this, 'add_rede_analytics_menu_item');

        // Hook AJAX para gerar novas chaves Google Pay
        $this->loader->add_action('wp_ajax_lkn_generate_new_google_pay_keys', $this, 'lkn_generate_new_google_pay_keys');
        
        // Hook AJAX para dispensar notificação de fraude
        $this->loader->add_action('wp_ajax_lkn_dismiss_fraud_notice', $this, 'lkn_dismiss_fraud_notice');

        // Hooks para exportação XLS em ações em massa (HPOS e Legacy)
        $this->loader->add_filter('bulk_actions-edit-shop_order', $plugin_admin, 'add_bulk_order_actions');
        $this->loader->add_filter('handle_bulk_actions-edit-shop_order', $plugin_admin, 'handle_bulk_export_rede_xls', 10, 3);
        $this->loader->add_filter('bulk_actions-woocommerce_page_wc-orders', $plugin_admin, 'add_bulk_order_actions');
        $this->loader->add_filter('handle_bulk_actions-woocommerce_page_wc-orders', $plugin_admin, 'handle_bulk_export_rede_xls', 10, 3);
    }

    /**
     * Handler AJAX para gerar novas chaves Google Pay
     */
    public function lkn_generate_new_google_pay_keys(): void {
        // Verificar nonce
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'lkn_keys_ajax_nonce' ) ) {
            wp_send_json_error( __( 'Invalid nonce.', 'woo-rede' ) );
        }

        // Check user capability
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'woo-rede' ) );
        }
        
        try {
            // Chamar a função de geração de novas chaves
            $result = $this->LknIntegrationRedeForWoocommerceGooglePayClass->autoGenerateNewKeys();
            
            if ($result) {
                wp_send_json_success(array(
                    'public_key'  => $result['public_key'],
                    'private_key' => $result['private_key'],
                    'message'     => __('New keys generated successfully!', 'woo-rede')
                ));
            } else {
                wp_send_json_error(__('Error generating new keys.', 'woo-rede'));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Internal error: ', 'woo-rede') . $e->getMessage());
        }
    }
    
    /**
     * Handler AJAX para dispensar notificação de fraude
     */
    public function lkn_dismiss_fraud_notice(): void {
        // Verificar nonce
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'lkn_dismiss_fraud_notice_nonce' ) ) {
            wp_send_json_error( __( 'Invalid nonce.', 'woo-rede' ) );
        }

        // Check user capability
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'woo-rede' ) );
        }
        
        // Atualizar option para marcar como dispensada
        update_option('lkn_fraud_notice_dismissed', 'yes');
        $updated = get_option('lkn_fraud_notice_dismissed') === 'yes';
        
        if ($updated) {
            wp_send_json_success(array(
                'message' => __('Notice dismissed successfully.', 'woo-rede')
            ));
        } else {
            wp_send_json_error(__('Error updating option.', 'woo-rede'));
        }
    }

    /**
     * Endpoint AJAX para retornar dados de parcelas do Maxipago
     */
    public function ajax_get_maxipago_credit_data()
    {
        $cart_total = 0;
        if (function_exists('WC') && WC()->cart) {
            // Soma dos produtos + taxa de entrega + impostos
            $cart_total = floatval(WC()->cart->get_cart_contents_total());
            $cart_total += floatval(WC()->cart->get_shipping_total());
            $cart_total += floatval(WC()->cart->get_taxes_total());

            WC()->cart->calculate_totals();

            $fees_objects = WC()->cart->get_fees();
            $extra_fees = 0;
            foreach ($fees_objects as $fee) {
                if (!LknIntegrationRedeForWoocommerceHelper::isOwnInterestDiscountFee($fee->name)) {
                    $extra_fees += floatval($fee->amount);
                }
            }

            $cart_total += $extra_fees;
        }
        $max_installments = 12;
        $min_parcels_value = 5;
        if (isset($this->wc_maxipago_credit_class) && method_exists($this->wc_maxipago_credit_class, 'get_option')) {
            $max_installments = intval($this->wc_maxipago_credit_class->get_option('max_parcels_number'));
            $min_parcels_value = $this->wc_maxipago_credit_class->get_option('min_parcels_value');
            if (!is_numeric($min_parcels_value) || $min_parcels_value < 1) {
                $min_parcels_value = 5;
            } else {
                $min_parcels_value = floatval($min_parcels_value);
            }
            if ($max_installments < 1) {
                $max_installments = 12;
            }
        }

        if (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                $product_limit = get_post_meta($product_id, 'lknMaxipagoProdutctInterest', true);
                if ($product_limit !== 'default' && is_numeric($product_limit)) {
                    $product_limit = (int) $product_limit;
                    if ($product_limit > 0 && $product_limit < $max_installments) {
                        $max_installments = $product_limit;
                    }
                }
            }
        }

        // Verificar se a licença PRO está ativa
        $is_pro_active = false;
        if (is_plugin_active('rede-for-woocommerce-pro/rede-for-woocommerce-pro.php')) {
            $pro_license = get_option('lknRedeForWoocommerceProLicense');
            if ($pro_license) {
                $license_data = base64_decode($pro_license);
                $is_pro_active = (strpos($license_data, 'active') !== false);
            }
        }

        // Obter valor mínimo da parcela das configurações
        $min_installment_value = 5; // Valor padrão
        if (isset($this->wc_maxipago_credit_class) && method_exists($this->wc_maxipago_credit_class, 'get_option')) {
            $configured_min_value = floatval($this->wc_maxipago_credit_class->get_option('min_parcels_value'));
            if ($configured_min_value > 0) {
                $min_installment_value = $configured_min_value;
            }
        }

        $installments = [];
        for ($i = 1; $i <= $max_installments; $i++) {
            $installment_value = $cart_total / $i;
            if ($installment_value < $min_parcels_value) {
                // Se nem mesmo 1x atende o valor mínimo, força 1x à vista
                if ($i === 1) {
                    /* translators: %1$d: number of installments, %2$s: installment price */
                    $base_label = sprintf("%dx de %s", 1, wc_price($cart_total));
                    $label = $is_pro_active ? $this->get_installment_label_with_interest(1, $base_label, 'maxipago_credit') : $base_label;
                    $installments[] = [
                        'key' => 1,
                        'label' => $label
                    ];
                }
                break;
            }
            $base_label = sprintf(
                /* translators: %1$d is the number of installments, %2$s is the formatted price per installment */
                "%dx de %s", 
                $i, 
                wc_price($installment_value)
            );

            // Se a licença PRO estiver ativa, aplicar lógica de juros/desconto
            if ($is_pro_active) {
                $label = $this->get_installment_label_with_interest($i, $base_label, 'maxipago_credit');
            } else {
                $label = $base_label;
            }

            $installments[] = [
                'key' => $i,
                'label' => $label
            ];
        }
        wp_send_json([
            'cartTotal' => $cart_total,
            'installments' => $installments
        ]);
    }

    /**
     * Endpoint AJAX para retornar dados de parcelas do Rede Credit
     */
    public function ajax_get_rede_credit_data()
    {
        $cart_total = 0;
        if (function_exists('WC') && WC()->cart) {
            // Soma dos produtos + taxa de entrega + impostos
            $cart_total = floatval(WC()->cart->get_cart_contents_total());
            $cart_total += floatval(WC()->cart->get_shipping_total());
            $cart_total += floatval(WC()->cart->get_taxes_total());

            WC()->cart->calculate_totals();

            $fees_objects = WC()->cart->get_fees();
            $extra_fees = 0;
            foreach ($fees_objects as $fee) {
                if (!LknIntegrationRedeForWoocommerceHelper::isOwnInterestDiscountFee($fee->name)) {
                    $extra_fees += floatval($fee->amount);
                }
            }

            $cart_total += $extra_fees;
        }
        $max_installments = 12;
        $min_parcels_value = 5;
        if (isset($this->wc_rede_credit_class) && method_exists($this->wc_rede_credit_class, 'get_option')) {
            $max_installments = intval($this->wc_rede_credit_class->get_option('max_parcels_number'));
            $min_parcels_value = $this->wc_rede_credit_class->get_option('min_parcels_value');
            if (!is_numeric($min_parcels_value) || $min_parcels_value < 1) {
                $min_parcels_value = 5;
            } else {
                $min_parcels_value = floatval($min_parcels_value);
            }
            if ($max_installments < 1) {
                $max_installments = 12;
            }
        }

        if (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                $product_limit = get_post_meta($product_id, 'lknRedeProdutctInterest', true);
                if ($product_limit !== 'default' && is_numeric($product_limit)) {
                    $product_limit = (int) $product_limit;
                    if ($product_limit > 0 && $product_limit < $max_installments) {
                        $max_installments = $product_limit;
                    }
                }
            }
        }

        // Verificar se a licença PRO está ativa
        $is_pro_active = false;
        if (is_plugin_active('rede-for-woocommerce-pro/rede-for-woocommerce-pro.php')) {
            $pro_license = get_option('lknRedeForWoocommerceProLicense');
            if ($pro_license) {
                $license_data = base64_decode($pro_license);
                $is_pro_active = (strpos($license_data, 'active') !== false);
            }
        }

        // Para rede_credit, verificar se as configurações de juros/desconto estão ativadas
        $apply_pro_features = false;
        if ($is_pro_active && isset($this->wc_rede_credit_class) && method_exists($this->wc_rede_credit_class, 'get_option')) {
            $installment_interest = $this->wc_rede_credit_class->get_option('installment_interest');
            $installment_discount = $this->wc_rede_credit_class->get_option('installment_discount');
            
            if ($installment_interest === 'yes' || $installment_discount === 'yes') {
                $apply_pro_features = true;
            }
        }

        // Obter valor mínimo da parcela das configurações
        $min_installment_value = 5; // Valor padrão
        if (isset($this->wc_rede_credit_class) && method_exists($this->wc_rede_credit_class, 'get_option')) {
            $configured_min_value = floatval($this->wc_rede_credit_class->get_option('min_parcels_value'));
            if ($configured_min_value > 0) {
                $min_installment_value = $configured_min_value;
            }
        }

        $installments = [];
        for ($i = 1; $i <= $max_installments; $i++) {
            $installment_value = $cart_total / $i;
            if ($installment_value < $min_parcels_value) {
                // Se nem mesmo 1x atende o valor mínimo, força 1x à vista
                if ($i === 1) {
                    $base_label = sprintf(
                        /* translators: %1$d is the number of installments, %2$s is the formatted price per installment */
                        "%dx de %s", 
                        1, 
                        wc_price($cart_total)
                    );
                    $label = $apply_pro_features ? $this->get_installment_label_with_interest(1, $base_label, self::GATEWAY_CREDIT) : $base_label;
                    $installments[] = [
                        'key' => 1,
                        'label' => $label
                    ];
                }
                break;
            }
            $base_label = sprintf(
                /* translators: %1$d is the number of installments, %2$s is the formatted price per installment */
                "%dx de %s", 
                $i, 
                wc_price($installment_value)
            );

            // Se as funcionalidades PRO estiverem ativadas e configuradas, aplicar lógica de juros/desconto
            if ($apply_pro_features) {
                $label = $this->get_installment_label_with_interest($i, $base_label, self::GATEWAY_CREDIT);
            } else {
                $label = $base_label;
            }

            $installments[] = [
                'key' => $i,
                'label' => $label
            ];
        }
        
        // Definir installment de crédito como 1 na sessão
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('lkn_installments_number_rede_credit', 1);
        }
        
        wp_send_json([
            'cartTotal' => $cart_total,
            'installments' => $installments
        ]);
    }

    /**
     * Endpoint AJAX para retornar dados de parcelas do Rede Debit
     * Reutiliza a lógica do Rede Credit, mas considera as configurações de débito
     */
    public function ajax_get_rede_debit_data()
    {
        $cart_total = 0;
        if (function_exists('WC') && WC()->cart) {
            // Soma dos produtos + taxa de entrega + impostos
            $cart_total = floatval(WC()->cart->get_cart_contents_total());
            $cart_total += floatval(WC()->cart->get_shipping_total());
            $cart_total += floatval(WC()->cart->get_taxes_total());

            WC()->cart->calculate_totals();

            $fees_objects = WC()->cart->get_fees();
            $extra_fees = 0;
            foreach ($fees_objects as $fee) {
                if (!LknIntegrationRedeForWoocommerceHelper::isOwnInterestDiscountFee($fee->name)) {
                    $extra_fees += floatval($fee->amount);
                }
            }

            $cart_total += $extra_fees;
        }
        
        $max_installments = 12;
        $min_parcels_value = 5;
        if (isset($this->wc_rede_debit_class) && method_exists($this->wc_rede_debit_class, 'get_option')) {
            $max_installments = intval($this->wc_rede_debit_class->get_option('max_parcels_number'));
            $min_parcels_value = $this->wc_rede_debit_class->get_option('min_parcels_value');
            if (!is_numeric($min_parcels_value) || $min_parcels_value < 1) {
                $min_parcels_value = 5;
            } else {
                $min_parcels_value = floatval($min_parcels_value);
            }
            if ($max_installments < 1) {
                $max_installments = 12;
            }
        }

        if (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                $product_limit = get_post_meta($product_id, 'lknRedeProdutctInterest', true);
                if ($product_limit !== 'default' && is_numeric($product_limit)) {
                    $product_limit = (int) $product_limit;
                    if ($product_limit > 0 && $product_limit < $max_installments) {
                        $max_installments = $product_limit;
                    }
                }
            }
        }

        // Verificar se a licença PRO está ativa
        $is_pro_active = false;
        if (is_plugin_active('rede-for-woocommerce-pro/rede-for-woocommerce-pro.php')) {
            $pro_license = get_option('lknRedeForWoocommerceProLicense');
            if ($pro_license) {
                $license_data = base64_decode($pro_license);
                $is_pro_active = (strpos($license_data, 'active') !== false);
            }
        }

        // Para rede_debit, verificar se as configurações de juros/desconto estão ativadas
        $apply_pro_features = false;
        if ($is_pro_active && isset($this->wc_rede_debit_class) && method_exists($this->wc_rede_debit_class, 'get_option')) {
            $installment_interest = $this->wc_rede_debit_class->get_option('installment_interest');
            $installment_discount = $this->wc_rede_debit_class->get_option('installment_discount');
            
            if ($installment_interest === 'yes' || $installment_discount === 'yes') {
                $apply_pro_features = true;
            }
        }

        // Obter valor mínimo da parcela das configurações
        $min_installment_value = 5; // Valor padrão
        if (isset($this->wc_rede_debit_class) && method_exists($this->wc_rede_debit_class, 'get_option')) {
            $configured_min_value = floatval($this->wc_rede_debit_class->get_option('min_parcels_value'));
            if ($configured_min_value > 0) {
                $min_installment_value = $configured_min_value;
            }
        }

        $installments = [];
        for ($i = 1; $i <= $max_installments; $i++) {
            $installment_value = $cart_total / $i;
            if ($installment_value < $min_parcels_value) {
                // Se nem mesmo 1x atende o valor mínimo, força 1x à vista
                if ($i === 1) {
                    $base_label = sprintf(
                        /* translators: %1$d is the number of installments, %2$s is the formatted price per installment */
                        "%dx de %s", 
                        1, 
                        wc_price($cart_total)
                    );
                    $label = $apply_pro_features ? $this->get_installment_label_with_interest(1, $base_label, self::GATEWAY_DEBIT) : $base_label;
                    $installments[] = [
                        'key' => 1,
                        'label' => $label
                    ];
                }
                break;
            }
            $base_label = sprintf(
                /* translators: %1$d is the number of installments, %2$s is the formatted price per installment */
                "%dx de %s", 
                $i, 
                wc_price($installment_value)
            );

            // Se as funcionalidades PRO estiverem ativadas e configuradas, aplicar lógica de juros/desconto
            if ($apply_pro_features) {
                $label = $this->get_installment_label_with_interest($i, $base_label, self::GATEWAY_DEBIT);
            } else {
                $label = $base_label;
            }

            $installments[] = [
                'key' => $i,
                'label' => $label
            ];
        }
        
        // Definir installment de debit como 1 na sessão e capturar tipo de cartão se enviado
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('lkn_installments_number_rede_debit', 1);
            
            // Se o tipo de cartão foi enviado na requisição, salvar na sessão
            if (isset($_POST['card_type']) && !empty($_POST['card_type'])) {
                $card_type = sanitize_text_field(wp_unslash($_POST['card_type']));
                if (in_array($card_type, ['credit', 'debit'])) {
                    WC()->session->set('lkn_card_type_rede_debit', $card_type);
                }
            }
        }
        
        wp_send_json([
            'cartTotal' => $cart_total,
            'installments' => $installments
        ]);
    }

    /**
     * Endpoint AJAX para atualizar a sessão de parcelas
     */
    public function ajax_update_installment_session()
    {
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';
        $installments = isset($_POST['installments']) ? intval($_POST['installments']) : 1;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        // Capturar tipo de cartão (apenas para rede_debit)
        $card_type = null;
        if ($payment_method === self::GATEWAY_DEBIT && isset($_POST['card_type'])) {
            $card_type = sanitize_text_field(wp_unslash($_POST['card_type']));
        }

        // Verificar nonce baseado no método de pagamento
        $nonce_action = '';
        switch ($payment_method) {
            case self::GATEWAY_CREDIT:
                $nonce_action = 'rede_payment_fields_nonce';
                break;
            case self::GATEWAY_DEBIT:
                $nonce_action = 'rede_debit_payment_fields_nonce';
                break;
            case 'maxipago_credit':
                $nonce_action = 'maxipago_payment_fields_nonce';
                break;
            default:
                wp_send_json_error(['message' => 'Método de pagamento não suportado']);
                return;
        }

        // Verificar nonce para segurança
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
            return;
        }

        if (empty($payment_method) || $installments < 1) {
            wp_send_json_error(['message' => 'Parâmetros inválidos']);
            return;
        }

        // Determinar a chave da sessão baseada no método de pagamento
        $session_key = '';
        switch ($payment_method) {
            case self::GATEWAY_CREDIT:
                $session_key = 'lkn_installments_number_rede_credit';
                break;
            case self::GATEWAY_DEBIT:
                $session_key = 'lkn_installments_number_rede_debit';
                break;
            case 'maxipago_credit':
                $session_key = 'lkn_installments_number_maxipago_credit';
                break;
            default:
                wp_send_json_error(['message' => 'Método de pagamento não suportado']);
                return;
        }

        // Atualizar a sessão do WooCommerce
        if (function_exists('WC') && WC()->session) {
            WC()->session->set($session_key, $installments);
            
            // Para rede_debit, salvar também o tipo de cartão na sessão
            if ($payment_method === self::GATEWAY_DEBIT && $card_type) {
                WC()->session->set('lkn_card_type_rede_debit', $card_type);
            }
            
            $response_data = [
                'message' => 'Sessão atualizada com sucesso',
                'payment_method' => $payment_method,
                'installments' => $installments,
                'session_key' => $session_key
            ];
            
            // Incluir tipo de cartão na resposta se for rede_debit
            if ($payment_method === self::GATEWAY_DEBIT && $card_type) {
                $response_data['card_type'] = $card_type;
            }
            
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error(['message' => 'Sessão do WooCommerce não disponível']);
        }
    }

    /**
     * Gera o label da parcela com informações de juros/desconto (funcionalidade PRO)
     */
    private function get_installment_label_with_interest($installment_number, $base_label, $gateway = null)
    {
        // Default to credit gateway if not specified
        if ($gateway === null) {
            $gateway = self::GATEWAY_CREDIT;
        }
        
        // Obter todas as configurações do gateway
        $gatewaySettings = get_option('woocommerce_' . $gateway . '_settings', array());
        if (!is_array($gatewaySettings)) {
            return $base_label;
        }

        // Obter soma dos produtos + taxa de entrega + impostos
        $cartTotal = 0;
        if (function_exists('WC') && WC()->cart) {
            // Soma dos produtos
            $cartTotal = floatval(WC()->cart->get_cart_contents_total());
            // Adicionar taxa de entrega
            $cartTotal += floatval(WC()->cart->get_shipping_total());
            // Adicionar impostos
            $cartTotal += floatval(WC()->cart->get_taxes_total());

            WC()->cart->calculate_totals();

            $fees_objects = WC()->cart->get_fees();
            $extra_fees = 0;
            foreach ($fees_objects as $fee) {
                if (!LknIntegrationRedeForWoocommerceHelper::isOwnInterestDiscountFee($fee->name)) {
                    $extra_fees += floatval($fee->amount);
                }
            }
        }

        // Calcular o valor da parcela individual
        $installmentValue = $cartTotal / $installment_number;

        // Verificar valor mínimo para aplicar juros
        $minInterest = isset($gatewaySettings['min_interest']) ? floatval($gatewaySettings['min_interest']) : 0;
        $ignoreInterest = ($minInterest > 0 && $installmentValue > $minInterest);

        // Verificar se há checkbox "sem juros" marcado
        $no_interest_key = "{$installment_number}x_no_interest";
        if (isset($gatewaySettings[$no_interest_key]) && $gatewaySettings[$no_interest_key] === 'yes') {
            // Para rede_debit, verificar se deve mostrar a informação "sem juros"
            if ($gateway === self::GATEWAY_DEBIT) {
                $show_percent = isset($gatewaySettings['interest_show_percent']) && $gatewaySettings['interest_show_percent'] === 'yes';
                if ($show_percent) {
                    return $base_label . ' sem juros';
                } else {
                    return $base_label;
                }
            } else {
                return $base_label . ' sem juros';
            }
        }

        // Verificar o valor de juros/desconto
        $value_key = "{$installment_number}x";
        $discount_key = "{$installment_number}x_discount";

        $value = 0;
        $is_discount = false;

        // Verificar se existe valor de desconto
        if (isset($gatewaySettings[$discount_key]) && !empty($gatewaySettings[$discount_key])) {
            $value = floatval($gatewaySettings[$discount_key]);
            $is_discount = true;

            // Se valor for negativo, corrigir para 0 e salvar
            if ($value < 0) {
                $value = 0;
                $gatewaySettings[$discount_key] = '0';
                update_option('woocommerce_' . $gateway . '_settings', $gatewaySettings);
            }
        }
        // Senão, verificar se existe valor normal (juros)
        elseif (isset($gatewaySettings[$value_key]) && !empty($gatewaySettings[$value_key])) {
            $value = floatval($gatewaySettings[$value_key]);
            $is_discount = false;

            // Se valor for negativo, corrigir para 0 e salvar
            if ($value < 0) {
                $value = 0;
                $gatewaySettings[$value_key] = '0';
                update_option('woocommerce_' . $gateway . '_settings', $gatewaySettings);
            }
        }

        // Se o valor for 0 ou vazio, adicionar "sem juros" apenas se configurado para mostrar
        if ($value === 0) {
            $show_percent = isset($gatewaySettings['interest_show_percent']) && $gatewaySettings['interest_show_percent'] === 'yes';
            if ($show_percent) {
                return $base_label . ' sem juros';
            } else {
                return $base_label;
            }
        }

        // Calcular novo valor da parcela com juros/desconto
        $newInstallmentValue = $installmentValue;

        // Verificar se deve mostrar porcentagem na label
        $show_percent = isset($gatewaySettings['interest_show_percent']) ? $gatewaySettings['interest_show_percent'] === 'yes' : true;

        if ($is_discount) {
            // Aplicar desconto
            $total_with_discount = ($cartTotal * (1 - ($value / 100))) + $extra_fees;
            $newInstallmentValue = $total_with_discount / $installment_number;
            $new_label = sprintf(
                /* translators: %1$d is the number of installments, %2$s is the formatted price per installment */
                "%dx de %s", 
                $installment_number, 
                wc_price($newInstallmentValue)
            );
            
            if ($show_percent) {
                return $new_label . " ({$value}% de desconto)";
            } else {
                return $new_label;
            }
        } else {
            // Para juros, verificar se deve ignorar devido ao valor mínimo da parcela
            if ($ignoreInterest) {
                $show_percent = isset($gatewaySettings['interest_show_percent']) && $gatewaySettings['interest_show_percent'] === 'yes';
                if ($show_percent) {
                    return $base_label . ' sem juros';
                } else {
                    return $base_label;
                }
            } else {
                // Aplicar juros
                $total_with_interest = ($cartTotal * (1 + ($value / 100))) + $extra_fees;
                $newInstallmentValue = $total_with_interest / $installment_number;
                $new_label = sprintf(
                    /* translators: %1$d is the number of installments, %2$s is the formatted price per installment */
                    "%dx de %s", 
                    $installment_number, 
                    wc_price($newInstallmentValue)
                );
                
                if ($show_percent) {
                    return $new_label . " ({$value}% de juros)";
                } else {
                    return $new_label;
                }
            }
        }
    }

    /**
     * Show admin notice for fraud detection plugin
     */
    public function lkn_admin_notice(): void
    {
        // Verificar se usuario já dispensou a notificação
        $notice_dismissed = get_option('lkn_fraud_notice_dismissed', 'no');
        
        if ($notice_dismissed === 'no' && (!file_exists(WP_PLUGIN_DIR . '/fraud-scam-detection-woocommerce/fraud-scam-detection-woocommerce.php') && !file_exists(WP_PLUGIN_DIR . '/fraud-and-scam-detection-for-woocommerce/fraud-scam-detection-woocommerce.php'))) {
            // Enfileirar script para dismiss da notificação
            wp_enqueue_script('lkn-rede-dismiss-notice', INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Admin/js/lkn-dismiss-fraud-notice.js', array('jquery'), $this->version, true);
            wp_localize_script('lkn-rede-dismiss-notice', 'lknRedeDismissNotice', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lkn_dismiss_fraud_notice_nonce'),
                'action' => 'lkn_dismiss_fraud_notice'
            ));
            
            require INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/views/notices/lkn-integration-rede-for-woocommerce-notice-download.php';
        }
    }

    /**
     * Exibe aviso crítico para atualização obrigatória do plugin PRO
     * Este aviso é relacionado às novas normas da Rede que entram em vigor em 05/01/2026
     */
    public function lkn_pro_update_critical_notice()
    {
        // Verificar se o plugin PRO existe (independente se está ativo)
        $pro_plugin_file = WP_PLUGIN_DIR . '/rede-for-woocommerce-pro/rede-for-woocommerce-pro.php';
        
        if (!file_exists($pro_plugin_file)) {
            return; // Plugin PRO não está instalado
        }

        // Obter dados do plugin PRO
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $pro_plugin_data = get_plugin_data($pro_plugin_file);
        $pro_version = $pro_plugin_data['Version'] ?? '0.0.0';
        
        // Verificar se a versão é menor que 2.2.0
        if (version_compare($pro_version, '2.2.0', '>=')) {
            return; // Versão é adequada, não mostrar aviso
        }

        // Exibir o aviso crítico
        ?>
        <div class="notice notice-warning" style="border-left-color: #ffb900; padding: 15px;">
            <h3 style="margin-top: 0; color: #000;">
                <?php esc_html_e('⚠️ Plugin Update Required - Rede Integration for WooCommerce Pro', 'woo-rede'); ?>
            </h3>
            <p style="font-size: 14px; line-height: 1.6;">
                <strong><?php esc_html_e('Important:', 'woo-rede'); ?></strong>
                <?php esc_html_e('Rede\'s authentication protocol will be changed to OAuth 2.0 on 01/05/2026. All customers using e.Rede must update the plugin. This measure will contribute to security and help prevent targeted attacks on your transactions. After the deadline, transactions can only be performed using the updated plugin.', 'woo-rede'); ?>
            </p>
            <p style="font-size: 14px; line-height: 1.6;">
                <strong><?php esc_html_e('Action Required (For PRO Users):', 'woo-rede'); ?></strong>
                <?php 
                echo sprintf(
                    /* translators: %1$s is the current PRO version, %2$s is the required version */
                    esc_html__('Your Rede Plugin PRO version %1$s needs to be updated to version %2$s or higher.', 'woo-rede'),
                    '<code>' . esc_html($pro_version) . '</code>',
                    '<code>2.2.0</code>'
                );
                ?>
            </p>
            <p style="margin-bottom: 0;">
                <a href="https://developer.userede.com.br/e-rede" target="_blank" class="button">
                    <?php esc_html_e('Check what changes', 'woo-rede'); ?>
                </a>
                <?php
                // Create update URL for PRO plugin
                $pro_plugin_slug = 'rede-for-woocommerce-pro/rede-for-woocommerce-pro.php';
                $update_url = wp_nonce_url(
                    admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($pro_plugin_slug)),
                    'upgrade-plugin_' . $pro_plugin_slug
                );
                ?>
                <a href="<?php echo esc_url($update_url); ?>" class="button button-primary">
                    <?php esc_html_e('Update Now', 'woo-rede'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function customize_wc_payment_gateway_pix_name($title, $gateway_id)
    {
        if ($gateway_id === self::GATEWAY_PIX_FREE) {
            $title = __('Rede Pix FREE', 'woo-rede');
        }
        return $title;
    }

    /**
     * Adiciona ação de verificação PIX no dropdown de ações da ordem
     */
    public function add_pix_verification_action($actions)
    {
        global $theorder;
        
        if (!$theorder) {
            return $actions;
        }
        
        $payment_method = $theorder->get_payment_method();
        
        // Só adiciona a ação se for um pedido PIX
        if (self::is_pix_gateway($payment_method)) {
            $actions['verify_pix_status'] = __('Verificar Status PIX', 'woo-rede');
        }
        
        return $actions;
    }

    /**
     * Processa a ação de verificação manual do PIX
     */
    public function process_pix_verification_action($order)
    {
        $payment_method = $order->get_payment_method();
        
        // Validar se é pedido PIX
        if (!self::is_pix_gateway($payment_method)) {
            $order->add_order_note('[' . $payment_method . '] ' . __('Verificação PIX: Esta ação é aplicável apenas a pedidos com método de pagamento PIX.', 'woo-rede'));
            return;
        }
        
        // Buscar TID da transação
        $tId = '';
        if ($payment_method === 'integration_rede_pix') {
            $tId = $order->get_meta('_wc_rede_integration_pix_transaction_tid');
        } elseif ($payment_method === 'rede_pix') {
            $tId = $order->get_meta('_wc_rede_pix_transaction_tid');
        }
        
        if (empty($tId)) {
            $order->add_order_note('[' . $payment_method . '] ' . __('Verificação PIX: Identificador da transação não localizado nos metadados do pedido.', 'woo-rede'));
            return;
        }
        
        // Obter configurações do gateway
        $gateway_id = ($payment_method === 'integration_rede_pix') ? 'integration_rede_pix' : 'rede_pix';
        $pixOptions = get_option('woocommerce_' . $gateway_id . '_settings');
        $environment = $pixOptions['environment'] ?? 'sandbox';
        
        try {
            // Renovar token OAuth2 se necessário
            LknIntegrationRedeForWoocommerceHelper::refresh_expired_rede_oauth_tokens(20);
            $token_data = LknIntegrationRedeForWoocommerceHelper::get_cached_rede_oauth_token_for_gateway($gateway_id, $environment);
            
            if (!$token_data || empty($token_data['token'])) {
                throw new \Exception(__('Erro ao obter token de autenticação.', 'woo-rede'));
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
            
            if (is_wp_error($response)) {
                throw new \Exception(__('Erro na comunicação com a API: ', 'woo-rede') . $response->get_error_message());
            }
            
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            $status = $response_body['authorization']['status'] ?? 'Unknown';
            
            if ($status === 'Approved') {
                // Obter valor e moeda do pedido usando método compatível
                $order_total = wc_price($order->get_total());
                
                // Só atualiza o status se o pedido estiver pendente
                if ($order->has_status('pending')) {
                    $paymentCompleteStatus = $pixOptions['payment_complete_status'] ?? 'processing';
                    if (empty($paymentCompleteStatus)) {
                        $paymentCompleteStatus = 'processing';
                    }
                    
                    // translators: %s is the order total amount
                    $order->add_order_note('[' . $gateway_id . '] ' . sprintf(__('Manual PIX Verification: Payment of %s confirmed by Rede.', 'woo-rede'), $order_total));
                    $order->set_date_paid(current_time('timestamp', true));
                    $order->update_status($paymentCompleteStatus);
                } else {
                    // translators: %s is the order total amount
                    $order->add_order_note('[' . $gateway_id . '] ' . sprintf(__('Manual PIX Verification: Payment of %s confirmed by Rede.', 'woo-rede'), $order_total));
                }
            } else {
                $order->add_order_note('[' . $gateway_id . '] ' . __('Manual PIX Verification: Payment not confirmed by Rede. Transaction status: ', 'woo-rede') . $status);
            }
            
        } catch (\Exception $e) {
            $order->add_order_note('[' . $gateway_id . '] ' . __('Manual PIX Verification: Failed to query payment from Rede. Details: ', 'woo-rede') . $e->getMessage());
        }
        
        $order->save();
    }

    /**
     * Limpa o cron 'update_rede_orders' ao atualizar o plugin via admin
     */
    public function clear_update_rede_orders_on_plugin_update($upgrader, $options)
    {
        if (
            isset($options['action'], $options['type'], $options['plugins']) &&
            $options['action'] === 'update' &&
            $options['type'] === 'plugin'
        ) {
            foreach ($options['plugins'] as $plugin) {
                if (strpos($plugin, 'integration-rede-for-woocommerce.php') !== false) {
                    wp_clear_scheduled_hook('update_rede_orders');
                }
            }
        }
    }

    public static function lknIntegrationRedeForWoocommercePluginRowMeta($plugin_meta, $plugin_file)
    {
        $new_meta_links['setting'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            admin_url('admin.php?page=wc-settings&tab=checkout'),
            __('Settings', 'woo-rede')
        );

        return array_merge($plugin_meta, $new_meta_links);
    }

    public static function lknIntegrationRedeForWoocommercePluginRowMetaPro($plugin_meta, $plugin_file)
    {
        // Defina o URL e o texto do link
        $url = 'https://www.linknacional.com.br/wordpress/woocommerce/rede/';
        $link_text = sprintf(
            '<span style="color: red; font-weight: bold;">%s</span>',
            __('Be pro', 'woo-rede')
        );

        // Crie o novo link de meta
        $new_meta_link = sprintf('<a href="%1$s">%2$s</a>', $url, $link_text);

        // Adicione o novo link ao array de metadados do plugin
        $plugin_meta[] = $new_meta_link;

        return $plugin_meta;
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks(): void
    {
        $plugin_public = new LknIntegrationRedeForWoocommercePublic($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->wc_rede_class->getInstance();
        $this->loader->add_filter('woocommerce_payment_gateways', $this->wc_rede_class, 'addGateway');

        $this->loader->add_action('woocommerce_thankyou_' . $this->wc_rede_credit_class->id, $this->wc_rede_credit_class, 'thankyou_page');
        $this->loader->add_action('woocommerce_thankyou_' . $this->wc_rede_debit_class->id, $this->wc_rede_debit_class, 'thankyou_page');
        $this->loader->add_action('woocommerce_checkout_fields', $this->wc_maxipago_credit_class, 'addNeighborhoodFieldToCheckout');
        $this->loader->add_action('woocommerce_checkout_fields', $this->wc_maxipago_debit_class, 'addNeighborhoodFieldToCheckout');
        $this->loader->add_action('wp_enqueue_scripts', $this->wc_rede_credit_class, 'checkoutScripts');
        $this->loader->add_action('wp_enqueue_scripts', $this->wc_rede_debit_class, 'checkoutScripts');
        $this->loader->add_action('wp_enqueue_scripts', $this->wc_maxipago_credit_class, 'checkoutScripts');
        $this->loader->add_action('wp_enqueue_scripts', $this->wc_maxipago_debit_class, 'checkoutScripts');

        $this->loader->add_action('before_woocommerce_init', $this, 'wcEditorBlocksActive');
        $this->loader->add_action('woocommerce_blocks_payment_method_type_registration', $this, 'wcEditorBlocksAddPaymentMethod');

        // Adiciona endpoint AJAX para atualizar sessão de parcelas no shortcode
        $this->loader->add_action('wp_ajax_update_installment_session', $this, 'update_installment_session');
        $this->loader->add_action('wp_ajax_nopriv_update_installment_session', $this, 'update_installment_session');

        // Adiciona endpoint AJAX para detecção de bandeira de cartão
        $this->loader->add_action('wp_ajax_lkn_get_offline_bin_card', $this, 'ajax_get_offline_bin_card');
        $this->loader->add_action('wp_ajax_nopriv_lkn_get_offline_bin_card', $this, 'ajax_get_offline_bin_card');

        // Adiciona o nome do gateway nas notas do pedido
        $this->loader->add_filter('woocommerce_new_order_note_data', $this, 'add_gateway_name_to_notes_global', 10, 2);

        // Adiciona informações de parcelamento na revisão do pedido
        $this->loader->add_action('woocommerce_review_order_after_order_total', $this, 'display_payment_installment_info');

        $this->loader->add_action('wp_ajax_lkn_get_recent_rede_orders', $this, 'ajax_get_recent_rede_orders');
        $this->loader->add_action('wp_ajax_nopriv_lkn_get_recent_rede_orders', $this, 'ajax_get_recent_rede_orders');
    }

    public function wcEditorBlocksActive(): void
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE,
                true
            );
        }
    }

    public function wcEditorBlocksAddPaymentMethod(\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry): void
    {
        if (! class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        $payment_method_registry->register(new LknIntegrationRedeForWoocommerceWcMaxipagoCreditBlocks());
        $payment_method_registry->register(new LknIntegrationRedeForWoocommerceWcMaxipagoDebitBlocks());
        $payment_method_registry->register(new LknIntegrationRedeForWoocommerceWcRedeCreditBlocks());
        $payment_method_registry->register(new LknIntegrationRedeForWoocommerceWcRedeDebitBlocks());
        $payment_method_registry->register(new LknIntegrationRedeForWoocommerceWcPixRedeBlocks());
        $payment_method_registry->register(new LknIntegrationRedeForWoocommerceGooglePayBlocks());
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run(): void
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    LknIntegrationRedeForWoocommerceLoader    Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }

    public function addSettings($plugin_meta)
    {
        $plugin_meta['setting'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            admin_url('admin.php?page=wc-settings&tab=checkout'),
            'Configurações'
        );

        return $plugin_meta;
    }

    public function add_gateway_name_to_notes_global($note_data, $args)
    {
        if (isset($note_data['comment_post_ID'])) {
            $order_id = $note_data['comment_post_ID'];
            $order = wc_get_order($order_id);

            if ($order && is_a($order, 'WC_Order')) {

                // Metodos do plugin integration rede (free) e integration rede pro
                $methods = ['maxipago_credit', 'maxipago_debit', 'maxipago_pix', 'integration_rede_pix', 'rede_credit', 'rede_debit', 'rede_pix', 'rede_google_pay'];
                $payment_method = $order->get_payment_method();

                if (in_array($payment_method, $methods)) {

                    // Só processar notas que contenham o marcador [$payment_method] para ignorar plugins terceiros
                    $pattern = '/\[' . preg_quote($payment_method, '/') . '\]\s*/';
                    if (preg_match($pattern, $note_data['comment_content'])) {

                        // Remover o marcador do conteúdo
                        $note_data['comment_content'] = preg_replace($pattern, '', $note_data['comment_content']);

                        $payment_gateways = WC()->payment_gateways->payment_gateways();
                        $gateway_title = 'Rede'; // Fallback

                        if (isset($payment_gateways[$payment_method])) {
                            $gateway_title = $payment_gateways[$payment_method]->get_title();
                        }

                        // Verificar se o prefixo já existe para evitar duplicação
                        $prefix = $gateway_title . ' — ';
                        if (strpos($note_data['comment_content'], $prefix) === false) {
                            $note_data['comment_content'] = $prefix . $note_data['comment_content'];
                        }
                    }
                }
            }
        }

        return $note_data;
    }

    /**
     * Exibe informações sobre o pagamento parcelado na revisão do pedido
     */
    public function display_payment_installment_info()
    {
        // Verificar se WooCommerce está ativo e a sessão existe
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $chosen_payment_method = WC()->session->get('chosen_payment_method');

        // Verificar se é um método de pagamento Rede ou Maxipago
        if (!in_array($chosen_payment_method, ['rede_credit', 'rede_debit', 'maxipago_credit'])) {
            return;
        }

        // Obter o total do carrinho
        $cart_total = WC()->cart->get_total('raw');

        if ($cart_total <= 0) {
            return;
        }

        // Obter configurações do gateway para verificar valor mínimo de parcelas
        $settings = get_option('woocommerce_' . $chosen_payment_method . '_settings', array());
        $min_parcels_value = isset($settings['min_parcels_value']) ? floatval($settings['min_parcels_value']) : 5;
        
        if (empty($min_parcels_value) || !is_numeric($min_parcels_value) || $min_parcels_value < 5) {
            $min_parcels_value = 5;
        }

        // Para rede_debit, verificar se permite crédito através da configuração de tipo de cartão
        $show_installments = true;
        if ($chosen_payment_method === 'rede_debit') {
            $card_type_restriction = isset($settings['card_type_restriction']) ? $settings['card_type_restriction'] : 'debit_only';
            
            // Verificar tipo de cartão na sessão
            $session_card_type = WC()->session->get('lkn_card_type_rede_debit');
            
            // Se o tipo da sessão for débito, forçar para não mostrar parcelas
            if ($session_card_type === 'debit') {
                $show_installments = false;
            } else {
                // Só mostra parcelas se permitir crédito (both ou credit_only) e não for débito na sessão
                if ($card_type_restriction === 'debit_only') {
                    $show_installments = false;
                }
            }
        }
        
        // Verificar se é possível ter mais de uma parcela com base no valor mínimo
        $max_possible_installments = floor($cart_total / $min_parcels_value);
        if ($max_possible_installments <= 1 || !$show_installments) {
            return; // Não exibe se só é possível 1x à vista ou se não permite crédito
        }

        // Obter a parcela selecionada da sessão baseada no método de pagamento
        $installment_session_key = '';
        if ($chosen_payment_method === 'rede_credit') {
            $installment_session_key = 'lkn_installments_number_rede_credit';
        } elseif ($chosen_payment_method === 'rede_debit') {
            $installment_session_key = 'lkn_installments_number_rede_debit';
        } elseif ($chosen_payment_method === 'maxipago_credit') {
            $installment_session_key = 'lkn_installments_number_maxipago_credit';
        }

        $installment = WC()->session->get($installment_session_key);

        // Para rede_debit, se o tipo de cartão for débito, forçar parcela para 1
        if ($chosen_payment_method === 'rede_debit') {
            $session_card_type = WC()->session->get('lkn_card_type_rede_debit');
            if ($session_card_type === 'debit') {
                $installment = 1;
            }
        }

        if (!$installment || $installment <= 0) {
            return;
        }

        // Determinar o nome do método de pagamento
        $payment_method_name = '';
        if ($chosen_payment_method === 'rede_credit') {
            $payment_method_name = __('Rede Credit Card', 'woo-rede');
        } elseif ($chosen_payment_method === 'rede_debit') {
            $payment_method_name = __('Rede Debit/Credit Card', 'woo-rede');
        } elseif ($chosen_payment_method === 'maxipago_credit') {
            $payment_method_name = __('Maxipago Credit Card', 'woo-rede');
        }

        // Gerar a informação de pagamento e label dinâmico
        if ($installment == 1) {
            $payment_label = __('Payment', 'woo-rede');
            $payment_info = __('Cash payment', 'woo-rede');
        } else {
            $payment_label = __('Installment', 'woo-rede');
            // Calcular valor da parcela (simples divisão)
            $installment_value = $cart_total / $installment;
            $formatted_value = wc_price($installment_value);

            $payment_info = sprintf(
                // translators: %1$d is the number of installments, %2$s is the formatted price per installment
                __('%1$dx of %2$s', 'woo-rede'),
                $installment,
                $formatted_value
            );
        }

        // Exibir a informação
        echo '<tr>';
        echo '<th>' . esc_html($payment_label) . '</th>';
        echo '<td>' . wp_kses_post($payment_info) . '</td>';
        echo '</tr>';
    }

    /**
     * Atualiza sessão de parcelas via AJAX (para shortcode checkout)
     */
    public function update_installment_session()
    {
        try {
            // Verifica o nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'lkn_rede_installment_shortcode_nonce')) {
                wp_send_json_error(['message' => 'Invalid nonce']);
                return;
            }

            // Obtém dados da requisição
            $gateway = isset($_POST['gateway']) ? sanitize_text_field(wp_unslash($_POST['gateway'])) : '';
            $installments = isset($_POST['installments']) ? intval(sanitize_text_field(wp_unslash($_POST['installments']))) : 1;

            // Valida gateway
            if (!in_array($gateway, ['rede_credit', 'rede_debit', 'maxipago_credit'])) {
                wp_send_json_error(['message' => 'Invalid gateway']);
                return;
            }

            // Valida parcelas
            if ($installments < 1 || $installments > 12) {
                wp_send_json_error(['message' => 'Invalid installments']);
                return;
            }

            // Atualiza sessão WooCommerce
            if (function_exists('WC') && WC()->session) {
                $session_key = 'lkn_installments_number_' . $gateway;
                WC()->session->set($session_key, $installments);
                
                wp_send_json_success([
                    'message' => 'Installments updated successfully',
                    'gateway' => $gateway,
                    'installments' => $installments
                ]);
            } else {
                wp_send_json_error(['message' => 'WooCommerce session not available']);
            }
        } catch (\Throwable $th) {
            wp_send_json_error([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ]);
        }
    }

    /**
     * Verifica e renova tokens OAuth2 expirados no checkout (blocks e shortcode)
     */
    public function lkn_rede_refresh_oauth_token_on_checkout() 
    {
        // Só executa em páginas de checkout
        if (!is_checkout()) {
            return;
        }

        // Verifica e renova tokens expirados (15 minutos)
        LknIntegrationRedeForWoocommerceHelper::refresh_expired_rede_oauth_tokens(15);
    }

    /**
     * Endpoint AJAX para detectar bandeira do cartão baseado no BIN
     */
    public function ajax_get_offline_bin_card()
    {
        // Verificar nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'redeCardNonce')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
            return;
        }

        // Obter número do cartão
        $number = isset($_POST['number']) ? sanitize_text_field(wp_unslash($_POST['number'])) : '';
        if (empty($number)) {
            wp_send_json_error(['message' => 'Número do cartão é obrigatório']);
            return;
        }

        $result = $this->getOfflineBinCard($number);
        wp_send_json($result);
    }

    /**
     * Retrieves the card brand based on the BIN number.
     *
     * @param string $number The card number.
     *
     * @return array Returns an array with the card brand if recognized, or an error message if not.
     */
    public function getOfflineBinCard($number)
    {
        $number = str_replace(' ', '', trim($number));

        $bin = [
            // visa
            '/^4[0-9]{2,15}$/',
            // elo
            '/^(431274|438935|451416|457393|4576|457631|457632|504175|627780|636297|636368|636369|(6503[1-3])|(6500(3[5-9]|4[0-9]|5[0-1]))|(6504(0[5-9]|1[0-9]|2[0-9]|3[0-9]))|(650(48[5-9]|49[0-9]|50[0-9]|51[1-9]|52[0-9]|53[0-7]))|(6505(4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-8]))|(6507(0[0-9]|1[0-8]))|(6507(2[0-7]))|(650(90[1-9]|91[0-9]|920))|(6516(5[2-9]|6[0-9]|7[0-9]))|(6550(0[0-9]|1[1-9]))|(6550(2[1-9]|3[0-9]|4[0-9]|5[0-8]))|(506(699|77[0-8]|7[1-6][0-9))|(509([0-9][0-9][0-9])))/',
            // hipercard
            '/^(606282|3841)\d{0,13}$/',
            // diners
            '/^3(?:0[0-5]|[68][0-9])[0-9]{0,11}$/',
            // discover
            '/^6(?:011|5[0-9]{2})[0-9]{0,12}$/',
            // jcb
            '/^(?:2131|1800|35\d{2})\d{0,11}$/',
            // aura
            '/^50[0-9]{2,17}$/',
            // amex
            '/^3[47][0-9]{2,13}$/',
            // mastercard
            '/^5[1-5]\d{0,14}$|^2(?:2(?:2[1-9]|[3-9]\d)|[3-6]\d\d|7(?:[01]\d|20))\d{0,12}$/',
        ];

        // Test the cardNumber bin
        foreach ($bin as $index => $regex) {
            if (preg_match($regex, $number)) {
                $brands = [
                    'visa',
                    'elo',
                    'hipercard',
                    'diners',
                    'discover',
                    'jcb',
                    'aura',
                    'amex',
                    'mastercard',
                ];

                return [
                    'status' => true,
                    'brand' => $brands[$index],
                ];
            }
        }

        // Caso não encontre nenhuma correspondência
        return [
            'status' => false,
            'message' => __('Card brand not found', 'woo-rede'),
        ];
    }

    /**
     * Adiciona link do Changelog na página de plugins
     */
    public function add_changelog_link($plugin_meta, $plugin_file)
    {
        // Verificar se é o nosso plugin
        if (strpos($plugin_file, 'integration-rede-for-woocommerce.php') !== false) {
            $changelog_link = sprintf(
                '<a href="%1$s" target="_blank">%2$s</a>',
                'https://br.wordpress.org/plugins/woo-rede/#developers',
                __('Changelog', 'woo-rede')
            );
            
            $plugin_meta[] = $changelog_link;
        }
        
        return $plugin_meta;
    }

    /**
     * Adiciona item personalizado ao menu Analytics do WooCommerce
     * Insere na posição correta antes das configurações
     *
     * @param array $items Lista de itens do menu Analytics
     * @return array Lista modificada com o item Rede
     */
    public function add_rede_analytics_menu_item($items)
    {
        $plugin_pro_is_valid = LknIntegrationRedeForWoocommerceHelper::isProLicenseValid();

        // Item Rede Transações
        $rede_item = array(
            'id'       => 'woocommerce-analytics-rede-transactions',
            'title'    => __('Rede Transactions', 'woo-rede'),
            'parent'   => 'woocommerce-analytics',
            'path'     => '/analytics/rede-transactions',
            'icon'     => 'dashicons-chart-bar',
            'position' => 2,
        );

        // Encontrar a posição das configurações para inserir antes
        $settings_key = null;
        foreach ($items as $key => $item) {
            if (isset($item['id']) && $item['id'] === 'woocommerce-analytics-settings') {
                $settings_key = $key;
                break;
            }
        }

        // Se encontrou as configurações, insere antes dela
        if ($settings_key !== null) {
            // Divide o array em duas partes
            $before_settings = array_slice($items, 0, array_search($settings_key, array_keys($items)), true);
            $from_settings = array_slice($items, array_search($settings_key, array_keys($items)), null, true);
            
            // Adiciona o item Rede entre elas
            $items = $before_settings + ['rede-transactions' => $rede_item] + $from_settings;
        } else {
            // Fallback: adiciona no final se não encontrar as configurações
            $items['rede-transactions'] = $rede_item;
        }

        return $items;
    }

    /**
     * Registra o script JavaScript e CSS para analytics do Rede
     * Usa a versão React compilada via webpack
     */
    public function register_rede_analytics_script()
    {
        $plugin_pro_is_valid = LknIntegrationRedeForWoocommerceHelper::isProLicenseValid();

        // Só carregar em páginas do WooCommerce Admin
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'woocommerce') === false) {
            return;
        }

        // Usar a versão React compilada
        wp_register_script(
            'lkn-rede-analytics',
            plugin_dir_url(__FILE__) . '../Admin/js/analytics/lknRedeAnalyticsCompiled.js',
            array('wp-hooks', 'wp-element', 'wp-i18n', 'wc-components', 'react', 'react-dom'),
            INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION,
            true
        );

        wp_enqueue_script('lkn-rede-analytics');
        
        // Enfileirar CSS do analytics também
        wp_enqueue_style(
            'lkn-rede-analytics-style',
            plugin_dir_url(__FILE__) . '../Admin/css/lkn-rede-analytics-react.css',
            array(),
            INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION
        );

        // Localiza script com dados para AJAX
        wp_localize_script('lkn-rede-analytics', 'lknRedeAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lkn_rede_orders_nonce'),
            'action_get_recent_orders' => 'lkn_get_recent_rede_orders',
            'gateway_brands_url' => plugin_dir_url(__FILE__) . '/assets/cardBrands/',
        ));

        wp_localize_script('lkn-rede-analytics', 'lknRedeAnalytics', array(
            'plugin_license' => $plugin_pro_is_valid ? 'active' : 'inactive',
            'screenshot_url' => plugin_dir_url(__FILE__) . 'assets/img/rede-transactions.webp',
            'pro_version' => 'https://www.linknacional.com.br/wordpress/woocommerce/rede/?utm=plugin-rede-free-transaction',
            'site_domain' => home_url(),
            'version_free' => defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION') ? INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION : 'N/A',
            'version_pro' => defined('REDE_FOR_WOOCOMMERCE_PRO_VERSION') ? REDE_FOR_WOOCOMMERCE_PRO_VERSION : 'N/A',
            'whatsapp_number' => LKN_WC_REDE_WPP_NUMBER
        ));

        // Traduções da página de analytics enviadas via wp_localize_script.
        // A chave é o texto original (usado como chave no TSX) e o valor já vem traduzido pelo __().
        wp_localize_script('lkn-rede-analytics', 'lknRedeAnalyticsI18n', array(
            // Columns
            'Card/PIX' => __('Card/PIX', 'woo-rede'),
            'CVV Sent' => __('CVV Sent', 'woo-rede'),
            'Type' => __('Type', 'woo-rede'),
            'Installments' => __('Installments', 'woo-rede'),
            'Installment Value' => __('Installment Value', 'woo-rede'),
            'Brand' => __('Brand', 'woo-rede'),
            'Expiry' => __('Expiry', 'woo-rede'),
            'Date/Time' => __('Date/Time', 'woo-rede'),
            'Total' => __('Total', 'woo-rede'),
            'Subtotal' => __('Subtotal', 'woo-rede'),
            'Shipping' => __('Shipping', 'woo-rede'),
            'Interest/Discount' => __('Interest/Discount', 'woo-rede'),
            'Currency' => __('Currency', 'woo-rede'),
            'Capture' => __('Capture', 'woo-rede'),
            'Recurrent' => __('Recurrent', 'woo-rede'),
            '3DS Auth' => __('3DS Auth', 'woo-rede'),
            'TID/PaymentId' => __('TID/PaymentId', 'woo-rede'),
            'Environment' => __('Environment', 'woo-rede'),
            'Gateway' => __('Gateway', 'woo-rede'),
            'Order ID' => __('Order ID', 'woo-rede'),
            'Reference' => __('Reference', 'woo-rede'),
            'PV' => __('PV', 'woo-rede'),
            'Token' => __('Token', 'woo-rede'),
            'Return Code' => __('Return Code', 'woo-rede'),
            'HTTP Status' => __('HTTP Status', 'woo-rede'),
            'Holder' => __('Holder', 'woo-rede'),
            'Support' => __('Support', 'woo-rede'),

            // WhatsApp support message (debug)
            '#support Hello! I need support with my Rede payment gateway. I am having problems with the transaction and here are the data for verification:' => __('#support Hello! I need support with my Rede payment gateway. I am having problems with the transaction and here are the data for verification:', 'woo-rede'),
            'Order:' => __('Order:', 'woo-rede'),
            'Date/Time:' => __('Date/Time:', 'woo-rede'),
            'Environment:' => __('Environment:', 'woo-rede'),
            'Plugin:' => __('Plugin:', 'woo-rede'),
            'Release' => __('Release', 'woo-rede'),
            'Dependent plugin:' => __('Dependent plugin:', 'woo-rede'),
            'Site:' => __('Site:', 'woo-rede'),
            'Gateway:' => __('Gateway:', 'woo-rede'),
            'Reference:' => __('Reference:', 'woo-rede'),
            'Card/PIX:' => __('Card/PIX:', 'woo-rede'),
            'CVV Sent:' => __('CVV Sent:', 'woo-rede'),
            'Card Type:' => __('Card Type:', 'woo-rede'),
            'Brand:' => __('Brand:', 'woo-rede'),
            'Expiry:' => __('Expiry:', 'woo-rede'),
            'Holder:' => __('Holder:', 'woo-rede'),
            'Installments:' => __('Installments:', 'woo-rede'),
            'Installment Value:' => __('Installment Value:', 'woo-rede'),
            'Capture:' => __('Capture:', 'woo-rede'),
            'Recurrent:' => __('Recurrent:', 'woo-rede'),
            '3DS Auth:' => __('3DS Auth:', 'woo-rede'),
            'TID/PaymentId:' => __('TID/PaymentId:', 'woo-rede'),
            'Total:' => __('Total:', 'woo-rede'),
            'Subtotal:' => __('Subtotal:', 'woo-rede'),
            'Shipping:' => __('Shipping:', 'woo-rede'),
            'Interest/Discount:' => __('Interest/Discount:', 'woo-rede'),
            'Currency:' => __('Currency:', 'woo-rede'),
            'PV:' => __('PV:', 'woo-rede'),
            'Token:' => __('Token:', 'woo-rede'),
            'Return Code:' => __('Return Code:', 'woo-rede'),
            'HTTP Status:' => __('HTTP Status:', 'woo-rede'),
            'Awaiting your reply, thank you!' => __('Awaiting your reply, thank you!', 'woo-rede'),

            // Errors
            'Failed to decode TOON response' => __('Failed to decode TOON response', 'woo-rede'),
            'Unrecognized response format' => __('Unrecognized response format', 'woo-rede'),
            'Error loading data' => __('Error loading data', 'woo-rede'),
            'Connection error while loading data' => __('Connection error while loading data', 'woo-rede'),

            // General UI
            'No data to export' => __('No data to export', 'woo-rede'),
            'Open WhatsApp for support' => __('Open WhatsApp for support', 'woo-rede'),
            'Support' => __('Support', 'woo-rede'),
            'Edit order in WooCommerce' => __('Edit order in WooCommerce', 'woo-rede'),
            'Search transactions...' => __('Search transactions...', 'woo-rede'),
            'Previous' => __('Previous', 'woo-rede'),
            'Next' => __('Next', 'woo-rede'),
            'Page' => __('Page', 'woo-rede'),
            'of' => __('of', 'woo-rede'),
            'Showing' => __('Showing', 'woo-rede'),
            'to' => __('to', 'woo-rede'),
            'records' => __('records', 'woo-rede'),
            'Loading...' => __('Loading...', 'woo-rede'),
            'No transactions found' => __('No transactions found', 'woo-rede'),
            'An error occurred while loading data' => __('An error occurred while loading data', 'woo-rede'),
            'Click to upgrade to Rede Analytics PRO' => __('Click to upgrade to Rede Analytics PRO', 'woo-rede'),
            'Column Configuration' => __('Column Configuration', 'woo-rede'),
            'Restore Default' => __('Restore Default', 'woo-rede'),
            'Close' => __('Close', 'woo-rede'),
            'Move up' => __('Move up', 'woo-rede'),
            'Move down' => __('Move down', 'woo-rede'),
            '💡 Tips:' => __('💡 Tips:', 'woo-rede'),
            'Check/uncheck boxes to show/hide columns' => __('Check/uncheck boxes to show/hide columns', 'woo-rede'),
            'Use ↑↓ or drag cards to reorder columns' => __('Use ↑↓ or drag cards to reorder columns', 'woo-rede'),
            'Settings are saved automatically' => __('Settings are saved automatically', 'woo-rede'),
            'Rede Transactions' => __('Rede Transactions', 'woo-rede'),
            'Configure column order and visibility' => __('Configure column order and visibility', 'woo-rede'),
            'Configure Columns' => __('Configure Columns', 'woo-rede'),
            'Export data in CSV format' => __('Export data in CSV format', 'woo-rede'),
            'Export CSV' => __('Export CSV', 'woo-rede'),
            'Export data in Excel format' => __('Export data in Excel format', 'woo-rede'),
            'Export XLS' => __('Export XLS', 'woo-rede'),
            'Recent transactions:' => __('Recent transactions:', 'woo-rede'),
            'Load up to:' => __('Load up to:', 'woo-rede'),
            'Query Dates:' => __('Query Dates:', 'woo-rede'),
            'Start Date:' => __('Start Date:', 'woo-rede'),
            'End Date:' => __('End Date:', 'woo-rede'),
            'Filter' => __('Filter', 'woo-rede'),
            'Today' => __('Today', 'woo-rede'),
            'Last 7 days' => __('Last 7 days', 'woo-rede'),
            'Last 30 days' => __('Last 30 days', 'woo-rede'),
            'Custom' => __('Custom', 'woo-rede'),
            'Reset Default' => __('Reset Default', 'woo-rede'),
            'Items per page:' => __('Items per page:', 'woo-rede'),
            'Loading transactions...' => __('Loading transactions...', 'woo-rede'),
            'Error:' => __('Error:', 'woo-rede'),
            'Try again' => __('Try again', 'woo-rede'),
            'of total' => __('of total', 'woo-rede'),
            'transactions' => __('transactions', 'woo-rede'),
            'Load more transactions' => __('Load more transactions', 'woo-rede'),
        ));
    }

    /**
     * AJAX handler to get recent Rede orders with metadata.
     *
     * @since 1.0.0
     */
    public function ajax_get_recent_rede_orders()
    {
        // Verificar nonce se fornecido
        if (isset($_POST['nonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'lkn_rede_orders_nonce')) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
            return;
        }

        // Verificar se é um usuário com permissões adequadas
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permissões insuficientes'));
            return;
        }

        // Verificar se o cliente quer resposta em formato TOON
        $response_format = isset($_POST['response_format']) ? sanitize_text_field(wp_unslash($_POST['response_format'])) : 'json';

        // Parâmetros de consulta
        $page = isset($_POST['page']) ? max(1, (int) sanitize_text_field(wp_unslash($_POST['page']))) : 1;
        $query_limit = isset($_POST['query_limit']) ? max(1, min(1000, (int) sanitize_text_field(wp_unslash($_POST['query_limit'])))) : 50;
        $offset = ($page - 1) * $query_limit;

        // Parâmetros de filtros de data com validação rigorosa
        $start_date = '';
        $end_date = '';
        
        // Validar e sanitizar data de início
        if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
            $raw_start_date = sanitize_text_field(wp_unslash($_POST['start_date']));
            // Validar formato de data Y-m-d
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_start_date)) {
                $date_obj = \DateTime::createFromFormat('Y-m-d', $raw_start_date);
                if ($date_obj && $date_obj->format('Y-m-d') === $raw_start_date) {
                    $start_date = $raw_start_date;
                }
            }
        }
        
        // Validar e sanitizar data de fim  
        if (isset($_POST['end_date']) && !empty($_POST['end_date'])) {
            $raw_end_date = sanitize_text_field(wp_unslash($_POST['end_date']));
            // Validar formato de data Y-m-d
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_end_date)) {
                $date_obj = \DateTime::createFromFormat('Y-m-d', $raw_end_date);
                if ($date_obj && $date_obj->format('Y-m-d') === $raw_end_date) {
                    $end_date = $raw_end_date;
                }
            }
        }

        // Se não há filtros de data válidos especificados, usar o filtro padrão de "hoje"
        if (empty($start_date) && empty($end_date)) {
            $today = gmdate('Y-m-d');
            $start_date = $today;
            $end_date = $today;
        }

        try {
            global $wpdb;
            
            // Query base estática
            $base_sql = "SELECT o.id 
                        FROM {$wpdb->prefix}wc_orders o
                        INNER JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
                        WHERE om.meta_key = 'lkn_rede_transaction_data'
                          AND om.meta_value != ''";
            
            // Construir query baseado nas condições
            if (!empty($start_date) && !empty($end_date)) {
                // Ambas as datas
                $rede_order_ids = $wpdb->get_col($wpdb->prepare(
                    /* placeholder: %1$s: start date in Y-m-d H:i:s format, %2$s: end date in Y-m-d H:i:s format */
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "$base_sql AND o.date_created_gmt >= %s AND o.date_created_gmt <= %s ORDER BY o.date_created_gmt DESC, o.id DESC",
                    $start_date . ' 00:00:00',
                    $end_date . ' 23:59:59'
                ));
            } elseif (!empty($start_date)) {
                // Apenas data de início
                $rede_order_ids = $wpdb->get_col($wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "$base_sql AND o.date_created_gmt >= %s ORDER BY o.date_created_gmt DESC, o.id DESC",
                    $start_date . ' 00:00:00'
                ));
            } elseif (!empty($end_date)) {
                // Apenas data de fim
                $rede_order_ids = $wpdb->get_col($wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "$base_sql AND o.date_created_gmt <= %s ORDER BY o.date_created_gmt DESC, o.id DESC",
                    $end_date . ' 23:59:59'
                ));
            } else {
                // Nenhum filtro de data
                $rede_order_ids = $wpdb->get_col($wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "$base_sql ORDER BY o.date_created_gmt DESC, o.id DESC"
                ));
            }
            
            // Se não há pedidos Rede, retornar resultado vazio
            if (empty($rede_order_ids)) {
                $response_data = array(
                    'message' => 'Nenhuma transação Rede encontrada',
                    'orders' => array(),
                    'pagination' => array(
                        'page' => $page,
                        'query_limit' => $query_limit,
                        'total_count' => 0,
                        'total_pages' => 0,
                        'has_next' => false
                    )
                );
                
                if ($response_format === 'toon') {
                    $this->send_toon_response(true, $response_data);
                } else {
                    wp_send_json_success($response_data);
                }
                return;
            }
            
            // SEGUNDA ETAPA: Aplicar paginação aos pedidos Rede
            $total_rede_count = count($rede_order_ids);
            $paginated_order_ids = array_slice($rede_order_ids, $offset, $query_limit);
            
            // TERCEIRA ETAPA: Processar os pedidos paginados
            $orders_data = array();

            foreach ($paginated_order_ids as $order_id) {
                // Usar função nativa do WooCommerce para pegar o pedido
                $order = wc_get_order($order_id);
                
                if (!$order) {
                    continue; // Pular se o pedido não existe
                }
                
                // Buscar metadados específicos do Rede
                $transaction_data = $order->get_meta('lkn_rede_transaction_data');
                $data_format = $order->get_meta('lkn_rede_data_format') ?: 'json';
                
                // Decodificar dados baseado no formato
                if ($data_format === 'toon') {
                    $transactionData = LknIntegrationRedeForWoocommerceHelper::decodeToonData($transaction_data);
                } else {
                    $transactionData = json_decode($transaction_data, true);
                }
                
                // Se decodificou com sucesso, adicionar aos dados
                if ($transactionData && is_array($transactionData)) {
                    $orders_data[] = array(
                        'order_id' => (int) $order_id,
                        'data_format' => $data_format,
                        'transaction_data' => $transactionData
                    );
                }
            }

            $response_data = array(
                'message' => sprintf(
                    /* translators: %1$d is the page number, %2$d is the transactions found, %3$d is the total count */
                    'Página %d - %d transações Rede encontradas de %d total', 
                    $page, 
                    count($orders_data), 
                    $total_rede_count
                ),
                'orders' => $orders_data,
                'pagination' => array(
                    'page' => $page,
                    'query_limit' => $query_limit,
                    'total_count' => (int) $total_rede_count,
                    'total_pages' => ceil($total_rede_count / $query_limit),
                    'has_next' => ($page * $query_limit) < $total_rede_count
                )
            );

            // Enviar resposta no formato solicitado
            if ($response_format === 'toon') {
                $this->send_toon_response(true, $response_data);
            } else {
                wp_send_json_success($response_data);
            }

        } catch (Exception $e) {
            $error_data = array(
                'message' => 'Erro ao buscar pedidos: ' . $e->getMessage()
            );
            
            if ($response_format === 'toon') {
                $this->send_toon_response(false, $error_data);
            } else {
                wp_send_json_error($error_data);
            }
        }
    }

    /**
     * Verifica e corrige o ambiente dos pedidos Rede baseado nas credenciais
     * Executa apenas uma vez através da opção check_rede_orders
     */
    public function check_and_fix_rede_orders_environment() 
    {
        // Verificar se já foi executado
        if (get_option('check_rede_orders', false)) {
            return;
        }

        // Marcar como executado para não rodar novamente - SEMPRE salva independente de erros
        update_option('check_rede_orders', true);

        try {
            // Buscar pedidos dos últimos 40 dias com transações Rede
            $date_limit = gmdate('Y-m-d H:i:s', strtotime('-60 days'));
            
            global $wpdb;
            
            // Query simples: apenas pedidos com lkn_rede_transaction_data nos últimos 40 dias
            $rede_orders = $wpdb->get_results($wpdb->prepare(
                "SELECT o.id, om_toon.meta_value as transaction_data
                 FROM {$wpdb->prefix}wc_orders o
                 INNER JOIN {$wpdb->prefix}wc_orders_meta om_toon ON o.id = om_toon.order_id 
                     AND om_toon.meta_key = 'lkn_rede_transaction_data'
                 WHERE o.date_created_gmt >= %s 
                     AND om_toon.meta_value != ''
                 ORDER BY o.date_created_gmt DESC",
                $date_limit
            ));

            if (empty($rede_orders)) {
                return;
            }

            foreach ($rede_orders as $order_data) {
                try {
                    $order_id = $order_data->id;
                    
                    // Carregar pedido WooCommerce
                    $order = wc_get_order($order_id);
                    if (!$order) {
                        continue;
                    }

                    // Obter metadados do pedido
                    $payment_method = $order->get_payment_method();
                    $transaction_data = $order_data->transaction_data; // Já vem da query
                    $data_format = $order->get_meta('lkn_rede_data_format') ?: 'json';
                    
                    // Obter configurações do gateway
                    $gateway_settings = get_option('woocommerce_' . $payment_method . '_settings', array());
                    if (empty($gateway_settings)) {
                        continue;
                    }

                    // Obter credenciais atuais do gateway
                    $current_pv = isset($gateway_settings['pv']) ? $gateway_settings['pv'] : '';
                    $current_token = isset($gateway_settings['token']) ? $gateway_settings['token'] : '';
                    $current_environment = isset($gateway_settings['environment']) ? $gateway_settings['environment'] : 'sandbox';

                    // Mapear ambiente para formato correto
                    $environment = ($current_environment === 'production') ? 'Produção' : 'Sandbox';

                    if (empty($current_pv) || empty($current_token)) {
                        continue;
                    }

                    // Decodificar dados da transação
                    if ($data_format === 'toon') {
                        $decoded_data = LknIntegrationRedeForWoocommerceHelper::decodeToonData($transaction_data);
                    } else {
                        $decoded_data = json_decode($transaction_data, true);
                    }

                    if (empty($decoded_data) || !is_array($decoded_data)) {
                        continue;
                    }

                    // Extrair credenciais do TOON
                    $toon_pv_masked = '';
                    $toon_token_masked = '';
                    $toon_environment = '';

                    if (isset($decoded_data['credentials']['pv_masked'])) {
                        $toon_pv_masked = $decoded_data['credentials']['pv_masked'];
                    }
                    if (isset($decoded_data['credentials']['token_masked'])) {
                        $toon_token_masked = $decoded_data['credentials']['token_masked'];
                    }
                    if (isset($decoded_data['system']['environment'])) {
                        $toon_environment = $decoded_data['system']['environment'];
                    }

                    // Verificar se as credenciais batem
                    $pv_matches = $this->compare_masked_credential($current_pv, $toon_pv_masked);
                    $token_matches = $this->compare_masked_credential($current_token, $toon_token_masked);

                    // Se as credenciais batem mas o ambiente está errado
                    if ($pv_matches && $token_matches && $toon_environment !== $environment) {
                        // Corrigir ambiente no TOON
                        $decoded_data['system']['environment'] = $environment;

                        // Recodificar dados
                        if ($data_format === 'toon') {
                            $updated_transaction_data = LknIntegrationRedeForWoocommerceHelper::encodeToonData($decoded_data);
                        } else {
                            $updated_transaction_data = wp_json_encode($decoded_data);
                        }

                        // Atualizar metadado do pedido (order já carregado)
                        $order->update_meta_data('lkn_rede_transaction_data', $updated_transaction_data);
                        $order->save();
                    }

                } catch (Exception $e) {
                    // Silencioso - continua processando outros pedidos
                    continue;
                }
            }
        } catch (Exception $e) {
            // Silencioso - opção já foi salva, então não vai executar novamente
            return;
        }
    }

    /**
     * Compara credencial atual com versão mascarada do TOON
     * Verifica se os caracteres visíveis batem
     */
    private function compare_masked_credential($current, $masked) 
    {
        if (empty($current) || empty($masked)) {
            return false;
        }

        // Verificar se tem asteriscos
        if (strpos($masked, '*') === false) {
            return false;
        }

        // Contar asteriscos para determinar tipo
        $asterisk_count = substr_count($masked, '*');
        
        // Usar regex para extrair prefixo e sufixo
        if (preg_match('/^([^*]+)\*+([^*]+)$/', $masked, $matches)) {
            if (count($matches) === 3) {
                $prefix = $matches[1];
                $suffix = $matches[2];
                
                // Verificar se começa com prefixo E termina com sufixo
                $starts_with_prefix = (substr($current, 0, strlen($prefix)) === $prefix);
                $ends_with_suffix = (substr($current, -strlen($suffix)) === $suffix);
                
                return $starts_with_prefix && $ends_with_suffix;
            }
        }

        return false;
    }

    /**
     * Send AJAX response in TOON format
     *
     * @param bool $success
     * @param array $data
     */
    private function send_toon_response($success, $data)
    {
        // Preparar dados de resposta no formato similar ao wp_send_json
        $response = array(
            'success' => $success,
            'data' => $data
        );

        // Codificar em TOON
        $toon_response = LknIntegrationRedeForWoocommerceHelper::encodeToonData($response);

        // Definir headers apropriados
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
            header('X-Content-Type-Options: nosniff');
            header('X-Robots-Tag: noindex');
        }

        // Enviar resposta e encerrar
        // Data sanitized and encoded via sanitize Wordpress functions.
        echo $toon_response; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_die('', '', array('response' => null));
    }
}
