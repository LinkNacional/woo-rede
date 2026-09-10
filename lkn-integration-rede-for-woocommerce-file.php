<?php

use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerce;
use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceActivator;
use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceDeactivator;

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION', '5.4.11');
}

if (! defined('LKN_WC_REDE_WPP_NUMBER')) {
    define('LKN_WC_REDE_WPP_NUMBER', '551135223406');
}

if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE', __DIR__ . '/integration-rede-for-woocommerce.php');
}

if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR', plugin_dir_path(INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE));
}

if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_BASENAME')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_BASENAME', plugin_basename(INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE));
}

if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL', plugin_dir_url(INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE));
}

if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE_BASENAME')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_FILE_BASENAME', plugin_basename(__DIR__ . '/integration-rede-for-woocommerce.php'));
}

if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_BASE_FILE')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_BASE_FILE', __DIR__ . '/integration-rede-for-woocommerce.php');
}

if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_LINK_URL_API')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_LINK_URL_API', 'https://api.linknacional.com');
}

if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_RATE_CACHE_KEY')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_RATE_CACHE_KEY', 'lkn_currency_rates_cache');
}
/**
 * The code that runs during plugin activation.
 * This action is documented in includes/LknIntegrationRedeForWoocommerceActivator.php
 */
function integration_rede_for_woocommerce_activate(): void
{
    LknIntegrationRedeForWoocommerceActivator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/LknIntegrationRedeForWoocommerceDeactivator.php
 */
function integration_rede_for_woocommerce_deactivate(): void
{
    LknIntegrationRedeForWoocommerceDeactivator::deactivate();
}

register_activation_hook(__FILE__, 'integration_rede_for_woocommerce_activate');
register_deactivation_hook(__FILE__, 'integration_rede_for_woocommerce_deactivate');

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function integration_rede_for_woocommerce_run(): void
{
    $plugin = new LknIntegrationRedeForWoocommerce();
    $plugin->run();
}
integration_rede_for_woocommerce_run();
