# Interferência Painel

Sistema central de gestão da Interferência, planejado para operar em
`https://interferencia.com.br/painel`.

Este repositório está na **Sprint 0 — Fundação**. Nesta fase existem apenas a
estrutura do projeto, documentação e arquivos de configuração de exemplo.
Nenhuma funcionalidade de negócio foi implementada.

## Stack prevista

- PHP 8.3
- MariaDB
- Bootstrap 5
- JavaScript
- VPS própria

## Estrutura

```text
api/        pontos de entrada da API
config/     configuração da aplicação
database/   migrações, seeds e documentação do banco
docs/       documentação funcional e técnica
kernel/     núcleo compartilhado da aplicação
modules/    módulos de negócio isolados
public/     raiz pública do servidor web
storage/    dados temporários e logs não versionados
tests/      testes automatizados
```

## Início rápido (desenvolvimento)

1. Instale PHP 8.3, Composer e MariaDB.
2. Copie `.env.example` para `.env` e preencha apenas valores locais.
3. Execute `composer install` quando as primeiras dependências forem definidas.
4. Aponte a raiz pública do servidor para `public/`.

O arquivo `.env` e o conteúdo gerado em `storage/` não devem ser versionados.
Consulte [docs/01-visao-geral.md](docs/01-visao-geral.md) para o contexto e
[DECISOES.md](DECISOES.md) para decisões consolidadas.

## Estado atual

- [x] Fundação de diretórios
- [x] Documentação inicial
- [x] Preparação para Git e Composer
- [ ] Kernel
- [ ] Autenticação e autorização
- [ ] Módulos de negócio

