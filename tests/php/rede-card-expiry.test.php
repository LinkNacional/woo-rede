<?php
/**
 * Teste puro (sem WordPress) da validação de validade do cartão.
 *
 * Cobre o bug do gateway de débito (rede_debit): o formatter do checkout envia
 * sempre "MM/AA" sem espaços, mas a normalização legada só expandia o ano quando
 * encontrava "/ " (barra + espaço) e depois entregava a string ao strtotime, que
 * lia "05/30" como 30 de maio do ano corrente (vencido) em vez de maio/2030.
 *
 * Aqui a lógica real de `validate_expiration_date()` dos gateways é exercitada
 * via reflection, com stubs mínimos no lugar do WordPress. Também confirma que o
 * fluxo de crédito NÃO foi alterado (continua usando o método legado do abstract).
 *
 * Rodar:
 *   php tests/php/rede-card-expiry.test.php
 */

// ── Stubs mínimos para carregar as classes sem o WordPress ──────────────────
if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}
if (! class_exists('WC_Payment_Gateway')) {
    class WC_Payment_Gateway {}
}
if (! function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = null)
    {
        return $text;
    }
}

require __DIR__ . '/../../vendor/autoload.php';

$HELPER  = 'Lknwoo\\IntegrationRedeForWoocommerce\\Includes\\LknIntegrationRedeForWoocommerceHelper';
$ABSTRACT = 'Lknwoo\\IntegrationRedeForWoocommerce\\Includes\\LknIntegrationRedeForWoocommerceWcRedeAbstract';
$CREDIT  = 'Lknwoo\\IntegrationRedeForWoocommerce\\Includes\\LknIntegrationRedeForWoocommerceWcRedeCredit';
$DEBIT   = 'Lknwoo\\IntegrationRedeForWoocommerce\\Includes\\LknIntegrationRedeForWoocommerceWcRedeDebit';

$MSG_FUTURE = 'Card expiration date must be future.';
$MSG_FORMAT = 'Expiration date must contain 2 or 4 digits';

$passed = 0;
$failed = 0;

/**
 * Executa um caso e contabiliza o resultado.
 */
function check($description, $actual, $expected)
{
    global $passed, $failed;

    if ($actual === $expected) {
        $passed++;
        echo "  ok   - $description\n";
        return;
    }

    $failed++;
    echo "  FAIL - $description\n";
    echo "         esperado: " . var_export($expected, true) . "\n";
    echo "         obtido:   " . var_export($actual, true) . "\n";
}

/**
 * Invoca o método protegido validate_expiration_date() de um gateway sem
 * instanciar o construtor (que depende do WordPress/WooCommerce).
 *
 * @return string 'OK' quando passa, ou a mensagem da Exception lançada.
 */
