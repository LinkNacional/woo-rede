# 🧪 Testes

Estrutura de testes do plugin, separada por linguagem:

```
tests/
├── js/     # Testes de JavaScript (Node, sem dependências)
└── php/    # Testes de PHP (script puro, sem dependências)
```

## Testes de JavaScript

Ver [`tests/js/README.md`](js/README.md). Rodar:

```bash
node tests/js/rede-card-fields.autocomplete.test.js
```

## Testes de PHP

Sempre que possível, os testes de PHP são scripts puros (sem WordPress nem
dependências externas), no mesmo espírito dos testes JS. Rodar:

```bash
php tests/php/rede-card-expiry.test.php
```

- `rede-card-expiry.test.php` — valida a validade do cartão do gateway de débito
  (`rede_debit`): aceita `MM/AA` e `MM/AAAA` (com/sem espaços), rejeita mês
  inválido/vencido e confirma que o fluxo de crédito não foi alterado. Usa stubs
  mínimos no lugar do WordPress e o autoload do `vendor/` para carregar os gateways.

> Suíte PHPUnit completa (com `bootstrap.php` + `tests/Unit/` + `composer test`)
> segue disponível como evolução futura, no padrão dos demais plugins da Link
> Nacional, quando o entorno de WordPress de teste estiver disponível.

> A pasta `tests/` **não** é empacotada no `.zip` de release — os workflows do
> GitHub Actions usam whitelist (`mv` de `Admin/`, `Includes/`, `Public/`, etc.).
