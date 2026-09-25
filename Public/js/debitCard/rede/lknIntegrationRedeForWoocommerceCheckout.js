import React from 'react';
import Cards from 'react-credit-cards';
import 'react-credit-cards/es/styles-compiled.css';
const settingsRedeDebit = window.wc.wcSettings.getSetting('rede_debit_data', {});
const labelRedeDebit = window.wp.htmlEntities.decodeEntities(settingsRedeDebit.title);
// Obtendo o nonce da variável global
const nonceRedeDebit = settingsRedeDebit.nonceRedeDebit;
const translationsRedeDebit = settingsRedeDebit.translations;
const cardTypeRestriction = settingsRedeDebit.cardTypeRestriction || 'debit_only';
const hideCardTypeSelector = settingsRedeDebit.hideCardTypeSelector || 'no';
// Mostra o seletor sempre que a restrição permite ambos os tipos; com um único tipo, só mostra se a opção de escondê-lo não estiver habilitada.
const showCardTypeSelector = cardTypeRestriction === 'both' ? true : hideCardTypeSelector !== 'yes';
// Com um único tipo, o seletor aparece porém "travado" (cinza/aparentando disabled).
// O bloqueio é puramente visual (CSS + atributos ARIA); NÃO usamos o atributo disabled
// para o valor continuar sendo enviado no checkout.
const lockCardTypeSelector = cardTypeRestriction !== 'both' && showCardTypeSelector;
// O background-color não vai aqui: alguns temas sobrescrevem o inline style, então ele é
// aplicado com !important via setProperty em um efeito (ver ContentRedeDebit).
const lockedSelectStyle = lockCardTypeSelector
  ? { color: '#767676', pointerEvents: 'none', cursor: 'not-allowed' }
  : undefined;
// Opções do seletor de tipo de cartão (tipo fixo quando a restrição é de um único tipo).
const cardTypeOptions = cardTypeRestriction === 'credit_only'
  ? [['credit', translationsRedeDebit.creditCard]]
  : cardTypeRestriction === 'debit_only'
    ? [['debit', translationsRedeDebit.debitCard]]
    : [['debit', translationsRedeDebit.debitCard], ['credit', translationsRedeDebit.creditCard]];
const minInstallmentsRede = settingsRedeDebit.minInstallmentsRede ? settingsRedeDebit.minInstallmentsRede.replace(',', '.') : '5.00';
const templateStyle = settingsRedeDebit['3dsTemplateStyle'] || 'basic';
const gatewayDescription = settingsRedeDebit.gatewayDescription || '';
const cardTemplateAssets = window.redeDebitAjax?.cardTemplateAssets || {};
// Opções do gateway: cartão animado (grátis) e bandeiras (PRO). Default ligadas.
const showCardAnimation = (settingsRedeDebit.showCardAnimation || 'yes') !== 'no';
const showCardBrandIcons = (settingsRedeDebit.showCardBrandIcons || 'yes') !== 'no';

// Flag de proteção contra duplo envio do checkout (evita duplicidade de transações)
let redeCheckoutSubmitted = false;
const REDE_CHECKOUT_SESSION_KEY = 'rede_checkout_processing';

