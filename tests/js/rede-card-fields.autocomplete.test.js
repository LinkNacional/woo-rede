'use strict'
/**
 * Teste automatizado (Node puro, sem dependências) do núcleo rede-card-fields.js.
 *
 * Foco: simular o autocomplete/autofill do navegador injetando a validade no
 * formato MM/AAAA (ex.: "06/2025") e verificar que o campo é normalizado para
 * MM/AA (ex.: "06/25") — inclusive quando o valor chega com ano de 4 dígitos.
 *
 * O núcleo é carregado em um DOM fake (vm) com um <input> falso ligado aos
 * seletores reais; o autocomplete é reproduzido disparando os eventos que o
 * navegador dispara ao preencher (input e change).
 *
 * Rodar:  node tests/js/rede-card-fields.autocomplete.test.js
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */

const assert = require('assert')
const fs = require('fs')
const path = require('path')
const vm = require('vm')

const CORE_FILE = path.join(__dirname, '..', '..', 'Public', 'js', 'rede-card-fields.js')
const EXPORT = 'LknRedeCardFields'
const SELECTOR = {
    expiry: '#rede-card-expiry',
    number: '#rede-card-number',
    cvc: '#rede-card-cvc'
}

// ---------------------------------------------------------------------------
// DOM fake mínimo (só o que o núcleo usa)
// ---------------------------------------------------------------------------
function createHarness () {
    class FakeEvent {
        constructor (type, opts) {
            opts = opts || {}
            this.type = type
            this.bubbles = !!opts.bubbles
            this.target = null
            this.defaultPrevented = false
        }

        preventDefault () { this.defaultPrevented = true }
    }

    class FakeInput {
        constructor (initialValue) {
            this._value = initialValue == null ? '' : String(initialValue)
            this._attrs = {}
            this._listeners = {}
            this.selectionStart = null
            this.selectionEnd = null
        }

        get value () { return this._value }
        set value (v) { this._value = v == null ? '' : String(v) }

        getAttribute (name) {
            return Object.prototype.hasOwnProperty.call(this._attrs, name) ? this._attrs[name] : null
        }

        setAttribute (name, val) { this._attrs[name] = String(val) }

        addEventListener (type, fn) {
            (this._listeners[type] = this._listeners[type] || []).push(fn)
        }

        dispatchEvent (event) {
            const list = (this._listeners[event.type] || []).slice()
            for (let i = 0; i < list.length; i++) {
                list[i].call(this, event)
            }
            return true
        }

        focus () {}
        setSelectionRange (s, e) { this.selectionStart = s; this.selectionEnd = e }
    }

    const registry = new Map()
    const document = {
        readyState: 'complete',
        addEventListener () {},
        removeEventListener () {},
        querySelectorAll (sel) { return registry.get(sel) || [] },
        querySelector (sel) { const a = registry.get(sel) || []; return a[0] || null },
        body: { addEventListener () {} },
        documentElement: {}
    }
    const window = {
        HTMLInputElement: FakeInput,
        setTimeout: setTimeout,
        clearTimeout: clearTimeout,
        requestAnimationFrame: function (fn) { return setTimeout(fn, 0) }
    }

    return { FakeEvent, FakeInput, registry, document, window }
}

// Carrega o núcleo dentro do sandbox (dispara init() pois readyState !== 'loading').
function loadCore (harness) {
    const code = fs.readFileSync(CORE_FILE, 'utf8')
    const sandbox = {
        window: harness.window,
        document: harness.document,
        Event: harness.FakeEvent,
        console: console
    }
    vm.createContext(sandbox)
    vm.runInContext(code, sandbox)
    return harness.window[EXPORT]
}

function fakeEvent (harness, type) {
    return new harness.FakeEvent(type, { bubbles: true })
}

// ---------------------------------------------------------------------------
// Suíte
// ---------------------------------------------------------------------------
let passed = 0
let failed = 0

