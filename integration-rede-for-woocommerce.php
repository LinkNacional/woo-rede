<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://linknacional.com.br
 * @since             1.0.0
 * @package           LknIntegrationRedeForWoocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Integration Rede Itaú for WooCommerce — Payment PIX, Credit Card and Debit
 * Description:       Receba pagamentos por meio de cartões de crédito e débito, de diferentes bandeiras, usando a tecnologia de autenticação 3DS e recursos avançados de proteção contra fraudes.
 * Version:           5.4.11
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Link Nacional
 * Author URI:        https://linknacional.com.br/wordpress
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       woo-rede
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

require_once 'lkn-integration-rede-for-woocommerce-file.php';
