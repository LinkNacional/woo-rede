# 5.4.11 - 10/09/2026
* Ajuste: Logs dos gateways adequados às normas ABECS.
* Ajuste: Textos do analytics ajustados para tradução.

# 5.4.10 - 26/08/2026
* Ajuste: Cancelamento ou timeout no desafio 3D Secure agora recusa a transação (onFailure sempre "decline").
* Ajuste: Cartões não registrados no 3DS (returnCode 204) são reenviados sem 3DS quando "Continuar sem 3DS" está habilitado.
* Correção: Falha na validação de segurança do webhook 3DS agora redireciona ao checkout e registra nota no pedido.

# 5.4.9 - 17/08/2026
* Ajuste: Validação da rota de webhook.

# 5.4.8 - 14/08/2026
* Ajuste: Mudança no texto de notificação ao detectar pagamento com frictionless no 3DS.
  
# 5.4.7 - 10/08/2026
* Segurança: Validação server-to-server no webhook PIX FREE (redePixListenerLegacy) para prevenir webhook forgery não autenticado.

# 5.4.6 - 28/07/2026
* Ajuste de sanitização de componentes.
  
# 5.4.5 - 28/07/2026
* Ajuste na tentativa de pagamento duplicado.

# 5.4.4 - 03/06/2026
* Ajuste nas notas customizadas.
* Ajuste na mudança do status do pedido.

# 5.4.3 - 28/05/2026
* Ajuste na lógica dos logs com erro no 3DS.

# 5.4.2 - 21/05/2026
* Migração do hook de cancelamento do pedido para versão PRO.

# 5.4.1 - 24/04/2026
* Ajuste nas parcelas com cupom.
* Mudança do tipo do campo do Order ID: Texto -> Link.

# 5.4.0 - 27/03/2026
* Novo sistema de exportação de planilha (PRO).
* Ajuste na notificação do plugin de antifraude.

# 5.3.3 - 16/03/2026
* Correção de variáveis indefinidas $order_currency e $orderId no método de pagamento via crédito da Rede.

# 5.3.2 - 16/03/2026
* Ajuste ao detectar o campo require na página de configuração.

# 5.3.1 - 09/03/2026
* Adição do ícone da rede.

# 5.3.0 - 24/02/2026
* Adição do novo método de pagamento Google Pay.

# 5.2.0 - 12/02/2026
* NOVO Tabela de transações da Rede.
* NOVO Sistema de envio de credenciais para o suporte.

# 5.1.7 - 30/01/2026
* Adição do campo de referência às notas do pedido.

# 5.1.6 - 28/01/2026
* Ajuste de vulnerabilidade na rota de deleção de logs.
* Adição de link de CHANGELOG na página de plugin.

# 5.1.5 - 26/01/2026
* Ajuste na obtenção da bandeira do cartão nas notas do pedido.

# 5.1.4 - 19/01/2026
* Ajuste no tamanho da variavel de referencia do pedido.

# 5.1.3 - 16/01/2026
* Correção em erros encontrados pelo Wordpress.

# 5.1.2 - 04/12/2025
* Novo sistema de cron ligado a versão PRO do plugin.

# 5.1.1 - 30/12/2025
* Lançamento do 3DS.

# 5.1.0 - 04/12/2025
* Novo sistema de requisição via 3DS no débito da Rede.

# 5.0.0 - 17/11/2025
* Novo sistema de requisições da Rede (V2).
* Melhoria na label das parcelas.
* Sistema de cron para verificação automática de pagamentos PIX.
* Otimização de scripts JavaScript para melhor performance.
* Reset automático de parcelas em mudanças do carrinho.

# 4.1.9 - 25/11/2025
* Ajuste na parcela quando é à vista.

# 4.1.8 - 24/11/2025
* Correção no valor minimo por parcela.

# 4.1.7 - 24/11/2025
* Correção na geração do select das parcelas.

# 4.1.6 - 24/11/2025
* Correção no select de parcelar ao selecionar um novo frete.
* Correção nas min das parcelas.

# 4.1.5 - 14/11/2025
* Correção em titulo e imagens do plugin.

# 4.1.4 - 07/11/2025
* Ajuste no limite de parcelas por produto.
* Ajuste no calculo final das parcelas de pagamento.

