<?php
/**
 * Aviso de remoção dos gateways maxiPago (a remoção já aconteceu).
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
            <strong><?php echo esc_html($plugin_name); ?></strong>
            <span class="lkn-rede-notice__badge"><?php esc_html_e('Removed', 'woo-rede'); ?></span>
        </p>
        <p>
            <?php esc_html_e('As previously announced, the MaxiPago gateways were removed from this plugin. Rede discontinued the MaxiPago payment platform on October 15, 2026.', 'woo-rede'); ?>
        </p>
        <p>
            <strong><?php esc_html_e('Your store now has the Rede gateways only (credit, debit, PIX and Google Pay).', 'woo-rede'); ?></strong>
            <?php esc_html_e('If your checkout was using MaxiPago, enable the matching Rede gateway so payments keep working.', 'woo-rede'); ?>
        </p>
        <p class="lkn-rede-notice__actions">
            <a class="button button-secondary" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Configure the Rede gateways', 'woo-rede'); ?></a>
        </p>
    </div>
    <button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.', 'woo-rede'); ?></span></button>
</div>
