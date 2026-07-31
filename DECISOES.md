# Decisões do Projeto

Este documento registra decisões arquiteturais duradouras. Mudanças futuras
devem acrescentar uma nova entrada, preservando o histórico e o motivo.

## 31/07/2026 — Fundação do produto

**Status:** aceita

- **Nome:** Interferência Painel.
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

