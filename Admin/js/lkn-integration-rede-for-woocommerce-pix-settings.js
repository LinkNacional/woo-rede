(function ($) {
  $(document).ready(function () {
    // Só exibe a dica "Disponível no PRO." para quem NÃO tem licença PRO ativa.
    // Com PRO ativo os campos já são funcionais e a dica perde o sentido (ex.: o
    // campo real convert_to_brl, que só existe com PRO, recebia a dica).
    const lknRedeProActive = (typeof lknPhpVariables !== 'undefined') && lknPhpVariables.isProLicenseValid

    function addProNotice ($input) {
      if (!$input.length) {
        return
      }

      const $fieldset = $input.closest('fieldset')
      if (!$fieldset.length) {
        return
      }

      // Layout do fieldset sempre aplicado (independe da licença).
      $fieldset.css({
        display: 'flex',
        'flex-direction': 'column',
        gap: '6px'
      })

      // A dica "Disponível no PRO." só aparece para quem NÃO tem licença ativa.
      if (!lknRedeProActive) {
        $fieldset.append('<p class="pro-version-info">Disponível no <a target="_blank" href="https://www.linknacional.com.br/wordpress/woocommerce/rede/">PRO</a>.</p>')
      }
    }

    // Expiration count
    const $countInput = $('#woocommerce_integration_rede_pix_expiration_count')

    const countDefaultValue = 24

    $countInput.on('input', function () {
      if ($(this).val() !== countDefaultValue.toString()) {
        $(this).val(countDefaultValue)
      }
    })

    addProNotice($countInput)

    // Select status
    const $selectInput = $('#woocommerce_integration_rede_pix_payment_complete_status')

    const selectDefaultValue = 'processing'

    $selectInput.on('change', function () {
      if ($(this).val() !== selectDefaultValue) {
        $(this).val(selectDefaultValue).trigger('change')
      }
    })

    addProNotice($selectInput)

    addProNotice($('#woocommerce_integration_rede_pix_show_button'))
    addProNotice($('#woocommerce_integration_rede_pix_convert_to_brl'))
    addProNotice($('#woocommerce_integration_rede_pix_fake_convert_to_brl'))

    // Select width
    $(document).ready(function () {
      function applyStyle () {
        $('.select2-container').css('width', 'fit-content')
      }

      const observer = new MutationObserver(function () {
        applyStyle()
      })

      observer.observe(document.body, { childList: true, subtree: true })
    })
  })
})(jQuery)
