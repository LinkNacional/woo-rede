<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

use WC_Order;
use WP_Query;

final class LknIntegrationRedeForWoocommerceWcRede
{
    public const FILE = __FILE__;

    public const VERSION = '3.1.2';
    protected static $instance = null;

    public function __construct()
    {
        $this->upgrade();
    }

    public function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function getTemplatesPath()
    {
        return plugin_dir_path(__FILE__) . 'templates/';
    }

    public function addGateway($methods)
    {
        $wc_rede_credit_class = new LknIntegrationRedeForWoocommerceWcRedeCredit();
        $wc_rede_debit_class = new LknIntegrationRedeForWoocommerceWcRedeDebit();
        $wc_rede_pix_class = new LknIntegrationRedeForWoocommerceWcPixRede();
        $wc_maxipago_credit_class = new LknIntegrationRedeForWoocommerceWcMaxipagoCredit();
        $wc_maxipago_debit_class = new LknIntegrationRedeForWoocommerceWcMaxipagoDebit();
        $wc_google_pay_class = new LknIntegrationRedeForWoocommerceGooglePay();

        array_push($methods, $wc_rede_credit_class);
        array_push($methods, $wc_rede_debit_class);
        array_push($methods, $wc_rede_pix_class);
        array_push($methods, $wc_maxipago_credit_class);
        array_push($methods, $wc_maxipago_debit_class);
        array_push($methods, $wc_google_pay_class);

        return $methods;
    }

    private function upgrade(): void
    {
        if (is_admin()) {
            $version = get_option('wc_rede_version', '0');

            if (version_compare($version, self::VERSION, '<')) {
                if ($options = get_option('woocommerce_rede_settings')) {
                    $credit_options = array(
                        'enabled' => $options['enabled'],
                        'title' => __('Enable', 'woo-rede'),

                        'environment' => $options['environment'],
                        'token' => $options['token'],
                        'pv' => $options['pv'],

                        'soft_descriptor' => $options['soft_descriptor'],

                        'min_parcels_value' => $options['smallest_installment'],
                        'max_parcels_number' => $options['installments'],
                    );

                    update_option('woocommerce_rede_credit_settings', $credit_options);

                    delete_option('woocommerce_rede_settings');
                }

                update_option('wc_rede_version', self::VERSION);
            }
        }
    }

    public function woocommerceMissingNotice(): void
    {
        deactivate_plugins(plugin_basename(INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE));
        include_once __DIR__ . '/views/notices/html-notice-woocommerce-missing.php';
    }

    public function softDescriptorNotice(): void
    {
        $errorCredit = get_option('lknIntegrationRedeForWoocommerceSoftDescriptorErrorCredit');
        $errorDebit = get_option('lknIntegrationRedeForWoocommerceSoftDescriptorErrorDebit');
        if ($errorCredit == true || $errorDebit == true) {
            $message = '
                <div class="notice notice-error" id="lknIntegrationRedeForWoocommerceSoftDescriptorErrorDiv">
                    <p>'
                . __('There was an error in the transaction, disable the', 'woo-rede') . ' ' .
                wp_kses_post('<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout'))
                    . '" target="_blank">' . __('Payment Description.', 'woo-rede') . '</a>') .
                '</p>
                </div>
            ';

            echo wp_kses_post($message);
        }
    }
}
