/* eslint-disable no-undef */
/**
 * Padronização dos campos de cartão no checkout em Blocos (React).
 *
 * Os campos são renderizados pelo React, então o núcleo (rede-card-fields.js) é
 * reexecutado sempre que a árvore é atualizada. O núcleo cuida da máscara,
 * do filtro de dígitos, do inputmode e da normalização da validade.
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */
(function (window, document) {
    'use strict'

    function boot () {
        const api = window.LknRedeCardFields
        if (!api || typeof api.init !== 'function') {
            return
        }

        let scheduled = false
        const schedule = function () {
            if (scheduled) {
                return
            }
            scheduled = true
            window.requestAnimationFrame(function () {
                scheduled = false
                api.init()
            })
        }

        api.init()

        if (window.MutationObserver) {
            new MutationObserver(schedule).observe(document.documentElement || document.body, {
                childList: true,
                subtree: true
            })
        }

        window.setTimeout(schedule, 400)
        window.setTimeout(schedule, 1200)
    }

    if (document.readyState !== 'loading') {
        boot()
    } else {
        document.addEventListener('DOMContentLoaded', boot)
    }
})(window, document)