# 4.1.3 - 05/11/2025
* Correção em calculos de taxas.

# 4.1.2 - 04/11/2025
* Novos atributos customizados de configuração.

# 4.1.1 - 30/10/2025
* Correção em calculo de parcelas no formulário por shortcode.

# 4.1.0 - 08/10/2025
* Novo estilo nas configurações dos gateways.
* Descrições mais detalhadas nas configurações.

# 4.0.9 - 09/10/2025
* Correção em exibição de icones.

# 4.0.8 - 22/09/2025
* Correção em calculo de impostos nas parcelas.

# 4.0.7 - 19/09/2025
* Correção em lançamento de versão.

# 4.0.6 - 15/09/2025
* Correção na rota do PIX.

# 4.0.5 - 15/09/2025
* Correção na rota do PIX.

# 4.0.4 - 15/09/2025
* Correção em atualização de valors das parcelas.
* Correção nos reembolsos dos pagamentos.
* Correção no endpoint da rota de verificação de PIX.

# 4.0.3 - 12/09/2025
* Correção em função de reembolso.

# 4.0.2 - 08/09/2025
* Atualização nos links de venda.
* Correção no textdomain do plugin.
* Icone do plugin nas confgurações do gateway

# 4.0.2 - 09/09/2025
* Correção em atualização de valors das parcelas.

# 4.0.1 - 03/09/2025
* Correção no calculo das parcelas no modo legacy.

# 4.0.0 - 25/08/2025
* Adição do campo de conversão de moeda.
* Correções de bugs e warnings.

# 3.11.0 - 23/07/2025
* Adição de configuração PRO para exibir botão "Finalizar e Gerar PIX" no checkout.
* Adição de links nas configurações para direcionar o usuário para documentações e tutoriais.
* Correção em exibição de texto de avaliação do plugin.

# 3.10.1 - 07/05/2025
- Correção em descrição dos métodos de pagamento.

# 3.10.0 - 08/07/2025
* Adição de compatibilidade com informação de juros ou desconto no checkout.

# 3.9.3 - 13/06/2025
* Mudança no feedback do logs do método de pagamento do Rede.

# 3.9.2 - 06/06/2025
* Correção na URL de verificação do PIX.

# 3.9.1 - 02/06/2025
* Correção em limite de parcelas por produto.
* Correção em exibição de logs no pedido.

# 3.9.0 - 15/05/2025
* Adição de compatibilidade com configuração PRO para adicionar desconto na parcela.
* Adição de compatibilidade com configuração PRO para definir o valor mínimo para parcelas sem juros.

# 3.8.0 - 08/05/2025
- Adição de configuração para exibir logs dentro dos pedidos.
- Correção em lógica do valor de parcela minima.

# 3.7.5 - 07/05/2025
- Correção em pagamento de débito com Maxipago.

# 3.7.4 - 06/05/2025
- Correção no reembolso de pagamento.
- Correção no action de lançamento de plugin.

# 3.7.3 - 06/05/2025
- Correção no reembolso de pagamento.

# 3.7.2 - 08/04/2025
- Atualização de "Tested up to" para a versão mais recente do WordPress.

# 3.7.1 - 18/03/2025
- Correção em requisição de débito com Maxipago.

# 3.7.0 - 11/03/2025
- Adição de validação 3DS para pagamentos de débito com Maxipago;
- Adição de endpoint para receber notificações no método de débito com Maxipago.

# 3.6.4 - 27/02/2025
- Correção campos do MaxiPago;
- Correção de reconhecimento do retorno MaxiPAgo;
- Adição de conversão do valor total da compra.

# 3.6.3 - 24/02/2025
- Correção configurações quando o plugin PRO está desativado.

# 3.6.2 - 03/02/2025
- Correção em validação do campo de CVV.

# 3.6.1 - 24/01/2025
- Correção de configuração.

# 3.6.0 - 24/01/2025
- Novo modo de pagamento via pix(rede).
- Adição do notice de donwloado do plugin: fraud-detection-for-woocommerce.
- Adição de mensagem de avaliação do plugin no footer.

