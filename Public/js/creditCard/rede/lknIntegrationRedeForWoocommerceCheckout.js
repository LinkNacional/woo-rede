import React from 'react';
import Cards from 'react-credit-cards';
import 'react-credit-cards/es/styles-compiled.css';
const settingsRedeCredit = window.wc.wcSettings.getSetting('rede_credit_data', {});
const labelRedeCredit = window.wp.htmlEntities.decodeEntities(settingsRedeCredit.title);
// Obtendo o nonce da variável global
const nonceRedeCredit = settingsRedeCredit.nonceRedeCredit;
const translationsRedeCredit = settingsRedeCredit.translations;
const minInstallmentsRede = settingsRedeCredit.minInstallmentsRede.replace(',', '.');
// Formatação padronizada dos campos de cartão (espelhada em Public/js/rede-card-fields.js).
const lknOnlyDigits = value => String(value == null ? '' : value).replace(/\D/g, '');
const lknFormatCardExpiry = value => {
  const digits = lknOnlyDigits(value);
  let month = digits.slice(0, 2);
  let year = digits.slice(2);
  if (month.length === 1 && month >= '2' && month <= '9') {
    month = '0' + month;
  } else if (month.length === 2 && parseInt(month, 10) > 12) {
    month = '12';
  }
  if (year.length > 2) year = year.slice(-2);
  return year.length ? month + '/' + year : month;
};
const ContentRedeCredit = props => {
  const totalAmountFloat = settingsRedeCredit.cartTotal;
  const [selectedValue, setSelectedValue] = window.wp.element.useState('1');
  const handleSortChange = event => {
    const value = String(event.target.value); // Garante que seja string
    setSelectedValue(value);
    updateCreditObject('rede_credit_installments', value);
    
    // Faz requisição AJAX para atualizar a sessão de parcelas
    window.jQuery.ajax({
      url: window.redeCreditAjax?.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php',
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'lkn_update_installment_session',
        payment_method: 'rede_credit',
        installments: value,
        nonce: window.redeCreditAjax?.installment_nonce
      },
      success: function (response) {
        // Invalida o cache do store para atualizar os dados apenas no sucesso da requisição
        if (window.wp && window.wp.data && window.wp.data.dispatch) {
          window.wp.data.dispatch('wc/store/cart').invalidateResolutionForStore();
        }
      },
      error: function () {
        // Em caso de erro, pode manter o comportamento atual ou mostrar uma mensagem
      }
    });
  };
  const {
    eventRegistration,
    emitResponse
  } = props;
  const {
    onPaymentSetup
  } = eventRegistration;
  const wcComponents = window.wc.blocksComponents;
  const [creditObject, setCreditObject] = window.wp.element.useState({
    rede_credit_number: '',
    rede_credit_installments: '1',
    rede_credit_expiry: '',
    rede_credit_cvc: '',
    rede_credit_holder_name: ''
  });
  
  const [focus, setFocus] = window.wp.element.useState('');
  const [options, setOptions] = window.wp.element.useState([]);

  // useCallback para estabilizar a função updateCreditObject
  const updateCreditObject = window.wp.element.useCallback((key, value) => {
    switch (key) {
      case 'rede_credit_expiry':
        setCreditObject(prevState => ({
          ...prevState,
          [key]: lknFormatCardExpiry(value)
        }));
        return;
      case 'rede_credit_cvc':
        setCreditObject(prevState => ({
          ...prevState,
          [key]: lknOnlyDigits(value).slice(0, 4)
        }));
        return;
      default:
        break;
    }
    setCreditObject(prevState => ({
      ...prevState,
      [key]: value
    }));
  }, []); // Sem dependências pois usa função de atualização

  // useCallback para estabilizar generateRedeInstallmentOptions
  const generateRedeInstallmentOptions = window.wp.element.useCallback(() => {
    window.jQuery.ajax({
      url: window.redeCreditAjax?.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php',
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'lkn_get_rede_credit_data',
        nonce: window.redeCreditAjax?.nonce || nonceRedeCredit
      },
      success: function (response) {
        if (response && Array.isArray(response.installments)) {
          const plainOptions = response.installments.map(opt => {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = opt.label;
            return {
              ...opt,
              label: tempDiv.textContent || tempDiv.innerText || ''
            };
          });
          
          setOptions(plainOptions);
          
          // Invalida o cache do store após atualizar as opções
          if (window.wp && window.wp.data && window.wp.data.dispatch) {
            window.wp.data.dispatch('wc/store/cart').invalidateResolutionForStore();
          }
        }
      }
    });
  }, []);

  // Intercepta requisições para atualizar parcelas após mudanças no shipping e cart totals
  window.wp.element.useEffect(() => {
    generateRedeInstallmentOptions();

    // Store do valor total atual para comparação
    let currentCartTotal = settingsRedeCredit.cartTotal || 0;

    const originalFetch = window.fetch;
    window.fetch = function(...args) {
      const [url, options] = args;
      
      // Verifica se é uma requisição para select-shipping-rate
      if (url && url.includes('/wp-json/wc/store/v1/cart/select-shipping-rate')) {
        return originalFetch.apply(this, args).then(response => {
          if (response.ok) {
            setTimeout(() => {
              setOptions([]);
              setSelectedValue('1');
              updateCreditObject('rede_credit_installments', '1');
              generateRedeInstallmentOptions();
            }, 500);
          }
          return response;
        }).catch(error => {
          return originalFetch.apply(this, args);
        });
      }
      
      // Verifica se é uma requisição batch da WooCommerce Store API
      if (url && url.includes('/wp-json/wc/store/v1/batch')) {
        // Executa a requisição original
        return originalFetch.apply(this, args).then(response => {
          // Clona a response para poder ler o conteúdo e verificar mudanças no total
          const responseClone = response.clone();
          
          // Verifica se a requisição foi bem-sucedida
          if (response.ok) {
            // Lê o conteúdo da resposta para verificar se há mudanças no total
            responseClone.json().then(batchData => {
              let totalChanged = false;
              
              // Verifica se há dados de carrinho na resposta batch
              if (batchData && batchData.responses) {
                batchData.responses.forEach(batchResponse => {
                  // Verifica se é uma resposta de carrinho e se tem dados válidos
                  if (batchResponse && batchResponse.body && 
                      (batchResponse.body.totals || batchResponse.body.cart_totals)) {
                    
                    const cartData = batchResponse.body;
                    let newTotal = 0;
                    
                    // Extrai o total do carrinho da resposta
                    if (cartData.totals && cartData.totals.total_price) {
                      // Parse do valor removendo símbolos de moeda
                      const totalString = cartData.totals.total_price.replace(/[^\d.,]/g, '');
                      const normalizedTotal = totalString.replace(',', '.');
                      newTotal = parseFloat(normalizedTotal) || 0;
                    } else if (cartData.cart_totals && cartData.cart_totals.total_price) {
                      // Parse do valor removendo símbolos de moeda
                      const totalString = cartData.cart_totals.total_price.replace(/[^\d.,]/g, '');
                      const normalizedTotal = totalString.replace(',', '.');
                      newTotal = parseFloat(normalizedTotal) || 0;
                    }
                    
                    // Compara com o total atual (tolerância de 0.01 para diferenças de arredondamento)
                    if (Math.abs(newTotal - currentCartTotal) > 0.01) {
                      totalChanged = true;
                      currentCartTotal = newTotal;
                    }
                  }
                });
              }
              
              // Se o total mudou, atualiza as parcelas
              if (totalChanged) {
                // Aguarda um momento para garantir que os dados foram processados
                setTimeout(() => {
                  // Limpa as opções atuais e busca as novas
                  setOptions([]);
                  setSelectedValue('1');
                  updateCreditObject('rede_credit_installments', '1');
                  generateRedeInstallmentOptions();
                }, 300);
              }
            }).catch(error => {
              // Em caso de erro ao processar o JSON, apenas continua
              console.warn('Erro ao processar dados do batch da Store API:', error);
            });
          }
          
          // Retorna a response original
          return response;
        }).catch(error => {
          // Em caso de erro, retorna a response original
          return originalFetch.apply(this, args);
        });
      }
      
      // Para outras requisições, executa normalmente
      return originalFetch.apply(this, args);
    };

    return () => {
      window.fetch = originalFetch;
    };
  }, [generateRedeInstallmentOptions, updateCreditObject]);

  // formatCreditCardNumber como useCallback
  const formatCreditCardNumber = window.wp.element.useCallback((value) => {
    if (value?.length > 24) return creditObject.rede_credit_number;
    const cleanedValue = value?.replace(/\D/g, '');
    return cleanedValue?.replace(/(.{4})/g, '$1 ')?.trim();
  }, [creditObject.rede_credit_number]);

  // useEffect otimizado sem creditObject como dependência
  window.wp.element.useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      // Usa uma ref ou callback para obter o estado atual
      return new Promise((resolve) => {
        setCreditObject(currentState => {
          const allFieldsFilled = Object.values(currentState).every(field => {
            const fieldStr = typeof field === 'string' ? field : String(field);
            return fieldStr.trim() !== '';
          });
          
          if (allFieldsFilled) {
            resolve({
              type: emitResponse.responseTypes.SUCCESS,
              meta: {
                paymentMethodData: {
                  rede_credit_number: currentState.rede_credit_number,
                  rede_credit_installments: currentState.rede_credit_installments,
                  rede_credit_expiry: currentState.rede_credit_expiry,
                  rede_credit_cvc: currentState.rede_credit_cvc,
                  rede_credit_holder_name: currentState.rede_credit_holder_name,
                  rede_card_nonce: nonceRedeCredit
                }
              }
            });
          } else {
            resolve({
              type: emitResponse.responseTypes.ERROR,
              message: translationsRedeCredit.fieldsNotFilled
            });
          }
          
          return currentState; // Retorna o estado sem modificá-lo
        });
      });
    });

    return () => {
      unsubscribe();
    };
  }, [onPaymentSetup, emitResponse.responseTypes.SUCCESS, emitResponse.responseTypes.ERROR, translationsRedeCredit]);

  return (
    <React.Fragment>
      <Cards
        number={creditObject.rede_credit_number}
        name={creditObject.rede_credit_holder_name}
        expiry={creditObject.rede_credit_expiry.replace(/\s+/g, '')}
        cvc={creditObject.rede_credit_cvc}
        placeholders={{
          name: 'NOME',
          expiry: 'MM/ANO',
          cvc: 'CVC',
          number: '•••• •••• •••• ••••'
        }}
        locale={{ valid: 'VÁLIDO ATÉ' }}
        focused={focus}
      />
      <wcComponents.TextInput
        id="rede_credit_holder_name"
        label={translationsRedeCredit.nameOnCard}
        value={creditObject.rede_credit_holder_name}
        maxLength={30}
        onChange={value => updateCreditObject('rede_credit_holder_name', value)}
        onFocus={() => setFocus('name')}
      />
      <wcComponents.TextInput
        id="rede_credit_number"
        label={translationsRedeCredit.cardNumber}
        value={formatCreditCardNumber(creditObject.rede_credit_number)}
        inputMode="numeric"
        onChange={value => updateCreditObject('rede_credit_number', formatCreditCardNumber(value))}
        onFocus={() => setFocus('number')}
      />
      <wcComponents.TextInput
        id="rede_credit_expiry"
        label={translationsRedeCredit.cardExpiringDate}
        value={creditObject.rede_credit_expiry}
        inputMode="numeric"
        onChange={value => updateCreditObject('rede_credit_expiry', value)}
        onFocus={() => setFocus('expiry')}
      />
      <wcComponents.TextInput
        id="rede_credit_cvc"
        label={translationsRedeCredit.securityCode}
        value={creditObject.rede_credit_cvc}
        inputMode="numeric"
        onChange={value => updateCreditObject('rede_credit_cvc', value)}
        onFocus={() => setFocus('cvc')}
      />
      {options.length > 1 && (
        <div className="lknIntegrationRedeForWoocommerceSelectBlocks">
          <label>{translationsRedeCredit.installments}</label>
          <select
            value={selectedValue}
            onChange={handleSortChange}
            readOnly={false}
          >
            {options.map(opt => (
              <option key={opt.key} value={opt.key}>{opt.label}</option>
            ))}
          </select>
        </div>
      )}
    </React.Fragment>
  );
}
const BlockGatewayRedeCredit = {
  name: 'rede_credit',
  label: labelRedeCredit,
  content: window.wp.element.createElement(ContentRedeCredit),
  edit: window.wp.element.createElement(ContentRedeCredit),
  canMakePayment: () => true,
  ariaLabel: labelRedeCredit,
  supports: {
    features: settingsRedeCredit.supports
  }
};
window.wc.wcBlocksRegistry.registerPaymentMethod(BlockGatewayRedeCredit);