<?php
/**
 * Teste puro (sem WordPress) do aviso de descontinuação da maxiPago.
 *
 * Cobre as regras do aviso: aparece SÓ quando a loja tem credenciais maxiPago
 * salvas, UMA única vez por requisição (mesmo com crédito e débito maxiPago
 * habilitados juntos), respeita a dispensa do lojista, exige `manage_options`
 * e o wp-admin, e o AJAX de dispensa grava a option apenas com permissão.
 *
 * Rodar:
 *   php tests/php/maxipago-deprecation-notice.test.php
 */

// ── Stubs mínimos para carregar a classe sem o WordPress ────────────────────
if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}
if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR', dirname(__DIR__, 2) . '/');
}
if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR_URL', 'http://exemplo.local/wp-content/plugins/woo-rede/');
}
if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_VERSION', '5.5.6');
}

$GLOBALS['lkn_opts'] = array();
$GLOBALS['lkn_is_admin'] = true;
$GLOBALS['lkn_can'] = true;
$GLOBALS['lkn_queued'] = array();
$GLOBALS['lkn_json'] = null;

/** Simula o wp_die() que encerra as respostas JSON no WordPress. */
final class LknMaxipagoTestDie extends Exception {}

if (! function_exists('is_admin')) {
    function is_admin() { return $GLOBALS['lkn_is_admin']; }
    function wp_doing_ajax() { return false; }
    function current_user_can($capability) { return $GLOBALS['lkn_can']; }
    function get_option($key, $default = false) {
        return array_key_exists($key, $GLOBALS['lkn_opts']) ? $GLOBALS['lkn_opts'][$key] : $default;
    }
    function update_option($key, $value) { $GLOBALS['lkn_opts'][$key] = $value; return true; }
    function apply_filters($tag, $value) { return $value; }
    function wp_create_nonce($action) { return 'nonce-' . $action; }
    function check_ajax_referer($action, $field = 'nonce') { return true; }
    function admin_url($path = '') { return 'http://exemplo.local/wp-admin/' . $path; }
    function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
    function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
    function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
    function esc_html_e($text, $domain = null) { echo esc_html($text); }
    function esc_attr_e($text, $domain = null) { echo esc_attr($text); }
    function __($text, $domain = null) { return $text; }
    function wp_enqueue_style($handle = '', ...$rest) { $GLOBALS['lkn_queued'][] = 'style:' . $handle; }
    function wp_enqueue_script($handle = '', ...$rest) { $GLOBALS['lkn_queued'][] = 'script:' . $handle; }
    function wp_send_json_success($data = null) {
        $GLOBALS['lkn_json'] = array('success' => true, 'data' => $data);
        throw new LknMaxipagoTestDie();
    }
    function wp_send_json_error($data = null, $code = null) {
        $GLOBALS['lkn_json'] = array('success' => false, 'data' => $data, 'code' => $code);
        throw new LknMaxipagoTestDie();
    }
}

require INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/LknIntegrationRedeForWoocommerceMaxipagoDeprecationNotice.php';

use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceMaxipagoDeprecationNotice as Notice;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok): void
{
    global $passed, $failed;

    if ($ok) {
        ++$passed;
        echo "OK    $label\n";
        return;
    }

    ++$failed;
    echo "FALHA $label\n";
}

/** Zera o guard de "um aviso por requisição" entre cenários. */
function resetNoticeGuard(): void
{
    $property = new ReflectionProperty(Notice::class, 'rendered');
    $property->setAccessible(true);
    $property->setValue(null, false);
}

function renderNotice(Notice $notice): string
{
    ob_start();
    $notice->maybe_render_notice();

    return (string) ob_get_clean();
}

function dismissNotice(Notice $notice): void
{
    try {
        $notice->dismiss_notice();
    } catch (LknMaxipagoTestDie $die) {
        // wp_send_json_* encerra a requisição no WordPress.
    }
}

function resetOptions(): void
{
    $GLOBALS['lkn_opts'] = array();
    $GLOBALS['lkn_is_admin'] = true;
    $GLOBALS['lkn_can'] = true;
    resetNoticeGuard();
}

echo "=== Quando o aviso aparece ===\n";

resetOptions();
$notice = new Notice();
check('loja sem maxiPago configurado => nao aparece', false === $notice->notice_would_show() && '' === renderNotice($notice));

resetOptions();
$GLOBALS['lkn_opts']['woocommerce_maxipago_credit_settings'] = array('enabled' => 'yes', 'merchant_id' => '123');
$notice = new Notice();
check('maxipago_credit habilitado => aparece', true === $notice->notice_would_show());

resetOptions();
$GLOBALS['lkn_opts']['woocommerce_maxipago_debit_settings'] = array('enabled' => 'yes', 'merchant_id' => '456');
$notice = new Notice();
check('maxipago_debit habilitado => aparece', true === $notice->notice_would_show());

