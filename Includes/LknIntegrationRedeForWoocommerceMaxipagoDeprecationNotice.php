<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Aviso de descontinuação dos gateways maxiPago.
 *
 * A Rede encerrou a plataforma maxiPago em 15/10/2026 e só os gateways Rede
 * (crédito, débito e PIX) seguem com suporte. Os gateways maxiPago do plugin
 * serão removidos em 23/11/2026 — este aviso existe para ninguém ser pego de
 * surpresa: aparece ANTES da remoção, é dispensável e é UM só, mesmo com
 * crédito e débito maxiPago habilitados ao mesmo tempo.
 *
 * Layout segue o padrão de avisos do woo-better / shipping-simulator
 * (ícone + nome do plugin + selo + botão de ação).
 */
final class LknIntegrationRedeForWoocommerceMaxipagoDeprecationNotice
{
    /** Identificador do aviso (data-dismissible) — isola o JS de cada aviso. */
    public const NOTICE_ID = 'lkn-rede-maxipago-deprecation';

    /** Ação AJAX que dispensa o aviso definitivamente. */
    public const AJAX_DISMISS = 'lkn_rede_dismiss_maxipago_deprecation';

    /** Ação do nonce de dispensa. */
    private const NONCE_DISMISS = 'lkn_rede_dismiss_maxipago_deprecation_nonce';

    /** Opção que marca o aviso como dispensado. */
    private const OPTION_DISMISSED = 'lkn_rede_maxipago_deprecation_notice_dismissed';

    /** Gateways maxiPago existentes no plugin gratuito. */
    private const MAXIPAGO_GATEWAYS = array('maxipago_credit', 'maxipago_debit');

    /**
     * Garante UM único aviso por requisição. Sem isso, cada hook registrado (ou
     * um gateway extra no futuro) poderia renderizar o mesmo aviso novamente.
     *
     * @var bool
     */
    private static $rendered = false;

    /**
     * Enfileira o CSS/JS do aviso somente quando ele realmente vai aparecer.
     */
    public function enqueue_assets(): void
    {
        if (! $this->notice_would_show()) {
            return;
        }

        wp_enqueue_style(
            'lkn-rede-notices',
            INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Admin/css/lkn-integration-rede-for-woocommerce-notices.css',
            array(),
            INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION
        );

        wp_enqueue_script(
            'lkn-rede-notices',
            INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Admin/js/lkn-integration-rede-for-woocommerce-notices.js',
            array(),
            INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION,
            true
        );
    }

    /**
     * Renderiza o aviso (uma única vez) no wp-admin.
     */
    public function maybe_render_notice(): void
    {
        if (! $this->notice_would_show()) {
            return;
        }

        self::$rendered = true;

        $notice_id    = self::NOTICE_ID;
        $action       = self::AJAX_DISMISS;
        $nonce        = wp_create_nonce(self::NONCE_DISMISS);
        $plugin_name  = __('Integration Rede Itaú for WooCommerce', 'woo-rede');
        $icon_url     = INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL . 'Includes/assets/WordpressAssets/icon-256x256.gif';
        $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=rede_credit');

        require INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/views/notices/lkn-integration-rede-for-woocommerce-notice-maxipago-deprecation.php';
    }

    /**
     * AJAX: dispensa o aviso definitivamente.
     */
    public function dismiss_notice(): void
    {
        check_ajax_referer(self::NONCE_DISMISS, 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(
                array('message' => __('You do not have permission to dismiss this notice.', 'woo-rede')),
                403
            );
        }

        update_option(self::OPTION_DISMISSED, 'yes');

        wp_send_json_success();
    }

    /**
     * O aviso deve aparecer nesta requisição?
     *
     * Regras: só no admin, só para quem administra a loja, só uma vez por
     * requisição, nunca depois de dispensado e só quando existem credenciais
     * maxiPago salvas (loja que realmente usa o gateway).
     */
    public function notice_would_show(): bool
    {
        if (! is_admin() || wp_doing_ajax()) {
            return false;
        }

        if (self::$rendered) {
            return false;
        }

        if (! current_user_can('manage_options')) {
            return false;
        }

        if ('yes' === get_option(self::OPTION_DISMISSED, 'no')) {
            return false;
        }

        $show = $this->has_maxipago_credentials();

        /**
         * Permite esconder/exibir o aviso de descontinuação da maxiPago.
         *
         * @param bool $show O aviso deve ser exibido?
         */
        return (bool) apply_filters('integration_rede_for_woocommerce_show_maxipago_deprecation_notice', $show);
    }

    /**
     * A loja tem credenciais maxiPago salvas (gateway habilitado ou apenas
     * configurado)? Lê direto das options para não depender da instância do
     * gateway — o aviso precisa funcionar mesmo com o gateway desligado.
     */
    private function has_maxipago_credentials(): bool
    {
        foreach (self::MAXIPAGO_GATEWAYS as $gateway_id) {
            $settings = get_option('woocommerce_' . $gateway_id . '_settings', array());

            if (! is_array($settings) || array() === $settings) {
                continue;
            }

            if (isset($settings['enabled']) && 'yes' === $settings['enabled']) {
                return true;
            }

            $merchant_id = isset($settings['merchant_id']) ? trim((string) $settings['merchant_id']) : '';

            if ('' !== $merchant_id) {
                return true;
            }
        }

        return false;
    }
}
