window.jQuery(function ($) {
  // Event delegation para capturar mudanças nos métodos de pagamento
  $(document).on('change', 'input[name="payment_method"][type="radio"]', function() {
    const selectedMethod = $(this).val();
    
    // Reset apenas se estiver mudando PARA um dos métodos de crédito
    if (selectedMethod === 'rede_credit') {
      resetInstallmentSelects();
      updateInstallmentSession('rede_credit', 1);
    } else if (selectedMethod === 'maxipago_credit') {
      resetInstallmentSelects();
      updateInstallmentSession('maxipago_credit', 1);
    } else {
      // Para outros métodos, apenas trigger update_checkout
      $(document.body).trigger('update_checkout');
    }
  });

  // Verifica se janela foi carregada antes da criação do card
  $(window).on('load', lknRedeCreditCardRender)
  // Cria o card somente quando a requisição for concluida
  $(document).on('updated_checkout', lknRedeCreditCardRender)
  $(document).on('wc-fragment-refreshed', lknRedeCreditCardRender)
  $(document).on('woocommerce_cart_updated', lknRedeCreditCardRender)
  $(document).on('woocommerce_checkout_update_order_review', lknRedeCreditCardRender)
  // Fallback para quando o evento não é disparado
  lknRedeCreditCardRender()

  function resetInstallmentSelects() {
    // Reset ambos os selects para 1x quando trocar de método
    const redeInstallmentSelect = document.querySelector('#rede-card-installments');
    if (redeInstallmentSelect && redeInstallmentSelect.value !== '1') {
      redeInstallmentSelect.value = '1';
    }
    
    const maxipagoInstallmentSelect = document.querySelector('#maxipago-card-installments');
    if (maxipagoInstallmentSelect && maxipagoInstallmentSelect.value !== '1') {
      maxipagoInstallmentSelect.value = '1';
    }
  }

  function lknRedeCreditCardRender() {
    // Não carrega para formulário em bloco
    if (!document.querySelector('.wc-block-checkout')) {
      // Verifica se é página de fatura ou se é página de checkout
      let $form = $('.woocommerce .woocommerce-checkout')
      if ($form.length === 0) {
        $form = $('#order_review')
      }
      const selectedPaymentMethod = $form.find('input[name="payment_method"]:checked')
      const inputCreditNumber = $form.find('#rede-card-number')
      if (selectedPaymentMethod && selectedPaymentMethod.val() !== 'rede_credit' && !inputCreditNumber) {
        return
      }
      const inputSelectors = {
        numberInput: '#rede-card-number',
        nameInput: '#rede-card-holder-name',
        expiryInput: '#rede-card-expiry',
        cvcInput: '#rede-card-cvc'
      }

      // maybe delete old card data
      $form.data('card', null)

      // init animated card
      $form.card({
        container: '#rede-card-animation',

        // A máscara/validação dos campos é responsabilidade do rede-card-fields.js
        // (padronizado: MM/AA, só dígitos, inputmode). Aqui só a animação do cartão.
        formatting: false,

        /**
           * Selectors
           */
        formSelectors: inputSelectors,

        /**
           * Placeholders
           */
        placeholders: {
          number: '•••• •••• •••• ••••',
          name: 'NOME',
          expiry: 'MM/ANO',
          cvc: 'CVC'
        },

        /**
           * Translation Brazilian Portuguese
           */
        messages: {
          validDate: 'VALIDADE',
          monthYear: ''
        },

        /**
           * Debug
           */
        debug: Boolean(wooRedeVars.debug)
      })

      // Workaround to maintain the card data rendered after checkout updates
      Object.values(inputSelectors).reverse().forEach(function (selector) {
        $(selector)[0]?.dispatchEvent(new CustomEvent('change'))
      })

      $(inputSelectors.numberInput)[0]?.dispatchEvent(new CustomEvent('focus'))
      $(inputSelectors.numberInput)[0]?.dispatchEvent(new CustomEvent('blur'))
    }

    //Remove o cpnj ou cpf do campo que não foi preenchido
    billingCnpjInput = document.querySelector('#billing_cnpj')
    billingCpfInput = document.querySelector('#billing_cpf')
    if (billingCnpjInput && billingCpfInput) {
      billingCnpjInput.addEventListener('change', function () {
        billingCpfInput.value = '';
      });
      billingCpfInput.addEventListener('change', function () {
        billingCnpjInput.value = '';
      });
    }
    const paymentBoxP = document.querySelector('.payment_box.payment_method_rede_credit p');
    if (paymentBoxP) {
      paymentBoxP.style.display = 'none';
    }
    
    // Adicionar listener simples no select de parcelas
    addInstallmentListener();
  }

  function addInstallmentListener() {
    const installmentSelect = document.querySelector('#rede-card-installments');
    if (installmentSelect && !installmentSelect.hasAttribute('data-listener-added')) {
      installmentSelect.addEventListener('change', function(e) {
        const installments = parseInt(e.target.value, 10) || 1;
        // Atualizar sessão com nova parcela
        updateInstallmentSession('rede_credit', installments);
      });
      installmentSelect.setAttribute('data-listener-added', 'true');
    }
  }

  function updateInstallmentSession(paymentMethod, installments) {
    // Determinar qual nonce e ajaxurl usar baseado no método de pagamento
    let ajaxurl = wooRedeVars.ajaxurl;
    let nonce = wooRedeVars.nonce;
    
    if (paymentMethod === 'maxipago_credit') {
      // Usar variáveis do Maxipago se disponível
      if (typeof wooMaxipagoVars !== 'undefined') {
        ajaxurl = wooMaxipagoVars.ajaxurl;
        nonce = wooMaxipagoVars.nonce;
      }
    }
    
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'lkn_update_installment_session',
        nonce: nonce,
        payment_method: paymentMethod,
        installments: installments
      },
      success: function(response) {
        if (response.success) {
          // Trigger para atualizar checkout com nova parcela
          $(document.body).trigger('update_checkout');
        } else {
          console.error('Erro ao atualizar sessão:', response.data);
        }
      },
      error: function(xhr, status, error) {
        console.error('Erro na requisição AJAX:', error);
      }
    });
  }
})