resetOptions();
$GLOBALS['lkn_opts']['woocommerce_maxipago_credit_settings'] = array('enabled' => 'no', 'merchant_id' => '123');
$notice = new Notice();
check('gateway desligado com credenciais salvas => aparece', true === $notice->notice_would_show());

resetOptions();
$GLOBALS['lkn_opts']['woocommerce_maxipago_credit_settings'] = array('enabled' => 'no');
$notice = new Notice();
check('gateway desligado e sem credenciais => nao aparece', false === $notice->notice_would_show());

resetOptions();
$GLOBALS['lkn_opts']['woocommerce_maxipago_credit_settings'] = array('enabled' => 'yes');
$GLOBALS['lkn_opts']['lkn_rede_maxipago_deprecation_notice_dismissed'] = 'yes';
$notice = new Notice();
check('aviso ja dispensado pelo lojista => nao aparece', false === $notice->notice_would_show() && '' === renderNotice($notice));

resetOptions();
$GLOBALS['lkn_opts']['woocommerce_maxipago_credit_settings'] = array('enabled' => 'yes');
$GLOBALS['lkn_can'] = false;
$notice = new Notice();
check('usuario sem manage_options => nao aparece', false === $notice->notice_would_show());

resetOptions();
$GLOBALS['lkn_opts']['woocommerce_maxipago_credit_settings'] = array('enabled' => 'yes');
$GLOBALS['lkn_is_admin'] = false;
$notice = new Notice();
check('fora do wp-admin => nao aparece', false === $notice->notice_would_show());

echo "\n=== Conteudo e UM aviso só ===\n";

resetOptions();
$GLOBALS['lkn_opts']['woocommerce_maxipago_credit_settings'] = array('enabled' => 'yes', 'merchant_id' => '123');
$GLOBALS['lkn_opts']['woocommerce_maxipago_debit_settings'] = array('enabled' => 'yes', 'merchant_id' => '456');
$notice = new Notice();
$html = renderNotice($notice) . renderNotice($notice);

check('credito + debito maxiPago => UMA vez', 1 === substr_count($html, 'lkn-rede-notice--deprecation'));
check('usa o padrao de aviso do woo-better (gif + nome + selo)', false !== strpos($html, 'Includes/assets/WordpressAssets/icon-256x256.gif') && false !== strpos($html, 'lkn-rede-notice__badge') && false !== strpos($html, 'Integration Rede Itaú for WooCommerce'));
check('avisa que so os gateways Rede continuam', false !== strpos($html, 'October 15, 2026') && false !== strpos($html, 'Only the Rede gateways (credit, debit and PIX)'));
check('informa a data de remocao (23/11/2026)', false !== strpos($html, 'November 23, 2026'));
check('leva para as configuracoes dos gateways Rede', false !== strpos($html, 'page=wc-settings&amp;tab=checkout&amp;section=rede_credit'));
check('dismiss com data-action e nonce', false !== strpos($html, 'data-dismissible="lkn-rede-maxipago-deprecation"') && false !== strpos($html, 'data-action="lkn_rede_dismiss_maxipago_deprecation"') && false !== strpos($html, 'data-nonce="nonce-'));

echo "\n=== Assets e dispensa (AJAX) ===\n";

resetOptions();
$GLOBALS['lkn_opts']['woocommerce_maxipago_credit_settings'] = array('enabled' => 'yes', 'merchant_id' => '123');
$GLOBALS['lkn_queued'] = array();
$notice = new Notice();
$notice->enqueue_assets();
check('enfileira CSS e JS do aviso', 2 === count($GLOBALS['lkn_queued']));

resetOptions();
$GLOBALS['lkn_opts']['woocommerce_maxipago_credit_settings'] = array('enabled' => 'no');
$GLOBALS['lkn_queued'] = array();
$notice = new Notice();
$notice->enqueue_assets();
check('sem maxiPago configurado => nao enfileira nada', 0 === count($GLOBALS['lkn_queued']));

resetOptions();
$notice = new Notice();
$GLOBALS['lkn_json'] = null;
dismissNotice($notice);
check('dismiss grava a option de dispensa', 'yes' === ($GLOBALS['lkn_opts']['lkn_rede_maxipago_deprecation_notice_dismissed'] ?? null) && true === ($GLOBALS['lkn_json']['success'] ?? null));

resetOptions();
$GLOBALS['lkn_can'] = false;
$GLOBALS['lkn_json'] = null;
$notice = new Notice();
dismissNotice($notice);
check('dismiss sem manage_options => 403 e nada gravado', 403 === ($GLOBALS['lkn_json']['code'] ?? null) && ! isset($GLOBALS['lkn_opts']['lkn_rede_maxipago_deprecation_notice_dismissed']));

echo "\n----------------------------------------\n";
echo "Passou: $passed  |  Falhou: $failed\n";

exit($failed === 0 ? 0 : 1);
