# Decisões do Projeto

Este documento registra decisões arquiteturais duradouras. Mudanças futuras
devem acrescentar uma nova entrada, preservando o histórico e o motivo.

## 31/07/2026 — Fundação do produto

**Status:** aceita

- **Nome original:** Interferência Painel.
- **URL de produção:** `https://interferencia.com.br/painel`.
- **Hospedagem:** VPS própria.
- **Stack:** PHP 8.3, MariaDB, Bootstrap 5 e JavaScript.
- **Arquitetura:** aplicação modular com kernel próprio e módulos de negócio
  desacoplados.
- **CRM:** desenvolvimento próprio, integrado ao painel.
- **WhatsApp:** integração oficial pela WhatsApp Cloud API, usando coexistência
  para preservar o uso do aplicativo WhatsApp Business e da API.
- **Escopo organizacional:** seis números/unidades: Sede/Central em Tijucas,
  Filial Tijucas (unidade distinta), Itapema, Porto Belo, São João Batista e
  Nova Trento.
- **Estratégia inicial:** concluir arquitetura e documentação antes de criar
  funcionalidades de negócio.

### Consequências

- O sistema deve funcionar corretamente sob o prefixo `/painel`, sem assumir
  instalação na raiz do domínio.
- Cada módulo deve declarar limites claros e depender do kernel apenas por
  contratos estáveis.
- Dados e credenciais de cada unidade/número devem ser segregados e protegidos.
- A integração do WhatsApp deve considerar webhooks, templates, consentimento,
  janela de atendimento e rastreabilidade, mas não será implementada na Sprint 0.
- O ambiente de produção não deve expor `config/`, `storage/`, `database/`,
  `kernel/` ou `modules/`; somente `public/` será público.

## 31/07/2026 — Nome oficial do produto

**Status:** aceita; substitui o nome original registrado na fundação

- **Nome oficial:** PAINEL INTER 📊.
- O endereço permanece `https://interferencia.com.br/painel`.
- O namespace técnico e o nome do pacote Composer permanecem estáveis para não
  acoplar identidade visual à estrutura interna do código.

## 31/07/2026 — Banco de produção

**Status:** aceita

- MariaDB administrado pelo HestiaCP.
- Banco da aplicação: `jonathan_painel_inter`.
- Usuário dedicado, restrito ao host local e ao banco da aplicação.
- Credencial armazenada somente no `.env` de produção com permissão `0600`.
- Nenhuma tabela de negócio criada nesta etapa; apenas o controle técnico
  `schema_migrations` foi inicializado.

## 31/07/2026 — Nomes das unidades de Tijucas

**Status:** aceita; substitui somente os nomes de exibição anteriores

- A unidade de código `sede-central-tijucas` passa a ser exibida como **Central**.
- A unidade de código `filial-tijucas` passa a ser exibida como **Tijucas**.
- Os códigos técnicos permanecem inalterados para preservar todos os vínculos já
  existentes no banco de dados.

## 31/07/2026 — Cor principal da interface

**Status:** aceita

- A cor principal da identidade visual do PAINEL INTER é o vermelho `#ed1c24`.
- Cores semânticas, como verde para sucesso e vermelho escuro para erro,
  permanecem reservadas à comunicação de estados do sistema.