# 3.5.3 - 14/01/2025
- Correção de valor total do pedido quando exitem taxas de entrega.

# 3.5.2 - 18/12/2024
- Correção de erro fatal em chamada de função de tradução.

# 3.5.1 - 13/12/2024
- Correção de segurança no CVV do cartão(Debito e crédito).

# 3.5.0 - 05/12/2024
- Atualização em versão do SDK;
- Adição de cartão animado no checkout do editor de blocos WooCommerce.

# 3.4.3 - 11/11/2024
- Correção de avisos de atributos indefinidos;
- Correção de declaração de compatibilidade para editor de blocos WooCommerce;
- Melhoria de tratamento de varáveis de pagamento.

# 3.4.2 - 08/11/2024
- Alteração em layout das configurações do administrador.

# 3.4.1 - 24/10/2024
- Alteração em lógica de script de notificações.

# 3.4.0 - 18/10/2024
- Correção de erros de compatibilidade com editor por blocos do woocommerce;
- Alteração em textos de hospedagem gratuita;
- Alteração em layout das configurações do administrador;
- Adição de configuração para desabilitar o campo de softdescriptor;
- Adição da informação de bandeira do cartão nas informações do pedido.

# 3.3.2 - 01/10/2024
- Correção em campo de CPF do plugin de campos brasileiros.

# 3.3.1 - 25/09/2024
- Correção em exibição de parcelas;

# 3.3.0 - 03/09/2024
- Adição de compatibilidade com novas configurações do plugin PRO;
- Adição de configuração para alterar status dos pedidos pagos.

# 3.2.0 - 06/08/2024
- Adição de configurações do Rede PRO como apenas leitura;
- Adição de configuração para carregar o script de correção de carregamento infinito.

# 3.1.3 - 30/07/2024
- Melhoria no carregamento de scripts;
- Correção de validação de campos gerando novos pedidos.

# 3.1.2 - 23/07/2024
* Correção de traduções não reconhecidas;
* Correção de geração de pedidos inválidos;
* Ajustes na validação de campos;
* Otimização de código.

# 3.1.1 - 22/07/2024
* Correção em scripts da animação do cartão.

# 3.1.0 - 16/07/2024
* Adição de compatibilidade com o plugin PRO.
* Adicionada opção de compatibilidade para carregar CSS legado.
* Adicionado botão facilitado para ver logs da transação;
* Correção de bug de issuer;
* Correção de bug de reembolso;
* Correção de bug de transação não sendo capturada de forma automática;
* Correção de bug de renderização de campos na página de finalização de compra;

# 3.0.5 - 29/05/2024
* Correção de função para fazer o reembolso de pedidos.

# 3.0.4 - 24/05/2024
* Correção de looping para criar animação de cartão.

# 3.0.3 - 23/05/2024
* Correção de erros em função para salvar o total da compra.

# 3.0.2 - 22/05/2024
* Correção de erros de layout de método de pagamento no checkout.

# 3.0.1 - 21/05/2024
* Correção de erros no método de pagamento no checkout.

# 3.0.0 - 09/05/2024
* Refatoração completa do plugin para arquitetura orientada a objetos;
* Adição da opção de débito para pagamentos com Rede;
* Adição de Maxipago para métodos de pagamento.

# 2.1.0 - 05/12/2020
* Atualização das informações de compatibilidade;
* Correção do ID do pedido;
* Implementação de filtro de parcelamento;
* Implementação de um filtro para exibir os dados da Rede na página do pedido apenas se este for o método de pagamento utilizado;
* Melhorias no layout do cartão na página de checkout.

# 2.0.2 - 23/05/2020
* Atualização das informações de compatibilidade;
* Correção do ID do pedido.

# 2.0.1 - 04/05/2020
* Atualização das informações de compatibilidade;
* Correção da internacionalização das mensagens de erro na página de checkout.

# 2.0.0 - 02/11/2019
* Correção da exibição do número de parcelas;
* Ajuste do placeholder do ano na expiração do cartão;
* Internacionalização e tradução do plugin para pt_BR;
* Inclusão de ícones de bandeiras de cartões de crédito;
* A data de expiração agora aceita 2 ou 4 dígitos para o ano;
* Sanitização dos campos de entrada.
