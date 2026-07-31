# Interface

## Objetivo

A interface será responsiva e orientada à operação diária, usando Bootstrap 5 e
JavaScript progressivo. O conteúdo e as ações essenciais devem funcionar sem
depender de um framework JavaScript de página única.

## Estrutura prevista

- Cabeçalho com identidade, contexto da unidade e conta do usuário.
- Navegação lateral por módulos autorizados.
- Área principal com título, contexto, ações e conteúdo.
- Feedback uniforme para sucesso, aviso, erro e carregamento.
- Tabelas e filtros adaptáveis a telas menores.

## Princípios

- Componentes consistentes e reutilizáveis.
- Ação primária clara; ações destrutivas diferenciadas e confirmadas.
- Formulários com rótulos visíveis, ajuda objetiva e erros próximos ao campo.
- Estados vazio, carregando, sem permissão e erro previstos desde o início.
- Unidade ativa sempre explícita quando alterar o escopo dos dados.
- URLs e assets compatíveis com a instalação em `/painel`.

## Acessibilidade

- HTML semântico e navegação completa por teclado.
- Foco visível e ordem lógica.
- Contraste adequado e informação nunca transmitida apenas por cor.
- Rótulos acessíveis, mensagens anunciáveis e alvos de toque apropriados.
- Preferência por recursos nativos antes de componentes personalizados.

## JavaScript

Será usado para aprimorar interações, não para esconder regras de autorização.
Toda entrada continuará validada no servidor. Scripts deverão ser modulares,
evitar dependências globais e oferecer tratamento de falha de rede.

## Pendente de validação

- Identidade visual, paleta, tipografia e logotipo.
- Dispositivos prioritários e navegadores suportados.
- Densidade de informação desejada para CRM e caixa de entrada.
- Necessidade de tema escuro e preferências por usuário.

