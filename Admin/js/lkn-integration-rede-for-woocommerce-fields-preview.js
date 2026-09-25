/**
 * Editor visual da seção "Fields" (preview + lápis) — Rede.
 *
 * - Alterna o preview entre Blocks/Classic (select "Checkout") e
 *   standard/modern/compact (select de Layout).
 * - Injeta a edição inline (lápis) de labels e placeholders.
 * - Persiste o valor nos campos ocultos
 *   field_label_* / field_placeholder_* (chave = modo + template + campo).
 *
 * @package Lknwoo\IntegrationRedeForWoocommerce
 */
(function () {
  'use strict'

  var TEXT = {
    editLabel: 'Edit label',
    editPlaceholder: 'Edit placeholder',
    save: 'Save',
    cancel: 'Cancel'
  }

  function hiddenInputs (editor) {
    var scope = editor.closest('table') || document
    return scope.querySelectorAll(
      'tr.lkn-fields-hidden-row input[data-lkn-field][data-lkn-kind][data-lkn-template][data-lkn-mode]'
    )
  }

  function showPreview (editor, mode, template) {
    editor.querySelectorAll('.lkn-fields-preview').forEach(function (preview) {
      var match = preview.getAttribute('data-mode') === mode &&
        preview.getAttribute('data-template') === template
      if (match) {
        preview.removeAttribute('hidden')
      } else {
        preview.setAttribute('hidden', 'hidden')
      }
    })
  }

  function applyValue (editor, mode, template, field, kind, value) {
    // Campo oculto (persistência).
    hiddenInputs(editor).forEach(function (input) {
      if (input.getAttribute('data-lkn-mode') === mode &&
        input.getAttribute('data-lkn-template') === template &&
        input.getAttribute('data-lkn-field') === field &&
        input.getAttribute('data-lkn-kind') === kind) {
        input.value = value
      }
    })

    // Preview — labels (apenas quando se edita a label).
    if (kind === 'label') {
      editor.querySelectorAll(
        '.lkn-edit-text[data-lkn-kind="label"][data-lkn-field="' + field + '"][data-lkn-template="' + template + '"][data-lkn-mode="' + mode + '"]'
      ).forEach(function (el) {
        el.textContent = value
      })
    }

    // Preview — placeholders (apenas quando se edita o placeholder).
    if (kind === 'placeholder') {
      editor.querySelectorAll(
        'input[data-lkn-ph-field="' + field + '"][data-lkn-mode="' + mode + '"][data-lkn-template="' + template + '"]'
      ).forEach(function (el) {
        el.setAttribute('placeholder', value)
      })
    }
  }

  function closePopover () {
    var existing = document.querySelector('.lkn-edit-popover')
    if (existing) existing.remove()
  }

  function openPopover (editor, btn) {
    closePopover()

    var mode = btn.getAttribute('data-lkn-mode') || 'blocks'
    var template = btn.getAttribute('data-lkn-template')
    var field = btn.getAttribute('data-lkn-field')
    var kind = btn.getAttribute('data-lkn-kind')

    var current = ''
    hiddenInputs(editor).forEach(function (input) {
      if (input.getAttribute('data-lkn-mode') === mode &&
        input.getAttribute('data-lkn-template') === template &&
        input.getAttribute('data-lkn-field') === field &&
        input.getAttribute('data-lkn-kind') === kind) {
        current = input.value
      }
    })

    var popover = document.createElement('div')
    popover.className = 'lkn-edit-popover'

    var label = document.createElement('label')
    label.className = 'lkn-edit-popover__label'
    label.textContent = (kind === 'placeholder' ? TEXT.editPlaceholder : TEXT.editLabel) + ' (' + mode + ' / ' + template + ')'

    var input = document.createElement('input')
    input.type = 'text'
    input.value = current

    var actions = document.createElement('div')
    actions.className = 'lkn-edit-popover__actions'

    var save = document.createElement('button')
    save.type = 'button'
    save.className = 'button button-primary'
    save.textContent = TEXT.save

    var cancel = document.createElement('button')
    cancel.type = 'button'
    cancel.className = 'button'
    cancel.textContent = TEXT.cancel

    actions.appendChild(cancel)
    actions.appendChild(save)
    popover.appendChild(label)
    popover.appendChild(input)
    popover.appendChild(actions)
    document.body.appendChild(popover)

    var rect = btn.getBoundingClientRect()
    var top = rect.bottom + window.scrollY + 6
    var left = rect.left + window.scrollX - 8
    var maxLeft = window.scrollX + document.documentElement.clientWidth - popover.offsetWidth - 10
    if (left > maxLeft) left = maxLeft
    popover.style.top = top + 'px'
    popover.style.left = Math.max(10, left) + 'px'

    input.focus()
    input.select()

    function commit () {
      applyValue(editor, mode, template, field, kind, input.value)
      closePopover()
    }

    save.addEventListener('click', commit)
    cancel.addEventListener('click', closePopover)
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault()
        commit()
      } else if (e.key === 'Escape') {
        closePopover()
      }
    })
  }

  function initEditor (editor) {
    if (editor.getAttribute('data-lkn-fields-editor-bound') === 'true') return
    editor.setAttribute('data-lkn-fields-editor-bound', 'true')

    var gateway = editor.getAttribute('data-gateway') || 'rede_debit'
    // Tipo de checkout (Blocos x Clássico) e template ativo (layout real).
    var modeSelect = document.getElementById('woocommerce_' + gateway + '_checkout_type')
    var layout = document.getElementById('woocommerce_' + gateway + '_3ds_template_style')

    function currentMode () {
      var v = modeSelect ? String(modeSelect.value || '') : ''
      return v === 'classic' ? 'classic' : 'blocks'
    }

    function currentTemplate () {
      var v = layout ? String(layout.value || '') : ''
      if (v === 'basic') v = 'standard' // o Rede chama o layout padrão de "basic"
      return v || 'standard'
    }

    function refresh () {
      showPreview(editor, currentMode(), currentTemplate())
    }

    ;[modeSelect, layout].forEach(function (sel) {
      if (! sel) return
      sel.addEventListener('change', refresh)
      if (window.jQuery) window.jQuery(sel).on('change select2:select', refresh)
    })
    refresh()

    // Sincroniza a descrição do preview com o campo "Description" da configuração
    // (o preview nasce com um texto estático; aqui ele passa a refletir o valor
    // real digitado pelo lojista). Aplica a TODOS os previews de layout.
    var descField = document.getElementById('woocommerce_' + gateway + '_description')
    function syncDescription () {
      if (! descField) return
      var val = descField.value
      editor.querySelectorAll('.lkn-preview-description').forEach(function (el) {
        el.textContent = val
      })
    }
    if (descField) {
      descField.addEventListener('input', syncDescription)
      descField.addEventListener('change', syncDescription)
      if (window.jQuery) window.jQuery(descField).on('input change', syncDescription)
      syncDescription()
    }

    editor.addEventListener('click', function (event) {
      var btn = event.target.closest('.lkn-edit-btn')
      if (! btn || ! editor.contains(btn)) return
      event.preventDefault()
      openPopover(editor, btn)
    })
  }

  function init () {
    document.querySelectorAll('.lkn-fields-editor').forEach(initEditor)
  }

  // O Preview é gerado como um campo do painel: o script de layout monta o card e o
  // input de apoio (com data-title-description) vira ruído no body. Removemos esse
  // input depois do transform, mantendo o <p class="description"> abaixo do preview.
  function stripPreviewInputs () {
    document.querySelectorAll('.lkn-fields-preview-row .lkn-fields-preview-input').forEach(function (el) {
      el.remove()
    })
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('.lkn-edit-popover') || event.target.closest('.lkn-edit-btn')) return
    closePopover()
  })

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    init()
  }

  window.addEventListener('load', function () {
    stripPreviewInputs()
    setTimeout(stripPreviewInputs, 200)
    setTimeout(stripPreviewInputs, 600)
  })
})()
