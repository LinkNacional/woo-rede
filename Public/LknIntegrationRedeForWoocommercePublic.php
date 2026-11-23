<?php
namespace Lknwoo\IntegrationRedeForWoocommerce\PublicView;

use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceHelper;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://linknacional.com.br
 * @since      1.0.0
 *
 * @package    LknIntegrationRedeForWoocommerce
 * @subpackage LknIntegrationRedeForWoocommerce/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    LknIntegrationRedeForWoocommerce
 * @subpackage LknIntegrationRedeForWoocommerce/public
 * @author     Link Nacional <contato@linknacional.com>
 */
final class LknIntegrationRedeForWoocommercePublic {
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles(): void {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/lknIntegrationRedeForWoocommercePublic.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts(): void {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/lknIntegrationRedeForWoocommercePublic.js', array('jquery'), $this->version, false);

        // Enqueue shortcode checkout script only on checkout pages
        if (is_checkout() || has_shortcode(get_post()->post_content ?? '', 'woocommerce_checkout') || has_block('woocommerce/checkout')) {
            // Verifica e redefine configurações PRO se a licença não for válida
            LknIntegrationRedeForWoocommerceHelper::checkAndResetProConfigurations();
            
            $session = WC()->session;
            if ($session) {
                $session->set('lkn_installments_number_rede_credit', 1);
                $session->set('lkn_installments_number_rede_debit', 1);
                $session->set('lkn_card_type_rede_debit', 'credit');
            }
        }

        // Enqueue installment label script for WooCommerce Blocks
        if (has_block('woocommerce/checkout') && !wp_script_is('lkn-installment-label', 'enqueued') && !wp_script_is('lkn-installment-label', 'done')) {
            wp_enqueue_script('lkn-rede-installment-label', plugin_dir_url(__FILE__) . 'js/lkn-installment-label.js', array(), $this->version, true);
            
            // Configuração de tradução para o script de label de parcelamento
            wp_localize_script('lkn-rede-installment-label', 'lknInstallmentLabelTranslations', array(
                'payment' => __('Payment', 'woo-rede'),
                'installment' => __('Installment', 'woo-rede'),
                'loading' => __('Loading...', 'woo-rede'),
                'calculatingInstallments' => __('Calculating installments...', 'woo-rede'),
                'cashPayment' => __('Cash payment', 'woo-rede'),
                'noInterest' => __('no interest', 'woo-rede'),
                'noDiscount' => __('no discount', 'woo-rede'),
                'withDiscount' => __('% discount', 'woo-rede'),
                'withInterest' => __('% interest', 'woo-rede'),
                'fallbackInstallment' => __('Payment', 'woo-rede'),
                'loadingPrice' => __('Loading price...', 'woo-rede')
            ));
        }
    }
}