function check (label, actual, expected) {
    try {
        assert.strictEqual(actual, expected)
        passed++
        console.log('  \u2713 ' + label + '  ->  ' + JSON.stringify(actual))
    } catch (e) {
        failed++
        console.log('  \u2717 ' + label)
        console.log('      esperado: ' + JSON.stringify(expected))
        console.log('      obtido:   ' + JSON.stringify(actual))
    }
}

console.log('\n=== ' + path.relative(process.cwd(), CORE_FILE) + ' ===\n')

// 1) Formatter puro: autocomplete com ano de 4 dígitos -> MM/AA
{
    const api = loadCore(createHarness())
    console.log('[1] formatExpiry (autocomplete MM/AAAA -> MM/AA)')
    check('06/2025 (autocomplete)', api.formatExpiry('06/2025'), '06/25')
    check('06/2035 (autocomplete)', api.formatExpiry('06/2035'), '06/35')
    check('06/2025 (só dígitos)   ', api.formatExpiry('062025'), '06/25')
    check('6       (mês 1 díg.)   ', api.formatExpiry('6'), '06')
    check('12/2030                ', api.formatExpiry('12/2030'), '12/30')
    check('13/2025  (mês > 12)    ', api.formatExpiry('13/2025'), '12/25')
    check('06/25    (já normalizado)', api.formatExpiry('06/25'), '06/25')
    check('06/2     (parcial)     ', api.formatExpiry('06/2'), '06/2')
    check('""       (vazio)       ', api.formatExpiry(''), '')
}

// 2) Autocomplete via evento "input" (o caminho que o navegador usa)
{
    const h = createHarness()
    const expiry = new h.FakeInput()
    h.registry.set(SELECTOR.expiry, [expiry])
    loadCore(h) // init() liga o input

    console.log('\n[2] Autocomplete por evento "input"')
    check('maxlength do campo = 7 (cabe MM/AAAA)', expiry.getAttribute('maxlength'), '7')
    check('inputmode = numeric', expiry.getAttribute('inputmode'), 'numeric')

    expiry.value = '06/2025' // navegador injeta o valor completo
    expiry.dispatchEvent(fakeEvent(h, 'input'))
    check('após autocomplete "input"', expiry.value, '06/25')
}

// 3) Autofill que dispara apenas "change"
{
    const h = createHarness()
    const expiry = new h.FakeInput()
    h.registry.set(SELECTOR.expiry, [expiry])
    loadCore(h)

    console.log('\n[3] Autofill por evento "change"')
    expiry.value = '06/2035'
    expiry.dispatchEvent(fakeEvent(h, 'change'))
    check('após autofill "change"', expiry.value, '06/35')
}

// 4) Valor já presente ANTES do script carregar (autofill precoce)
{
    const h = createHarness()
    const expiry = new h.FakeInput('06/2025') // preenchido antes do JS
    h.registry.set(SELECTOR.expiry, [expiry])
    loadCore(h) // init() → bindField() → run() normaliza o valor existente

    console.log('\n[4] Valor preenchido antes do script carregar')
    check('normalizado no init()', expiry.value, '06/25')
}

// 5) Número e CVC (regressão do núcleo)
{
    const h = createHarness()
    const number = new h.FakeInput()
    const cvc = new h.FakeInput()
    h.registry.set(SELECTOR.number, [number])
    h.registry.set(SELECTOR.cvc, [cvc])
    const api = loadCore(h)

    console.log('\n[5] Número e CVC')
    number.value = '4111111111111111'
    number.dispatchEvent(fakeEvent(h, 'input'))
    check('número agrupado 4-4-4-4', number.value, '4111 1111 1111 1111')

    cvc.value = '123456' // autocomplete pode trazer mais dígitos
    cvc.dispatchEvent(fakeEvent(h, 'input'))
    check('cvc limitado a 4', cvc.value, '1234')

    check('formatCvc puro', api.formatCvc('12a3456'), '1234')
    check('formatNumber puro', api.formatNumber('4111-1111-1111-1111'), '4111 1111 1111 1111')
}

console.log('\n----------------------------------------')
console.log('  passed: ' + passed + '   failed: ' + failed)
console.log('----------------------------------------\n')

process.exit(failed === 0 ? 0 : 1)
