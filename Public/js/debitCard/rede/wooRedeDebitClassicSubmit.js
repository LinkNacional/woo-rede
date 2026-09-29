/**
 * Rede Débito/Crédito - Botão "Place order" dos layouts PRO do shortcode
 * (Moderno e Compacto).
 *
 * O botão customizado (#rede-debit-submit-btn) dispara o botão nativo do
 * WooCommerce (#place_order), já que o checkout clássico envia via AJAX.
 * O checkout de Blocos tem o próprio bundle (React) e não usa este script.
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */
(function () {
  'use strict'

  function wire () {
    var btn = document.getElementById('rede-debit-submit-btn')
    if (!btn || btn.hasAttribute('data-rede-submit-wired')) return
    btn.setAttribute('data-rede-submit-wired', 'true')
    btn.addEventListener('click', function (e) {
      e.preventDefault()
      var wooSubmit = document.getElementById('place_order')
      if (wooSubmit && !wooSubmit.disabled) {
        wooSubmit.click()
      }
    })
  }

  function init () {
    wire()
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    init()
  }

  if (window.jQuery) {
    window.jQuery(document.body).on('updated_checkout', function () {
      setTimeout(init, 200)
    })
  }
})()
