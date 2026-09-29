# Testes de JavaScript

Testes dos scripts de front-end que rodam no navegador. Sem framework nem
bundler: usam só `vm` + `assert` do Node — **zero dependências**.

## Arquivos

- `rede-card-fields.autocomplete.test.js` — testa o núcleo
  `Public/js/rede-card-fields.js` (`window.LknRedeCardFields`).
  Simula o autocomplete/autofill do navegador injetando a validade em `MM/AAAA`
  (ex.: `06/2025`) e verifica a normalização para `MM/AA` (ex.: `06/25`), além
  de número e CVC.

## Como rodar

```bash
node tests/js/rede-card-fields.autocomplete.test.js
```

Encerra com código `0` quando tudo passa (`passed: 18   failed: 0`).

## Convenções

- Um arquivo por núcleo, com sufixo `.test.js`.
- Sem dependências externas: carregue o script **real** via `vm` num DOM fake
  (o `<input>` simulado dispara os mesmos eventos do navegador: `input`/`change`).
- A pasta `tests/` **não** vai para o `.zip` de release (workflow usa whitelist).
