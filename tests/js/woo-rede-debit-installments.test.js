'use strict'
/**
 * Teste de regressão (Node puro, sem dependências) do gateway "Rede Débito e
 * Crédito 3DS" — Public/js/debitCard/rede/wooRedeDebit.js.
 *
 * Bug que originou o teste (5.6.0, issue #367): ao remover o maxiPago foram
 * apagados o `}` de resetInstallmentSelects() e a declaração
 * `function lknRedeDebitCardRender()`. O script passava no parse, mas estourava
 * `ReferenceError: lknRedeDebitCardRender is not defined` ao executar, então
 * addInstallmentListener() nunca rodava, o change da parcela não avisava o
 * servidor e os juros ficavam travados em 1x.
 *
 * Cobre, com o arquivo real carregado num DOM fake:
 *   1. o script executa sem lançar (pega o ReferenceError da 5.6.0);
 *   2. as quatro funções internas continuam declaradas no arquivo;
 *   3. trocar a parcela dispara lkn_update_installment_session com o valor certo;
 *   4. trocar o tipo de cartão dispara a action com card_type;
 *   5. após o sucesso do AJAX, o checkout é atualizado (update_checkout).
 *
 * Rodar:  node tests/js/woo-rede-debit-installments.test.js
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */

const assert = require('assert')
const fs = require('fs')
const path = require('path')
const vm = require('vm')

const SCRIPT_FILE = path.join(__dirname, '..', '..', 'Public', 'js', 'debitCard', 'rede', 'wooRedeDebit.js')

let passed = 0
let failed = 0

function check (description, condition, extra) {
    if (condition) {
        passed++
        console.log('  ok   ' + description)
        return
    }
    failed++
    console.log('  FAIL ' + description + (extra ? ' -> ' + extra : ''))
}

// ---------------------------------------------------------------------------
// DOM fake mínimo: só os seletores e eventos que o script usa
// ---------------------------------------------------------------------------
function createElement (id, value) {
    return {
        id: id,
        value: value == null ? '' : String(value),
        _attrs: {},
        _listeners: {},
        hasAttribute (name) {
            return Object.prototype.hasOwnProperty.call(this._attrs, name)
        },
        setAttribute (name, v) {
            this._attrs[name] = String(v)
        },
        addEventListener (type, fn) {
            this._listeners[type] = this._listeners[type] || []
            this._listeners[type].push(fn)
        },
        dispatchEvent (ev) {
            (this._listeners[ev.type] || []).forEach(function (fn) {
                fn({ type: ev.type, target: this })
            }, this)
            return true
        }
    }
}

function createHarness () {
    const installments = createElement('rede-debit-card-installments', '1')
    const cardType = createElement('rede-debit-card-type', 'debit')
    const elements = {
        '#rede-debit-card-installments': installments,
        '#rede-debit-card-type': cardType
    }

    const env = {
        ajaxCalls: [],
        triggers: []
    }

    const document = {
        body: { tag: 'body' },
        _listeners: {},
        querySelector: function (selector) {
            return elements[selector] || null
        },
        addEventListener: function (type, fn) {
            this._listeners[type] = this._listeners[type] || []
            this._listeners[type].push(fn)
        }
    }

    function jqWrapper () {
        return {
            length: 0,
            0: undefined,
            on: function () { return this },
            off: function () { return this },
            find: function () { return this },
            data: function () { return this },
            val: function () { return undefined },
            is: function () { return false },
            prop: function () { return this },
            addClass: function () { return this },
            trigger: function (name) { env.triggers.push(name); return this }
        }
    }

    // jQuery(fn) executa o callback no ready — o script inteiro vive dentro dele.
    const jq = function (arg) {
        if (typeof arg === 'function') { arg(jq); return }
        return jqWrapper()
    }
    jq.ajax = function (opts) { env.ajaxCalls.push(opts) }
    jq.fn = {}

    const window = {
        jQuery: jq,
        wooRedeDebit: { ajaxurl: '/wp-admin/admin-ajax.php', nonce: 'nonce-fake', debug: false }
    }
    window.window = window

    return {
        env: env,
        elements: elements,
        sandbox: {
            window: window,
            document: document,
            jQuery: jq,
            $: jq,
            console: { log: function () {}, error: function () {}, warn: function () {} },
            setTimeout: setTimeout,
            clearTimeout: clearTimeout,
            CustomEvent: function CustomEvent (type) { this.type = type }
        }
    }
}

