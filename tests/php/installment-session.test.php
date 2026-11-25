<?php
/**
 * Feature test (sem WordPress) do endpoint AJAX que grava a parcela escolhida
 * na sessão — `ajax_update_installment_session`.
 *
 * Contexto (issue #367): no gateway "Rede Débito e Crédito 3DS" o script do
 * checkout morria com ReferenceError antes de registrar o listener do select de
 * parcelas. Com isso a action nunca era chamada e a loja cobrava sempre o juro
 * de 1x. O PHP estava correto; o contrato é quebrado do lado do JS.
 *
 * Este teste trava os dois lados do contrato:
 *   1. o handler grava a parcela e o tipo de cartão na sessão do WooCommerce;
 *   2. o handler rejeita nonce inválido, método não suportado e parcela < 1;
 *   3. o wooRedeDebit.js envia exatamente a action e os campos que o handler lê.
 *
 * Rodar:
 *   php tests/php/installment-session.test.php
 */

// ── Stubs mínimos para carregar a classe sem o WordPress ────────────────────
if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (! function_exists('__')) {
    function __($text, $domain = null)
    {
        return $text;
    }
}
if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}
if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        return is_string($value) ? trim(strip_tags($value)) : $value;
    }
}
if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action)
    {
        return $nonce === 'nonce-' . $action ? 1 : false;
    }
}

/**
 * Interrompe a execução como wp_send_json_* e devolve o payload ao teste.
 */
class LknTestJsonResponse extends Exception
{
    public $success;
    public $payload;

    public function __construct($success, $payload)
    {
        $this->success = $success;
        $this->payload = $payload;
        parent::__construct('json response');
    }
}

if (! function_exists('wp_send_json_error')) {
    function wp_send_json_error($payload = null, $status = null)
    {
        throw new LknTestJsonResponse(false, $payload);
    }
}
if (! function_exists('wp_send_json_success')) {
    function wp_send_json_success($payload = null, $status = null)
    {
        throw new LknTestJsonResponse(true, $payload);
    }
}

/**
 * Sessão fake do WooCommerce.
 */
class LknTestSession
{
    public $data = array();

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }
}

class LknTestWooCommerce
{
    public $session;

    public function __construct()
    {
        $this->session = new LknTestSession();
    }
}

if (! function_exists('WC')) {
    function WC()
    {
        static $wc = null;
        if ($wc === null) {
            $wc = new LknTestWooCommerce();
        }
        return $wc;
    }
}

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../Includes/LknIntegrationRedeForWoocommerce.php';

$CLASS = 'Lknwoo\\IntegrationRedeForWoocommerce\\Includes\\LknIntegrationRedeForWoocommerce';

$passed = 0;
$failed = 0;

function check($description, $actual, $expected)
{
    global $passed, $failed;

    if ($actual === $expected) {
        $passed++;
        echo "  ok   {$description}\n";
        return;
    }

    $failed++;
    echo "  FAIL {$description}\n";
    echo '       esperado: ' . var_export($expected, true) . "\n";
    echo '       obtido:   ' . var_export($actual, true) . "\n";
}

/**
 * Executa o handler com o $_POST informado e devolve a resposta capturada.
 */
function call_handler($CLASS, array $post)
{
    $_POST = $post;

    $reflection = new ReflectionClass($CLASS);
    $instance = $reflection->newInstanceWithoutConstructor();

    try {
        $instance->ajax_update_installment_session();
    } catch (LknTestJsonResponse $response) {
        return $response;
    }

    return null;
}

function reset_session()
{
    WC()->session->data = array();
}

$reflection = new ReflectionClass($CLASS);
$instance = $reflection->newInstanceWithoutConstructor();

// ── 1) Débito: grava parcela e tipo de cartão ──────────────────────────────
echo "\n[1] rede_debit grava parcela e tipo de cartão\n";
reset_session();
$response = call_handler($CLASS, array(
    'payment_method' => 'rede_debit',
    'installments' => '12',
    'card_type' => 'credit',
    'nonce' => 'nonce-rede_debit_payment_fields_nonce',
));
check('responde com sucesso', $response && $response->success, true);
check('parcela gravada na sessão', WC()->session->get('lkn_installments_number_rede_debit'), 12);
check('tipo de cartão gravado na sessão', WC()->session->get('lkn_card_type_rede_debit'), 'credit');
check('devolve a parcela', $response->payload['installments'], 12);

// ── 2) Débito: nonce inválido não altera a sessão ──────────────────────────
echo "\n[2] nonce inválido\n";
reset_session();
$response = call_handler($CLASS, array(
    'payment_method' => 'rede_debit',
    'installments' => '6',
    'nonce' => 'nonce-errado',
));
check('responde com erro', $response && $response->success, false);
check('mensagem: Invalid nonce', $response->payload['message'], 'Invalid nonce');
check('sessão continua vazia', WC()->session->get('lkn_installments_number_rede_debit'), null);

// ── 3) Método não suportado ────────────────────────────────────────────────
echo "\n[3] método não suportado\n";
reset_session();
$response = call_handler($CLASS, array(
    'payment_method' => 'boleto',
    'installments' => '3',
    'nonce' => 'nonce-qualquer',
));
check('responde com erro', $response && $response->success, false);
check('mensagem: Unsupported payment method', $response->payload['message'], 'Unsupported payment method');

// ── 4) Crédito: usa a própria chave de sessão ──────────────────────────────
echo "\n[4] rede_credit\n";
reset_session();
$response = call_handler($CLASS, array(
    'payment_method' => 'rede_credit',
    'installments' => '3',
    'nonce' => 'nonce-rede_payment_fields_nonce',
));
check('responde com sucesso', $response && $response->success, true);
check('parcela de crédito na sessão', WC()->session->get('lkn_installments_number_rede_credit'), 3);
check('não escreve na chave do débito', WC()->session->get('lkn_installments_number_rede_debit'), null);

// ── 5) Parcela inválida ────────────────────────────────────────────────────
echo "\n[5] parcela inválida\n";
reset_session();
$response = call_handler($CLASS, array(
    'payment_method' => 'rede_debit',
    'installments' => '0',
    'nonce' => 'nonce-rede_debit_payment_fields_nonce',
));
check('responde com erro', $response && $response->success, false);
check('mensagem: Invalid parameters', $response->payload['message'], 'Invalid parameters');

// ── 6) Contrato com o script do checkout ───────────────────────────────────
echo "\n[6] contrato wooRedeDebit.js <-> handler\n";
$js = file_get_contents(__DIR__ . '/../../Public/js/debitCard/rede/wooRedeDebit.js');

check('JS usa a action registrada', strpos($js, "action: 'lkn_update_installment_session'") !== false, true);
check('JS envia payment_method', strpos($js, 'payment_method: paymentMethod') !== false, true);
check('JS envia installments', strpos($js, 'installments: installments') !== false, true);
check('JS envia card_type', strpos($js, 'requestData.card_type = cardType') !== false, true);
check('JS usa o nonce do wooRedeDebit', strpos($js, 'window.wooRedeDebit.nonce') !== false, true);

echo "\n----------------------------------------\n";
echo "  passed: {$passed}   failed: {$failed}\n";
echo "----------------------------------------\n\n";

exit($failed === 0 ? 0 : 1);
