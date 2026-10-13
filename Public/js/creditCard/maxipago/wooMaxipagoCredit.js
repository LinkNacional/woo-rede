window.jQuery(function ($) {
  // O controle do radio button agora é feito apenas pelo wooRedeCredit.js
  // para evitar conflitos entre os gateways
  
  // Verifica se janela foi carregada antes da criação do card
  $(window).on('load', lknMaxipagoCreditCardRender)
  // Cria o card somente quando a requisição for concluida
  $(document).on('updated_checkout', lknMaxipagoCreditCardRender)
  $(document).on('wc-fragment-refreshed', lknMaxipagoCreditCardRender)
  $(document).on('woocommerce_cart_updated', lknMaxipagoCreditCardRender)
  $(document).on('woocommerce_checkout_update_order_review', lknMaxipagoCreditCardRender)
  // Fallback para quando o evento não é disparado
  lknMaxipagoCreditCardRender()

  function lknMaxipagoCreditCardRender() {
    if (!document.querySelector('.wc-block-checkout')) {
      // Função para formatação de CPF
      function formatarCPF(cpf) {
        cpf = cpf.replace(/\D/g, '') // Remove caracteres não numéricos
        cpf = cpf.slice(0, 11) // Limita o CPF ao máximo de 11 caracteres
        cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2') // Adiciona ponto após os primeiros 3 dígitos
        cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2') // Adiciona ponto após os segundos 3 dígitos
        cpf = cpf.replace(/(\d{3})(\d{1,2})$/, '$1-$2') // Adiciona hífen após os últimos 3 dígitos
        return cpf
      }
      
      $('#maxipagoCreditCardCpf').on('input', function () {
        const input = $(this)
        let cpf = input.val()
        cpf = formatarCPF(cpf)
        input.val(cpf)
      })
      
      let $form = $('.woocommerce .woocommerce-checkout')
      if ($form.length === 0) {
        $form = $('#order_review')
      }
      const selectedPaymentMethod = $form.find('input[name="payment_method"]:checked')
      const inputCreditNumber = $form.find('#maxipago-card-number')
      if (selectedPaymentMethod.length && selectedPaymentMethod.val() !== 'maxipago_credit' && inputCreditNumber.length === 0) {
        return
      }

      const inputSelectors = {
        numberInput: '#maxipago-card-number',
        nameInput: '#maxipago-card-holder-name',
        expiryInput: '#maxipago-card-expiry',
        cvcInput: '#maxipago-card-cvc'
      }

      // maybe delete old card data
      $form.data('card', null)

      // init animated card. A lib jquery.card pode estar carregada por outro
      // gateway (ex.: Cielo) com o container ausente; só inicializa quando o
      // container deste formulário existe no DOM (evita o erro de getAttribute).
      if (typeof $form.card === 'function' && document.querySelector('#maxipago-card-animation')) {
        $form.card({
          container: '#maxipago-card-animation',

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
          debug: !!window.wooMaxipagoVars.debug
        })
      }

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
    const paymentBoxP = document.querySelector('.payment_box.payment_method_maxipago_credit p');
    if (paymentBoxP) {
      paymentBoxP.style.display = 'none';
    }
    
    // Adicionar listener simples no select de parcelas
    addInstallmentListener();
  }

  function addInstallmentListener() {
    const installmentSelect = document.querySelector('#maxipago-card-installments');
    if (installmentSelect && !installmentSelect.hasAttribute('data-listener-added')) {
      installmentSelect.addEventListener('change', function(e) {
        const installments = parseInt(e.target.value, 10) || 1;
        // Atualizar sessão com nova parcela
        updateInstallmentSession('maxipago_credit', installments);
      });
      installmentSelect.setAttribute('data-listener-added', 'true');
    }
  }

  function updateInstallmentSession(paymentMethod, installments) {
    $.ajax({
      url: wooMaxipagoVars.ajaxurl,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'lkn_update_installment_session',
        nonce: wooMaxipagoVars.nonce,
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