// Observer global para adicionar as bandeiras ao lado do TÍTULO do método (fora do
// componente React). Vale para TODOS os layouts (Basic/Modern/Compact). A opção
// "Show card brand icons" controla essa faixa. O compacto mostra as bandeiras no
// CAMPO de número de forma independente (ver renderCompactTemplate).
{
  const addCardBrandIcons = () => {
    const radioInput = document.querySelector('input[value="rede_debit"][type="radio"]');
    if (!radioInput) return;

    const label = radioInput.closest('label');
    if (!label) return;

    const labelGroup = label.querySelector('.wc-block-components-radio-control__label-group');
    if (!labelGroup) return;

    // Classe de estilos do template moderno — só no layout moderno.
    if (templateStyle === 'modern') {
      const paymentContent = document.querySelector('#radio-control-wc-payment-method-options-rede_debit__content');
      if (paymentContent) {
        paymentContent.classList.add('rede-modern-template-active');
      }
    }

    // Bandeiras ao lado do título desativadas pela opção "Show card brand icons".
    if (!showCardBrandIcons) {
      return;
    }

    // Verifica se já foram adicionados os ícones
    if (labelGroup.querySelector('.rede-card-brands')) {
      return;
    }

    // Aplica estilos ao labelGroup
    labelGroup.style.display = 'flex';
    labelGroup.style.justifyContent = 'space-between';
    labelGroup.style.alignItems = 'center';
    labelGroup.style.gap = '10px';
    
    // Função para aplicar estilos responsivos
    const applyResponsiveStyles = () => {
      if (window.innerWidth <= 768) {
        labelGroup.style.flexDirection = 'column';
      } else {
        labelGroup.style.flexDirection = 'row';
      }
    };
    
    // Aplica estilos iniciais
    applyResponsiveStyles();
    
    // Adiciona listener para mudanças de tamanho da tela (apenas uma vez)
    if (!labelGroup.hasAttribute('data-resize-listener')) {
      labelGroup.setAttribute('data-resize-listener', 'true');
      window.addEventListener('resize', applyResponsiveStyles);
    }

    // Cria container dos ícones das bandeiras
    const cardBrandsContainer = document.createElement('div');
    cardBrandsContainer.className = 'rede-card-brands';
    cardBrandsContainer.style.display = 'flex';
    cardBrandsContainer.style.flexDirection = 'row';
    cardBrandsContainer.style.flexWrap = 'wrap';
    cardBrandsContainer.style.alignItems = 'center';
    cardBrandsContainer.style.gap = '8px';

    // Adiciona ícones das bandeiras
    const brands = [
      { key: 'visa', src: cardTemplateAssets.visa },
      { key: 'mastercard', src: cardTemplateAssets.mastercard },
      { key: 'amex', src: cardTemplateAssets.amex },
      { key: 'elo', src: cardTemplateAssets.elo },
      { key: 'otherCard', src: cardTemplateAssets.otherCard }
    ];

    brands.forEach(brand => {
      if (brand.src) {
        const img = document.createElement('img');
        img.src = brand.src;
        img.alt = brand.key;
        img.style.width = '40px';
        img.style.height = '40px';
        img.style.objectFit = 'contain';
        cardBrandsContainer.appendChild(img);
      }
    });

    labelGroup.appendChild(cardBrandsContainer);
  };

  // Observer global que roda independentemente do React
  const globalObserver = new MutationObserver((mutations) => {
    let shouldCheck = false;
    let shouldResetBrands = false;
    
    mutations.forEach((mutation) => {
      if (mutation.type === 'childList' && mutation.target.closest && 
          (mutation.target.closest('.wc-block-components-radio-control') || 
           mutation.target.querySelector && mutation.target.querySelector('input[value="rede_debit"]'))) {
        shouldCheck = true;
      }
      
      // Detecta mudanças em outros gateways (quando Rede Debit é desmarcado)
      if (mutation.type === 'childList') {
        const redeDebitRadio = document.querySelector('input[value="rede_debit"][type="radio"]');
        if (redeDebitRadio && !redeDebitRadio.checked) {
          shouldResetBrands = true;
        }
      }
    });
    
    if (shouldCheck) {
      setTimeout(addCardBrandIcons, 50);
    }
    
    if (shouldResetBrands) {
      // Reset das bandeiras quando gateway não é Rede Debit
      setTimeout(() => {
        const brandContainer = document.querySelector('.rede-card-brands');
        if (brandContainer) {
          const allBrandImages = brandContainer.querySelectorAll('img');
          allBrandImages.forEach((img) => {
            img.style.setProperty('filter', 'none', 'important');
            img.style.setProperty('opacity', '1', 'important');
            img.style.setProperty('transition', 'all 0.3s ease', 'important');
          });
        }
        
        // Remove classe do template moderno quando não é Rede Debit
        const paymentContent = document.querySelector('#radio-control-wc-payment-method-options-rede_debit__content');
        if (paymentContent) {
          paymentContent.classList.remove('rede-modern-template-active');
        }
      }, 100);
    }
  });

  // Inicia observação quando o DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      globalObserver.observe(document.body, { childList: true, subtree: true });
      setTimeout(() => {
        addCardBrandIcons();
      }, 100);
    });
  } else {
    globalObserver.observe(document.body, { childList: true, subtree: true });
    setTimeout(() => {
      addCardBrandIcons();
    }, 100);
  }
}

