# Decisões do Projeto

## 06/08/2026 — AVA Central e múltiplos Moodles

**Status:** aceita

- Uma organização poderá usar simultaneamente Moodle próprio e Moodle Central.
- O Moodle Central inicial será `avacursos.com.br` e poderá atender várias
  organizações com cursos compartilhados/revendidos.
- Organizações sem Moodle próprio poderão operar somente no AVA Central.
- A integração será modelada por plataformas de aprendizagem; não haverá um
  único campo “Moodle da organização”.
- Uma matrícula comercial poderá gerar entregas acadêmicas em um ou mais AVAs.
- IDs de usuário, curso e matrícula remotos serão únicos somente dentro da
  plataforma correspondente.
- No AVA Central, a conta da pessoa será reutilizada e o cancelamento suspenderá
  a matrícula específica, não o usuário global que possa ter outros acessos.
- Cada organização visualizará apenas os acessos e resultados acadêmicos que
  patrocinou ou que esteja autorizada a acompanhar.

## 06/08/2026 — Plataforma Mundo Inter e sites das franquias

**Status:** aceita

- **Marca do ecossistema:** Mundo Inter.
- **Domínio central:** `mundointer.com.br`.
- **Produto de gestão:** PAINEL INTER, publicado em
  `painel.mundointer.com.br`.
- A aplicação evoluirá para uma plataforma multitenant de organizações e
  unidades, com isolamento obrigatório de dados, usuários e integrações.
- Cada organização poderá usar domínio e identidade visual próprios.
- Cada franquia/rede poderá publicar um site institucional personalizado com
  unidades, páginas e catálogo de cursos.
- O catálogo público reutilizará cursos, preços e campanhas administrados no
  PAINEL INTER, sem duplicar a fonte de dados.
- A loja começará com checkout hospedado pelo Asaas; dados de cartão não serão
  processados nem armazenados pelo Mundo Inter.
- O fluxo comercial integrará site, lead, aluno, matrícula, cobrança e AVA de
  forma rastreável e idempotente.
- O Moodle continuará externo à aplicação central e poderá ser independente por
  organização, com domínio e infraestrutura próprios.
- A operação atual será migrada e estabilizada na nova VPS antes da primeira
  alteração estrutural multitenant.

### Consequências

- Tabelas de negócio receberão `organization_id` progressivamente.
- Domínios serão configurações verificadas e não servirão, isoladamente, como
  mecanismo de autorização.
- Site público e painel usarão superfícies e sessões separadas, embora
  compartilhem catálogo e eventos de negócio.
- Nenhuma segunda organização real será ativada antes de testes automatizados de
  isolamento entre tenants.
- Arquivos e documentos evoluirão para armazenamento privado compatível com S3.

## 05/08/2026 — Cadastro unificado e Moodle

- O PAINEL INTER será a fonte central de identidade, atendimento, contratos e financeiro dos alunos.
- O Moodle continuará como ambiente acadêmico para cursos, acessos, matrículas, progresso, notas e conclusão.
- A integração começará somente para leitura, importando cursos, usuários matriculados e matrículas em lotes.
- Nenhuma pessoa será vinculada automaticamente apenas pelo nome; correspondências ambíguas exigirão revisão administrativa.
- O token do Moodle será armazenado criptografado e acessível somente ao Admin System.

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