function evalGatewayExpiry($className, $expiry)
{
    $ref = new ReflectionClass($className);
    $obj = $ref->newInstanceWithoutConstructor();
    $method = $ref->getMethod('validate_expiration_date');
    $method->setAccessible(true);

    try {
        $method->invoke($obj, $expiry);
        return 'OK';
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

// Datas de referência relativas ao "agora" (UTC), para o teste não envelhecer.
$future2   = gmdate('m/y', strtotime('+2 years'));
$future4   = gmdate('m/Y', strtotime('+2 years'));
$past2     = gmdate('m/y', strtotime('-2 years'));
$past4     = gmdate('m/Y', strtotime('-2 years'));
$current   = gmdate('m/y');
$futureMon = substr($future2, 0, 2);
$futureYr  = substr($future2, 3, 2);

// Mês imediatamente anterior (fronteira do off-by-one), calculado por inteiros
// para independer de fuso horário.
$curYear    = (int) gmdate('Y');
$curMonth   = (int) gmdate('n');
$prevYear   = $curMonth === 1 ? $curYear - 1 : $curYear;
$prevMonth  = $curMonth === 1 ? 12 : $curMonth - 1;
$prevExpiry = sprintf('%02d/%02d', $prevMonth, $prevYear % 100);

echo "\n=== Helper::evaluateCardExpiration() — lógica pura ===\n";
check("futuro 2 dígitos ($future2) => VALID",   call_user_func([$HELPER, 'evaluateCardExpiration'], $future2), 'valid');
check("futuro 4 dígitos ($future4) => VALID",   call_user_func([$HELPER, 'evaluateCardExpiration'], $future4), 'valid');
check("mês corrente ($current) => VALID",        call_user_func([$HELPER, 'evaluateCardExpiration'], $current), 'valid');
check("mês anterior ($prevExpiry) => EXPIRED",   call_user_func([$HELPER, 'evaluateCardExpiration'], $prevExpiry), 'expired');
check("passado 2 dígitos ($past2) => EXPIRED",   call_user_func([$HELPER, 'evaluateCardExpiration'], $past2), 'expired');
check("passado 4 dígitos ($past4) => EXPIRED",   call_user_func([$HELPER, 'evaluateCardExpiration'], $past4), 'expired');
check("com espaços ($futureMon / $futureYr) => VALID", call_user_func([$HELPER, 'evaluateCardExpiration'], "$futureMon / $futureYr"), 'valid');
check("mês inválido 13/xx => INVALID",           call_user_func([$HELPER, 'evaluateCardExpiration'], '13/' . substr($future2, 3, 2)), 'invalid');
check("mês inválido 00/xx => INVALID",           call_user_func([$HELPER, 'evaluateCardExpiration'], '00/' . substr($future2, 3, 2)), 'invalid');
check("formato 'abc' => INVALID",                call_user_func([$HELPER, 'evaluateCardExpiration'], 'abc'), 'invalid');
check("formato '1230' => INVALID",               call_user_func([$HELPER, 'evaluateCardExpiration'], '1230'), 'invalid');
check("formato '12/' => INVALID",                call_user_func([$HELPER, 'evaluateCardExpiration'], '12/'), 'invalid');

echo "\n=== Gateway débito (rede_debit) — mensagens lançadas ===\n";
check("futuro 2 dígitos ($future2) => aceita",   evalGatewayExpiry($DEBIT, $future2), 'OK');
check("futuro 4 dígitos ($future4) => aceita",   evalGatewayExpiry($DEBIT, $future4), 'OK');
check("mês corrente ($current) => aceita",        evalGatewayExpiry($DEBIT, $current), 'OK');
check("mês anterior ($prevExpiry) => vencido",    evalGatewayExpiry($DEBIT, $prevExpiry), $MSG_FUTURE);
check("com espaços ('$futureMon / $futureYr') => aceita", evalGatewayExpiry($DEBIT, "$futureMon / $futureYr"), 'OK');
check("passado 2 dígitos ($past2) => vencido",   evalGatewayExpiry($DEBIT, $past2), $MSG_FUTURE);
check("passado 4 dígitos ($past4) => vencido",   evalGatewayExpiry($DEBIT, $past4), $MSG_FUTURE);
check("mês inválido (13/xx) => formato",         evalGatewayExpiry($DEBIT, '13/' . substr($future2, 3, 2)), $MSG_FORMAT);
check("formato inválido ('1/2') => formato",     evalGatewayExpiry($DEBIT, '1/2'), $MSG_FORMAT);
check("formato inválido ('05-45') => formato",   evalGatewayExpiry($DEBIT, '05-45'), $MSG_FORMAT);

// Casos do relatório (01/10/2026). Só rodam enquanto ainda forem futuros, para
// o teste não envelhecer: 05/30 (mai/2030), 12/35 (dez/2035) e 12/30 (dez/2030).
if ((int) gmdate('Ym') <= 203012) {
    echo "\n=== Casos do relatório (valores futuros) ===\n";
    check("débito aceita 05/30 (mai/2030)", evalGatewayExpiry($DEBIT, '05/30'), 'OK');
    check("débito aceita 12/30 (dez/2030)", evalGatewayExpiry($DEBIT, '12/30'), 'OK');
    check("débito aceita 12/35 (dez/2035)", evalGatewayExpiry($DEBIT, '12/35'), 'OK');
    check("débito aceita 05/2030 (4 dígitos)", evalGatewayExpiry($DEBIT, '05/2030'), 'OK');
}

echo "\n=== Escopo: crédito permanece no método legado do abstract ===\n";
$abstractMethod = new ReflectionMethod($ABSTRACT, 'validate_expiration_date');
$debitMethod    = new ReflectionMethod($DEBIT, 'validate_expiration_date');
$creditMethod   = new ReflectionMethod($CREDIT, 'validate_expiration_date');

check(
    'Credit NÃO sobrepõe (declarado no abstract)',
    $creditMethod->getDeclaringClass()->getName(),
    $ABSTRACT
);
check(
    'Debit USA o override próprio',
    $debitMethod->getDeclaringClass()->getName(),
    $DEBIT
);
check('O abstract mantém a implementação legada', $abstractMethod->getDeclaringClass()->getName(), $ABSTRACT);

echo "\n----------------------------------------\n";
echo "Passou: $passed  |  Falhou: $failed\n";

exit($failed === 0 ? 0 : 1);
