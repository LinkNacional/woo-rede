window.jQuery(function ($) {
  // === PROTEÇÃO CONTRA DUPLO ENVIO DO CHECKOUT ===
  var redeDebitCheckoutSubmitted = false;
  
  // Intercepta o submit do formulário de checkout via listener nativo (executa ANTES do jQuery)
  document.addEventListener('submit', function(e) {
    var form = e.target;
    if (form && (form.classList.contains('checkout') || form.classList.contains('woocommerce-checkout'))) {
      if ($('#payment_method_rede_debit').is(':checked')) {
        if (redeDebitCheckoutSubmitted) {
          e.preventDefault();
          e.stopImmediatePropagation();
          return false;
        }
        redeDebitCheckoutSubmitted = true;
        $('#place_order').prop('disabled', true).addClass('disabled');
      }
    }
  }, true); // 'true' = fase de captura, executa antes do jQuery
  
  // Desabilita botão também no clique (camada extra)
  $(document).on('click', '#place_order', function() {
    if ($('#payment_method_rede_debit').is(':checked') && redeDebitCheckoutSubmitted) {
      return false;
    }
  });
  // === FIM PROTEÇÃO ===
  
  // Event delegation para capturar mudanças nos métodos de pagamento
  $(document).on('change', 'input[name="payment_method"][type="radio"]', function() {
    const selectedMethod = $(this).val();
    
    // Reset apenas se estiver mudando PARA o método de débito/crédito
    if (selectedMethod === 'rede_debit') {
      resetInstallmentSelects();
      // Capturar tipo atual do select ao invés de forçar débito
      const cardTypeSelect = document.querySelector('#rede-debit-card-type');
      const cardType = cardTypeSelect ? cardTypeSelect.value : 'debit';
      updateInstallmentSession('rede_debit', 1, cardType);
    } else if (selectedMethod === 'rede_credit') {
      resetInstallmentSelects();
      // Não chamar updateInstallmentSession para outros métodos - deixar seus próprios scripts handlearem
      // updateInstallmentSession('rede_credit', 1);
    } else if (selectedMethod === 'maxipago_credit') {
      resetInstallmentSelects();
      // Não chamar updateInstallmentSession para outros métodos - deixar seus próprios scripts handlearem
      // updateInstallmentSession('maxipago_credit', 1);
    } else {
      // Para outros métodos, apenas trigger update_checkout
      $(document.body).trigger('update_checkout');
    }
  });

  // Verifica se janela foi carregada antes da criação do card
  $(window).on('load', lknRedeDebitCardRender)
  // Cria o card somente quando a requisição for concluida
  $(document).on('updated_checkout', lknRedeDebitCardRender)
  $(document).on('wc-fragment-refreshed', lknRedeDebitCardRender)
  $(document).on('woocommerce_cart_updated', lknRedeDebitCardRender)
  $(document).on('woocommerce_checkout_update_order_review', lknRedeDebitCardRender)
  // Fallback para quando o evento não é disparado
  lknRedeDebitCardRender()

  function resetInstallmentSelects() {
    // Reset todos os selects de parcelas para 1x quando trocar de método
    const redeDebitInstallmentSelect = document.querySelector('#rede-debit-card-installments');
    if (redeDebitInstallmentSelect && redeDebitInstallmentSelect.value !== '1') {
      redeDebitInstallmentSelect.value = '1';
    }
    
    const redeCreditInstallmentSelect = document.querySelector('#rede-card-installments');
    if (redeCreditInstallmentSelect && redeCreditInstallmentSelect.value !== '1') {
      redeCreditInstallmentSelect.value = '1';
    }
    
    const maxipagoCreditInstallmentSelect = document.querySelector('#maxipago-card-installments');
    if (maxipagoCreditInstallmentSelect && maxipagoCreditInstallmentSelect.value !== '1') {
      maxipagoCreditInstallmentSelect.value = '1';
    }
  }

  function lknRedeDebitCardRender() {
    if (!document.querySelector('.wc-block-checkout')) {
      // Cria o card somente quando a requisição for concluida
      let $form = $('.woocommerce .woocommerce-checkout')
      if ($form.length === 0) {
        $form = $('#order_review')
      }
      const selectedPaymentMethod = $form.find('input[name="payment_method"]:checked')
      const inputDebitNumber = $form.find('#rede-card-number')
      if (selectedPaymentMethod && selectedPaymentMethod.val() !== 'rede_debit' && !inputDebitNumber) {
        return
      }
      const inputSelectors = {
        numberInput: '#rede-debit-card-number',
        nameInput: '#rede-debit-card-holder-name',
        expiryInput: '#rede-debit-card-expiry',
        cvcInput: '#rede-debit-card-cvc'
      }

      // maybe delete old card data
      $form.data('card', null)

      // init animated card (apenas quando a lib jquery.card está carregada —
      // ou seja, quando a opção "Show animated card" está habilitada).
      if (typeof $form.card === 'function') {
        $form.card({
          container: '#rede-debit-card-animation',

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
          debug: !!window.wooRedeDebit.debug
        })
      }

      // Workaround to maintain the card data rendered after checkout updates
      Object.values(inputSelectors).reverse().forEach(function (selector) {
        $(selector)[0]?.dispatchEvent(new CustomEvent('change'))
      })

      $(inputSelectors.numberInput)[0]?.dispatchEvent(new CustomEvent('focus'))
      $(inputSelectors.numberInput)[0]?.dispatchEvent(new CustomEvent('blur'))
    }

    // Adicionar listener no select de parcelas
    addInstallmentListener();
  }

  function addInstallmentListener() {
    const installmentSelect = document.querySelector('#rede-debit-card-installments');
    if (installmentSelect && !installmentSelect.hasAttribute('data-listener-added')) {
      installmentSelect.addEventListener('change', function(e) {
        const installments = parseInt(e.target.value, 10) || 1;
        
        // Capturar tipo de cartão selecionado do select
        const cardTypeSelect = document.querySelector('#rede-debit-card-type');
        const cardType = cardTypeSelect ? cardTypeSelect.value : 'debit';
        
        // Atualizar sessão com nova parcela e tipo de cartão
        updateInstallmentSession('rede_debit', installments, cardType);
      });
      installmentSelect.setAttribute('data-listener-added', 'true');
    }
    
    // Adicionar listener para mudança de tipo de cartão também
    const cardTypeSelect = document.querySelector('#rede-debit-card-type');
    if (cardTypeSelect && !cardTypeSelect.hasAttribute('data-listener-added')) {
      cardTypeSelect.addEventListener('change', function(e) {
        const cardType = e.target.value;
        const installmentSelect = document.querySelector('#rede-debit-card-installments');
        const installments = installmentSelect ? parseInt(installmentSelect.value, 10) || 1 : 1;
        
        // Atualizar sessão com tipo de cartão e parcelas atuais
        updateInstallmentSession('rede_debit', installments, cardType);
      });
      cardTypeSelect.setAttribute('data-listener-added', 'true');
    }
  }

  function updateInstallmentSession(paymentMethod, installments, cardType = null) {
    // Usar sempre as variáveis do wooRedeDebit para todos os métodos
    const ajaxurl = window.wooRedeDebit.ajaxurl;
    const nonce = window.wooRedeDebit.nonce;
    
    // Preparar dados da requisição
    const requestData = {
      action: 'lkn_update_installment_session',
      nonce: nonce,
      payment_method: paymentMethod,
      installments: installments
    };
    
    // Adicionar tipo de cartão apenas para rede_debit
    if (paymentMethod === 'rede_debit' && cardType) {
      requestData.card_type = cardType;
    }
    
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      dataType: 'json',
      data: requestData,
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