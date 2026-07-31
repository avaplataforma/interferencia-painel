# Interface

## Objetivo

A interface será responsiva e orientada à operação diária, usando Bootstrap 5 e
JavaScript progressivo. O conteúdo e as ações essenciais devem funcionar sem
depender de um framework JavaScript de página única.

A base visual utiliza Bootstrap 5.3.8 armazenado em `public/assets/vendor`, sem
dependência de CDN em produção, além de um tema complementar próprio.

A marca fornecida pela empresa é exibida na autenticação e na navegação interna,
e também é usada como favicon e ícone para atalhos em dispositivos móveis.

A política de segurança de conteúdo permite imagens, estilos, fontes, scripts e
conexões somente da própria aplicação; imagens `data:` são aceitas para ícones
gerados localmente. Objetos incorporados e enquadramento por terceiros continuam
bloqueados.

## Estrutura prevista

- [x] Cabeçalho com identidade, contexto da unidade e conta do usuário.
- [x] Navegação lateral responsiva por módulos autorizados.
- [x] Área principal com título, contexto, ações e conteúdo.
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
