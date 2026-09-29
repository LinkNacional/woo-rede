(function ($) {
  $(window).load(function () {
    // Detectar a página atual
    const adminPage = lknFindGetParameter('section')
    const pluginPages = [
      'maxipago_credit',
      'maxipago_debit',
      'maxipago_pix',
      'rede_credit',
      'rede_debit',
      'rede_pix',
      'integration_rede_pix'
    ]

    // Function to create feature message components
    function createFeatureMessage(iconHtml, title, text, href) {
      // Se href for fornecido, cria um <a>, senão um <div>
      const featureMessage = document.createElement(href ? 'a' : 'div');
      featureMessage.className = 'custom-feature-message';
      if (href) {
        featureMessage.href = href;
        featureMessage.target = '_blank';
        featureMessage.rel = 'noopener noreferrer';
        featureMessage.style.textDecoration = 'none';
        featureMessage.style.color = 'inherit';
      }

      // Ícone (HTML)
      const infoIcon = document.createElement('span');
      infoIcon.className = 'feature-icon';
      infoIcon.innerHTML = iconHtml;

      // Container para título + texto
      const contentDiv = document.createElement('div');
      contentDiv.className = 'feature-message-content';
      contentDiv.innerHTML = `<strong>${title}</strong><br>${text}`;

      featureMessage.appendChild(infoIcon);
      featureMessage.appendChild(contentDiv);

      return featureMessage;
    }

    function lknFindGetParameter(parameterName) {
      let result = null
      let tmp = []
      location.search
        .substr(1)
        .split('&')
        .forEach(function (item) {
          tmp = item.split('=')
          if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1])
        })
      return result
    }

    // Insere as mensagens no container de configurações
    const cardContainer = document.getElementById('lknIntegrationRedeForWoocommerceSettingsCardContainer');
    
    if (cardContainer) {
      // Cria o bloco de mensagem "Google Pay e Tabela de Transações"
      const featureMessage2 = createFeatureMessage(
        '✔️',
        'Google Pay e Tabela de Transações:',
        'Disponíveis exclusivamente para assinantes Pro e clientes de nossa hospedagem.'
      );

      // Adiciona a mensagem ao container
      cardContainer.appendChild(featureMessage2);
      
      // Criar cartão promocional
      const promotionalCard = document.createElement('div');
      promotionalCard.className = 'woo-better-promotional-card';

      // Adiciona um elemento de fundo decorativo
      const backgroundDecor = document.createElement('div');
      backgroundDecor.className = 'promotional-card-background-decor';
      promotionalCard.appendChild(backgroundDecor);

      // Conteúdo do cartão
      const cardContent = document.createElement('div');
      cardContent.className = 'promotional-card-content';

      // Título do plugin
      const cardTitle = document.createElement('h3');
      cardTitle.className = 'promotional-card-title';
      cardTitle.textContent = 'Plugin Link de Pagamento de Faturas';

      // Traço decorativo
      const titleDivider = document.createElement('hr');
      titleDivider.className = 'promotional-card-title-divider';
      titleDivider.style.cssText = 'width: 100%; height: 1px; background: white; border: none; margin: 8px 0 16px 0;';

      // Descrição do plugin
      const cardDescription = document.createElement('p');
      cardDescription.className = 'promotional-card-description';
      cardDescription.textContent = 'O Plugin Link de Pagamento oferece a solução completa para o seu negócio. Gere links personalizados, aceite pagamento em múltiplos cartões, configure cobranças recorrentes, crie orçamentos e venda diretamente pelo WhatsApp!';

      // Container dos botões
      const buttonsContainer = document.createElement('div');
      buttonsContainer.className = 'promotional-card-buttons';

      // Botão Saiba mais (sempre presente)
      const learnMoreButton = document.createElement('button');
      learnMoreButton.className = 'promotional-card-button learn-more';
      learnMoreButton.textContent = 'Saiba mais';
      
      learnMoreButton.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        window.open('https://br.wordpress.org/plugins/invoice-payment-for-woocommerce/', '_blank');
      });

      buttonsContainer.appendChild(learnMoreButton);

      // Botão Instalar (apenas se o plugin não estiver instalado)
      if (!lknPhpVariables.invoice_plugin_installed) {
        const installButton = document.createElement('button');
        installButton.className = 'promotional-card-button install';
        installButton.textContent = 'Instalar';
        
        installButton.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          const installUrl = `/wp-admin/update.php?action=install-plugin&plugin=${lknPhpVariables.plugin_slug}&_wpnonce=${lknPhpVariables.install_nonce}`;
          window.open(installUrl, '_blank');
        });

        buttonsContainer.appendChild(installButton);
      }

      // Monta o conteúdo do cartão
      cardContent.appendChild(cardTitle);
      cardContent.appendChild(titleDivider);
      cardContent.appendChild(cardDescription);
      cardContent.appendChild(buttonsContainer);
      promotionalCard.appendChild(cardContent);
      
      // Adiciona o cartão promocional ao container
      cardContainer.appendChild(promotionalCard);
    }

    // Aviso: os campos marcados como PRO são apenas demonstração no plano gratuito —
    // o lojista pode ajustá-los à vontade, mas só têm efeito com licença PRO ativa.
    if (adminPage && pluginPages.includes(adminPage)) {
      const wcForm = document.getElementById('mainform')

      if (typeof lknPhpVariables !== 'undefined' && !lknPhpVariables.isProLicenseValid && wcForm && !document.getElementById('lknRedeFakeProNotice')) {
        const notice = document.createElement('div')
        notice.id = 'lknRedeFakeProNotice'
        notice.className = 'notice notice-error inline'
        notice.setAttribute('style', 'margin: 10px 0; padding: 8px 12px; border-left-color: #d63638;')

        const p = document.createElement('p')
        p.setAttribute('style', 'margin: 4px 0;')
        p.textContent = (typeof lknPhpProFieldsVariables !== 'undefined' && lknPhpProFieldsVariables.fakeProNotice)
          ? lknPhpProFieldsVariables.fakeProNotice
          : 'The features marked with the PRO badge are a preview in the free plan. You can adjust them freely to explore them, but they only take effect with an active PRO license.'
        notice.appendChild(p)

        // Coloca logo ACIMA do botão "Salvar alterações" (fim do formulário), para
        // o lojista ver o aviso antes de salvar.
        const submit = wcForm.querySelector('p.submit') || wcForm.querySelector('.woocommerce-save-button')
        if (submit && submit.parentNode) {
          submit.parentNode.insertBefore(notice, submit)
        } else {
          const header = wcForm.querySelector('h2') || wcForm.firstElementChild
          if (header && header.parentNode) {
            header.parentNode.insertBefore(notice, header.nextSibling)
          } else {
            wcForm.insertBefore(notice, wcForm.firstChild)
          }
        }
      }
    }
  })
})(jQuery)
