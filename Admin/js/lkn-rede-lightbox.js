/**
 * Ajustes de comportamento/layout do lightbox (Thickbox) das imagens de layout.
 *
 * 1) Cria um "gutter" ao redor da imagem: o Thickbox do core dimensiona a janela
 *    como "imagem + 30px", então os controles ficariam por dentro da imagem.
 *    Aqui aumentamos a janela (--lkn-tb-gap) e recentralizamos, deixando espaço
 *    para o CSS colocar os controles FORA da imagem, dentro de uma moldura.
 * 2) Clique na imagem/bloco NÃO fecha NEM recarrega (o <a href=""> navegaria sem
 *    o preventDefault). Clique FORA fecha; setas e fechar seguem normais.
 * 3) Reformata o contador ("imagem 2 de 3" -> "2 / 3").
 */
(function () {
  var GAP = 72 // gutter em volta da imagem (px)

  function isVisible (el) {
    return !!el && el.offsetParent !== undefined && getComputedStyle(el).visibility === 'visible'
  }

  // (2) Clique na imagem não fecha/recarrega.
  document.addEventListener(
    'click',
    function (e) {
      var win = document.getElementById('TB_window')
      if (!isVisible(win)) return
      if (!win.contains(e.target)) return // clique no overlay: deixa fechar
      if (e.target.closest && e.target.closest('#TB_prev, #TB_next, #TB_closeWindowButton')) return
      e.preventDefault()
      e.stopPropagation()
    },
    true
  )

  // (4) Contador "2 / 3".
  function formatCounter (line) {
    if (!line || line.getAttribute('data-lkn-counter') === '1') return
    var changed = false
    for (var i = 0; i < line.childNodes.length; i++) {
      var node = line.childNodes[i]
      if (node.nodeType === 3) {
        var m = node.nodeValue.match(/(\d+)\s*(?:de|of)\s*(\d+)/i)
        if (m) {
          node.nodeValue = ' ' + m[1] + ' / ' + m[2] + ' '
          changed = true
        }
      }
    }
    if (changed) line.setAttribute('data-lkn-counter', '1')
  }

  // (1) Aumenta a janela criando o gutter e recentraliza a moldura.
  // O gutter nunca é menor que MIN_GAP (senão os controles entram na imagem);
  // se faltar espaço na viewport, a imagem é reduzida proporcionalmente.
  var MIN_GAP = 60

  function tune () {
    var win = document.getElementById('TB_window')
    if (!win) return
    var img = win.querySelector('img#TB_Image')
    if (!img) return

    // Guarda o tamanho ORIGINAL (definido pelo core) para não acumular encolhimento.
    if (!img.hasAttribute('data-lkn-ow')) {
      img.setAttribute('data-lkn-ow', img.getAttribute('width') || img.offsetWidth)
      img.setAttribute('data-lkn-oh', img.getAttribute('height') || img.offsetHeight)
    }
    var ow = parseInt(img.getAttribute('data-lkn-ow'), 10) || img.offsetWidth
    var oh = parseInt(img.getAttribute('data-lkn-oh'), 10) || img.offsetHeight
    if (!ow || !oh) return

    var availW = Math.max(240, window.innerWidth - 40)
    var availH = Math.max(200, window.innerHeight - 40)

    // Encolhe a imagem (se preciso) para caber com o gutter mínimo.
    var maxW = availW - MIN_GAP * 2
    var scale = ow > maxW ? (maxW / ow) : 1
    var iw = Math.round(ow * scale)
    var ih = Math.round(oh * scale)
    img.style.width = iw + 'px'
    img.style.height = ih + 'px'

    // Mede a legenda já na largura do conteúdo (afeta a quebra de linha).
    win.style.setProperty('--lkn-tb-gap', MIN_GAP + 'px')
    win.style.boxSizing = 'border-box'
    win.style.width = (iw + MIN_GAP * 2) + 'px'
    var cap = win.querySelector('#TB_caption')
    var capH = cap ? cap.offsetHeight : 0

    // Ainda não couber na altura? Reduz mais um pouco.
    var maxH = availH - MIN_GAP * 2 - capH
    if (ih > maxH) {
      var s2 = maxH / ih
      iw = Math.round(iw * s2)
      ih = Math.round(ih * s2)
      img.style.width = iw + 'px'
      img.style.height = ih + 'px'
      capH = cap ? cap.offsetHeight : 0
    }

    // Gutter final: o maior possível (até GAP), nunca menor que MIN_GAP.
    var fit = Math.min(
      GAP,
      Math.floor((availW - iw) / 2),
      Math.floor((availH - ih - capH) / 2)
    )
    var gap = Math.max(MIN_GAP, fit)

    win.style.setProperty('--lkn-tb-gap', gap + 'px')
    var w = iw + gap * 2
    var h = ih + capH + gap * 2
    win.style.width = w + 'px'
    win.style.height = h + 'px'
    win.style.marginLeft = (-Math.round(w / 2)) + 'px'
    win.style.marginTop = (-Math.round(h / 2)) + 'px'
    win.style.setProperty('--lkn-img-center', (gap + ih / 2) + 'px')
  }

  // Remove o texto "« Anterior"/"Próximo »" que o core injeta dentro dos links
  // (fica só o nosso ícone). Sem isso, sobra texto sem sentido ao lado da seta.
  function stripNavText () {
    var sel = ['#TB_prev a', '#TB_next a']
    for (var s = 0; s < sel.length; s++) {
      var a = document.querySelector(sel[s])
      if (!a) continue
      for (var i = a.childNodes.length - 1; i >= 0; i--) {
        if (a.childNodes[i].nodeType === 3) a.removeChild(a.childNodes[i])
      }
    }
  }

  function boot () {
    formatCounter(document.getElementById('TB_secondLine'))
    stripNavText()
    tune()
  }

  if (window.MutationObserver) {
    new MutationObserver(function () {
      var win = document.getElementById('TB_window')
      if (!win || !win.querySelector('img#TB_Image')) return
      var line = document.getElementById('TB_secondLine')
      if (line) formatCounter(line)
      stripNavText()
      tune()
      // Reaplica no próximo frame (garante medir a legenda já renderizada).
      window.requestAnimationFrame(tune)
    }).observe(document.body, { childList: true, subtree: true })
  }

  document.addEventListener('DOMContentLoaded', boot)
  window.addEventListener('load', boot)
  window.addEventListener('resize', function () {
    if (document.getElementById('TB_window')) tune()
  })
})()
