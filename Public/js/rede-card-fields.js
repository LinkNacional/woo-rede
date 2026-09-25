/* eslint-disable no-undef */
/**
 * Padronização dos campos de cartão (núcleo compartilhado).
 *
 * Regras aplicadas a número, validade e CVC (o nome NUNCA é tocado):
 *  - Número do cartão .....: só dígitos, agrupado de 4 em 4 -> "0000 0000 0000 0000".
 *  - Validade .............: só dígitos, sempre "MM/AA" (sem espaços). Mês de um dígito
 *                            2-9 vira "0X"; mês > 12 é limitado a 12; ano com 4 dígitos
 *                            (autocomplete) é cortado para 2 ("25/2035" -> "25/35").
 *  - CVC ..................: só dígitos, no máximo 4.
 *  - inputmode="numeric" ..: força o teclado numérico no celular.
 *
 * Expõe window.LknRedeCardFields e faz o auto-init no checkout clássico (shortcode).
 * O checkout em Blocos (React) usa rede-card-fields-blocks.js, que reexecuta init()
 * depois de cada render.
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */
(function (window, document) {
    'use strict'

    const MAX_NUMBER_DIGITS = 19
    const GROUP_SIZE = 4
    const MAX_CVC_DIGITS = 4
    const EXPIRY_SEPARATOR = '/'

    // maxlength unificado dos campos. A validade fica com folga (MM/YYYY) para que
    // o autocomplete com ano de 4 dígitos chegue inteiro e seja normalizado para
    // MM/AA pelo formatador (o valor final nunca passa de 5 caracteres).
    const MAXLENGTH = {
        number: 23,
        expiry: 7,
        cvc: 4
    }

    const SELECTORS = {
        number: ['#rede-card-number', '#rede-debit-card-number'],
        expiry: ['#rede-card-expiry', '#rede-debit-card-expiry'],
        cvc: ['#rede-card-cvc', '#rede-debit-card-cvc']
    }

    function onlyDigits (value) {
        return String(value == null ? '' : value).replace(/\D/g, '')
    }

    function formatNumber (value) {
        const digits = onlyDigits(value).slice(0, MAX_NUMBER_DIGITS)
        let out = ''
        for (let i = 0; i < digits.length; i++) {
            if (i > 0 && i % GROUP_SIZE === 0) {
                out += ' '
            }
            out += digits.charAt(i)
        }
        return out
    }

    function formatExpiry (value) {
        const digits = onlyDigits(value)
        let month = digits.slice(0, 2)
        let year = digits.slice(2)

        if (month.length === 1 && month >= '2' && month <= '9') {
            month = '0' + month
        } else if (month.length === 2 && parseInt(month, 10) > 12) {
            month = '12'
        }

        if (year.length > 2) {
            year = year.slice(-2)
        }

        return year.length ? month + EXPIRY_SEPARATOR + year : month
    }

    function formatCvc (value) {
        return onlyDigits(value).slice(0, MAX_CVC_DIGITS)
    }

    function nativeSetValue (input, value) {
        const descriptor = window.HTMLInputElement &&
            window.HTMLInputElement.prototype &&
            Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')
        if (descriptor && descriptor.set) {
            descriptor.set.call(input, value)
        } else {
            input.value = value
        }
    }

    function caretAfterDigits (text, digitCount) {
        let seen = 0
        for (let i = 0; i < text.length; i++) {
            if (/\d/.test(text.charAt(i))) {
                seen++
            }
            if (seen >= digitCount) {
                return i + 1
            }
        }
        return text.length
    }

    function run (input, formatter, preserveCaret) {
        if (input.__lknCardFieldsRunning) {
            return
        }
        const before = input.value
        const caret = input.selectionStart
        const digitsBeforeCaret = (typeof caret === 'number') ? onlyDigits(before.slice(0, caret)).length : null

        const after = formatter(before)
        if (after === before) {
            return
        }

        input.__lknCardFieldsRunning = true
        nativeSetValue(input, after)
        try {
            input.dispatchEvent(new Event('input', { bubbles: true }))
        } catch (e) {
            // Navegadores antigos: ignora.
        }
        input.__lknCardFieldsRunning = false

        if (preserveCaret && digitsBeforeCaret !== null && typeof input.setSelectionRange === 'function') {
            const newCaret = caretAfterDigits(after, digitsBeforeCaret)
            try {
                input.setSelectionRange(newCaret, newCaret)
            } catch (e) {
                // Inputs sem suporte a seleção: ignora.
            }
        }
    }

    function bindField (input, formatter, preserveCaret, maxLength) {
        if (!input || input.getAttribute('data-lkn-card-fields') === '1') {
            return
        }
        input.setAttribute('data-lkn-card-fields', '1')
        input.setAttribute('inputmode', 'numeric')
        if (maxLength) {
            input.setAttribute('maxlength', String(maxLength))
        }

        const handler = function () {
            run(input, formatter, preserveCaret)
        }

        input.addEventListener('input', handler)
        input.addEventListener('change', handler)
        input.addEventListener('blur', handler)
        input.addEventListener('paste', function () {
            window.setTimeout(handler, 0)
        })
        // Filtro explícito: bloqueia digitação de qualquer caractere que não seja dígito.
        input.addEventListener('keypress', function (event) {
            if (event.ctrlKey || event.metaKey || event.altKey) {
                return
            }
            const char = event.key
            if (char && char.length === 1 && !/\d/.test(char)) {
                event.preventDefault()
            }
        })

        // Normaliza um valor já preenchido (ex.: autofill antes do script carregar).
        run(input, formatter, preserveCaret)
    }

    function init () {
        SELECTORS.number.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (input) {
                bindField(input, formatNumber, true, MAXLENGTH.number)
            })
        })
        SELECTORS.expiry.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (input) {
                bindField(input, formatExpiry, false, MAXLENGTH.expiry)
            })
        })
        SELECTORS.cvc.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (input) {
                bindField(input, formatCvc, false, MAXLENGTH.cvc)
            })
        })
    }

    window.LknRedeCardFields = {
        init: init,
        formatNumber: formatNumber,
        formatExpiry: formatExpiry,
        formatCvc: formatCvc
    }

    if (document.readyState !== 'loading') {
        init()
    } else {
        document.addEventListener('DOMContentLoaded', init)
    }
    document.addEventListener('updated_checkout', init)
    document.body && document.body.addEventListener('updated_checkout', init)
})(window, document)
