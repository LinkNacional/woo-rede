<?php
/**
 * Aviso de remoção do gateway maxiPago (a remoção já aconteceu).
 *
 * Tom informativo e formal: comunica o que mudou, destaca o que continua
 * funcionando e orienta a migração sem alarmar quem nunca usou o maxiPago.
 *
 * @var string $notice_id    Identificador do aviso (data-dismissible).
 * @var string $action      Ação AJAX de dispensa.
 * @var string $nonce       Nonce de dispensa.
 * @var string $plugin_name Nome do plugin exibido no aviso.
 * @var string $icon_url    URL do ícone (gif) do plugin.
 * @var string $settings_url URL das configurações dos gateways Rede.
 */

if (! defined('ABSPATH')) {
    exit;
}

$removal_text = sprintf(
    /* translators: %s: the gateway name MaxiPago, shown in bold. */
    __('The %s gateway is no longer available in this plugin. Rede ended this payment service on October 15, 2026.', 'woo-rede'),
    '<strong>MaxiPago</strong>'
);

$working_text = sprintf(
    /* translators: %s: the words "Rede payments", shown in bold. */
    __('%s by credit card, debit card, PIX and Google Pay keep working normally.', 'woo-rede'),
    '<strong>' . esc_html__('Rede payments', 'woo-rede') . '</strong>'
);
?>
<div class="notice notice-warning is-dismissible lkn-rede-notice lkn-rede-notice--removed"
    data-dismissible="<?php echo esc_attr($notice_id); ?>"
    data-action="<?php echo esc_attr($action); ?>"
    data-nonce="<?php echo esc_attr($nonce); ?>">
    <div class="lkn-rede-notice__icon">
        <img src="<?php echo esc_url($icon_url); ?>" alt="<?php echo esc_attr($plugin_name); ?>">
    </div>
    <div class="lkn-rede-notice__content">
        <p class="lkn-rede-notice__title">
            <strong><?php echo esc_html($plugin_name); ?> - <?php esc_html_e('MaxiPago gateway', 'woo-rede'); ?></strong>
            <span class="lkn-rede-notice__badge"><?php esc_html_e('Removed', 'woo-rede'); ?></span>
        </p>
        <p>
            <?php echo wp_kses($removal_text, array('strong' => array())); ?>
        </p>
        <p>
            <?php echo wp_kses($working_text, array('strong' => array())); ?>
        </p>
        <p>
            <?php esc_html_e('If you use the MaxiPago payment gateway, we ask that you migrate to the Rede gateways.', 'woo-rede'); ?>
        </p>
        <p class="lkn-rede-notice__actions">
            <a class="button button-secondary" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Go to payment settings', 'woo-rede'); ?></a>
        </p>
    </div>
    <button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.', 'woo-rede'); ?></span></button>
</div>
