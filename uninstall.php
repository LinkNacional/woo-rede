<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://linknacional.com.br
 * @since      1.0.0
 *
 * @package    LknIntegrationRedeForWoocommerce
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('woocommerce_rede_credit_settings');
delete_option('woocommerce_rede_debit_settings');
delete_option('woocommerce_maxipago_credit_settings');
delete_option('woocommerce_maxipago_debit_settings');

// Metadados das notificações de atualização do plugin PRO (aviso, tela e e-mail).
// Removê-los na desinstalação permite reinstalar e testar o fluxo do zero, sem
// herdar as flags de "tela já exibida", "aviso dispensado" ou "e-mail já enviado".
delete_option('lkn_rede_pro_update_screen_shown');
delete_option('lkn_rede_pro_update_notice_dismissed');
delete_option('lkn_rede_pro_update_email_sent');