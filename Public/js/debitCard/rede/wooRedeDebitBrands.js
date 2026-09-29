/**
 * Rede Débito — faixa de bandeiras no topo do formulário (checkout CLÁSSICO/shortcode).
 *
 * Realça, conforme o BIN digitado, a bandeira correspondente na faixa
 * `#rede-debit-card-brands` (as demais ficam cinza; sem match, todas cinza; input
 * vazio, todas coloridas). Reusa a detecção offline do Rede (AJAX
 * lkn_get_offline_bin_card). Vale para TODOS os layouts clássicos (standard,
 * modern e compact) — a faixa existe em todos quando a opção "Show card brand
 * icons" está ligada.
 *
 * NÃO cuida das bandeiras do CAMPO do compacto (essas são do wooRedeDebitCompact).
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */
(function () {
  'use strict'

  var cfg = window.redeDebitBrands || {}
  // Bandeiras "conhecidas" (com ícone próprio). Qualquer outra cai em 'other_card'
  // (mesma regra do Gutenberg do Rede).
  var BRANDS = ['visa', 'mastercard', 'amex', 'elo']
  var OTHER = 'other_card'
  var debounceTimer = null
  var lastValue = null

  function brandImages () {
    return document.querySelectorAll('#rede-debit-card-brands img')
  }

  // Marca só a bandeira detectada como ativa; as demais ficam cinza.
  function highlight (brand) {
    brandImages().forEach(function (img) {
      var b = (img.getAttribute('data-brand') || '').toLowerCase()
      img.classList.toggle('is-dim', b !== brand)
    })
  }

  // Todas coloridas (input vazio) ou todas cinza (sem match).
  function setAll (active) {
    brandImages().forEach(function (img) {
      img.classList.toggle('is-dim', !active)
    })
  }

  function detect (number) {
    if (!window.jQuery) return
    window.jQuery.ajax({
      url: cfg.ajaxurl || '/wp-admin/admin-ajax.php',
      type: 'POST',
      dataType: 'json',
      data: { action: 'lkn_get_offline_bin_card', number: number, nonce: cfg.nonce },
      success: function (response) {
        var input = document.getElementById('rede-debit-card-number')
        if (!input || input.value.replace(/\s+/g, '') !== number) return // resposta obsoleta
        if (response && response.status && response.brand) {
          var detected = String(response.brand).toLowerCase()
          // Bandeira conhecida → destaca ela; senão destaca "outros cartões".
          highlight(BRANDS.indexOf(detected) !== -1 ? detected : OTHER)
        } else {
          setAll(false)
        }
      },
      error: function () { setAll(false) }
    })
  }

  function refresh () {
    var input = document.getElementById('rede-debit-card-number')
    if (!input) return
    var digits = input.value.replace(/\s+/g, '')
    if (digits === lastValue) return
    lastValue = digits
    clearTimeout(debounceTimer)

    if (digits.length === 0) {
      setAll(true)
      return
    }
    setAll(false)
    if (digits.length >= 6) {
      debounceTimer = setTimeout(function () { detect(digits) }, 500)
    }
  }

  function bindBrands () {
    var input = document.getElementById('rede-debit-card-number')
    if (input && !input.hasAttribute('data-rede-brands-init')) {
      input.setAttribute('data-rede-brands-init', 'true')
      input.addEventListener('input', refresh)
    }
  }

  function init () {
    // Só age quando a faixa de bandeiras do topo existe (qualquer layout clássico).
    if (!document.getElementById('rede-debit-card-brands')) {
      return
    }
    bindBrands()
    // Força releitura do estado atual (o WooCommerce recria a payment box a cada
    // updated_checkout; sem isso o guard de lastValue manteria a faixa "suja").
    lastValue = null
    refresh()
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    init()
  }

  // Reinicializa após atualizações do checkout (mudança de método de pagamento etc.)
  if (window.jQuery) {
    window.jQuery(document.body).on('updated_checkout', function () {
      setTimeout(init, 200)
    })
  }
})()
