# 🧪 Testes

Estrutura de testes do plugin, separada por linguagem:

```
tests/
└── js/     # Testes de JavaScript (Node, sem dependências)
```

## Testes de JavaScript

Ver [`tests/js/README.md`](js/README.md). Rodar:

```bash
node tests/js/rede-card-fields.autocomplete.test.js
```

## Testes de PHP

Ainda não há suíte PHP neste plugin. Quando existir, seguir o padrão dos demais
plugins da Link Nacional: PHPUnit com `bootstrap.php` na raiz de `tests/` e casos
em `tests/Unit/`, referenciados por `phpunit.xml.dist` e pelo script `composer test`.

> A pasta `tests/` **não** é empacotada no `.zip` de release — os workflows do
> GitHub Actions usam whitelist (`mv` de `Admin/`, `Includes/`, `Public/`, etc.).
