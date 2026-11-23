<?php
/**
 * Teste puro (sem WordPress) do bloco de MCC (programas de monitoramento).
 *
 * Cobre: a tabela com os 8 MCCs do comunicado da Rede, o marcador que o layout
 * JS procura no campo PV, o <template> impresso só na tela de gateway que usa PV
 * e a presença do marcador nos 4 gateways do plugin gratuito (o `rede_pix` do
 * PRO é coberto pelo mesmo JS, mas o arquivo é de outro repositório).
 *
 * Rodar:
 *   php tests/php/mcc-info.test.php
 */

// ── Stubs mínimos para carregar a classe sem o WordPress ────────────────────
if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}
if (! defined('INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR')) {
    define('INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR', dirname(__DIR__, 2) . '/');
}

$GLOBALS['lkn_request'] = array();

if (! function_exists('is_admin')) {
    function is_admin() { return true; }
    function wp_doing_ajax() { return false; }
    function apply_filters($tag, $value) { return $value; }
    function sanitize_text_field($value) { return trim((string) $value); }
    function wp_unslash($value) { return $value; }
    function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
    function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
    function esc_html__($text, $domain = null) { return $text; }
    function esc_attr__($text, $domain = null) { return $text; }
    function __($text, $domain = null) { return $text; }
    function esc_html_e($text, $domain = null) { echo esc_html($text); }
    function esc_attr_e($text, $domain = null) { echo esc_attr($text); }
}

/** Simula $_GET da requisição atual. */
function setRequest(array $query): void
{
    $GLOBALS['lkn_request'] = $query;
    $_GET = $query;
}

require INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Includes/LknIntegrationRedeForWoocommerceMccInfo.php';

use Lknwoo\IntegrationRedeForWoocommerce\Includes\LknIntegrationRedeForWoocommerceMccInfo as MccInfo;

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

function renderTemplate(): string
{
    ob_start();
    (new MccInfo())->maybe_render_template();

    return (string) ob_get_clean();
}

echo "=== Tabela de MCCs ===\n";

$html = MccInfo::render_block();

check('bloco tem a classe lkn-rede-mcc', false !== strpos($html, 'class="lkn-rede-mcc"'));
check('explica que o problema nao e do plugin', false !== strpos($html, 'that is not a failure of this plugin'));
check('tem a tabela dentro de <details>', false !== strpos($html, '<details') && false !== strpos($html, 'lkn-rede-mcc__table'));
check('cabecalho MCC / Activity / Brands', false !== strpos($html, '>MCC<') && false !== strpos($html, '>Activity<') && false !== strpos($html, '>Brands<'));

$mccs = MccInfo::mccs();
check('sao 8 MCCs (comunicado da Rede)', 8 === count($mccs));
check('8 linhas na tabela', 8 === substr_count($html, '<tr><td>'));

$codigos = array_column($mccs, 0);
foreach (array('5122', '5912', '5993', '9406', '7994 / 5816', '4816', '6211', '6538') as $codigo) {
    check("MCC $codigo presente", in_array($codigo, $codigos, true) && false !== strpos($html, '<td>' . $codigo . '</td>'));
}

$bandeiras = implode(' | ', array_column($mccs, 2));
check('9406 = Mastercard, Amex (sem Visa)', 'Mastercard, Amex' === $mccs[3][2]);
check('6538 = Amex', 'Amex' === $mccs[7][2]);
check('7994 / 5816 = Mastercard, Visa', 'Mastercard, Visa' === $mccs[4][2]);
check('bandeiras montadas', '' !== $bandeiras);

echo "\n=== Marcador do campo PV ===\n";

check('marcador exposto como data-rede-mcc-info=1', array('data-rede-mcc-info' => '1') === MccInfo::field_custom_attributes());

echo "\n=== <template> so na tela de gateway que usa PV ===\n";

foreach (array('rede_credit', 'rede_debit', 'integration_rede_pix', 'rede_google_pay', 'rede_pix') as $section) {
    setRequest(array('page' => 'wc-settings', 'tab' => 'checkout', 'section' => $section));
    $out = renderTemplate();
    check("$section => template impresso uma vez", 1 === substr_count($out, '<template id="lkn-rede-mcc-info">') && false !== strpos($out, '<div class="lkn-rede-mcc">'));
}

foreach (array('cod', 'bacs', 'cheque', '') as $section) {
    setRequest(array('page' => 'wc-settings', 'tab' => 'checkout', 'section' => $section));
    check("'" . $section . "' => sem template", '' === renderTemplate());
}

setRequest(array('page' => 'wc-settings', 'tab' => 'general'));
check('outra aba de settings => sem template', '' === renderTemplate());

echo "\n=== Marcador presente nos gateways ===\n";

$files = array(
    'rede_credit' => 'Includes/LknIntegrationRedeForWoocommerceWcRedeCredit.php',
    'rede_debit' => 'Includes/LknIntegrationRedeForWoocommerceWcRedeDebit.php',
    'integration_rede_pix' => 'Includes/LknIntegrationRedeForWoocommerceWcPixRede.php',
    'rede_google_pay' => 'Includes/LknIntegrationRedeForWoocommerceGooglePay.php',
);

foreach ($files as $gateway => $file) {
    $source = (string) file_get_contents(INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . $file);
    check(
        "$gateway marca o campo pv com data-rede-mcc-info",
        false !== strpos($source, "'data-rede-mcc-info' => '1'")
    );
}

// O PRO (`rede_pix`) é outro repositório: conferimos quando ele está ao lado.
$proFile = rtrim(INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR, '/') . '/../rede-for-woocommerce-pro/Includes/LknRedeForWoocommerceProPixRede.php';
if (file_exists($proFile)) {
    $proSource = (string) file_get_contents($proFile);
    check("PRO rede_pix marca o campo pv com data-rede-mcc-info", false !== strpos($proSource, "'data-rede-mcc-info' => '1'"));
}

echo "\n=== Layout JS clona o template abaixo da descricao ===\n";

$js = (string) file_get_contents(INTEGRATION_REDE_FOR_WOOCOMMERCE_DIR . 'Admin/js/lkn-integration-rede-for-woocommerce-settings-layout.js');
check('JS procura o marcador', false !== strpos($js, "hasAttribute('data-rede-mcc-info')"));
check('JS clona a partir do template', false !== strpos($js, "getElementById('lkn-rede-mcc-info')"));
check('JS insere depois do p.description', false !== strpos($js, "insertAdjacentElement('afterend', mccBlock)"));
check('JS nao duplica o bloco', false !== strpos($js, "divBody.querySelector('.lkn-rede-mcc')"));

echo "\n----------------------------------------\n";
echo "Passou: $passed  |  Falhou: $failed\n";

exit($failed === 0 ? 0 : 1);
