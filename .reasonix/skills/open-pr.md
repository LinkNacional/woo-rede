---
name: open-pr
description: Abre PR de dev → main no woo-rede (integration-rede-for-woocommerce) no padrão Link Nacional (título VERSION - repo(resumo); corpo com metadados e CHANGELOG)
---

# open-pr (woo-rede)

Abre um Pull Request de `dev` → `main` via `gh pr create`, no padrão Link Nacional, para o repositório `integration-rede-for-woocommerce`.

## Parâmetros (via `arguments`)

O usuário pode passar: `version=5.4.11 tested_up=7.1 summary=Logs ABECS + textos do analytics`. Qualquer valor ausente é extraído do código.

- **version** — versão da release (Stable tag / cabeçalho PHP)
- **tested_up** — WP testado até
- **summary** — resumo CURTO usado no TÍTULO. Se ausente, derive da entrada mais recente do `CHANGELOG.md` (NÃO do `git log`).

## Fluxo de execução

### 1. Extrair metadados (se não vierem nos arguments)

```bash
# Cabeçalho PHP (fonte da verdade da versão)
grep -m1 -E "^\s*\*\s*Version:" integration-rede-for-woocommerce.php
grep -m1 -E "^\s*\*\s*Requires PHP:" integration-rede-for-woocommerce.php

# README.txt (Tested up to / Stable tag)
grep -m1 -i "^Tested up to:" README.txt
grep -m1 -i "^Stable tag:" README.txt

# Nome do repositório no GitHub (NÃO usar basename $PWD — a pasta local é "woo-rede")
REPO_NAME=$(basename -s .git "$(git config --get remote.origin.url)")
# → integration-rede-for-woocommerce
```

### 2. Ler o changelog da versão atual (fonte do resumo e dos bullets)

⚠️ **Regra anti-redundância.** NÃO use `git log` para gerar o resumo — o range de commits está dessincronizado e traz itens de versões já publicadas. Leia a entrada mais recente do changelog:

```bash
# Preferir CHANGELOG.md (português). Fallback: README.txt, seção ## Changelog.
head -n 20 CHANGELOG.md
```

A entrada mais recente tem o formato `# VERSION - DD/MM/AA` seguido de bullets `* ...`.

- **TÍTULO**: resuma esses bullets em uma frase curta (≤ ~12 palavras).
- **CORPO (seção CHANGELOG)**: copie os bullets das `CHANGELOG.md` (português), sem hash e sem reescrever.

### 3. Montar TÍTULO

Formato exato (obrigatório) — **sem espaço antes do parêntese**:

```
VERSION - REPO_NAME(RESUMO_CURTO)
```

Exemplo:

```
5.4.11 - integration-rede-for-woocommerce(Logs de acordo com às normas ABECS + textos do analytics para tradução)
```

### 4. Montar CORPO

Use exatamente este gabarito. Os campos de cabeçalho saem do `README.txt`/cabeçalho PHP; a Descrição e a Instalação são boilerplate fixo; só o CHANGELOG e a versão variam:

```markdown
# Integration Rede for WooCommerce
Contribuidores: linknacional
Link: https://www.linknacional.com.br/wordpress/
Tags: woocommerce, pagamento, rede, maxipago, credit, debit, pix, gateway
Testado até: {TESTED_UP}
Versão estável: {VERSION}
Licença: GPLv2 ou posterior
URI da Licença: https://opensource.org/licenses/MIT
Traduções: Português(Brasil) / Inglês

Integre pagamentos via Rede e Maxipago à sua loja WooCommerce com suporte completo a cartão de crédito, débito e PIX.

## Descrição

O Integration Rede for WooCommerce oferece integração completa com os gateways de pagamento Rede e Maxipago, permitindo que sua loja WooCommerce aceite pagamentos via:

- **Cartão de Crédito** (Rede e Maxipago)
- **Cartão de Débito** (Rede e Maxipago)
- **PIX** (Rede)

A [Rede](https://www.userede.com.br) é uma das principais adquirentes do Brasil, oferecendo soluções seguras e confiáveis para processamento de pagamentos. O [Maxipago](https://www.maxipago.com) é uma plataforma robusta de gateway de pagamentos com ampla cobertura internacional.

**Recursos Principais**

- Sistema de parcelamento inteligente com cálculo de juros
- Validação 3DS para débito Maxipago
- Cartão animado no checkout
- Sistema de cron para verificação automática de PIX
- Compatibilidade com checkout por shortcode
- Suporte a reembolsos automáticos
- Logs detalhados de transações
- Conversão de moeda
- Compatibilidade com editor de blocos WooCommerce

**Dependências**

Este plugin depende do WooCommerce. Certifique-se de que o WooCommerce está instalado e configurado antes de instalar o Integration Rede for WooCommerce.

**Instruções de uso**

1. Procure na barra lateral do WordPress por 'Integration Rede for WooCommerce'.
2. Nas opções do WooCommerce, acesse 'Pagamentos' e configure os gateways disponíveis: 'Rede Credit', 'Rede Debit', 'Rede PIX', 'Maxipago Credit', 'Maxipago Debit'.
3. Configure as credenciais de API necessárias para cada gateway.
4. Defina as opções de parcelamento, juros e limites conforme necessário.
5. Salve as configurações.

Pronto! Seus clientes poderão pagar via Rede e Maxipago com múltiplos métodos de pagamento.

## Instalação

1. Baixe o plugin.
2. No painel administrativo do WordPress, vá para Plugins > Adicionar Novo.
3. Clique em "Enviar Plugin" e selecione o arquivo ZIP do plugin que você baixou.
4. Clique em "Instalar Agora" e, em seguida, em "Ativar Plugin".
5. Certifique-se de que o plugin WooCommerce também está ativado.

## CHANGELOG:

{BULLETS copiados da entrada mais recente do CHANGELOG.md, no formato "* Item". NÃO invente a partir do git log.}
```

### 5. Abrir o PR

Sempre `dev` → `main`:

```bash
gh pr create \
  --base main \
  --head dev \
  --title "VERSION - REPO_NAME(RESUMO_CURTO)" \
  --body "$(cat <<'EOF'
...corpo...
EOF
)"
```

### 6. Confirmar

Mostre a URL retornada pelo `gh` e o comando usado. Se o PR já existir para `dev` → `main`, o `gh` vai avisar — não force `--force` sem pedir.

## Regras

- **Nunca** edite arquivos do repo para abrir o PR (é só `gh pr create`).
- Título SEMPRE no formato `VERSION - integration-rede-for-woocommerce(resumo)`, **sem espaço antes do parêntese**.
- `REPO_NAME` é o nome do repositório no GitHub (`integration-rede-for-woocommerce`) — **não** use `basename $PWD` (a pasta local se chama `woo-rede`).
- Corpo SEMPRE com o cabeçalho de metadados, Descrição, Instalação e a seção `## CHANGELOG:` com os bullets da versão.
- Se `version` / `tested_up` divergirem entre o cabeçalho PHP e o `README.txt`, use o **cabeçalho PHP** e avise.
- Nunca inclua hashes de commit no corpo.
- Bullets do corpo SEMPRE vindos da entrada mais recente do `CHANGELOG.md` — nunca do `git log`.
