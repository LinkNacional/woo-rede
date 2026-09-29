/**
 * Rede Débito/Crédito - Layout Compacto (checkout clássico / shortcode)
 *
 * Injeta as bandeiras (Visa, Mastercard, Elo) dentro do campo de número e os
 * ícones de validade/código, e anima as bandeiras conforme o usuário digita
 * (mesmo esquema do layout moderno): ao digitar todos ficam cinza; com 6+
 * dígitos a bandeira detectada fica colorida e as demais cinzas; sem match (ou
 * erro) todas ficam cinzas.
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */
(function () {
  'use strict'

  var cfg = window.redeDebitCompact || {}
  var assets = cfg.assets || {}
  var BRANDS = ['visa', 'mastercard', 'elo']
  var debounceTimer = null
  var lastValue = null

  function injectIcons () {
    // Bandeiras dentro do campo de número
    var brandsWrap = document.querySelector('.rede-compact-card-brands')
    if (brandsWrap && brandsWrap.querySelectorAll('img').length === 0) {
      BRANDS.forEach(function (brand) {
        if (!assets[brand]) return
        var img = document.createElement('img')
        img.src = assets[brand]
        img.alt = brand
        img.setAttribute('data-brand', brand)
        brandsWrap.appendChild(img)
      })
    }

    // Ícones dos campos (validade = calendário, código = chave)
    document.querySelectorAll('.rede-compact-field[data-icon]').forEach(function (field) {
      var wrap = field.querySelector('.rede-compact-input-wrap')
      if (!wrap || wrap.querySelector('.rede-compact-field-icon')) return
      var key = field.getAttribute('data-icon')
      if (!assets[key]) return
      var img = document.createElement('img')
      img.className = 'rede-compact-field-icon'
      img.src = assets[key]
      img.alt = ''
      img.setAttribute('aria-hidden', 'true')
      wrap.appendChild(img)
    })
  }

  function paint (brand) {
    document.querySelectorAll('.rede-compact-card-brands img').forEach(function (img) {
      var b = (img.getAttribute('data-brand') || '').toLowerCase()
      if (brand && b === brand) {
        img.style.filter = 'none'
        img.style.opacity = '1'
      } else {
        img.style.filter = 'grayscale(100%)'
        img.style.opacity = '0.35'
      }
    })
  }

  function colorAll () { paint(null) }
  function grayAll () { paint('__none__') }

  function detect (number) {
    var clean = number.replace(/\s+/g, '')
    if (clean.length < 6) return
    if (!window.jQuery) return

    window.jQuery.ajax({
      url: cfg.ajaxurl || '/wp-admin/admin-ajax.php',
      type: 'POST',
      dataType: 'json',
      data: { action: 'lkn_get_offline_bin_card', number: clean, nonce: cfg.nonce },
      success: function (response) {
        var input = document.getElementById('rede-debit-card-number')
        if (!input || input.value.replace(/\s+/g, '') !== clean) return // resposta obsoleta

        if (response && response.status && response.brand && BRANDS.indexOf(String(response.brand).toLowerCase()) !== -1) {
          paint(String(response.brand).toLowerCase())
        } else {
          grayAll()
        }
      },
      error: function () { grayAll() }
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
      colorAll()
      return
    }
    grayAll()
    if (digits.length >= 6) {
      debounceTimer = setTimeout(function () { detect(digits) }, 500)
    }
  }

  function bind () {
    var input = document.getElementById('rede-debit-card-number')
    if (input && !input.hasAttribute('data-rede-compact-init')) {
      input.setAttribute('data-rede-compact-init', 'true')
      input.addEventListener('input', refresh)
    }
  }

  function init () {
    // Só age no checkout clássico (o compacto de Blocos tem seu próprio JS no
    // bundle React). Ambos usam .rede-compact-card-brands, então o guard evita
    // que este script injete/duplique ícones na tela de Blocos.
    if (!document.querySelector('.rede-compact-classic')) {
      return
    }
    injectIcons()
    bind()
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
