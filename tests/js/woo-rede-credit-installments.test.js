'use strict'
/**
 * Teste de regressão (Node puro, sem dependências) do gateway "Rede Crédito" —
 * Public/js/creditCard/rede/wooRedeCredit.js.
 *
 * Mesma regressão da issue #367, no arquivo do crédito: a remoção do maxiPago na
 * 5.6.0 apagou o `}` de resetInstallmentSelects() e a declaração
 * `function lknRedeCreditCardRender()`. O script passava no parse e estourava
 * ReferenceError ao executar, então addInstallmentListener() nunca rodava e a
 * parcela escolhida não chegava à sessão.
 *
 * Rodar:  node tests/js/woo-rede-credit-installments.test.js
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */

const assert = require('assert')
const fs = require('fs')
const path = require('path')
const vm = require('vm')

const SCRIPT_FILE = path.join(__dirname, '..', '..', 'Public', 'js', 'creditCard', 'rede', 'wooRedeCredit.js')

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
    const installments = createElement('rede-card-installments', '1')
    const elements = { '#rede-card-installments': installments }
    const env = { ajaxCalls: [], triggers: [] }

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

    const jq = function (arg) {
        if (typeof arg === 'function') { arg(jq); return }
        return jqWrapper()
    }
    jq.ajax = function (opts) { env.ajaxCalls.push(opts) }
    jq.fn = {}

    const window = {
        jQuery: jq,
        wooRedeVars: { ajaxurl: '/wp-admin/admin-ajax.php', nonce: 'nonce-fake' }
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
            wooRedeVars: window.wooRedeVars,
            console: { log: function () {}, error: function () {}, warn: function () {} },
            setTimeout: setTimeout,
            clearTimeout: clearTimeout,
            CustomEvent: function CustomEvent (type) { this.type = type }
        }
    }
}

function loadScript (harness) {
    const source = fs.readFileSync(SCRIPT_FILE, 'utf8')
    new vm.Script(source, { filename: SCRIPT_FILE })
    vm.runInNewContext(source, harness.sandbox, { filename: SCRIPT_FILE })
}

console.log('\n[1] Estrutura do arquivo')
{
    const source = fs.readFileSync(SCRIPT_FILE, 'utf8')
    check('declara resetInstallmentSelects()', source.includes('function resetInstallmentSelects()'))
    check('declara lknRedeCreditCardRender()', source.includes('function lknRedeCreditCardRender()'))
    check('declara addInstallmentListener()', source.includes('function addInstallmentListener()'))
    check('declara updateInstallmentSession()', source.includes('function updateInstallmentSession('))
}

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
        harness.elements['#rede-card-installments'].hasAttribute('data-listener-added')
    )
}

console.log('\n[3] Troca de parcela')
{
    const harness = createHarness()
    loadScript(harness)

    harness.elements['#rede-card-installments'].value = '12'
    harness.elements['#rede-card-installments'].dispatchEvent({ type: 'change' })

    check('dispara um POST', harness.env.ajaxCalls.length === 1, harness.env.ajaxCalls.length)
    const call = harness.env.ajaxCalls[0] || { data: {} }
    check('action = lkn_update_installment_session', call.data.action === 'lkn_update_installment_session', call.data.action)
    check('payment_method = rede_credit', call.data.payment_method === 'rede_credit', call.data.payment_method)
    check('installments = 12', call.data.installments === 12, call.data.installments)
    check('envia o nonce do plugin', call.data.nonce === 'nonce-fake', call.data.nonce)

    harness.env.ajaxCalls.length = 0
    harness.elements['#rede-card-installments'].value = ''
    harness.elements['#rede-card-installments'].dispatchEvent({ type: 'change' })
    check('parcela vazia vira 1', harness.env.ajaxCalls[0].data.installments === 1, harness.env.ajaxCalls[0].data.installments)
}

console.log('\n[4] Atualização do checkout')
{
    const harness = createHarness()
    loadScript(harness)

    harness.elements['#rede-card-installments'].value = '6'
    harness.elements['#rede-card-installments'].dispatchEvent({ type: 'change' })
    harness.env.ajaxCalls[0].success({ success: true })

    check('dispara update_checkout', harness.env.triggers.indexOf('update_checkout') !== -1, JSON.stringify(harness.env.triggers))
}

console.log('\n----------------------------------------')
console.log('  passed: ' + passed + '   failed: ' + failed)
console.log('----------------------------------------\n')

process.exit(failed === 0 ? 0 : 1)
