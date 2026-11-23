(function ($) {
  'use strict'

  $(document).ready(function () {
    function handleEndpoint(endpointElement, baseUrl, listenerPath, settings) {
      if (endpointElement) {
        const url = baseUrl + listenerPath

        const pElement = document.createElement('p')
        pElement.textContent = url

        endpointElement.parentElement.appendChild(pElement)
        endpointElement.parentElement.id = 'integrationwoocommerceRedePixEndpointField'

        const span = document.createElement('span')
        const textElement = document.createElement('p')

        if (settings.endpointStatus) {
          span.classList.add('dashicons', 'dashicons-yes-alt')
          span.style.color = 'green'
          textElement.textContent = settings.translations.endpointSuccess
        } else {
          span.classList.add('dashicons', 'dashicons-dismiss')
          span.style.color = 'red'
          textElement.textContent = settings.translations.endpointError + ' '
          let link = document.createElement('a');
          link.href = 'https://youtu.be/g8IA3QUiV8o?si=I_InLyKUurv7epWh&t=703';
          link.target = '_blank';
          link.textContent = settings.translations.howToConfigure;
          textElement.appendChild(link);
        }

        textElement.style.paddingBottom = '2px'
        const div = document.createElement('div')
        div.appendChild(span)
        div.appendChild(textElement)
        div.id = 'integrationwoocommerceRedePixEndpointAlert'

        endpointElement.parentNode.appendChild(div)

        endpointElement.remove()
      }
    }

    const baseUrl = wpApiSettings.root
    const redeEndpointElement = document.querySelector('#woocommerce_integration_rede_pix_endpoint')

    handleEndpoint(redeEndpointElement, baseUrl, 'redePRO/redePixListener', lknRedeForWoocommerceProSettings)
  })
})(jQuery)