function loadScript (harness) {
    const source = fs.readFileSync(SCRIPT_FILE, 'utf8')
    // 1) parse: falha de sintaxe some aqui, antes de qualquer execução
    new vm.Script(source, { filename: SCRIPT_FILE })
    // 2) execução: aqui a 5.6.0 lançava ReferenceError em lknRedeDebitCardRender
    vm.runInNewContext(source, harness.sandbox, { filename: SCRIPT_FILE })
    return source
}

// ---------------------------------------------------------------------------
// 1) Estrutura do arquivo (regressão exata da 5.6.0)
// ---------------------------------------------------------------------------
console.log('\n[1] Estrutura do arquivo')
{
    const source = fs.readFileSync(SCRIPT_FILE, 'utf8')
    check('declara resetInstallmentSelects()', source.includes('function resetInstallmentSelects()'))
    check('declara lknRedeDebitCardRender()', source.includes('function lknRedeDebitCardRender()'))
    check('declara addInstallmentListener()', source.includes('function addInstallmentListener()'))
    check('declara updateInstallmentSession()', source.includes('function updateInstallmentSession('))
}

// ---------------------------------------------------------------------------
// 2) O script executa sem lançar
// ---------------------------------------------------------------------------
console.log('\n[2] Carga do script no checkout')
{
    const harness = createHarness()
    let error = null
    try {
        loadScript(harness)
    } catch (e) {
        error = e
    }
    check('executa sem lançar erro', error === null, error && (error.name + ': ' + error.message))
    check(
        'registra o listener do select de parcelas',
        harness.elements['#rede-debit-card-installments'].hasAttribute('data-listener-added')
    )
    check(
        'registra o listener do select de tipo de cartão',
        harness.elements['#rede-debit-card-type'].hasAttribute('data-listener-added')
    )
}

// ---------------------------------------------------------------------------
// 3) Trocar a parcela avisa o servidor (juros corretos)
// ---------------------------------------------------------------------------
console.log('\n[3] Troca de parcela')
{
    const harness = createHarness()
    loadScript(harness)

    harness.elements['#rede-debit-card-installments'].value = '12'
    harness.elements['#rede-debit-card-installments'].dispatchEvent({ type: 'change' })

    check('dispara um POST', harness.env.ajaxCalls.length === 1, harness.env.ajaxCalls.length)
    const call = harness.env.ajaxCalls[0] || { data: {} }
    check('action = lkn_update_installment_session', call.data.action === 'lkn_update_installment_session', call.data.action)
    check('payment_method = rede_debit', call.data.payment_method === 'rede_debit', call.data.payment_method)
    check('installments = 12', call.data.installments === 12, call.data.installments)
    check('card_type = debit', call.data.card_type === 'debit', call.data.card_type)
    check('envia o nonce do plugin', call.data.nonce === 'nonce-fake', call.data.nonce)

    // valor inválido no select cai em 1x, nunca em NaN
    harness.env.ajaxCalls.length = 0
    harness.elements['#rede-debit-card-installments'].value = ''
    harness.elements['#rede-debit-card-installments'].dispatchEvent({ type: 'change' })
    check('parcela vazia vira 1', harness.env.ajaxCalls[0].data.installments === 1, harness.env.ajaxCalls[0].data.installments)
}

// ---------------------------------------------------------------------------
// 4) Trocar o tipo de cartão mantém a parcela escolhida
// ---------------------------------------------------------------------------
console.log('\n[4] Troca de tipo de cartão')
{
    const harness = createHarness()
    loadScript(harness)

    harness.elements['#rede-debit-card-installments'].value = '3'
    harness.elements['#rede-debit-card-type'].value = 'credit'
    harness.elements['#rede-debit-card-type'].dispatchEvent({ type: 'change' })

    const call = harness.env.ajaxCalls[0] || { data: {} }
    check('dispara a action', call.data.action === 'lkn_update_installment_session', call.data.action)
    check('envia parcelas atuais (3)', call.data.installments === 3, call.data.installments)
    check('envia card_type = credit', call.data.card_type === 'credit', call.data.card_type)
}

// ---------------------------------------------------------------------------
// 5) Sucesso do AJAX atualiza o checkout
// ---------------------------------------------------------------------------
console.log('\n[5] Atualização do checkout')
{
    const harness = createHarness()
    loadScript(harness)

    harness.elements['#rede-debit-card-installments'].value = '6'
    harness.elements['#rede-debit-card-installments'].dispatchEvent({ type: 'change' })
    const call = harness.env.ajaxCalls[0]
    call.success({ success: true })

    check('dispara update_checkout', harness.env.triggers.indexOf('update_checkout') !== -1, JSON.stringify(harness.env.triggers))
}

console.log('\n----------------------------------------')
console.log('  passed: ' + passed + '   failed: ' + failed)
console.log('----------------------------------------\n')

process.exit(failed === 0 ? 0 : 1)
