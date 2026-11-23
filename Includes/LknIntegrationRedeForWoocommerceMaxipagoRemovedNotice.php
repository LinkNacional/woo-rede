<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Aviso de REMOÇÃO dos gateways maxiPago.
 *
 * A Rede encerrou a plataforma maxiPago em 15/10/2026. O aviso anterior (de
 * descontinuação) saiu do plugin junto com os gateways na 5.6.0; este é o
 * sucessor: informa que a remoção JÁ aconteceu, para o lojista que ainda não
 * tinha migrado entender o motivo de o gateway ter desaparecido do checkout.
 *
 * Segue o mesmo layout do aviso anterior (ícone + nome do plugin + selo +
 * botão de ação) e é dispensável. Aparece para TODOS os administradores da
 * loja, independentemente de a loja ter ou não usado a maxiPago: o aviso é
 * institucional (comunicado de descontinuidade), não um diagnóstico da loja.
 * Usa option/ação próprias: quem já tinha dispensado o aviso de
 * descontinuação PRECISA ver este.
 *
 * Só existe no plugin gratuito — quem usa o PRO tem o FREE instalado também,
 * então não há aviso duplicado.
 */
final class LknIntegrationRedeForWoocommerceMaxipagoRemovedNotice
{
    /** Identificador do aviso (data-dismissible) — isola o JS de cada aviso. */
    public const NOTICE_ID = 'lkn-rede-maxipago-removed';

    /** Ação AJAX que dispensa o aviso definitivamente. */
    public const AJAX_DISMISS = 'lkn_rede_dismiss_maxipago_removed';

    /** Ação do nonce de dispensa. */
    private const NONCE_DISMISS = 'lkn_rede_dismiss_maxipago_removed_nonce';

    /** Opção que marca o aviso como dispensado. */
    private const OPTION_DISMISSED = 'lkn_rede_maxipago_removed_notice_dismissed';

    /**
     * Garante UM único aviso por requisição.
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
        $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout');

        require INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/views/notices/lkn-integration-rede-for-woocommerce-notice-maxipago-removed.php';
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
     * requisição e nunca depois de dispensado. Não há checagem de uso da
     * maxiPago: o aviso é um comunicado de descontinuidade para toda a base.
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

        /**
         * Permite esconder o aviso de remoção da maxiPago.
         *
         * @param bool $show O aviso deve ser exibido?
         */
        return (bool) apply_filters('integration_rede_for_woocommerce_show_maxipago_removed_notice', true);
    }

}
