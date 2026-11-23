/**
 */
document.addEventListener('DOMContentLoaded', function () {

    let lastInstallmentCount = 0; // Controla o número de parcelas anterior
    let isSelectVisible = false; // Controla se o select está visível
    let isInitialized = false;
    let lastSelectedMethod = null;

    function getInstallmentCount() {
        // Verificar primeiro se é rede_debit com template moderna (card_installment_selector)
        const selectedPaymentRadio = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
        if (selectedPaymentRadio && selectedPaymentRadio.value === 'rede_debit') {
            const modernSelect = document.querySelector('#card_installment_selector');
            if (modernSelect && modernSelect.options.length > 0) {
                const validOptions = Array.from(modernSelect.options).filter(option => {
                    const optionText = option.textContent || option.innerText;
                    return !optionText.includes('Calculando') && 
                           !optionText.includes('🔄') && 
                           option.value !== 'loading';
                });
                
                return validOptions.length;
            }
        }
        
        const redeSelectContainers = document.querySelectorAll('.lknIntegrationRedeForWoocommerceSelectBlocks:not(.lknIntegrationRedeForWoocommerceSelect3dsInstallments)');
        
        if (redeSelectContainers.length === 0) {
            return 0;
        }

        for (let container of redeSelectContainers) {
            const select = container.querySelector('select');
            if (select && select.options.length > 0) {
                // Remove opções de loading/carregamento da contagem
                const validOptions = Array.from(select.options).filter(option => {
                    const optionText = option.textContent || option.innerText;
                    return !optionText.includes('Calculando') && 
                           !optionText.includes('🔄') && 
                           option.value !== 'loading';
                });
                
                return validOptions.length;
            }
        }

        return 0;
    }

    function shouldShowInstallmentLabel() {
        // Verificar se deve esconder para rede_debit com cartão débito
        if (shouldHideForRedeDebitCard()) {
            isSelectVisible = false;
            return false;
        }
        
        const installmentCount = getInstallmentCount();
        
        // Verificação especial para rede_debit em modo crédito
        const selectedPaymentRadio = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
        if (selectedPaymentRadio && selectedPaymentRadio.value === 'rede_debit') {
            const cardTypeSelector = document.querySelector('#card_type_selector');
            const isInCreditMode = !cardTypeSelector || cardTypeSelector.value === 'credit';
            const hasInstallmentSelect = document.querySelector('#card_installment_selector');
            
            // Se está em modo crédito e tem select de parcelas, deve mostrar
            if (isInCreditMode && hasInstallmentSelect && hasInstallmentSelect.options.length > 0) {
                isSelectVisible = true;
                return true;
            }
        }
        
        // Se o número de parcelas mudou, atualiza o controle
        if (installmentCount !== lastInstallmentCount) {
            lastInstallmentCount = installmentCount;
            
            // Define se deve mostrar baseado no número de parcelas
            if (installmentCount <= 1) {
                isSelectVisible = false; // Esconde quando ≤1 parcela
            } else if (installmentCount > 1) {
                isSelectVisible = true; // Mostra quando >1 parcela
            }
        }
        
        // Verificação adicional para forçar exibição quando há parcelas disponíveis
        if (installmentCount > 1 && !isSelectVisible) {
            isSelectVisible = true;
        }
        
        // Força verificação se não há elementos visíveis mas deveria haver
        if (!isSelectVisible && installmentCount > 1) {
            const existingLabels = document.querySelectorAll('.rede-payment-info-blocks');
            if (existingLabels.length === 0) {
                isSelectVisible = true;
            }
        }

        return isSelectVisible;
    }

    function isRedeMethodSelected() {
        const selectedPaymentRadio = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');

        if (selectedPaymentRadio) {
            const selectedMethod = selectedPaymentRadio.value;
            const isRede = selectedMethod === 'rede_credit' || selectedMethod === 'rede_debit';
            return isRede;
        }

        return false;
    }

    function shouldHideForRedeDebitCard() {
        const selectedPaymentRadio = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
        
        if (selectedPaymentRadio && selectedPaymentRadio.value === 'rede_debit') {
            const cardTypeSelector = document.querySelector('#card_type_selector');
            if (cardTypeSelector && cardTypeSelector.value === 'debit') {
                return true; // Esconde para rede_debit quando tipo é debit
            }
        }
        
        return false; // Mostra para outros casos
    }

    function getInstallmentInfo() {
        // Verificar primeiro se é rede_debit com template moderna (card_installment_selector)
        const selectedPaymentRadio = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
        if (selectedPaymentRadio && selectedPaymentRadio.value === 'rede_debit') {
            const modernSelect = document.querySelector('#card_installment_selector');
            if (modernSelect) {
                if (modernSelect.options.length === 0) {
                    return { text: lknInstallmentLabelTranslations.loading, isLoading: true };
                }
                
                const selectedOption = modernSelect.options[modernSelect.selectedIndex];
                if (selectedOption) {
                    const optionText = selectedOption.textContent || selectedOption.innerText;
                    const selectedValue = selectedOption.value;

                    if (optionText.includes('Calculando parcelas') || optionText.includes('🔄') || selectedValue === 'loading') {
                        return { text: lknInstallmentLabelTranslations.calculatingInstallments, isLoading: true };
                    }

                    let cleanText = optionText
                        .replace(/\s*\(.*?\)\s*/g, '')
                        .replace(/\s*sem\s+juros\s*/gi, '')
                        .replace(/\s*sem\s+desconto\s*/gi, '')
                        .replace(/\s*à\s+vista\s*/gi, '')
                        .replace(/&nbsp;/g, ' ')
                        .replace(/🔄/g, '')
                        .trim();

                    if (selectedValue === '1') {
                        return { text: lknInstallmentLabelTranslations.cashPayment, isLoading: false, value: selectedValue };
                    } else {
                        return { text: cleanText, isLoading: false, value: selectedValue };
                    }
                }
            }
        }
        
        const redeSelectContainers = document.querySelectorAll('.lknIntegrationRedeForWoocommerceSelectBlocks:not(.lknIntegrationRedeForWoocommerceSelect3dsInstallments)');

        if (redeSelectContainers.length === 0) {
            return { text: lknInstallmentLabelTranslations.loading, isLoading: true };
        }

        for (let container of redeSelectContainers) {
            const skeleton = container.querySelector('.wc-block-components-skeleton__element');
            if (skeleton) {
                return { text: lknInstallmentLabelTranslations.loading, isLoading: true };
            }

            const select = container.querySelector('select');

            if (!select) {
                return { text: lknInstallmentLabelTranslations.loading, isLoading: true };
            }

            if (select.options.length === 0) {
                return { text: lknInstallmentLabelTranslations.loading, isLoading: true };
            }

            if (select) {
                const selectedOption = select.options[select.selectedIndex];

                if (selectedOption) {
                    const optionText = selectedOption.textContent || selectedOption.innerText;
                    const selectedValue = selectedOption.value;

                    if (optionText.includes('Calculando parcelas') || optionText.includes('🔄') || selectedValue === 'loading') {
                        return { text: lknInstallmentLabelTranslations.calculatingInstallments, isLoading: true };
                    }

                    let cleanText = optionText
                        .replace(/\s*\(.*?\)\s*/g, '')
                        .replace(/\s*sem\s+juros\s*/gi, '')
                        .replace(/\s*sem\s+desconto\s*/gi, '')
                        .replace(/\s*à\s+vista\s*/gi, '')
                        .replace(/&nbsp;/g, ' ')
                        .replace(/🔄/g, '')
                        .trim();

                    if (selectedValue === '1') {
                        return { text: lknInstallmentLabelTranslations.cashPayment, isLoading: false, value: selectedValue };
                    } else {
                        return { text: cleanText, isLoading: false, value: selectedValue };
                    }
                }
            }
        }

        const fallbackSelects = document.querySelectorAll(
            'select[name*="installments"], select[name*="parcelas"], ' +
            '.rede_credit_select select, ' +
            'select[id*="rede"], ' +
            '#card_installment_selector'
        );

        for (let select of fallbackSelects) {
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption) {
                const optionText = selectedOption.textContent || selectedOption.innerText;
                const selectedValue = selectedOption.value;

                if (optionText && !optionText.includes('Calculando') && !optionText.includes('🔄')) {
                    let cleanText = optionText
                        .replace(/\s*\(.*?\)\s*/g, '')
                        .replace(/\s*sem\s+juros\s*/gi, '')
                        .replace(/\s*sem\s+desconto\s*/gi, '')
                        .replace(/\s*à\s+vista\s*/gi, '')
                        .replace(/&nbsp;/g, ' ')
                        .replace(/🔄/g, '')
                        .trim();

                    if (selectedValue === '1') {
                        return { text: lknInstallmentLabelTranslations.cashPayment, isLoading: false, value: selectedValue };
                    } else {
                        return { text: cleanText, isLoading: false, value: selectedValue };
                    }
                }
            }
        }

        return { text: lknInstallmentLabelTranslations.fallbackInstallment, isLoading: false, value: '2' };
    }

    function insertLoadingSkeleton(totalDiv) {
        if (totalDiv.parentNode.querySelector('.rede-payment-info-blocks')) {
            return;
        }

        const loadingSkeleton = document.createElement('div');
        loadingSkeleton.className = 'wc-block-components-totals-item wc-block-components-totals-footer-item rede-payment-info-blocks loading-skeleton';
        loadingSkeleton.style.fontSize = 'small';

        const animationStyle = document.createElement('style');
        if (!document.getElementById('rede-loading-animation')) {
            animationStyle.id = 'rede-loading-animation';
            animationStyle.textContent = `
                @keyframes rede-pulse {
                    0%, 100% { 
                        opacity: 0.6;
                        transform: scale(1);
                    }
                    50% { 
                        opacity: 1;
                        transform: scale(1.02);
                    }
                }
                
                @keyframes rede-shimmer {
                    0% {
                        background-position: -200px 0;
                    }
                    100% {
                        background-position: calc(200px + 100%) 0;
                    }
                }
                
                .rede-payment-info-blocks.loading-skeleton {
                    animation: rede-pulse 1.5s ease-in-out infinite;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
                    background-size: 200px 100%;
                    animation: rede-shimmer 1.5s infinite;
                }
                
                .rede-payment-info-blocks.loading-skeleton .wc-block-components-skeleton__element {
                    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                    background-size: 200% 100%;
                    animation: rede-shimmer 1.2s infinite;
                    border-radius: 4px;
                }
            `;
            document.head.appendChild(animationStyle);
        }

        loadingSkeleton.innerHTML = `
            <span class="wc-block-components-totals-item__label">${lknInstallmentLabelTranslations.installment}</span>
            <div class="wc-block-components-totals-item__value">
                <div class="wc-block-components-skeleton__element" aria-live="polite" aria-label="${lknInstallmentLabelTranslations.loadingPrice}" style="width: 80px; height: 1em;"></div>
            </div>
            <div class="wc-block-components-totals-item__description"></div>
        `;

        totalDiv.parentNode.insertBefore(loadingSkeleton, totalDiv.nextSibling);
    }

    function insertRedeInfo() {
        // Primeiro tenta encontrar elementos não processados
        let totalItemDivs = document.querySelectorAll('.wc-block-components-totals-item.wc-block-components-totals-footer-item:not(.rede-processed)');
        
        // Se não encontrou, busca todos os elementos (para casos de re-criação)
        if (totalItemDivs.length === 0) {
            totalItemDivs = document.querySelectorAll('.wc-block-components-totals-item.wc-block-components-totals-footer-item');
        }

        if (totalItemDivs.length === 0) {
            return;
        }

        totalItemDivs.forEach((totalDiv, index) => {
            totalDiv.classList.add('rede-processed');

            const existingInfo = totalDiv.parentNode.querySelector('.rede-payment-info-blocks:not(.loading-skeleton)');
            if (existingInfo) {
                return;
            }

            const redeSelected = isRedeMethodSelected();
            if (!redeSelected) {
                return;
            }

            // Verifica se deve mostrar o label de parcelamento - mais permissivo na verificação inicial
            const shouldShow = shouldShowInstallmentLabel();
            const hasInstallmentSelects = document.querySelectorAll('.lknIntegrationRedeForWoocommerceSelectBlocks:not(.lknIntegrationRedeForWoocommerceSelect3dsInstallments) select').length > 0;
            
            if (!shouldShow && !hasInstallmentSelects) {
                return; // Só não mostra se realmente não há parcelas disponíveis
            }

            const installmentInfo = getInstallmentInfo();

            if (installmentInfo.isLoading) {
                insertLoadingSkeleton(totalDiv);
                return;
            }

            let labelText = lknInstallmentLabelTranslations.installment;
            if (installmentInfo.value === '1') {
                labelText = lknInstallmentLabelTranslations.payment;
            }

            const redeInfo = document.createElement('div');
            redeInfo.className = 'wc-block-components-totals-item wc-block-components-totals-footer-item rede-payment-info-blocks';
            redeInfo.style.fontSize = 'small';
            redeInfo.setAttribute('data-installment-value', installmentInfo.value);

            redeInfo.innerHTML = `
                <span class="wc-block-components-totals-item__label">${labelText}</span>
                <div class="wc-block-components-totals-item__value">
                    <span class="wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-footer-item-tax-value">${installmentInfo.text}</span>
                </div>
                <div class="wc-block-components-totals-item__description"></div>
            `;

            totalDiv.parentNode.insertBefore(redeInfo, totalDiv.nextSibling);
        });
    }

    function removeRedeInfo() {
        const existingInfos = document.querySelectorAll('.rede-payment-info-blocks');

        existingInfos.forEach(function (existingInfo) {
            existingInfo.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => {
                if (existingInfo && existingInfo.parentNode) {
                    existingInfo.remove();
                }
            }, 300);
        });

        const processedTotals = document.querySelectorAll('.wc-block-components-totals-item.wc-block-components-totals-footer-item.rede-processed');
        processedTotals.forEach(function (total) {
            total.classList.remove('rede-processed');
        });
    }

    function updateLoadingSkeletons() {
        const loadingSkeletons = document.querySelectorAll('.rede-payment-info-blocks.loading-skeleton');
        const existingParcelamentos = document.querySelectorAll('.rede-payment-info-blocks:not(.loading-skeleton)');
        const totalElements = loadingSkeletons.length + existingParcelamentos.length;

        const shouldShow = shouldShowInstallmentLabel();

        // Se não deve mostrar o label, remove todos os elementos existentes
        if (!shouldShow) {
            loadingSkeletons.forEach(function (skeleton) {
                if (skeleton && skeleton.parentNode) {
                    skeleton.remove();
                }
            });
            existingParcelamentos.forEach(function (parcelamento) {
                if (parcelamento && parcelamento.parentNode) {
                    parcelamento.remove();
                }
            });
            return;
        }

        if (totalElements > 0) {
            const installmentInfo = getInstallmentInfo();

            if (!installmentInfo.isLoading) {
                function updateElement(element) {
                    let labelText = lknInstallmentLabelTranslations.installment;
                    if (installmentInfo.value === '1') {
                        labelText = lknInstallmentLabelTranslations.payment;
                    }

                    element.className = 'wc-block-components-totals-item wc-block-components-totals-footer-item rede-payment-info-blocks';
                    element.setAttribute('data-installment-value', installmentInfo.value);

                    element.innerHTML = `
                        <span class="wc-block-components-totals-item__label">${labelText}</span>
                        <div class="wc-block-components-totals-item__value">
                            <span class="wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-footer-item-tax-value">${installmentInfo.text}</span>
                        </div>
                        <div class="wc-block-components-totals-item__description"></div>
                    `;
                }

                loadingSkeletons.forEach(function (skeleton) {
                    updateElement(skeleton);
                });

                existingParcelamentos.forEach(function (parcelamento) {
                    updateElement(parcelamento);
                });
            }
        } else if (shouldShow) {
            // NOVO: Se deve mostrar mas não há elementos, cria novos
            insertRedeInfo();
        }
    }

    function activateLoadingSkeleton() {
        if (!isRedeMethodSelected()) {
            return;
        }

        // Se não deve mostrar o label, não ativa loading skeleton
        if (!shouldShowInstallmentLabel()) {
            return;
        }

        const existingParcelamentos = document.querySelectorAll('.rede-payment-info-blocks:not(.loading-skeleton)');

        if (existingParcelamentos.length > 0) {
            existingParcelamentos.forEach(function (parcelamento) {
                parcelamento.classList.add('loading-skeleton');

                parcelamento.innerHTML = `
                    <span class="wc-block-components-totals-item__label">${lknInstallmentLabelTranslations.installment}</span>
                    <div class="wc-block-components-totals-item__value">
                        <div class="wc-block-components-skeleton__element" aria-live="polite" aria-label="${lknInstallmentLabelTranslations.loadingPrice}" style="width: 80px; height: 1em;"></div>
                    </div>
                    <div class="wc-block-components-totals-item__description"></div>
                `;
            });
        } else {
            const totalComponents = document.querySelectorAll('.wc-block-components-totals-item.wc-block-components-totals-footer-item:not(.rede-processed)');

            if (totalComponents.length > 0) {
                totalComponents.forEach(function (totalComponent) {
                    insertLoadingSkeleton(totalComponent);
                    totalComponent.classList.add('rede-processed');
                });
            }
        }
    }

    function observeTotalChanges() {
        const totalElement = document.querySelector('.wc-block-components-totals-item.wc-block-components-totals-footer-item .wc-block-formatted-money-amount');

        if (!totalElement) {
            return;
        }

        if (totalElement.dataset.observerAdded) {
            return;
        }

        let lastTotal = null;
        let observerTimeout = null;
        let checkCount = 0;
        const maxChecks = 30;

        function checkAndUpdate() {
            checkCount++;
            const currentTotal = totalElement.textContent || totalElement.innerText;

            if (currentTotal !== lastTotal) {
                lastTotal = currentTotal;
                const installmentInfo = getInstallmentInfo();

                if (installmentInfo.isLoading) {
                    activateLoadingSkeleton();
                } else {
                    updateLoadingSkeletons();
                }
            }

            if (checkCount < maxChecks) {
                if (checkCount <= 10) {
                    observerTimeout = setTimeout(checkAndUpdate, 300);
                }
            }
        }

        const totalObserver = new MutationObserver(function (mutations) {
            let hasChanges = false;

            mutations.forEach(function (mutation) {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    hasChanges = true;
                }
            });

            if (hasChanges) {
                checkCount = 0;

                if (observerTimeout) {
                    clearTimeout(observerTimeout);
                }

                checkAndUpdate();
            }
        });

        totalObserver.observe(totalElement, {
            childList: true,
            subtree: true,
            characterData: true
        });

        totalElement.dataset.observerAdded = 'true';
        checkAndUpdate();
    }

    function checkPaymentMethod() {
        const checkedInput = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
        const selectedMethod = checkedInput ? checkedInput.value : null;

        if (selectedMethod === 'rede_credit' || selectedMethod === 'rede_debit') {
            // Reset o controle de parcelas para forçar nova verificação
            lastInstallmentCount = -1;
            
            insertRedeInfo();
            updateLoadingSkeletons();

            setTimeout(() => {
                addSelectChangeListeners();
            }, 200);

            setTimeout(() => {
                observeTotalChanges();
            }, 500);

            // Verificações adicionais com múltiplos timeouts para garantir que labels sejam criados
            [1000, 2000, 3500, 5000].forEach(delay => {
                setTimeout(() => {
                    if (shouldShowInstallmentLabel()) {
                        const existingLabels = document.querySelectorAll('.rede-payment-info-blocks');
                        if (existingLabels.length === 0) {
                            // Reset do estado processado para permitir nova criação
                            const processedDivs = document.querySelectorAll('.rede-processed');
                            processedDivs.forEach(div => div.classList.remove('rede-processed'));
                            insertRedeInfo();
                        }
                    }
                }, delay);
            });

            lastSelectedMethod = selectedMethod;
        } else if (selectedMethod !== lastSelectedMethod) {
            removeRedeInfo();
            lastSelectedMethod = selectedMethod;
        }
    }

    function initializePaymentListeners() {
        const paymentInputs = document.querySelectorAll('input[name="radio-control-wc-payment-method-options"]');

        if (paymentInputs.length > 0 && !isInitialized) {
            paymentInputs.forEach(function (input) {
                input.addEventListener('change', checkPaymentMethod);
            });
            
            // Verificação inicial imediata para método já selecionado
            checkPaymentMethod();
            
            // Verificações adicionais com timeouts para garantir que elementos estejam prontos
            setTimeout(() => {
                checkPaymentMethod();
            }, 500);
            
            setTimeout(() => {
                checkPaymentMethod();
            }, 1500);
            
            setTimeout(() => {
                checkPaymentMethod();
            }, 3000);

            isInitialized = true;
        }

        checkPaymentMethod();
    }

    const observer = new MutationObserver(function (mutations) {
        let shouldCheckPayments = false;
        let shouldCheckTotals = false;
        let shouldCheckSelects = false;
        let shouldCheckCardTypeSelector = false;

        for (let mutation of mutations) {
            if (mutation.type === 'childList') {
                for (let node of mutation.addedNodes) {
                    if (node.nodeType === 1) {
                        if ((node.querySelector && node.querySelector('input[name="radio-control-wc-payment-method-options"]')) ||
                            (node.name && node.name === 'radio-control-wc-payment-method-options')) {
                            shouldCheckPayments = true;
                        }

                        if ((node.classList && node.classList.contains('wc-block-components-totals-item')) ||
                            (node.querySelector && node.querySelector('.wc-block-components-totals-item'))) {
                            shouldCheckTotals = true;
                        }

                        if ((node.classList && node.classList.contains('lknIntegrationRedeForWoocommerceSelectBlocks')) ||
                            (node.querySelector && node.querySelector('.lknIntegrationRedeForWoocommerceSelectBlocks'))) {
                            shouldCheckSelects = true;
                        }

                        // Verificar se o card_type_selector foi adicionado
                        if ((node.id === 'card_type_selector') ||
                            (node.querySelector && node.querySelector('#card_type_selector'))) {
                            shouldCheckCardTypeSelector = true;
                        }
                    }
                }
            }
        }

        if (shouldCheckPayments) {
            initializePaymentListeners();
        }

        if (shouldCheckTotals) {
            setTimeout(() => {
                checkPaymentMethod();
            }, 300);
        }

        if (shouldCheckSelects || shouldCheckCardTypeSelector) {
            setTimeout(() => {
                updateLoadingSkeletons();
                addSelectChangeListeners();
                
                // Re-verificar se é rede_debit para garantir que card_type_selector seja monitorado
                const selectedPaymentRadio = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
                if (selectedPaymentRadio && selectedPaymentRadio.value === 'rede_debit') {
                    // Força nova verificação para rede_debit após mudanças no DOM
                    // Múltiplas tentativas para sites lentos
                    const verifyAndAdd = (attempt = 0) => {
                        const cardTypeSelector = document.querySelector('#card_type_selector');
                        if (cardTypeSelector && !cardTypeSelector.dataset.listenerAdded) {
                            // Remove flag para re-adicionar listener
                            cardTypeSelector.dataset.listenerAdded = '';
                            addSelectChangeListeners();
                        } else if (attempt < 3 && !cardTypeSelector) {
                            // Se não encontrou ainda, tenta novamente
                            setTimeout(() => verifyAndAdd(attempt + 1), 500);
                        }
                    };
                    
                    setTimeout(() => verifyAndAdd(), 200);
                }
            }, 500);
        }
    });

    function addSelectChangeListeners() {
        const redeSelectContainers = document.querySelectorAll('.lknIntegrationRedeForWoocommerceSelectBlocks:not(.lknIntegrationRedeForWoocommerceSelect3dsInstallments)');

        redeSelectContainers.forEach(container => {
            const select = container.querySelector('select');
            if (select && !select.dataset.listenerAdded) {
                select.addEventListener('change', function () {
                    setTimeout(() => {
                        updateLoadingSkeletons();
                    }, 100);
                });

                // Adiciona observer para mudanças no conteúdo do select
                if (!select.dataset.observerAdded) {
                    const selectObserver = new MutationObserver(function(mutations) {
                        let optionsChanged = false;
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'childList') {
                                optionsChanged = true;
                            }
                        });
                        
                        if (optionsChanged) {
                            setTimeout(() => {
                                if (shouldShowInstallmentLabel()) {
                                    const existingLabels = document.querySelectorAll('.rede-payment-info-blocks');
                                    if (existingLabels.length === 0) {
                                        // Reset processed state to allow new creation
                                        const processedDivs = document.querySelectorAll('.rede-processed');
                                        processedDivs.forEach(div => div.classList.remove('rede-processed'));
                                        insertRedeInfo();
                                    }
                                }
                            }, 300);
                        }
                    });
                    
                    selectObserver.observe(select, {
                        childList: true,
                        subtree: true
                    });
                    
                    select.dataset.observerAdded = 'true';
                }

                select.dataset.listenerAdded = 'true';
            }
        });

        // Adicionar listener para o select de tipo de cartão (rede_debit)
        const cardTypeSelector = document.querySelector('#card_type_selector');
        if (cardTypeSelector && !cardTypeSelector.dataset.listenerAdded) {
            cardTypeSelector.addEventListener('change', function() {
                // Verificar se é rede_debit selecionado
                const selectedPaymentRadio = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
                
                if (selectedPaymentRadio && selectedPaymentRadio.value === 'rede_debit') {
                    // Reset o controle de parcelas para forçar nova verificação
                    lastInstallmentCount = -1;
                    isSelectVisible = false; // Reset visibility
                    
                    // Remove todas as infos existentes primeiro
                    removeRedeInfo();
                    
                    if (cardTypeSelector.value === 'credit') {
                        // Quando volta para crédito, força nova detecção
                        // Para sites lentos: múltiplas tentativas com intervalos crescentes
                        const retryAttempts = [100, 500, 1000, 2000, 4000];
                        let attemptIndex = 0;
                        
                        const retryUpdate = () => {
                            // Verifica se ainda está em crédito
                            if (cardTypeSelector.value !== 'credit') {
                                return;
                            }
                            
                            // Reset flags para forçar nova verificação
                            const processedDivs = document.querySelectorAll('.rede-processed');
                            processedDivs.forEach(div => div.classList.remove('rede-processed'));
                            
                            // Força atualização
                            updateLoadingSkeletons();
                            
                            // Verifica se conseguiu inserir as labels
                            setTimeout(() => {
                                const hasLabels = document.querySelectorAll('.rede-payment-info-blocks').length > 0;
                                const hasInstallmentSelect = document.querySelector('#card_installment_selector');
                                const shouldContinue = !hasLabels && hasInstallmentSelect && attemptIndex < retryAttempts.length;
                                
                                if (shouldContinue) {
                                    attemptIndex++;
                                    setTimeout(retryUpdate, retryAttempts[attemptIndex - 1]);
                                }
                            }, 300);
                        };
                        
                        // Primeira tentativa imediata
                        retryUpdate();
                    }
                }
            });
            
            cardTypeSelector.dataset.listenerAdded = 'true';
        }

        // Adicionar listener para o select de parcelas moderno (rede_debit template moderna)
        const modernInstallmentSelector = document.querySelector('#card_installment_selector');
        if (modernInstallmentSelector && !modernInstallmentSelector.dataset.listenerAdded) {
            modernInstallmentSelector.addEventListener('change', function () {
                setTimeout(() => {
                    updateLoadingSkeletons();
                }, 100);
            });

            // Adiciona observer para mudanças no conteúdo do select moderno
            if (!modernInstallmentSelector.dataset.observerAdded) {
                const modernSelectObserver = new MutationObserver(function(mutations) {
                    let optionsChanged = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            optionsChanged = true;
                        }
                    });
                    
                    if (optionsChanged) {
                        // Para sites lentos: tenta múltiplas vezes com delays diferentes
                        const attemptUpdate = (delay, maxAttempts = 3, currentAttempt = 0) => {
                            setTimeout(() => {
                                if (shouldShowInstallmentLabel()) {
                                    const existingLabels = document.querySelectorAll('.rede-payment-info-blocks');
                                    if (existingLabels.length === 0) {
                                        // Reset processed state to allow new creation
                                        const processedDivs = document.querySelectorAll('.rede-processed');
                                        processedDivs.forEach(div => div.classList.remove('rede-processed'));
                                        insertRedeInfo();
                                        
                                        // Verifica se conseguiu inserir, senão tenta novamente
                                        setTimeout(() => {
                                            const newLabels = document.querySelectorAll('.rede-payment-info-blocks');
                                            if (newLabels.length === 0 && currentAttempt < maxAttempts) {
                                                attemptUpdate(delay * 1.5, maxAttempts, currentAttempt + 1);
                                            }
                                        }, 200);
                                    }
                                }
                            }, delay);
                        };
                        
                        // Primeira tentativa rápida
                        attemptUpdate(300);
                    }
                });
                
                modernSelectObserver.observe(modernInstallmentSelector, {
                    childList: true,
                    subtree: true
                });
                
                modernInstallmentSelector.dataset.observerAdded = 'true';
            }

            modernInstallmentSelector.dataset.listenerAdded = 'true';
        }
    }

    const checkoutArea = document.querySelector('.wc-block-checkout') || document.body;

    // Observer principal para mudanças no DOM
    observer.observe(checkoutArea, {
        childList: true,
        subtree: true
    });

    // Observer específico para o container do total - detecta quando só aparece o total sem as parcelas
    const totalsContainer = document.querySelector('.wc-block-components-totals-wrapper');
    if (totalsContainer) {
        const totalsObserver = new MutationObserver(function(mutations) {
            let shouldCheck = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    // Verifica se houve mudança no total mas não há labels de parcelas
                    const hasTotal = totalsContainer.querySelector('.wc-block-components-totals-footer-item');
                    const hasRedeLabels = totalsContainer.querySelector('.rede-payment-info-blocks');
                    
                    if (hasTotal && !hasRedeLabels) {
                        shouldCheck = true;
                    }
                }
            });
            
            if (shouldCheck) {
                setTimeout(() => {
                    // Verifica se é rede_debit em modo crédito e deveria ter labels
                    const selectedPaymentRadio = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
                    if (selectedPaymentRadio && selectedPaymentRadio.value === 'rede_debit') {
                        const cardTypeSelector = document.querySelector('#card_type_selector');
                        const isInCreditMode = !cardTypeSelector || cardTypeSelector.value === 'credit';
                        const hasInstallmentSelect = document.querySelector('#card_installment_selector');
                        
                        if (isInCreditMode && hasInstallmentSelect) {
                            // Reset e força recriação
                            const processedDivs = document.querySelectorAll('.rede-processed');
                            processedDivs.forEach(div => div.classList.remove('rede-processed'));
                            lastInstallmentCount = -1;
                            isSelectVisible = false;
                            
                            updateLoadingSkeletons();
                        }
                    }
                }, 500);
            }
        });
        
        totalsObserver.observe(totalsContainer, {
            childList: true,
            subtree: true
        });
    }

    // Observer adicional para verificações periódicas mais inteligentes
    let checkAttempts = 0;
    const maxCheckAttempts = 30; // 1 minuto de tentativas (30 x 2s)
    
    const intelligentChecker = setInterval(() => {
        checkAttempts++;
        
        if (isRedeMethodSelected() && shouldShowInstallmentLabel()) {
            const existingLabels = document.querySelectorAll('.rede-payment-info-blocks');
            const hasInstallmentSelects = document.querySelectorAll('.lknIntegrationRedeForWoocommerceSelectBlocks:not(.lknIntegrationRedeForWoocommerceSelect3dsInstallments) select').length > 0;
            const hasModernSelect = document.querySelector('#card_installment_selector');
            
            // Verifica se é rede_debit e está em modo crédito
            const selectedPaymentRadio = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
            const isRedeDebit = selectedPaymentRadio && selectedPaymentRadio.value === 'rede_debit';
            const cardTypeSelector = document.querySelector('#card_type_selector');
            const isInCreditMode = !cardTypeSelector || cardTypeSelector.value === 'credit';
            
            const shouldHaveLabels = hasInstallmentSelects || (isRedeDebit && isInCreditMode && hasModernSelect);
            
            if (existingLabels.length === 0 && shouldHaveLabels) {
                // Força reset e recriação
                const processedDivs = document.querySelectorAll('.rede-processed');
                processedDivs.forEach(div => div.classList.remove('rede-processed'));
                lastInstallmentCount = -1; // Reset para forçar nova detecção
                insertRedeInfo();
            }
        }
        
        // Para o polling após encontrar labels ou esgotar tentativas
        if (checkAttempts >= maxCheckAttempts || document.querySelectorAll('.rede-payment-info-blocks').length > 0) {
            clearInterval(intelligentChecker);
        }
    }, 2000); // Verifica a cada 2 segundos

    initializePaymentListeners();
});