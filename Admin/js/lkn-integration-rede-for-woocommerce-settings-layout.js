(function ($) {
    $(window).load(function () {

        // Hidden 'currency_quote'
        document.querySelectorAll('input[type="text"]').forEach(function (input) {
            if ((input.name && input.name.includes('currency_quote')) || (input.id && input.id.includes('currency_quote'))) {
                input.style.display = 'none';
            }
        });

        // Selecionar os elementos
        let lknIntegrationRedeForWoocommerceSettingsLayoutMenuVar = 1
        const mainForm = document.querySelector('#mainform')
        const fistH1 = mainForm.querySelector('h1')
        const submitP = mainForm.querySelector('p.submit')
        const tables = mainForm.querySelectorAll('table')

        if (mainform && fistH1 && submitP && tables) {
            // Criar uma nova div
            const newDiv = document.createElement('div')
            newDiv.id = 'lknIntegrationRedeForWoocommerceSettingsLayoutDiv'

            // Acessar o próximo elemento após fistH1
            let currentElement = fistH1 // Começar com fistH1

            // Mover fistH1 e todos os elementos entre fistH1 e submitP para a nova div
            while (currentElement && currentElement !== submitP.nextElementSibling) {
                const nextElement = currentElement.nextElementSibling // Armazenar o próximo elemento antes de mover    
                newDiv.appendChild(currentElement) // Mover o elemento atual para a nova div
                currentElement = nextElement // Atualizar currentElement para o próximo
            }

            // Mover submitP para a nova div
            newDiv.appendChild(submitP)

            // Adicionar a nova div ao mainForm
            mainForm.appendChild(newDiv)

            const subTitles = mainForm.querySelectorAll('.wc-settings-sub-title')
            const descriptionElement = mainForm.querySelector('p')
            const divElement = document.createElement('div')
            if (subTitles && descriptionElement) {
                // Criar a div que irá conter os novos elementos <p>
                divElement.id = 'lknIntegrationRedeForWoocommerceSettingsLayoutMenu'
                let aElements = []
                subTitles.forEach((subTitle, index) => {
                    // Criar um novo elemento <a> e adicionar o elemento <p> a ele
                    const aElement = document.createElement('a')
                    aElement.textContent = subTitle.textContent
                    aElement.href = '#' + subTitle.textContent
                    aElement.className = 'nav-tab'
                    aElement.onclick = (event) => {
                        // Verificar se é a aba Transactions/Transações
                        const tabText = subTitle.textContent.toLowerCase();
                        if (tabText === 'transactions' || tabText === 'transações') {
                            event.preventDefault();
                            event.stopPropagation();
                            
                            // Usar URL do wp_localize_script
                            const analyticsUrl = lknWcRedeTranslationsInput.analytics_url;

                            // Abrir em nova aba
                            window.open(analyticsUrl, '_blank');
                            return false;
                        }
                        
                        lknIntegrationRedeForWoocommerceSettingsLayoutMenuVar = index + 1
                        aElements.forEach((pElement, indexP) => {
                            if (indexP == index) {
                                aElements[index].className = 'nav-tab nav-tab-active'
                            } else {
                                aElements[indexP].className = 'nav-tab'
                            }
                        })
                        changeLayout()
                    }

                    // Adicionar o novo elemento <a> à div
                    divElement.appendChild(aElement)
                    aElements.push(aElement)

                    // Remover o subtítulo original
                    subTitle.parentNode.removeChild(subTitle)
                })

                aElements[0].className = 'nav-tab nav-tab-active'

                // Inserir a div após mainForm.querySelector('p')
                descriptionElement.parentNode.insertBefore(divElement, descriptionElement.nextSibling)

                tables.forEach((table, index) => {
                    if (index != 0 && index != 1) {
                        table.style.display = 'none'
                    }
                    table.menuIndex = index
                })

                function changeLayout() {
                    tables.forEach((table, index) => {
                        const currentSection = lknIntegrationRedeForWoocommerceSettingsLayoutMenuVar;

                        if (currentSection === 1) {
                            // Primeira seção (General) mostra tabelas 0 e 1
                            if (index === 0 || index === 1) {
                                table.style.display = 'flex';
                            } else {
                                table.style.display = 'none';
                            }
                        } else {
                            // Outras seções mostram apenas sua tabela correspondente
                            // Seção 2 → tabela 2, Seção 3 → tabela 3, etc.
                            if (index === currentSection) {  // ← CORRIGIDO: remover o "- 1"
                                table.style.display = 'flex';
                            } else {
                                table.style.display = 'none';
                            }
                        }
                    })
                }

                // Corrige bug de layout quando alguma mensagem é exibida
                const divToMove = document.getElementById('lknIntegrationRedeForWoocommerceSettingsLayoutMenu')

                if (divToMove) {
                    const lknIntegrationRedeForWoocommerceSettingsLayoutDiv = document.getElementById('lknIntegrationRedeForWoocommerceSettingsLayoutDiv')

                    if (lknIntegrationRedeForWoocommerceSettingsLayoutDiv) {
                        const fifthElement = lknIntegrationRedeForWoocommerceSettingsLayoutDiv.children[3]

                        if (fifthElement) {
                            lknIntegrationRedeForWoocommerceSettingsLayoutDiv.insertBefore(divToMove, fifthElement.nextSibling)
                        }
                    }
                }

                // Caso o formulário tenha um campo inválido, navega para a aba correta
                mainForm.addEventListener('invalid', function (event) {
                    const invalidField = event.target
                    if (invalidField) {
                        let parentNode = invalidField.parentNode
                        while (parentNode && parentNode.tagName !== 'TABLE') {
                            parentNode = parentNode.parentNode
                        }
                        if (parentNode && typeof parentNode.menuIndex !== 'undefined') {
                            const tableIndex = parentNode.menuIndex;
                            let targetTabIndex = -1;
                            
                            // Determina qual aba deve ser ativada baseado no índice da tabela
                            if (tableIndex === 0 || tableIndex === 1) {
                                // Tabelas 0 e 1 pertencem à primeira aba (General)
                                targetTabIndex = 0;
                            } else {
                                // Outras tabelas seguem a regra: tabela index = aba index
                                targetTabIndex = tableIndex - 1;
                            }
                            
                            // Clica na aba correspondente se ela existir e não for a aba já ativa
                            if (targetTabIndex >= 0 && targetTabIndex < aElements.length && 
                                lknIntegrationRedeForWoocommerceSettingsLayoutMenuVar !== (targetTabIndex + 1)) {
                                
                                // Atualiza a variável de controle da seção ativa
                                lknIntegrationRedeForWoocommerceSettingsLayoutMenuVar = targetTabIndex + 1;
                                
                                // Atualiza as classes das abas
                                aElements.forEach((aElement, index) => {
                                    if (index === targetTabIndex) {
                                        aElement.className = 'nav-tab nav-tab-active';
                                    } else {
                                        aElement.className = 'nav-tab';
                                    }
                                });
                                
                                // Atualiza o layout para mostrar a aba correta
                                changeLayout();
                            }
                        }
                    }
                }, true)

                // Verifica se há hash na URL e clica na tab correspondente
                const urlHash = window.location.hash
                if (urlHash) {
                    const targetElement = aElements.find(a => a.href.endsWith(urlHash))
                    if (targetElement) {
                        targetElement.click()
                    }
                }
            }

            const hrElement = document.createElement('hr')
            hrElement.style.margin = "2px 0px 40px"
            divElement.parentElement.insertBefore(hrElement, divElement.nextSibling)
            let descriptionP = hrElement.nextElementSibling;
            let menu = document.querySelector('#lknIntegrationRedeForWoocommerceSettingsLayoutMenu');
            if (descriptionP && menu) {
                menu.parentElement.insertBefore(descriptionP, menu);
            }
        }

        document.querySelectorAll('.form-table > tbody > tr').forEach(tr => {
            // As linhas de campos ocultos da seção "Fields" não passam pelo transform.
            if (tr.classList.contains('lkn-fields-hidden-row')) {
                return;
            }
            const td = tr.querySelector('td');
            const th = tr.querySelector('th');
            if (td && th) {
                const span = th.querySelector("span")
                if (span) {
                    if (span.classList.contains("woocommerce-help-tip")) {
                        const ariaLabel = span.getAttribute('aria-label');
                        let desc = document.createElement('p');
                        desc.innerHTML = ariaLabel;
                        th.appendChild(desc);
                        span.style.display = 'none';
                    } else {
                        const novaSpan = th.querySelector(".lknIntegrationRedeForWoocommerceTooltiptext");
                        if (novaSpan) {
                            let desc = document.createElement('p');
                            desc.innerHTML = novaSpan.innerHTML.trim();
                            let lastChild = th.lastElementChild;
                            th.querySelector('label').appendChild(desc);
                            novaSpan.previousElementSibling.style.display = 'none';
                        }
                    }
                }
                let headerCart = document.createElement('div');
                let titleHeader = document.createElement('div');
                let descriptionTitle = document.createElement('div');
                let divHR = document.createElement('div');

                titleHeader.className = 'lkn-field-title';
                descriptionTitle.className = 'lkn-field-description';

                const titleTh = th.querySelector('label');
                const fieldId = titleTh.getAttribute('for');

                let textContent = titleTh.childNodes[0].textContent.trim();
                // Se o input tiver data-title-label, usa ele
                if (fieldId) {
                    const fieldConfig = document.getElementById(fieldId);
                    if (fieldConfig && fieldConfig.hasAttribute('data-title-label')) {
                        const customLabel = fieldConfig.getAttribute('data-title-label');
                        if (customLabel && customLabel.trim() !== '') {
                            textContent = customLabel.trim();
                        }
                    }
                }
                titleHeader.innerText = textContent;

                if (fieldId) {
                    const fieldConfig = document.getElementById(fieldId);
                    if (fieldConfig) {
                        const dataTitleDescription = fieldConfig.getAttribute('data-title-description');
                        descriptionTitle.innerHTML = dataTitleDescription ?? '';
                        
                        // Campos marcados como PRO: lkn-is-pro (travado) ou lkn-pro-badge
                        // (selo PRO, porém editável — usado nos campos fake do plano free).
                        const isProField = fieldConfig.getAttribute('lkn-is-pro') === 'true'
                            || fieldConfig.getAttribute('lkn-pro-badge') === 'true';
                        const isProLocked = fieldConfig.getAttribute('lkn-is-pro') === 'true';
                        if (isProField) {
                            // Criar o link PRO dinamicamente
                            const proLink = document.createElement('a');
                            proLink.className = 'lknIntegrationRedeForWoocommerceBecomePRO';
                            proLink.href = 'https://www.linknacional.com.br/wordpress/woocommerce/rede/';
                            proLink.target = '_blank';
                            
                            // Verificar se existe a variável global com o texto do PRO
                            if (typeof lknPhpProFieldsVariables !== 'undefined' && lknPhpProFieldsVariables.becomePRO) {
                                proLink.textContent = lknPhpProFieldsVariables.becomePRO;
                            } else {
                                proLink.textContent = 'PRO'; // fallback text
                            }
                            
                            titleHeader.appendChild(proLink);
                            
                            // Só bloqueia de fato os campos exclusivos do PRO (lkn-is-pro).
                            // Os campos com lkn-pro-badge permanecem editáveis (fakes).
                            if (isProLocked) {
                                if (!fieldConfig.hasAttribute('disabled')) {
                                    fieldConfig.disabled = true;
                                }
                                // Se for um campo select, aplicar estilo cinza no select2
                                if (fieldConfig.tagName.toLowerCase() === 'select') {
                                    const selectId = fieldConfig.id;
                                    const select2Container = document.querySelector(`#select2-${selectId}-container`);
                                    if (select2Container) {
                                        select2Container.style.opacity = '0.6';
                                        select2Container.style.filter = 'grayscale(0.5)';
                                        select2Container.style.pointerEvents = 'none';
                                    }
                                }
                            }
                        }
                    } else {
                        descriptionTitle.innerHTML = '';
                    }
                }

                divHR.style.borderTop = '1px solid rgb(204, 204, 204)';
                divHR.style.margin = '8px 0px';
                divHR.style.width = '100%';

                headerCart.appendChild(titleHeader);
                headerCart.appendChild(descriptionTitle);
                headerCart.appendChild(divHR);

                const fieldset = td.firstElementChild;
                fieldset.insertBefore(headerCart, fieldset.firstElementChild);

                const divBody = document.createElement('div');
                divBody.className = 'lkn-rede-field-body';
                while (fieldset.childNodes.length > 2) {
                    divBody.appendChild(fieldset.childNodes[2]);
                }
                fieldset.appendChild(divBody);
                if (fieldId) {
                    const fieldConfig = document.getElementById(fieldId);
                    if (fieldConfig) {
                        const elementoPai = fieldConfig.getAttribute('merge-top') ? fieldConfig.getAttribute('merge-top') : false;
                        let input = document.getElementById(elementoPai) ?? false;
                        if (elementoPai && input) {
                            const label = input.parentElement;
                            const divBody = label.parentElement;
                            const fieldsetPai = divBody.parentElement;
                            const fieldsetFilho = td.querySelector('fieldset');

                            let containerCampos = fieldsetPai.querySelector('.lkn-rede-container-campos');

                            if (!containerCampos) {
                                containerCampos = document.createElement('div');
                                fieldsetPai.appendChild(containerCampos);
                                containerCampos.classList.add('lkn-rede-container-campos');
                            }

                            containerCampos.append(fieldsetFilho);
                            tr.style.display = 'none';
                        }
                        const numberLabel = fieldConfig.getAttribute('type-number-label') ? fieldConfig.getAttribute('type-number-label') : false;
                        if (numberLabel) {
                            fieldConfig.style.marginRight = '10px'
                            fieldConfig.outerHTML = `<div style="display: flex;">${fieldConfig.outerHTML}<label style="color: #2C3338;">${numberLabel}</label></div>`;
                        }
                        const mergeCheckbox = fieldConfig.getAttribute('merge-checkbox') ? fieldConfig.getAttribute('merge-checkbox') : false;
                        if (mergeCheckbox) {
                            const parentInput = document.getElementById(mergeCheckbox).closest('div.lkn-rede-field-body');
                            if (parentInput) {
                                const labelCheckbox = fieldConfig.closest('label');
                                fieldConfig.closest('tr').style.display = 'none';
                                parentInput.appendChild(labelCheckbox);
                            }
                        }

                        // Adicionar preview de imagem para o campo de template style
                        if (fieldId === 'woocommerce_rede_debit_3ds_template_style' && typeof lknWcRedeLayoutSettings !== 'undefined') {
                            const previewContainer = document.createElement('div');
                            previewContainer.style.marginTop = '10px';
                            // O body do campo é flex (align-items:start), então o
                            // container encolhe ao conteúdo; width:100% faz a imagem
                            // (width:100%) ocupar a largura total, como no Cielo.
                            previewContainer.style.width = '100%';

                            const buildImage = (src, alt, maxW) => {
                                const img = document.createElement('img');
                                img.src = src || '';
                                img.alt = alt || '';
                                // PRO (imagem única): mesma largura do preview de edição.
                                // Free (3 miniaturas): pequena, por comparação.
                                img.style.maxWidth = maxW || '200px';
                                img.style.width = '100%';
                                img.style.border = '1px solid #ddd';
                                img.style.borderRadius = '4px';
                                img.style.cursor = 'zoom-in';
                                return img;
                            };

                            // Envolve a imagem numa âncora .thickbox para abrir no lightbox
                            // (galeria de visualização) nativo do WordPress, em tamanho maior.
                            const buildThickbox = (src, alt, maxW) => {
                                const link = document.createElement('a');
                                link.className = 'thickbox';
                                link.rel = 'lkn-rede-layout-gallery';
                                link.href = src || '';
                                link.title = alt || '';
                                link.style.display = 'block';
                                link.style.cursor = 'zoom-in';
                                link.appendChild(buildImage(src, alt, maxW));
                                return link;
                            };

                            // Rótulo localizado lido do próprio <select> (ex.: "Modelo Básico").
                            const optionLabel = (value) => {
                                const opt = Array.from(fieldConfig.options).find(o => o.value === value);
                                return opt ? opt.textContent.trim() : value;
                            };

                            // Imagens de preview por tipo de checkout (Block x Shortcode/Clássico).
                            const redeGatewayId = (typeof lknWcRedeTranslationsInput !== 'undefined' && lknWcRedeTranslationsInput.gateway_id)
                                ? lknWcRedeTranslationsInput.gateway_id
                                : 'rede_debit';
                            const modeSelect = document.getElementById('woocommerce_' + redeGatewayId + '_checkout_type')
                                || document.querySelector('select[id$="_checkout_type"]');
                            const getMode = () => {
                                const v = modeSelect ? String(modeSelect.value || '') : '';
                                return v === 'classic' ? 'classic' : 'blocks';
                            };
                            const getSources = () => lknWcRedeLayoutSettings[getMode()]
                                || lknWcRedeLayoutSettings.blocks
                                || lknWcRedeLayoutSettings.classic
                                || {};

                            // Renderiza/atualiza o preview (redefinido em cada ramo abaixo).
                            let renderPreview = () => {};

                            const isProField = fieldConfig.getAttribute('lkn-pro-badge') === 'true'
                                || fieldConfig.getAttribute('lkn-is-pro') === 'true';

                            if (isProField) {
                                // PRO desabilitado: exibe os modelos para comparação.
                                previewContainer.style.display = 'flex';
                                previewContainer.style.flexWrap = 'wrap';
                                previewContainer.style.gap = '16px';

                                const buildItem = (src, caption) => {
                                    const item = document.createElement('div');
                                    item.style.textAlign = 'center';
                                    if (src) {
                                        item.appendChild(buildThickbox(src, caption));
                                        const cap = document.createElement('p');
                                        cap.textContent = caption;
                                        cap.style.margin = '6px 0 0';
                                        cap.style.fontWeight = 'bold';
                                        cap.style.fontSize = '13px';
                                        item.appendChild(cap);
                                    }
                                    return item;
                                };

                                renderPreview = () => {
                                    const sources = getSources();
                                    previewContainer.innerHTML = '';
                                    previewContainer.appendChild(buildItem(sources.basic, optionLabel('basic')));
                                    previewContainer.appendChild(buildItem(sources.modern, optionLabel('modern')));
                                    previewContainer.appendChild(buildItem(sources.compact, optionLabel('compact')));
                                };
                            } else {
                                // PRO ativo: preview único que segue a opção escolhida.
                                const previewLabel = document.createElement('p');
                                previewLabel.textContent = 'Preview:';
                                previewLabel.style.margin = '5px 0';
                                previewLabel.style.fontWeight = 'bold';

                                // Envolve a imagem numa âncora .thickbox (lightbox do WP).
                                const previewLink = document.createElement('a');
                                previewLink.className = 'thickbox';
                                previewLink.rel = 'lkn-rede-layout-gallery';
                                previewLink.style.display = 'block';
                                previewLink.style.cursor = 'zoom-in';
                                const previewImage = buildImage('', '', '100%');
                                previewImage.style.display = 'block';
                                previewLink.appendChild(previewImage);

                                // Função para atualizar a imagem
                                function updatePreviewImage() {
                                    const selectedValue = fieldConfig.value;
                                    const sources = getSources();
                                    const src = sources[selectedValue];
                                    if (src) {
                                        previewImage.src = src;
                                        previewImage.alt = selectedValue + ' Template Preview';
                                        previewImage.style.display = 'block';
                                        previewLink.href = src;
                                        previewLink.title = selectedValue + ' Template Preview';
                                        previewLink.style.display = 'block';
                                    } else {
                                        // Sem imagem para este template: evita exibir a
                                        // imagem anterior.
                                        previewImage.removeAttribute('src');
                                        previewImage.style.display = 'none';
                                        previewLink.removeAttribute('href');
                                        previewLink.style.display = 'none';
                                    }
                                }
                                renderPreview = updatePreviewImage;

                                // Adicionar evento de mudança usando Select2 event
                                $(fieldConfig).on('select2:select', function() {
                                    updatePreviewImage();
                                });

                                // Fallback para mudanças diretas no select (caso Select2 não esteja ativo)
                                fieldConfig.addEventListener('change', function() {
                                    updatePreviewImage();
                                });

                                previewContainer.appendChild(previewLabel);
                                previewContainer.appendChild(previewLink);
                            }

                            // Reage à troca do tipo de checkout (Block x Shortcode/Clássico).
                            if (modeSelect) {
                                modeSelect.addEventListener('change', renderPreview);
                                if (window.jQuery) {
                                    window.jQuery(modeSelect).on('change select2:select', renderPreview);
                                }
                            }

                            // Render inicial.
                            renderPreview();

                            divBody.appendChild(previewContainer);
                        }
                    }
                }
            }
        })

        const divGeral = document.createElement('div');
        const card = document.querySelector('#lknIntegrationRedeForWoocommerceSettingsCardContainer');
        const divSettingsLayout = document.querySelector('#lknIntegrationRedeForWoocommerceSettingsLayoutDiv');
        divSettingsLayout.parentElement.appendChild(divGeral);
        divGeral.appendChild(divSettingsLayout);
        divGeral.appendChild(card);
        divGeral.className = 'lknIntegrationRedeForWoocommerceDivGeral';

        // === LÓGICA DO WHATSAPP - INÍCIO ===
        const sendConfigsInput = document.querySelector('input[id^="woocommerce_"][id$="_send_configs"]');

        // Lógica para customizar o botão de suporte WhatsApp
        if (sendConfigsInput) {
            // Extrai o nome do gateway do id
            const idMatch = sendConfigsInput.id.match(/^woocommerce_(.+)_send_configs$/);
            let gatewayName = '';
            if (idMatch && idMatch[1]) {
                gatewayName = idMatch[1].replace(/_/g, ' ');
                gatewayName = gatewayName.charAt(0).toUpperCase() + gatewayName.slice(1);
            }

            // Define o label do botão
            const supportLabel = lknWcRedeTranslations && lknWcRedeTranslations.sendConfigs ? lknWcRedeTranslations.sendConfigs : 'Suporte';
            sendConfigsInput.value = `${supportLabel}`.trim();

            // Plano gratuito (licença PRO inválida): botão apenas decorativo (cinza, sem ação).
            const redeProLicenseValid = (typeof lknPhpVariables !== 'undefined' && lknPhpVariables.isProLicenseValid);
            if (!redeProLicenseValid) {
                sendConfigsInput.type = 'button';
                sendConfigsInput.disabled = true;
                sendConfigsInput.style.width = 'fit-content';
                sendConfigsInput.style.setProperty('padding', '10px 18px 10px 32px', 'important');
                sendConfigsInput.style.background = 'url("https://cdn.simpleicons.org/whatsapp/999") no-repeat 8px center/18px, #f0f0f1';
                sendConfigsInput.style.color = '#a7aaad';
                sendConfigsInput.style.fill = '#a7aaad';
                sendConfigsInput.style.border = '1px solid #dcdcde';
                sendConfigsInput.style.borderRadius = '2px';
                sendConfigsInput.style.fontWeight = 'bold';
                sendConfigsInput.style.cursor = 'not-allowed';
                sendConfigsInput.style.outline = 'none';
                sendConfigsInput.onmouseover = null;
                sendConfigsInput.onmouseout = null;
                sendConfigsInput.onclick = null;
            } else {

            // Adiciona o ícone do WhatsApp antes do texto
            sendConfigsInput.style.width = 'fit-content';
            // padding-left generoso (ícone em 8px, 18px de largura) — com !important
            // para vencer o `.input-text { padding: .5em .8em !important }` do WooCommerce.
            sendConfigsInput.style.setProperty('padding-top', '10px', 'important');
            sendConfigsInput.style.setProperty('padding-bottom', '10px', 'important');
            sendConfigsInput.style.setProperty('padding-left', '32px', 'important');
            sendConfigsInput.style.setProperty('padding-right', '18px', 'important');
            sendConfigsInput.style.background = 'url("https://cdn.simpleicons.org/whatsapp/white") no-repeat 8px center/18px, #25d366';
            sendConfigsInput.style.color = '#fff';
            sendConfigsInput.style.fill = '#fff';
            sendConfigsInput.style.border = 'none';
            sendConfigsInput.style.borderRadius = '2px';
            sendConfigsInput.style.fontWeight = 'bold';
            sendConfigsInput.style.cursor = 'pointer';
            sendConfigsInput.style.outline = '#25d366';
            sendConfigsInput.style.transition = 'background 0.2s';
            sendConfigsInput.onmouseover = function() {
                this.style.backgroundColor = '#128c7e';
            };
            sendConfigsInput.onmouseout = function() {
                this.style.backgroundColor = '#25d366';
            };
            sendConfigsInput.style.backgroundColor = '#25d366';

            // Altera o tipo para button (opcional, se não for submit)
            sendConfigsInput.type = 'button';

            // Adiciona ação para abrir WhatsApp com mensagem formatada
            const whatsappNumber = lknWcRedeTranslationsInput && lknWcRedeTranslationsInput.whatsapp_number ? lknWcRedeTranslationsInput.whatsapp_number : '55999999999';
            const gatewayId = lknWcRedeTranslationsInput && lknWcRedeTranslationsInput.gateway_id ? lknWcRedeTranslationsInput.gateway_id : 'unknown_gateway';
            const siteDomain = lknWcRedeTranslationsInput && lknWcRedeTranslationsInput.site_domain ? lknWcRedeTranslationsInput.site_domain : window.location.hostname;
            sendConfigsInput.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Remove classes de animação imediatamente após o clique
                this.classList.remove('is-busy', 'components-button__busy-animation', 'animation');
                const settings = lknWcRedeTranslationsInput.gateway_settings || {};
                let message = '#suporte-info Olá! Preciso de suporte com meu gateway de pagamento Rede. Estou com problemas na transação e segue os dados para verificação:';
                message += ` Gateway: ${gatewayId} | Site: ${siteDomain} | Plugin: lkn-integration-rede-for-woocommerce v${lknWcRedeTranslationsInput.version_free} | Plugin dependente: ${lknWcRedeTranslationsInput.version_pro && lknWcRedeTranslationsInput.version_pro !== 'N/A' ? 'lkn-integration-rede-for-woocommerce-pro v' + lknWcRedeTranslationsInput.version_pro : 'N/A'} | `;
                message += gatewayId.includes('pix') ? 'endpoint: ' + (lknWcRedeTranslationsInput.endpointStatus ? 'true' : 'null') + ' | ' : '';
                const sensitiveKeys = ['pv', 'token', 'license', 'card_token', 'google_pay_private_key', 'google_pay_public_key'];

                Object.keys(settings).forEach(function(key) {
                    if (key === 'rede') return;
                    if (key === 'developers') return;
                    if (key === 'gateway') return;
                    if (key === 'credit_options') return;
                    if (key === 'currency_quote') return;
                    if (key === 'endpoint') return;
                    if (key === 'send_configs') return;
                    if (key === 'general') return; // Ignora 'general'
                    if (key === 'validate_license') return; // Ignora 'validate_license'
                    if (key === 'pro') return; // Ignora 'pro'
                    if (key === 'fake_license_field') return; // Ignora 'fake_license_field'
                    if (key === 'fake_cardholder_field') return; // Ignora 'fake_cardholder_field'
                    if (key === 'fake_layout') return; // Ignora 'fake_layout'
                    if (key === 'fake_and_more_field') return; // Ignora 'fake_and_more_field',
                    if (key === 'transactions') return; // Ignora 'transactions'

                    let value = settings[key];

                    // 1. Normalização de valores vazios/nulos
                    if (value === undefined || value === null || value === '') {
                        value = 'null';
                    }

                    // 2. Lógica de Censura Dinâmica
                    if (sensitiveKeys.includes(key) && value !== 'null') {
                        const strValue = String(value);
                        const len = strValue.length;

                        // Regra: Mostra no máximo 4, mas nunca mais que 1/3 da string para garantir segurança em strings curtas
                        // Ex: Se tem 32 chars, mostra 4. Se tem 4 chars, mostra 1. Se tem 2, mostra 0.
                        const keep = Math.min(4, Math.floor(len / 3)); 
                        
                        const start = strValue.slice(0, keep);
                        const end = strValue.slice(-keep);
                        // Se keep for 0, o slice(-0) pega tudo, então tratamos isso:
                        const safeEnd = keep > 0 ? strValue.slice(-keep) : '';
                        
                        // O meio é preenchido com asteriscos fixos (***) ou baseados no tamanho real
                        const middle = '*'.repeat(Math.max(1, len - (keep * 2)));

                        value = `${start}${middle}${safeEnd}`;
                    }

                    message += ` ${key}: ${value} |`;
                });
                message += ' Aguardo retorno, obrigado!';
                window.open(`https://api.whatsapp.com/send/?phone=${whatsappNumber}&text=${encodeURIComponent(message)}`,'_blank');
            };
            }
        }
        // === LÓGICA DO WHATSAPP - FIM ===

        // === CONDIÇÃO: esconder seletor de tipo de cartão (apenas restrição de um único tipo) ===
        const lknRestrictionField = document.getElementById('woocommerce_rede_debit_card_type_restriction');
        const lknHideSelectorField = document.getElementById('woocommerce_rede_debit_hide_card_type_selector');

        if (lknRestrictionField && lknHideSelectorField) {
            const setHideSelectorAvailability = () => {
                const isBoth = lknRestrictionField.value === 'both';
                const label = lknHideSelectorField.closest('label') || lknHideSelectorField.parentElement;

                // NÃO usar 'disabled': o formulário ignora campos com esse atributo no submit.
                // "Fingimos" o estado desabilitado com atributo próprio + estilo + bloqueio de clique.
                if (isBoth) {
                    lknHideSelectorField.setAttribute('data-lkn-fake-disabled', 'true');
                    lknHideSelectorField.style.pointerEvents = 'none';
                    lknHideSelectorField.style.opacity = '0.5';
                    if (label) {
                        label.setAttribute('data-lkn-fake-disabled', 'true');
                        label.style.pointerEvents = 'none';
                        label.style.opacity = '0.5';
                        label.style.cursor = 'not-allowed';
                    }
                } else {
                    lknHideSelectorField.removeAttribute('data-lkn-fake-disabled');
                    lknHideSelectorField.style.pointerEvents = '';
                    lknHideSelectorField.style.opacity = '';
                    if (label) {
                        label.removeAttribute('data-lkn-fake-disabled');
                        label.style.pointerEvents = '';
                        label.style.opacity = '';
                        label.style.cursor = '';
                    }
                }
            };

            // Bloqueia o toggle quando "fake disabled" (pointer-events cobre o mouse; isto cobre teclado).
            const blockWhenFakeDisabled = (event) => {
                if (lknHideSelectorField.getAttribute('data-lkn-fake-disabled') !== 'true') {
                    return;
                }
                // Só impede a ativação (Espaço/Enter); não bloqueia Tab ou outros atalhos.
                if (event.type === 'keydown' && event.key !== ' ' && event.key !== 'Enter' && event.key !== 'Spacebar') {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
            };
            lknHideSelectorField.addEventListener('click', blockWhenFakeDisabled, true);
            lknHideSelectorField.addEventListener('keydown', blockWhenFakeDisabled, true);

            // Aplica o estado inicial e reage a mudanças no select (inclui select2).
            setHideSelectorAvailability();
            jQuery('#woocommerce_rede_debit_card_type_restriction').on('change select2:select', setHideSelectorAvailability);
        }
        // === FIM CONDIÇÃO ===

    })
})(jQuery)