const ContentRedeDebit = props => {
  const totalAmountFloat = settingsRedeDebit.cartTotal;
  const [selectedValue, setSelectedValue] = window.wp.element.useState('1');
  const handleSortChange = event => {
    const value = String(event.target.value); // Garante que seja string
    setSelectedValue(value);
    updateDebitObject('rede_debit_installments', value);
    
    // Faz requisição AJAX para atualizar a sessão de parcelas
    window.jQuery.ajax({
      url: window.redeDebitAjax?.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php',
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'lkn_update_installment_session',
        payment_method: 'rede_debit',
        installments: value,
        card_type: debitObject.card_type,
        nonce: window.redeDebitAjax?.installment_nonce
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
  const [debitObject, setDebitObject] = window.wp.element.useState({
    rede_debit_number: '',
    rede_debit_installments: '1',
    rede_debit_expiry: '',
    rede_debit_cvc: '',
    rede_debit_holder_name: '',
    card_type: cardTypeRestriction === 'both' ? 'credit' : (cardTypeRestriction === 'credit_only' ? 'credit' : 'debit')
  });
  
  const [focus, setFocus] = window.wp.element.useState('');
  const [options, setOptions] = window.wp.element.useState([]);
  const [detectedBrand, setDetectedBrand] = window.wp.element.useState(null);
  const [brandDetectionTimeout, setBrandDetectionTimeout] = window.wp.element.useState(null);

  // Limpa flags de processamento ao montar o componente (evita que sessionStorage sujo
  // de um teste/crash anterior bloqueie o checkout na próxima carga da página)
  window.wp.element.useEffect(() => {
    sessionStorage.removeItem(REDE_CHECKOUT_SESSION_KEY);
    redeCheckoutSubmitted = false;
  }, []);

  // Aplica o background-color do seletor travado com !important (alguns temas
  // sobrescrevem o inline style sem prioridade). Não usamos o atributo disabled
  // para o valor continuar sendo enviado no checkout.
  window.wp.element.useEffect(() => {
    const select = document.getElementById('card_type_selector');
    if (!select) return;
    if (lockCardTypeSelector) {
      select.style.setProperty('background-color', '#f0f0f1', 'important');
    } else {
      select.style.removeProperty('background-color');
    }
  }, [lockCardTypeSelector, templateStyle]);

  // Placeholders personalizáveis (seção "Fields" do admin): o TextInput do Blocks
  // não aceita a prop placeholder, então aplicamos direto no input. Vale para
  // todos os layouts (o React renderiza os campos em todos).
  window.wp.element.useEffect(() => {
    const ph = settingsRedeDebit.fieldPlaceholders || {}
    const map = {
      rede_debit_holder_name: ph.holder_name,
      rede_debit_number: ph.card_number,
      rede_debit_expiry: ph.expiry,
      rede_debit_cvc: ph.cvc
    }
    const applyPlaceholders = () => {
      Object.keys(map).forEach((id) => {
        const el = document.getElementById(id)
        if (el && map[id]) {
          el.setAttribute('placeholder', map[id])
        }
      })
    }
    applyPlaceholders()
    const t1 = setTimeout(applyPlaceholders, 400)
    const t2 = setTimeout(applyPlaceholders, 1200)
    return () => {
      clearTimeout(t1)
      clearTimeout(t2)
    }
  }, []);

  // Função para buscar dados atualizados do backend e gerar as opções de installments (com debounce)
  let installmentTimeout = null;
  const generateRedeInstallmentOptions = async () => {
    if (installmentTimeout) clearTimeout(installmentTimeout);
    installmentTimeout = setTimeout(() => {
      try {
        window.jQuery.ajax({
          url: window.redeDebitAjax?.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php',
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'lkn_get_rede_debit_data',
            card_type: debitObject.card_type,
            nonce: window.redeDebitAjax?.nonce || nonceRedeDebit
          },
          success: function (response) {
            // Invalida o cache do store para atualizar os dados
            if (window.wp && window.wp.data && window.wp.data.dispatch) {
              window.wp.data.dispatch('wc/store/cart').invalidateResolutionForStore();
            }
            
            if (response && Array.isArray(response.installments)) {
              // Remove tags HTML do label para exibir texto plano
              const plainOptions = response.installments.map(opt => {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = opt.label;
                return {
                  ...opt,
                  label: tempDiv.textContent || tempDiv.innerText || ''
                };
              });
              
              // Remove todas as opções atuais e adiciona as novas
              setOptions(plainOptions);
              
              // Sempre garante que há uma opção selecionada válida
              const currentSelection = selectedValue || '1';
              const validOption = plainOptions.find(opt => String(opt.key) === String(currentSelection));
              
              if (!validOption && plainOptions.length > 0) {
                // Se a seleção atual não é válida, seleciona a primeira opção
                const firstOption = String(plainOptions[0].key);
                setSelectedValue(firstOption);
                updateDebitObject('rede_debit_installments', firstOption);
              } else if (validOption && selectedValue !== String(validOption.key)) {
                // Se a opção é válida mas o state não está sincronizado, atualiza
                setSelectedValue(String(validOption.key));
                updateDebitObject('rede_debit_installments', String(validOption.key));
              }
              
              // Invalida o cache do store após atualizar as opções
              if (window.wp && window.wp.data && window.wp.data.dispatch) {
                window.wp.data.dispatch('wc/store/cart').invalidateResolutionForStore();
              }
            }
          },
          error: function () {
            // Se falhar, mantém as opções atuais
          }
        });
      } catch (error) {
        // Se falhar, mantém as opções atuais
      }
    }, 400); // 400ms de debounce
  };

  // Intercepta requisições para atualizar parcelas após mudanças no shipping e cart totals
  window.wp.element.useEffect(() => {
    // Sempre faz a requisição para atualizar a sessão (tanto para crédito quanto débito)
    generateRedeInstallmentOptions();
    
    // Se for débito, ainda limpa as opções do frontend
    if (debitObject.card_type === 'debit') {
      setOptions([]);
      setSelectedValue('1');
      updateDebitObject('rede_debit_installments', '1');
    }

    // Store do valor total atual para comparação
    let currentCartTotal = settingsRedeDebit.cartTotal || 0;

    // Intercepta o fetch original para capturar requisições da Store API
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
      const [url, options] = args;
      
      // Verifica se é uma requisição para select-shipping-rate
      if (url && url.includes('/wp-json/wc/store/v1/cart/select-shipping-rate')) {
        // Executa a requisição original
        return originalFetch.apply(this, args).then(response => {
          // Clona a response para poder ler o conteúdo
          const responseClone = response.clone();
          
          // Verifica se a requisição foi bem-sucedida
          if (response.ok) {
            // Aguarda um breve momento para a atualização do carrinho e então atualiza as parcelas
            setTimeout(() => {
              // Só atualiza se for cartão de crédito
              if (cardTypeRestriction === 'credit_only' || debitObject.card_type === 'credit') {
                // Limpa as opções atuais e busca as novas
                setOptions([]);
                setSelectedValue('1');
                updateDebitObject('rede_debit_installments', '1'); // Garante que seja string
                generateRedeInstallmentOptions();
              }
            }, 500);
          }
          
          // Retorna a response original
          return response;
        }).catch(error => {
          // Em caso de erro, retorna a response original
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
              
              // Se o total mudou e é cartão de crédito, atualiza as parcelas
              if (totalChanged && (cardTypeRestriction === 'credit_only' || debitObject.card_type === 'credit')) {
                // Aguarda um momento para garantir que os dados foram processados
                setTimeout(() => {
                  // Limpa as opções atuais e busca as novas
                  setOptions([]);
                  setSelectedValue('1');
                  updateDebitObject('rede_debit_installments', '1');
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

    // Cleanup: restaura o fetch original quando o componente é desmontado
    return () => {
      window.fetch = originalFetch;
    };
  }, [debitObject.card_type]);

  // useEffect para resetar bandeiras e limpar flags de checkout quando componente for desmontado
  window.wp.element.useEffect(() => {
    return () => {
      // Cleanup: reset das bandeiras quando o componente é desmontado (mudança de gateway)
      if (templateStyle === 'modern' || templateStyle === 'compact') {
        const brandContainer = document.querySelector('.rede-card-brands, .rede-compact-card-brands');
        if (brandContainer) {
          const allBrandImages = brandContainer.querySelectorAll('img');
          allBrandImages.forEach((img) => {
            img.style.setProperty('filter', 'none', 'important');
            img.style.setProperty('opacity', '1', 'important');
            img.style.setProperty('transition', 'all 0.3s ease', 'important');
          });
        }
      }
      // Limpa a flag de processamento do sessionStorage ao desmontar
      sessionStorage.removeItem(REDE_CHECKOUT_SESSION_KEY);
      redeCheckoutSubmitted = false;
    };
  }, []);

  // useEffect para gerenciar eventos de focus e blur nos campos do cartão
  window.wp.element.useEffect(() => {
    const handleFocusBlur = () => {
      // Lista de IDs dos campos do cartão
      const cardFields = [
        'rede_debit_number',
        'rede_debit_holder_name', 
        'rede_debit_expiry',
        'rede_debit_cvc'
      ];

      cardFields.forEach(fieldId => {
        const input = document.getElementById(fieldId);
        if (input) {
          // Remove event listeners existentes para evitar duplicação
          input.removeEventListener('focus', handleFieldFocus);
          input.removeEventListener('blur', handleFieldBlur);
          
          // Adiciona os novos event listeners
          input.addEventListener('focus', handleFieldFocus);
          input.addEventListener('blur', handleFieldBlur);

          // Verifica se o campo já tem valor e aplica a classe is-active
          const container = input.closest('.wc-block-components-text-input');
          if (container && input.value && input.value.trim() !== '') {
            container.classList.add('is-active');
          }
        }
      });

      function handleFieldFocus(event) {
        const input = event.target;
        const container = input.closest('.wc-block-components-text-input');
        if (container && !container.classList.contains('is-active')) {
          container.classList.add('is-active');
        }
      }

      function handleFieldBlur(event) {
        const input = event.target;
        const container = input.closest('.wc-block-components-text-input');
        if (container) {
          // Remove a classe is-active apenas se o campo estiver vazio
          if (!input.value || input.value.trim() === '') {
            container.classList.remove('is-active');
          }
          // Se o campo tem valor, mantém a classe is-active
        }
      }

      // Cleanup function para remover os event listeners
      return () => {
        cardFields.forEach(fieldId => {
          const input = document.getElementById(fieldId);
          if (input) {
            input.removeEventListener('focus', handleFieldFocus);
            input.removeEventListener('blur', handleFieldBlur);
          }
        });
      };
    };

    // Executa imediatamente e configura um observer para mudanças no DOM
    const setupListeners = handleFocusBlur();

    // Observer para detectar quando novos elementos são adicionados ao DOM
    const observer = new MutationObserver(() => {
      // Aguarda um pouco para garantir que os elementos foram renderizados
      setTimeout(handleFocusBlur, 100);
    });

    // Observa mudanças no container do formulário
    const paymentContainer = document.querySelector('#radio-control-wc-payment-method-options-rede_debit__content');
    if (paymentContainer) {
      observer.observe(paymentContainer, { 
        childList: true, 
        subtree: true 
      });
    } else {
      // Se não encontrou o container específico, observa o body
      observer.observe(document.body, { 
        childList: true, 
        subtree: true 
      });
    }

    // Cleanup geral
    return () => {
      observer.disconnect();
      if (setupListeners) {
        setupListeners();
      }
    };
  }, [debitObject]); // Reexecuta quando debitObject muda

  const formatDebitCardNumber = value => {
    if (value?.length > 24) return debitObject.rede_debit_number;
    // Remove caracteres não numéricos
    const cleanedValue = value?.replace(/\D/g, '');
    // Adiciona espaços a cada quatro dígitos
    const formattedValue = cleanedValue?.replace(/(.{4})/g, '$1 ')?.trim();
    return formattedValue;
  };

  // Função para detectar bandeira do cartão
  const detectCardBrand = (cardNumber) => {
    const cleanNumber = cardNumber.replace(/\s/g, '');
    
    // Limpa timeout anterior sempre
    if (brandDetectionTimeout) {
      clearTimeout(brandDetectionTimeout);
    }
    
    // Se não tiver nada digitado, volta ao estado normal
    if (cleanNumber.length === 0) {
      setDetectedBrand(null);
      updateCardBrandStyles(null);
      return;
    }
    
    // Se tem menos de 6 dígitos, deixa tudo cinza sem timeout
    if (cleanNumber.length > 0 && cleanNumber.length < 6) {
      setDetectedBrand('loading');
      updateCardBrandStyles('loading');
      return;
    }

    // Cria novo timeout apenas se tem 6+ dígitos
    const timeout = setTimeout(() => {
      window.jQuery.ajax({
        url: window.redeDebitAjax?.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php',
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'lkn_get_offline_bin_card',
          number: cleanNumber,
          nonce: window.redeDebitAjax?.bin_detection_nonce
        },
        success: function (response) {
          if (response.status && response.brand) {
            setDetectedBrand(response.brand);
            updateCardBrandStyles(response.brand);
          } else {
            setDetectedBrand('other');
            updateCardBrandStyles('other');
          }
        },
        error: function () {
          setDetectedBrand('other');
          updateCardBrandStyles('other');
        }
      });
    }, 1500);

    setBrandDetectionTimeout(timeout);
  };

  // Função para atualizar estilos das bandeiras (label do moderno ou campo do compacto)
  const updateCardBrandStyles = (detectedBrand) => {
    const brandContainers = document.querySelectorAll('.rede-card-brands, .rede-compact-card-brands');
    if (!brandContainers.length) {
      return;
    }

    const supportedBrands = ['visa', 'mastercard', 'amex', 'elo'];

    brandContainers.forEach(brandContainer => {
      const allBrandImages = brandContainer.querySelectorAll('img');
      if (allBrandImages.length === 0) {
        return;
      }

      allBrandImages.forEach((img) => {
        const brandKey = img.alt;

        if (detectedBrand === null) {
          // Sem detecção - todos normais
          img.style.setProperty('filter', 'none', 'important');
          img.style.setProperty('opacity', '1', 'important');
          img.style.setProperty('transition', 'all 0.3s ease', 'important');
        } else if (detectedBrand === 'loading') {
          // Estado de carregamento - todos cinza
          img.style.setProperty('filter', 'grayscale(1)', 'important');
          img.style.setProperty('opacity', '0.4', 'important');
          img.style.setProperty('transition', 'all 0.3s ease', 'important');
        } else if (brandKey === 'otherCard') {
          // Other card sempre ativo se não for uma das principais
          if (supportedBrands.includes(detectedBrand)) {
            img.style.setProperty('filter', 'grayscale(1)', 'important');
            img.style.setProperty('opacity', '0.4', 'important');
          } else {
            img.style.setProperty('filter', 'none', 'important');
            img.style.setProperty('opacity', '1', 'important');
          }
          img.style.setProperty('transition', 'all 0.3s ease', 'important');
        } else if (brandKey === detectedBrand) {
          // Bandeira detectada - ativa
          img.style.setProperty('filter', 'none', 'important');
          img.style.setProperty('opacity', '1', 'important');
          img.style.setProperty('transition', 'all 0.3s ease', 'important');
        } else {
          // Outras bandeiras - cinza
          img.style.setProperty('filter', 'grayscale(1)', 'important');
          img.style.setProperty('opacity', '0.4', 'important');
          img.style.setProperty('transition', 'all 0.3s ease', 'important');
        }
      });
    });
  };

  // Formatação padronizada (espelhada em Public/js/rede-card-fields.js).
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

  const updateDebitObject = (key, value) => {
    switch (key) {
      case 'rede_debit_expiry':
        setDebitObject(prevState => ({
          ...prevState,
          [key]: lknFormatCardExpiry(value)
        }));
        return;
      case 'rede_debit_cvc':
        setDebitObject(prevState => ({
          ...prevState,
          [key]: lknOnlyDigits(value).slice(0, 4)
        }));
        return;
      default:
        break;
    }
    setDebitObject(prevState => ({
      ...prevState,
      [key]: value
    }));

    // Detecta bandeira do cartão quando o número é alterado (TODOS os layouts).
    // Aplica às bandeiras da faixa do título e às do CAMPO do compacto.
    if (key === 'rede_debit_number') {
      detectCardBrand(value);
    }
  };
  window.wp.element.useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      // Verifica se todos os campos obrigatórios estão preenchidos
      const requiredFields = ['rede_debit_number', 'rede_debit_expiry', 'rede_debit_cvc', 'rede_debit_holder_name'];
      if (cardTypeRestriction === 'both') {
        requiredFields.push('card_type');
      }
      
      const allFieldsFilled = requiredFields.every(field => debitObject[field] && debitObject[field].trim() !== '');
      if (allFieldsFilled) {
        return {
          type: emitResponse.responseTypes.SUCCESS,
          meta: {
            paymentMethodData: {
              rede_debit_number: debitObject.rede_debit_number,
              rede_debit_installments: debitObject.rede_debit_installments,
              rede_debit_expiry: debitObject.rede_debit_expiry,
              rede_debit_cvc: debitObject.rede_debit_cvc,
              rede_debit_holder_name: debitObject.rede_debit_holder_name,
              rede_debit_card_type: debitObject.card_type,
              rede_card_nonce: nonceRedeDebit
            }
          }
        };
      }
      return {
        type: emitResponse.responseTypes.ERROR,
        message: translationsRedeDebit.fieldsNotFilled
      };
    });

    // Cancela a inscrição quando este componente é desmontado.
    return () => {
      unsubscribe();
    };
  }, [debitObject,
  // Adiciona debitObject como dependência
  emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS, onPaymentSetup, translationsRedeDebit // Adicione translationsRedeDebit como dependência
  ]);
  
  // Botão "Finalizar" compartilhado entre os templates moderno e compacto.
  // Recebe a classe do botão (para reutilizar a mesma lógica nos dois layouts).
  const renderSubmitButton = (buttonClass) => (
    <button
      type="button"
      className={buttonClass}
      onClick={() => {
        // Proteção contra duplo envio
        if (redeCheckoutSubmitted) {
          return;
        }
        if (sessionStorage.getItem(REDE_CHECKOUT_SESSION_KEY)) {
          return;
        }

        redeCheckoutSubmitted = true;
        sessionStorage.setItem(REDE_CHECKOUT_SESSION_KEY, '1');

        // 1. Bloqueia visualmente o botão customizado (NÃO afeta o botão real)
        const selfButton = document.querySelector('.' + buttonClass);
        const originalButtonText = selfButton ? selfButton.textContent : '';
        if (selfButton) {
          selfButton.classList.add('blocked');
          selfButton.disabled = true;
          selfButton.textContent = translationsRedeDebit?.processing || 'Processando...';
        }

        // 2. Busca o botão REAL do WooCommerce Blocks
        let checkoutButton = document.querySelector('.wp-element-button.wc-block-components-checkout-place-order-button');

        if (!checkoutButton) {
          // Fallback por texto
          const allButtons = document.querySelectorAll('button');
          for (const btn of allButtons) {
            const text = (btn.textContent || '').toLowerCase();
            if (text.includes('finalizar') || text.includes('place order') || text.includes('comprar')) {
              checkoutButton = btn;
              break;
            }
          }
        }

        if (!checkoutButton) {
          sessionStorage.removeItem(REDE_CHECKOUT_SESSION_KEY);
          redeCheckoutSubmitted = false;
          return;
        }

        let observer = null;
        let safetyTimeout = null;

        // Função que libera todos os locks (erro, timeout, etc.)
        const releaseLock = () => {
          if (observer) {
            observer.disconnect();
            observer = null;
          }
          if (safetyTimeout) {
            clearTimeout(safetyTimeout);
            safetyTimeout = null;
          }
          sessionStorage.removeItem(REDE_CHECKOUT_SESSION_KEY);
          redeCheckoutSubmitted = false;
          if (selfButton) {
            selfButton.classList.remove('blocked');
            selfButton.disabled = false;
            selfButton.textContent = originalButtonText;
          }
          if (checkoutButton && checkoutButton.disabled) {
            checkoutButton.disabled = false;
          }
        };

        // 3. MutationObserver: re-desabilita se o WC Blocks reabilitar durante o processamento
        observer = new MutationObserver(() => {
          if (!checkoutButton.disabled) {
            checkoutButton.disabled = true;
          }
        });
        observer.observe(checkoutButton, { attributes: true, attributeFilter: ['disabled'] });

        // 4. Timeout de segurança: se em 15s o checkout não redirecionou (erro/travamento),
        //    libera os botões para o usuário tentar novamente
        safetyTimeout = setTimeout(() => {
          releaseLock();
        }, 15000);

        // 5. CLICA PRIMEIRO (botão ainda habilitado → React processa o evento)
        // ⚠️ NUNCA desabilitar antes do click — botões disabled ignoram eventos React
        checkoutButton.click();

        // 6. SÓ AGORA desabilita (após o React já ter capturado o evento)
        checkoutButton.disabled = true;
      }}
    >
      {redeDebitAjax.completeOrder}
    </button>
  );

  // Template moderno com nova estrutura
  const renderModernTemplate = () => (
    <React.Fragment>
      <div className="modern-template-container">
        {/* Card preview */}
        {showCardAnimation && (<Cards
          number={debitObject.rede_debit_number}
          name={debitObject.rede_debit_holder_name}
          expiry={debitObject.rede_debit_expiry.replace(/\s+/g, '')}
          cvc={debitObject.rede_debit_cvc}
          placeholders={{
            name: 'NOME',
            expiry: 'MM/ANO',
            cvc: 'CVC',
            number: '•••• •••• •••• ••••'
          }}
          locale={{ valid: 'VÁLIDO ATÉ' }}
          focused={focus}
        />)}

        {/* Nome do portador - 100% */}
        <div className="modern-field-row-full">
          <wcComponents.TextInput
            id="rede_debit_holder_name"
            label={translationsRedeDebit.nameOnCard}
            value={debitObject.rede_debit_holder_name}
            maxLength={30}
            onChange={value => updateDebitObject('rede_debit_holder_name', value)}
            onFocus={() => setFocus('name')}
          />
        </div>

        {/* Número do cartão e tipo do cartão - 50% cada */}
        <div className="modern-field-row-half">
          <div className="modern-field-with-icon">
            <wcComponents.TextInput
              id="rede_debit_number"
              label={translationsRedeDebit.cardNumber}
              value={formatDebitCardNumber(debitObject.rede_debit_number)}
              onChange={value => updateDebitObject('rede_debit_number', formatDebitCardNumber(value))}
              onFocus={() => setFocus('number')}
              inputMode="numeric"
              pattern="[0-9]*"
            />
            {cardTemplateAssets.lock && (
              <img src={cardTemplateAssets.lock} alt="" className="modern-field-icon" />
            )}
          </div>
          {showCardTypeSelector && (
            <div className="modern-select-wrapper">
              <select
                id="card_type_selector"
                value={debitObject.card_type}
                onChange={e => {
                  const value = e.target.value;
                  updateDebitObject('card_type', value);
                }}
                className="modern-select"
                style={lockedSelectStyle}
                aria-disabled={lockCardTypeSelector ? 'true' : undefined}
                tabIndex={lockCardTypeSelector ? -1 : undefined}
                data-lkn-locked={lockCardTypeSelector ? 'true' : undefined}
              >
                {cardTypeOptions.map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </div>
          )}
        </div>

        {/* Data de expiração e código de segurança - 50% cada */}
        <div className="modern-field-row-half">
          <div className="modern-field-with-icon">
            <wcComponents.TextInput
              id="rede_debit_expiry"
              label={translationsRedeDebit.cardExpiringDate}
              value={debitObject.rede_debit_expiry}
              onChange={value => updateDebitObject('rede_debit_expiry', value)}
              onFocus={() => setFocus('expiry')}
              inputMode="numeric"
              pattern="[0-9]*"
            />
            {cardTemplateAssets.calendar && (
              <img src={cardTemplateAssets.calendar} alt="" className="modern-field-icon" />
            )}
          </div>
          <div className="modern-field-with-icon">
            <wcComponents.TextInput
              id="rede_debit_cvc"
              label={translationsRedeDebit.securityCode}
              value={debitObject.rede_debit_cvc}
              onChange={value => updateDebitObject('rede_debit_cvc', value)}
              onFocus={() => setFocus('cvc')}
              inputMode="numeric"
              pattern="[0-9]*"
            />
            {cardTemplateAssets.key && (
              <img src={cardTemplateAssets.key} alt="" className="modern-field-icon" />
            )}
          </div>
        </div>

        {/* Parcelas - apenas para crédito */}
        {(cardTypeRestriction === 'credit_only' || debitObject.card_type === 'credit') && options.length > 1 && (
          <div className="modern-field-row-full">
            <div className="modern-select-wrapper">
              <label>{translationsRedeDebit.installments}</label>
              <select
                value={selectedValue}
                id="card_installment_selector"
                onChange={handleSortChange}
                readOnly={false}
                className="modern-select"
              >
                {options.map(opt => (
                  <option key={opt.key} value={opt.key}>{opt.label}</option>
                ))}
              </select>
            </div>
          </div>
        )}

        {/* Botão finalizar */}
        <div className="modern-field-row-full">
          {renderSubmitButton('modern-submit-button')}
        </div>
        
        {/* Descrição do gateway */}
        {gatewayDescription && (
          <div className="modern-gateway-description">
            {gatewayDescription}
          </div>
        )}
      </div>
    </React.Fragment>
  );

  // Template básico (original)
  const renderBasicTemplate = () => (
    <React.Fragment>
      {showCardAnimation && (<Cards
        number={debitObject.rede_debit_number}
        name={debitObject.rede_debit_holder_name}
        expiry={debitObject.rede_debit_expiry.replace(/\s+/g, '')}
        cvc={debitObject.rede_debit_cvc}
        placeholders={{
          name: 'NOME',
          expiry: 'MM/ANO',
          cvc: 'CVC',
          number: '•••• •••• •••• ••••'
        }}
        locale={{ valid: 'VÁLIDO ATÉ' }}
        focused={focus}
      />)}
      <wcComponents.TextInput
        id="rede_debit_holder_name"
        label={translationsRedeDebit.nameOnCard}
        value={debitObject.rede_debit_holder_name}
        maxLength={30}
        onChange={value => updateDebitObject('rede_debit_holder_name', value)}
        onFocus={() => setFocus('name')}
      />
      <wcComponents.TextInput
        id="rede_debit_number"
        label={translationsRedeDebit.cardNumber}
        value={formatDebitCardNumber(debitObject.rede_debit_number)}
        onChange={value => updateDebitObject('rede_debit_number', formatDebitCardNumber(value))}
        onFocus={() => setFocus('number')}
        inputMode="numeric"
        pattern="[0-9]*"
      />
      <wcComponents.TextInput
        id="rede_debit_expiry"
        label={translationsRedeDebit.cardExpiringDate}
        value={debitObject.rede_debit_expiry}
        onChange={value => updateDebitObject('rede_debit_expiry', value)}
        onFocus={() => setFocus('expiry')}
        inputMode="numeric"
        pattern="[0-9]*"
      />
      <wcComponents.TextInput
        id="rede_debit_cvc"
        label={translationsRedeDebit.securityCode}
        value={debitObject.rede_debit_cvc}
        onChange={value => updateDebitObject('rede_debit_cvc', value)}
        onFocus={() => setFocus('cvc')}
        inputMode="numeric"
        pattern="[0-9]*"
      />
      {showCardTypeSelector && (
        <div className="lknIntegrationRedeForWoocommerceSelectBlocks lknIntegrationRedeForWoocommerceSelect3dsInstallments">
          <label htmlFor="card_type_selector">{translationsRedeDebit.cardType}</label>
          <select
            id="card_type_selector"
            value={debitObject.card_type}
            onChange={e => {
              const value = e.target.value;
              updateDebitObject('card_type', value);
            }}
            style={lockedSelectStyle}
            aria-disabled={lockCardTypeSelector ? 'true' : undefined}
            tabIndex={lockCardTypeSelector ? -1 : undefined}
            data-lkn-locked={lockCardTypeSelector ? 'true' : undefined}
          >
            {cardTypeOptions.map(([value, label]) => (
              <option key={value} value={value}>{label}</option>
            ))}
          </select>
        </div>
      )}
      {(cardTypeRestriction === 'credit_only' || debitObject.card_type === 'credit') && options.length > 1 && (
        <div className="lknIntegrationRedeForWoocommerceSelectBlocks">
          <label>{translationsRedeDebit.installments}</label>
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
      
      {/* Botão de finalizar custom — recurso PRO (mesma regra dos layouts
          moderno/compacto). Sem PRO, usa o botão nativo do WooCommerce. */}
      {settingsRedeDebit.isProValid && renderSubmitButton('rede-basic-submit-button')}

      {/* Descrição do gateway */}
      {gatewayDescription && (
        <div className="basic-gateway-description">
          {gatewayDescription}
        </div>
      )}
    </React.Fragment>
  );

  // Template compacto (novo layout): campos lado a lado, bandeiras dentro do
  // campo de número. Reaproveita os mesmos IDs/estado do restante do componente.
  const renderCompactTemplate = () => (
    <React.Fragment>
      <div className="rede-compact-container">
        {/* Preview do cartão */}
        {showCardAnimation && (<Cards
          number={debitObject.rede_debit_number}
          name={debitObject.rede_debit_holder_name}
          expiry={debitObject.rede_debit_expiry.replace(/\s+/g, '')}
          cvc={debitObject.rede_debit_cvc}
          placeholders={{
            name: 'NOME',
            expiry: 'MM/ANO',
            cvc: 'CVC',
            number: '•••• •••• •••• ••••'
          }}
          locale={{ valid: 'VÁLIDO ATÉ' }}
          focused={focus}
        />)}

        {/* Linha 1: Nome do portador + Tipo do cartão */}
        <div className={'rede-compact-row rede-compact-row--top' + (showCardTypeSelector ? '' : ' rede-compact-row--name-only')}>
          <div className="rede-compact-field rede-compact-field--name">
            <wcComponents.TextInput
              id="rede_debit_holder_name"
              label={translationsRedeDebit.nameOnCard}
              value={debitObject.rede_debit_holder_name}
              maxLength={30}
              onChange={value => updateDebitObject('rede_debit_holder_name', value)}
              onFocus={() => setFocus('name')}
            />
          </div>
          {showCardTypeSelector && (
            <div className="rede-compact-field rede-compact-field--type">
              <label htmlFor="card_type_selector">{translationsRedeDebit.cardType}</label>
              <select
                id="card_type_selector"
                value={debitObject.card_type}
                onChange={e => {
                  updateDebitObject('card_type', e.target.value);
                }}
                className="rede-compact-select"
                style={lockedSelectStyle}
                aria-disabled={lockCardTypeSelector ? 'true' : undefined}
                tabIndex={lockCardTypeSelector ? -1 : undefined}
                data-lkn-locked={lockCardTypeSelector ? 'true' : undefined}
              >
                {cardTypeOptions.map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </div>
          )}
        </div>

        {/* Linha 2: Número do cartão (com bandeiras) + Data + Código */}
        <div className="rede-compact-row rede-compact-row--card">
          <div className="rede-compact-field rede-compact-field--number">
            <div className="rede-compact-field-with-icon">
              <wcComponents.TextInput
                id="rede_debit_number"
                label={translationsRedeDebit.cardNumber}
                value={formatDebitCardNumber(debitObject.rede_debit_number)}
                onChange={value => updateDebitObject('rede_debit_number', formatDebitCardNumber(value))}
                onFocus={() => setFocus('number')}
                inputMode="numeric"
                pattern="[0-9]*"
              />
              <div className="rede-compact-card-brands">
                {['visa', 'mastercard', 'elo'].map(brand => cardTemplateAssets[brand] && (
                  <img key={brand} src={cardTemplateAssets[brand]} alt={brand} data-brand={brand} />
                ))}
              </div>
            </div>
          </div>
          <div className="rede-compact-field rede-compact-field--exp">
            <div className="rede-compact-field-with-icon">
              <wcComponents.TextInput
                id="rede_debit_expiry"
                label={translationsRedeDebit.cardExpiringDate}
                value={debitObject.rede_debit_expiry}
                onChange={value => updateDebitObject('rede_debit_expiry', value)}
                onFocus={() => setFocus('expiry')}
                inputMode="numeric"
                pattern="[0-9]*"
              />
              {cardTemplateAssets.calendar && (
                <img src={cardTemplateAssets.calendar} alt="" className="rede-compact-field-icon" aria-hidden="true" />
              )}
            </div>
          </div>
          <div className="rede-compact-field rede-compact-field--cvc">
            <div className="rede-compact-field-with-icon">
              <wcComponents.TextInput
                id="rede_debit_cvc"
                label={translationsRedeDebit.securityCode}
                value={debitObject.rede_debit_cvc}
                onChange={value => updateDebitObject('rede_debit_cvc', value)}
                onFocus={() => setFocus('cvc')}
                inputMode="numeric"
                pattern="[0-9]*"
              />
              {cardTemplateAssets.key && (
                <img src={cardTemplateAssets.key} alt="" className="rede-compact-field-icon" aria-hidden="true" />
              )}
            </div>
          </div>
        </div>

        {/* Linha 3: Parcelas (apenas crédito) */}
        {(cardTypeRestriction === 'credit_only' || debitObject.card_type === 'credit') && options.length > 1 && (
          <div className="rede-compact-row rede-compact-row--installments">
            <div className="rede-compact-field rede-compact-field--installments">
              <label htmlFor="card_installment_selector">{translationsRedeDebit.installments}</label>
              <select
                value={selectedValue}
                id="card_installment_selector"
                onChange={handleSortChange}
                readOnly={false}
                className="rede-compact-select"
              >
                {options.map(opt => (
                  <option key={opt.key} value={opt.key}>{opt.label}</option>
                ))}
              </select>
            </div>
          </div>
        )}

        {/* Botão finalizar */}
        <div className="rede-compact-row rede-compact-row--submit">
          {renderSubmitButton('rede-compact-submit-button')}
        </div>

        {/* Descrição do gateway */}
        {gatewayDescription && (
          <div className="rede-compact-description">
            {gatewayDescription}
          </div>
        )}
      </div>
    </React.Fragment>
  );

  return templateStyle === 'modern'
    ? renderModernTemplate()
    : templateStyle === 'compact'
      ? renderCompactTemplate()
      : renderBasicTemplate();
};
const BlockGatewayRedeDebit = {
  name: 'rede_debit',
  label: labelRedeDebit,
  content: window.wp.element.createElement(ContentRedeDebit),
  edit: window.wp.element.createElement(ContentRedeDebit),
  canMakePayment: () => true,
  ariaLabel: labelRedeDebit,
  supports: {
    features: settingsRedeDebit.supports
  }
};
window.wc.wcBlocksRegistry.registerPaymentMethod(BlockGatewayRedeDebit);